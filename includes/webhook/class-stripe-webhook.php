<?php
/**
 * Stripe Webhook Handler
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Simple_Booking_Stripe_Webhook
 */
class Simple_Booking_Stripe_Webhook {

    /**
     * REST namespace
     */
    const REST_NAMESPACE = 'simple-booking/v1';

    /**
     * REST route
     */
    const REST_ROUTE = 'webhook';

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_rest_route' ) );
    }

    /**
     * Register REST route
     */
    public function register_rest_route() {
        register_rest_route(
            self::REST_NAMESPACE,
            self::REST_ROUTE,
            array(
                'methods'  => 'POST',
                'callback' => array( $this, 'handle_webhook' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    /**
     * Handle webhook
     */
    public function handle_webhook( WP_REST_Request $request ) {
        // Get headers
        $signature = $request->get_header( 'stripe_signature' );
        $payload   = $request->get_body();

        // Verify signature
        $stripe = new Simple_Booking_Stripe();
        $event  = $stripe->verify_signature( $payload, $signature );

        if ( ! $event ) {
            return new WP_REST_Response(
                array( 'error' => 'Invalid signature' ),
                400
            );
        }

        // Handle event
        switch ( $event->type ) {
            case 'checkout.session.completed':
                $this->handle_checkout_completed( $event->data->object );
                break;
        }

        return new WP_REST_Response( array( 'received' => true ), 200 );
    }

    /**
     * Handle checkout.session.completed
     */
    private function handle_checkout_completed( $session ) {
        // Check if booking already exists
        $existing = Simple_Booking_Post::get_by_payment_id( $session->id );
        if ( $existing ) {
            return;
        }

        // Get metadata
        $metadata = $session->metadata;

        // Validate metadata
        if ( empty( $metadata->service_id ) || empty( $metadata->start_datetime ) ) {
            return;
        }

        // Get service
        $service = Simple_Booking_Service::get_service( $metadata->service_id );
        if ( ! $service ) {
            return;
        }

        // Calculate end datetime
        $start_datetime = sanitize_text_field( $metadata->start_datetime );
        $duration        = isset( $service['duration'] ) ? absint( $service['duration'] ) : 60;
        $end_datetime    = $this->calculate_end_datetime( $start_datetime, $duration );

        // Prepare booking data
        $booking_data = array(
            'service_id'       => $metadata->service_id,
            'service_name'    => $service['name'],
            'customer_name'   => isset( $metadata->customer_name ) ? $metadata->customer_name : '',
            'customer_email'  => isset( $metadata->customer_email ) ? $metadata->customer_email : '',
            'customer_phone'  => isset( $metadata->customer_phone ) ? $metadata->customer_phone : '',
            'start_datetime'  => $start_datetime,
            'end_datetime'    => $end_datetime,
            'stripe_payment_id' => $session->id,
        );

        // Create booking
        $booking_id = Simple_Booking_Booking_Creator::create_booking( $booking_data );

        if ( is_wp_error( $booking_id ) ) {
            // Log error
            error_log( 'Simple Booking: Failed to create booking - ' . $booking_id->get_error_message() );
            return;
        }

        // Send confirmation email
        Simple_Booking_Booking_Creator::send_confirmation_email( $booking_id );
    }

    /**
     * Calculate end datetime
     */
    private function calculate_end_datetime( $start_datetime, $duration_minutes ) {
        $timezone = wp_timezone();
        $start    = new DateTime( $start_datetime, $timezone );
        $start->add( new DateInterval( 'PT' . $duration_minutes . 'M' ) );
        return $start->format( 'Y-m-d H:i:s' );
    }
}

// Initialize webhook handler
new Simple_Booking_Stripe_Webhook();
