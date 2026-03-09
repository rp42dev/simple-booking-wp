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
        $attempt = 0;

        while ( $attempt <= $max_retries ) {
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
                if ( $attempt < $max_retries ) {
                    self::debug_log( 'Webhook attempt ' . ( $attempt + 1 ) . ' failed with error: ' . $response->get_error_message() . '; retrying...' );
                    $attempt++;
                    sleep( self::calculate_backoff_delay( $attempt ) );
                    continue;
                }
                return $response;
            }

            $status_code = wp_remote_retrieve_response_code( $response );

            // Success
            if ( $status_code >= 200 && $status_code < 300 ) {
                if ( $attempt > 0 ) {
                    self::debug_log( 'Webhook succeeded on attempt ' . ( $attempt + 1 ) );
                }
                return true;
            }

            // Rate limit or server error - retry with backoff
            if ( ( $status_code === 429 || $status_code >= 500 ) && $attempt < $max_retries ) {
                self::debug_log( 'Webhook attempt ' . ( $attempt + 1 ) . ' returned ' . $status_code . '; retrying...' );
                $attempt++;
                sleep( self::calculate_backoff_delay( $attempt ) );
                continue;
            }

            // Client error or final attempt - return error
            return new WP_Error(
                'webhook_request_failed',
                sprintf( __( 'Webhook request failed with status code %d', 'simple-booking' ), intval( $status_code ) )
            );
        }

        return new WP_Error(
            'webhook_max_retries',
            __( 'Webhook request exceeded maximum retry attempts', 'simple-booking' )
        );
    }

    /**
     * Calculate exponential backoff delay in seconds.
     *
     * @param int $attempt Current attempt number (1-based).
     * @return int Delay in seconds.
     */
    private static function calculate_backoff_delay( $attempt ) {
        // Exponential backoff: 1s, 2s, 4s
        return min( pow( 2, $attempt - 1 ), 4 );
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
        $entry = sprintf( \"[%s] WEBHOOK: %s\\n\", $timestamp, $message );
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
            'service_name'  => isset( $booking_data['service_name'] ) ? sanitize_text_field( $booking_data['service_name'] ) : '',
            'customer_name' => isset( $booking_data['customer_name'] ) ? sanitize_text_field( $booking_data['customer_name'] ) : '',
            'email'         => isset( $booking_data['customer_email'] ) ? sanitize_email( $booking_data['customer_email'] ) : '',
            'date'          => isset( $booking_data['start_datetime'] ) ? sanitize_text_field( $booking_data['start_datetime'] ) : '',
            'time'          => isset( $booking_data['start_datetime'] ) ? sanitize_text_field( $booking_data['start_datetime'] ) : '',
            'meeting_link'  => isset( $booking_data['meeting_link'] ) ? esc_url_raw( $booking_data['meeting_link'] ) : '',
        );
    }
}
