<?php
/**
 * Booking Webhook Handler
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Simple_Booking_Booking_Webhook
 */
class Simple_Booking_Booking_Webhook {

    /**
     * Cron hook used for deferred webhook retries.
     */
    const RETRY_HOOK = 'simple_booking_retry_booking_webhook';

    /**
     * Register webhook hooks.
     *
     * @return void
     */
    public static function register_hooks() {
        add_action( self::RETRY_HOOK, array( __CLASS__, 'process_scheduled_retry' ), 10, 4 );
    }

    /**
     * Send booking.created webhook
     *
     * @param array $booking_data Booking payload data.
     * @return true|WP_Error
     */
    public static function send_booking_created( $booking_data ) {
        $webhook_url = simple_booking()->get_setting( 'webhook_url' );

        if ( empty( $webhook_url ) ) {
            return true;
        }

        $payload = array(
            'event' => 'booking.created',
            'data'  => self::normalize_payload( $booking_data ),
        );

        return self::send_with_retry( $webhook_url, $payload );
    }

    /**
     * Send webhook request with exponential backoff retry logic.
     *
     * @param string $webhook_url Target webhook URL.
     * @param array  $payload     Webhook payload.
     * @param int    $max_retries Maximum number of retry attempts.
     * @return true|WP_Error
     */
    private static function send_with_retry( $webhook_url, $payload, $max_retries = 3 ) {
        $context = self::build_context_label( $payload );
        $result = self::send_request( $webhook_url, $payload );

        if ( true === $result['success'] ) {
            return true;
        }

        if ( ! empty( $result['retryable'] ) && $max_retries > 0 ) {
            $delay = self::get_retry_delay( $result, 1 );
            self::schedule_retry( $webhook_url, $payload, 1, $max_retries, $delay );
            self::debug_log( 'Webhook [' . $context . '] initial delivery deferred to background after ' . $result['message'] . '; retry scheduled in ' . $delay . 's' );
            return true;
        }

        return new WP_Error( 'webhook_request_failed', $result['message'] );
    }

    /**
     * Process a scheduled webhook retry.
     *
     * @param string $webhook_url
     * @param array  $payload
     * @param int    $attempt
     * @param int    $max_retries
     * @return void
     */
    public static function process_scheduled_retry( $webhook_url, $payload, $attempt, $max_retries ) {
        $attempt = absint( $attempt );
        $max_retries = absint( $max_retries );
        $context = self::build_context_label( $payload );

        $result = self::send_request( $webhook_url, $payload );

        if ( true === $result['success'] ) {
            self::debug_log( 'Webhook [' . $context . '] succeeded on scheduled attempt ' . ( $attempt + 1 ) );
            return;
        }

        if ( ! empty( $result['retryable'] ) && $attempt < $max_retries ) {
            $next_attempt = $attempt + 1;
            $delay = self::get_retry_delay( $result, $next_attempt );
            self::schedule_retry( $webhook_url, $payload, $next_attempt, $max_retries, $delay );
            self::debug_log( 'Webhook [' . $context . '] scheduled attempt ' . ( $attempt + 1 ) . ' failed with ' . $result['message'] . '; next retry in ' . $delay . 's' );
            return;
        }

        self::debug_log( 'Webhook [' . $context . '] permanently failed after scheduled attempt ' . ( $attempt + 1 ) . ': ' . $result['message'] );
    }

    /**
     * Send webhook request once.
     *
     * @param string $webhook_url
     * @param array  $payload
     * @return array
     */
    private static function send_request( $webhook_url, $payload ) {
        $response = wp_remote_post(
            $webhook_url,
            array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
                'body'    => wp_json_encode( $payload ),
                'timeout' => 10,
            )
        );

        if ( is_wp_error( $response ) ) {
            return array(
                'success'   => false,
                'retryable' => true,
                'message'   => $response->get_error_message(),
                'response'  => null,
            );
        }

        $status_code = (int) wp_remote_retrieve_response_code( $response );

        if ( $status_code >= 200 && $status_code < 300 ) {
            return array(
                'success'   => true,
                'retryable' => false,
                'message'   => 'success',
                'response'  => $response,
            );
        }

