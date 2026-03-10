<?php
/**
 * Outlook Calendar OAuth Handler
 * 
 * Handles OAuth flow and REST API endpoints for Microsoft Outlook Calendar
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Simple_Booking_Outlook_Calendar {

    /**
     * REST namespace
     */
    const REST_NAMESPACE = 'simple-booking/v1';

    /**
     * Option key for OAuth state
     */
    const STATE_OPTION = 'simple_booking_outlook_oauth_state';

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
    }

    /**
     * Register REST routes
     */
    public function register_rest_routes() {
        // OAuth callback
        register_rest_route(
            self::REST_NAMESPACE,
            'outlook/oauth',
            array(
                'methods'  => 'GET',
                'callback' => array( $this, 'handle_oauth_callback' ),
                'permission_callback' => '__return_true',
            )
        );

        // Check auth status
        register_rest_route(
            self::REST_NAMESPACE,
            'outlook/status',
            array(
                'methods'  => 'GET',
                'callback' => array( $this, 'get_auth_status' ),
                'permission_callback' => array( $this, 'check_admin_permission' ),
            )
        );

        // Disconnect
        register_rest_route(
            self::REST_NAMESPACE,
            'outlook/disconnect',
            array(
                'methods'  => 'POST',
                'callback' => array( $this, 'disconnect' ),
                'permission_callback' => array( $this, 'check_admin_permission' ),
            )
        );

        // Probe slot availability/debug
        register_rest_route(
            self::REST_NAMESPACE,
            'outlook/probe',
            array(
                'methods'  => 'GET',
                'callback' => array( $this, 'probe_slot' ),
                'permission_callback' => array( $this, 'check_admin_permission' ),
            )
        );
    }

    /**
     * Check admin permission
     */
    public function check_admin_permission() {
        return current_user_can( 'manage_options' );
    }

    /**
     * Handle OAuth callback
     */
    public function handle_oauth_callback( $request ) {
        $code = $request->get_param( 'code' );
        $state = $request->get_param( 'state' );
        $error = $request->get_param( 'error' );

        // Check for errors
        if ( ! empty( $error ) ) {
            wp_redirect( admin_url( 'options-general.php?page=simple-booking-settings&outlook=error&message=' . urlencode( $error ) ) );
            exit;
        }

        // Verify state
        $saved_state = get_option( self::STATE_OPTION );
        if ( empty( $state ) || $state !== $saved_state ) {
            wp_redirect( admin_url( 'options-general.php?page=simple-booking-settings&outlook=error&message=invalid_state' ) );
            exit;
        }

        // Exchange code for token
        if ( class_exists( 'Simple_Booking_Calendar_Provider_Manager' ) ) {
            $manager = new Simple_Booking_Calendar_Provider_Manager();
            $provider = $manager->get_provider( 'outlook' );
            
            if ( ! is_wp_error( $provider ) && method_exists( $provider, 'exchange_code' ) ) {
                $result = $provider->exchange_code( $code );
                
                if ( is_wp_error( $result ) ) {
                    wp_redirect( admin_url( 'options-general.php?page=simple-booking-settings&outlook=error&message=' . urlencode( $result->get_error_message() ) ) );
                    exit;
                }
            }
        }

        // Clean up state
        delete_option( self::STATE_OPTION );

        // Redirect to settings page with success message
        wp_redirect( admin_url( 'options-general.php?page=simple-booking-settings&outlook=connected' ) );
        exit;
    }

    /**
     * Get authentication status
     */
    public function get_auth_status() {
        if ( class_exists( 'Simple_Booking_Calendar_Provider_Manager' ) ) {
            $manager = new Simple_Booking_Calendar_Provider_Manager();
            $provider = $manager->get_provider( 'outlook' );
            
            if ( ! is_wp_error( $provider ) && method_exists( $provider, 'is_connected' ) ) {
                return array(
                    'connected' => $provider->is_connected(),
                );
            }
        }

        return array( 'connected' => false );
    }

    /**
     * Disconnect Outlook Calendar
     */
    public function disconnect() {
        delete_option( 'simple_booking_outlook_tokens' );
        delete_option( self::STATE_OPTION );

        return array(
            'success' => true,
            'message' => __( 'Outlook Calendar disconnected', 'simple-booking' ),
        );
    }

    /**
     * Probe Outlook slot availability.
     *
     * Query params:
     * - start_datetime (optional ISO string)
     * - duration_minutes (optional int)
     *
     * @param WP_REST_Request $request
     * @return array|WP_Error
     */
    public function probe_slot( $request ) {
        $start_datetime = $request->get_param( 'start_datetime' );
        if ( empty( $start_datetime ) ) {
            $start_datetime = gmdate( 'c', time() + HOUR_IN_SECONDS );
        }

        $duration_minutes = absint( $request->get_param( 'duration_minutes' ) );
        if ( $duration_minutes <= 0 ) {
            $duration_minutes = 60;
        }

        if ( ! class_exists( 'Simple_Booking_Calendar_Provider_Manager' ) ) {
            return new WP_Error( 'outlook_probe_unavailable', __( 'Calendar provider manager not available.', 'simple-booking' ) );
        }

        $manager = new Simple_Booking_Calendar_Provider_Manager();
        $provider = $manager->get_provider( 'outlook' );

        if ( is_wp_error( $provider ) ) {
            return $provider;
        }

        if ( ! method_exists( $provider, 'probe_slot' ) ) {
            return new WP_Error( 'outlook_probe_method_missing', __( 'Outlook probe method is not available.', 'simple-booking' ) );
        }

        return $provider->probe_slot( $start_datetime, $duration_minutes );
    }
}

// Initialize Outlook Calendar handler
new Simple_Booking_Outlook_Calendar();
