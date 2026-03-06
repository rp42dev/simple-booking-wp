<?php
/**
 * Booking Webhook Handler
 *
 * Sends webhook notifications when bookings are created
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
     * @param array $booking_data Booking data array
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public static function send_booking_created( $booking_data ) {
        // Get webhook URL from settings
        $webhook_url = simple_booking()->get_setting( 'webhook_url' );

        if ( empty( $webhook_url ) ) {
            // No webhook URL configured, skip silently
            return true;
        }

        // Validate URL
        if ( ! filter_var( $webhook_url, FILTER_VALIDATE_URL ) ) {
            return new WP_Error( 'invalid_webhook_url', __( 'Invalid webhook URL', 'simple-booking' ) );
        }

        // Prepare payload
        $payload = self::prepare_payload( $booking_data );

        // Send POST request
        $response = wp_remote_post(
            $webhook_url,
            array(
                'headers'     => array(
                    'Content-Type' => 'application/json',
                    'User-Agent'   => 'SimpleBooking/2.0',
                ),
                'body'        => wp_json_encode( $payload ),
                'timeout'     => 15,
                'blocking'    => false, // Non-blocking to avoid slowing down booking creation
                'data_format' => 'body',
            )
        );

        // Log webhook attempt (if debug mode is enabled)
        if ( simple_booking()->get_setting( 'debug_mode', false ) ) {
            $log_message = sprintf(
                "[Webhook] Sent booking.created to %s\nPayload: %s\nResponse: %s",
                $webhook_url,
                wp_json_encode( $payload, JSON_PRETTY_PRINT ),
                is_wp_error( $response ) ? $response->get_error_message() : 'Success (non-blocking)'
            );
            self::log_webhook( $log_message );
        }

        return true;
    }

    /**
     * Prepare webhook payload
     *
     * @param array $booking_data Raw booking data
     * @return array Formatted payload
     */
    private static function prepare_payload( $booking_data ) {
        // Format date and time nicely
        $booking_datetime = '';
        $booking_date = '';
        $booking_time = '';

        if ( isset( $booking_data['start_datetime'] ) ) {
            try {
                $dt = new DateTime( $booking_data['start_datetime'], wp_timezone() );
                $booking_datetime = $dt->format( DateTime::ATOM );
                $booking_date = $dt->format( 'F j, Y' );
                $booking_time = $dt->format( 'g:i A' );
            } catch ( Exception $e ) {
                $booking_datetime = $booking_data['start_datetime'];
                $booking_date = $booking_data['start_datetime'];
                $booking_time = '';
            }
        }

        // Build payload
        $payload = array(
            'event'      => 'booking.created',
            'timestamp'  => current_time( 'timestamp' ),
            'data'       => array(
                'service_name'    => isset( $booking_data['service_name'] ) ? $booking_data['service_name'] : '',
                'customer_name'   => isset( $booking_data['customer_name'] ) ? $booking_data['customer_name'] : '',
                'customer_email'  => isset( $booking_data['customer_email'] ) ? $booking_data['customer_email'] : '',
                'customer_phone'  => isset( $booking_data['customer_phone'] ) ? $booking_data['customer_phone'] : '',
                'date'            => $booking_date,
                'time'            => $booking_time,
                'datetime'        => $booking_datetime,
                'meeting_link'    => isset( $booking_data['meeting_link'] ) ? $booking_data['meeting_link'] : '',
                'booking_id'      => isset( $booking_data['booking_id'] ) ? $booking_data['booking_id'] : '',
            ),
        );

        return $payload;
    }

    /**
     * Log webhook activity
     *
     * @param string $message Log message
     */
    private static function log_webhook( $message ) {
        $log_file = SIMPLE_BOOKING_PATH . 'debug-webhook.txt';
        $timestamp = current_time( 'mysql' );
        $entry = sprintf( "[%s] %s\n\n", $timestamp, $message );
        @file_put_contents( $log_file, $entry, FILE_APPEND );
    }
}