        return array(
            'success'   => false,
            'retryable' => ( 429 === $status_code || $status_code >= 500 ),
            'message'   => sprintf( __( 'Webhook request failed with status code %d', 'simple-booking' ), $status_code ),
            'response'  => $response,
        );
    }

    /**
     * Schedule a retry event.
     *
     * @param string $webhook_url
     * @param array  $payload
     * @param int    $attempt
     * @param int    $max_retries
     * @param int    $delay
     * @return void
     */
    private static function schedule_retry( $webhook_url, $payload, $attempt, $max_retries, $delay ) {
        $args = array( $webhook_url, $payload, absint( $attempt ), absint( $max_retries ) );

        if ( false !== wp_next_scheduled( self::RETRY_HOOK, $args ) ) {
            return;
        }

        wp_schedule_single_event( time() + max( 1, absint( $delay ) ), self::RETRY_HOOK, $args );
    }

    /**
     * Build short context label for webhook logs.
     *
     * @param array $payload
     * @return string
     */
    private static function build_context_label( $payload ) {
        $data = isset( $payload['data'] ) && is_array( $payload['data'] ) ? $payload['data'] : array();

        if ( ! empty( $data['booking_id'] ) ) {
            return 'booking_id=' . absint( $data['booking_id'] );
        }

        $email = isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '';
        $date  = isset( $data['date'] ) ? sanitize_text_field( $data['date'] ) : '';

        if ( '' !== $email || '' !== $date ) {
            return 'email=' . $email . ',date=' . $date;
        }

        return 'unknown';
    }

    /**
     * Resolve delay for the next retry attempt.
     *
     * @param array $result
     * @param int   $attempt
     * @return int
     */
    private static function get_retry_delay( $result, $attempt ) {
        if ( ! empty( $result['response'] ) ) {
            $retry_after = wp_remote_retrieve_header( $result['response'], 'retry-after' );
            if ( is_string( $retry_after ) && '' !== trim( $retry_after ) ) {
                if ( ctype_digit( trim( $retry_after ) ) ) {
                    return min( max( 1, (int) trim( $retry_after ) ), 900 );
                }

                $retry_timestamp = strtotime( $retry_after );
                if ( false !== $retry_timestamp ) {
                    return min( max( 1, $retry_timestamp - time() ), 900 );
                }
            }
        }

        return self::calculate_backoff_delay( $attempt );
    }

    /**
     * Calculate exponential backoff delay in seconds.
     *
     * @param int $attempt Current attempt number (1-based).
     * @return int Delay in seconds.
     */
    private static function calculate_backoff_delay( $attempt ) {
        // Exponential backoff for deferred retries: 60s, 120s, 240s.
        return min( (int) pow( 2, max( 0, $attempt - 1 ) ) * 60, 240 );
    }

    /**
     * Debug log helper for webhook operations.
     *
     * @param string $message Log message.
     * @return void
     */
    private static function debug_log( $message ) {
        if ( ! simple_booking()->get_setting( 'debug_mode', false ) ) {
            return;
        }

        $log_file = SIMPLE_BOOKING_PATH . 'debug-google.txt';
        $timestamp = current_time( 'mysql' );
        $entry = sprintf( "[%s] WEBHOOK: %s\n", $timestamp, $message );
        @file_put_contents( $log_file, $entry, FILE_APPEND );
    }

    /**
     * Normalize webhook payload
     *
     * @param array $booking_data Booking payload data.
     * @return array
     */
    private static function normalize_payload( $booking_data ) {
        return array(
            'booking_id'    => isset( $booking_data['booking_id'] ) ? absint( $booking_data['booking_id'] ) : 0,
            'service_name'  => isset( $booking_data['service_name'] ) ? sanitize_text_field( $booking_data['service_name'] ) : '',
            'customer_name' => isset( $booking_data['customer_name'] ) ? sanitize_text_field( $booking_data['customer_name'] ) : '',
            'email'         => isset( $booking_data['customer_email'] ) ? sanitize_email( $booking_data['customer_email'] ) : '',
            'date'          => isset( $booking_data['start_datetime'] ) ? sanitize_text_field( $booking_data['start_datetime'] ) : '',
            'time'          => isset( $booking_data['start_datetime'] ) ? sanitize_text_field( $booking_data['start_datetime'] ) : '',
            'meeting_link'  => isset( $booking_data['meeting_link'] ) ? esc_url_raw( $booking_data['meeting_link'] ) : '',
        );
    }
}
