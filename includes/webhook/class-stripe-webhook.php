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
        // Ensure Pro files are loaded (re-check in case they were skipped during init)
        if ( function_exists( 'simple_booking' ) ) {
            simple_booking()->load_pro_dependencies();
        }

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
            case 'customer.subscription.updated':
            case 'customer.subscription.deleted':
                $this->handle_subscription_updated( $event->data->object );
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

        // Validate basic metadata
        if ( empty( $metadata->service_id ) ) {
            return;
        }

        // Get service
        $service = Simple_Booking_Service::get_service( $metadata->service_id );
        if ( ! $service ) {
            return;
        }

        // Check if it's a membership
        $is_membership = isset( $service['service_type'] ) && 'recurring_group' === $service['service_type'];

        // Validate start_datetime for regular bookings
        if ( ! $is_membership && empty( $metadata->start_datetime ) ) {
            return;
        }

        if ( $is_membership ) {
            $this->create_membership( $session, $metadata, $service );
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
            'meeting_link'    => isset( $service['meeting_link'] ) ? $service['meeting_link'] : '',
            'auto_google_meet' => isset( $service['auto_google_meet'] ) ? $service['auto_google_meet'] : '0',
        );

        if ( isset( $metadata->reschedule_from_booking_id ) && isset( $metadata->reschedule_token ) ) {
            $booking_data['reschedule_from_booking_id'] = absint( $metadata->reschedule_from_booking_id );
            $booking_data['reschedule_token'] = sanitize_text_field( $metadata->reschedule_token );
        }

        // Create booking
        $booking_id = Simple_Booking_Booking_Creator::create_booking( $booking_data );

        if ( is_wp_error( $booking_id ) ) {
            // Log error
            error_log( 'Simple Booking: Failed to create booking - ' . $booking_id->get_error_message() );
            return;
        }
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

    /**
     * Create a group membership
     */
    private function create_membership( $session, $metadata, $service ) {
        $membership_id = wp_insert_post( array(
            'post_type'   => 'booking_membership',
            'post_title'  => sprintf( __( 'Membership: %1$s - %2$s', 'simple-booking' ), $metadata->customer_name, $service['name'] ),
            'post_status' => 'publish',
        ) );

        if ( is_wp_error( $membership_id ) ) {
            error_log( 'Simple Booking: Failed to create membership - ' . $membership_id->get_error_message() );
            return;
        }

        update_post_meta( $membership_id, '_customer_name', sanitize_text_field( $metadata->customer_name ) );
        update_post_meta( $membership_id, '_customer_email', sanitize_email( $metadata->customer_email ) );
        update_post_meta( $membership_id, '_service_id', absint( $metadata->service_id ) );
        update_post_meta( $membership_id, '_stripe_subscription_id', sanitize_text_field( $session->subscription ) );
        update_post_meta( $membership_id, '_stripe_customer_id', sanitize_text_field( $session->customer ) );
        update_post_meta( $membership_id, '_customer_timezone', isset( $metadata->customer_timezone ) ? sanitize_text_field( $metadata->customer_timezone ) : '' );
        update_post_meta( $membership_id, '_status', 'active' );

        // Trigger welcome email (implemented later)
        do_action( 'simple_booking_membership_created', $membership_id, $metadata, $service );
    }

    /**
     * Handle subscription updates (cancellations, past due, etc)
     */
    private function handle_subscription_updated( $subscription ) {
        $subscription_id = $subscription->id;
        $status          = $subscription->status;

        // Find the membership post by Stripe Subscription ID
        $args = array(
            'post_type'  => 'booking_membership',
            'meta_key'   => '_stripe_subscription_id',
            'meta_value' => $subscription_id,
            'posts_per_page' => 1,
            'post_status' => 'any',
        );
        $query = new WP_Query( $args );

        if ( $query->have_posts() ) {
            $membership_id = $query->posts[0]->ID;
            update_post_meta( $membership_id, '_status', sanitize_text_field( $status ) );
            
            // Track if they cancelled but still have time remaining
            if ( isset( $subscription->cancel_at_period_end ) ) {
                update_post_meta( $membership_id, '_cancel_at_period_end', $subscription->cancel_at_period_end ? '1' : '0' );
            }

            do_action( 'simple_booking_membership_updated', $membership_id, $status );
        }
    }
}

// Initialize webhook handler
new Simple_Booking_Stripe_Webhook();
