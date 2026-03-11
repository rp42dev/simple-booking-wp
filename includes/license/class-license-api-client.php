<?php
/**
 * License API Client
 * 
 * Handles communication with Lemon Squeezy license server.
 * 
 * @package Simple_Booking
 * @subpackage License
 * @since 3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Simple_Booking_License_API_Client
 * 
 * Communicates with Lemon Squeezy API to validate and manage licenses.
 */
class Simple_Booking_License_API_Client {

    /**
     * Lemon Squeezy API base URL
     */
    const LEMONSQUEEZY_API_URL = 'https://api.lemonsqueezy.com/v1';

    /**
     * API request timeout (seconds)
     */
    const REQUEST_TIMEOUT = 10;

    /**
     * Get the API client instance name (site identifier).
     * 
     * @return string
     */
    public static function get_instance_name() {
        $custom = defined( 'SIMPLE_BOOKING_LICENSE_INSTANCE_NAME' ) ? SIMPLE_BOOKING_LICENSE_INSTANCE_NAME : '';
        return $custom ?: get_site_url();
    }

    /**
     * Validate a license key with Lemon Squeezy.
     * 
     * Makes a request to Lemon Squeezy /licenses/validate endpoint.
     * 
     * @param string $license_key License key to validate
     * @return array|WP_Error {
     *     'valid'         => (bool) License is valid
     *     'expires_at'    => (string) ISO date when license expires
     *     'expires_soon'  => (bool) License expires within 30 days
     *     'data'          => (array) Full Lemon Squeezy response
     * } or WP_Error on request failure
     */
    public static function validate_license( $license_key ) {
        $license_key = sanitize_text_field( $license_key );

        if ( empty( $license_key ) ) {
            return new WP_Error(
                'empty_license_key',
                'License key is required.'
            );
        }

        $response = wp_remote_post(
            self::LEMONSQUEEZY_API_URL . '/licenses/validate',
            array(
                'timeout' => self::REQUEST_TIMEOUT,
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ),
                'body'    => wp_json_encode( array(
                    'license_key'  => $license_key,
                    'instance_name' => self::get_instance_name(),
                ) ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'api_request_failed',
                'License validation request failed: ' . $response->get_error_message()
            );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = wp_remote_retrieve_body( $response );

        if ( $status_code !== 200 ) {
            $data = json_decode( $body, true );
            $msg  = isset( $data['message'] ) ? $data['message'] : 'Invalid response from license server';

            return new WP_Error(
                'validation_failed',
                $msg,
                array( 'status_code' => $status_code )
            );
        }

        $data = json_decode( $body, true );

        if ( ! isset( $data['valid'] ) ) {
            return new WP_Error(
                'invalid_response',
                'Unexpected response format from license server'
            );
        }

        $is_valid   = (bool) $data['valid'];
        $expires_at = isset( $data['expires_at'] ) ? $data['expires_at'] : null;

        // Check if license expires within 30 days
        $expires_soon = false;
        if ( $expires_at ) {
            $expiry_time = strtotime( $expires_at );
            $thirty_days = time() + ( 30 * DAY_IN_SECONDS );
            $expires_soon = ( $expiry_time <= $thirty_days );
        }

        return array(
            'valid'        => $is_valid,
            'expires_at'   => $expires_at,
            'expires_soon' => $expires_soon,
            'data'         => $data,
        );
    }

    /**
     * Activate a license key on an instance.
     * 
     * Makes a request to Lemon Squeezy /licenses/activate endpoint.
     * 
     * @param string $license_key License key to activate
     * @return array|WP_Error {
     *     'activated' => (bool) License was successfully activated
     *     'instance'  => (string) Instance name where activated
     *     'data'      => (array) Full Lemon Squeezy response
     * } or WP_Error on failure
     */
    public static function activate_license( $license_key ) {
        $license_key = sanitize_text_field( $license_key );

        if ( empty( $license_key ) ) {
            return new WP_Error(
                'empty_license_key',
                'License key is required.'
            );
        }

        $response = wp_remote_post(
            self::LEMONSQUEEZY_API_URL . '/licenses/activate',
            array(
                'timeout' => self::REQUEST_TIMEOUT,
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ),
                'body'    => wp_json_encode( array(
                    'license_key'   => $license_key,
                    'instance_name' => self::get_instance_name(),
                ) ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'api_request_failed',
                'License activation request failed: ' . $response->get_error_message()
            );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = wp_remote_retrieve_body( $response );

        if ( $status_code !== 200 ) {
            $data = json_decode( $body, true );
            $msg  = isset( $data['message'] ) ? $data['message'] : 'License activation failed';

            return new WP_Error(
                'activation_failed',
                $msg,
                array( 'status_code' => $status_code )
            );
        }

        $data = json_decode( $body, true );

        return array(
            'activated' => (bool) isset( $data['activated'] ) ? $data['activated'] : true,
            'instance'  => self::get_instance_name(),
            'data'      => $data,
        );
    }

    /**
     * Deactivate a license key from an instance.
     * 
     * Makes a request to Lemon Squeezy /licenses/deactivate endpoint.
     * 
     * @param string $license_key License key to deactivate
     * @return array|WP_Error {
     *     'deactivated' => (bool) License was successfully deactivated
     *     'instance'    => (string) Instance name from which deactivated
     *     'data'        => (array) Full Lemon Squeezy response
     * } or WP_Error on failure
     */
    public static function deactivate_license( $license_key ) {
        $license_key = sanitize_text_field( $license_key );

        if ( empty( $license_key ) ) {
            return new WP_Error(
                'empty_license_key',
                'License key is required.'
            );
        }

        $response = wp_remote_post(
            self::LEMONSQUEEZY_API_URL . '/licenses/deactivate',
            array(
                'timeout' => self::REQUEST_TIMEOUT,
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ),
                'body'    => wp_json_encode( array(
                    'license_key'   => $license_key,
                    'instance_name' => self::get_instance_name(),
                ) ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'api_request_failed',
                'License deactivation request failed: ' . $response->get_error_message()
            );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = wp_remote_retrieve_body( $response );

        if ( $status_code !== 200 ) {
            $data = json_decode( $body, true );
            $msg  = isset( $data['message'] ) ? $data['message'] : 'License deactivation failed';

            return new WP_Error(
                'deactivation_failed',
                $msg,
                array( 'status_code' => $status_code )
            );
        }

        $data = json_decode( $body, true );

        return array(
            'deactivated' => (bool) isset( $data['deactivated'] ) ? $data['deactivated'] : true,
            'instance'    => self::get_instance_name(),
            'data'        => $data,
        );
    }
}
