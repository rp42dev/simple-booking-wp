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
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        if ( $status_code < 200 || $status_code >= 300 ) {
            return new WP_Error(
                'webhook_request_failed',
                sprintf( __( 'Webhook request failed with status code %d', 'simple-booking' ), intval( $status_code ) )
            );
        }

        return true;
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
