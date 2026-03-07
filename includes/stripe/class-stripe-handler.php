<?php
/**
 * Stripe Handler
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Simple_Booking_Stripe
 */
class Simple_Booking_Stripe {

    /**
     * Stripe instance
     */
    private $stripe;

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_stripe();
    }

    /**
     * Initialize Stripe
     */
    private function init_stripe() {
        if ( ! class_exists( '\Stripe\StripeClient' ) ) {
            $vendor_path = defined( 'SIMPLE_BOOKING_VENDOR' ) ? SIMPLE_BOOKING_VENDOR : SIMPLE_BOOKING_PATH . 'vendor';
            if ( file_exists( $vendor_path . '/stripe-php/init.php' ) ) {
                require_once $vendor_path . '/stripe-php/init.php';
            }
        }

        if ( ! class_exists( '\Stripe\StripeClient' ) ) {
            return;
        }

        $secret_key = simple_booking()->get_setting( 'stripe_secret_key' );
        if ( empty( $secret_key ) ) {
            return;
        }

        \Stripe\Stripe::setApiKey( $secret_key );
        $this->stripe = new \Stripe\StripeClient( $secret_key );
    }

    /**
     * Create checkout session
     */
    public function create_checkout_session( $service, $booking_data ) {
        if ( empty( $service['stripe_price_id'] ) ) {
            return new WP_Error(
                'no_price_id',
                __( 'No Stripe Price ID configured for this service.', 'simple-booking' )
            );
        }

        $site_url = get_site_url();

        // Get success and cancel page URLs
        $success_url = $this->get_success_page_url();
        $cancel_url = $this->get_cancel_page_url();

        try {
            $session = \Stripe\Checkout\Session::create(
                array(
                    'mode'         => 'payment',
                    'line_items'   => array(
                        array(
                            'price' => $service['stripe_price_id'],
                            'quantity' => 1,
                        ),
                    ),
                    'success_url'  => $this->append_query_param( $success_url, 'session_id', '{CHECKOUT_SESSION_ID}' ),
                    'cancel_url'   => $cancel_url,
                    'metadata'     => array(
                        'customer_name'    => sanitize_text_field( $booking_data['customer_name'] ),
                        'customer_email'   => sanitize_email( $booking_data['customer_email'] ),
                        'customer_phone'   => sanitize_text_field( $booking_data['customer_phone'] ),
                        'service_id'       => absint( $booking_data['service_id'] ),
                        'start_datetime'   => sanitize_text_field( $booking_data['start_datetime'] ),
                        'reschedule_from_booking_id' => isset( $booking_data['reschedule_from_booking_id'] ) ? absint( $booking_data['reschedule_from_booking_id'] ) : 0,
                        'reschedule_token' => isset( $booking_data['reschedule_token'] ) ? sanitize_text_field( $booking_data['reschedule_token'] ) : '',
                    ),
                )
            );

            return $session;
        } catch ( \Exception $e ) {
            return new WP_Error(
                'stripe_error',
                $e->getMessage()
            );
        }
    }

    /**
     * Verify webhook signature
     */
    public function verify_signature( $payload, $signature ) {
        $webhook_secret = simple_booking()->get_setting( 'stripe_webhook_secret' );
        if ( empty( $webhook_secret ) || empty( $signature ) ) {
            return false;
        }

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $signature,
                $webhook_secret
            );
            return $event;
        } catch ( \Exception $e ) {
            return false;
        }
    }

    /**
     * Get session by ID
     */
    public function get_session( $session_id ) {
        try {
            return \Stripe\Checkout\Session::retrieve( $session_id );
        } catch ( \Exception $e ) {
            return null;
        }
    }

    /**
     * Get publishable key
     */
    public function get_publishable_key() {
        return simple_booking()->get_setting( 'stripe_publishable_key' );
    }

    /**
     * Get success page URL
     */
    private function get_success_page_url() {
        $page_id = get_option( 'simple_booking_success_page' );
        if ( $page_id ) {
            // Verify page still exists
            $page = get_post( $page_id );
            if ( $page && $page->post_status === 'publish' ) {
                return get_permalink( $page_id );
            }
            // Page was deleted, clear the option
            delete_option( 'simple_booking_success_page' );
        }
        // Fallback to home URL with query param
        return home_url( '/' );
    }

    /**
     * Get cancel page URL
     */
    private function get_cancel_page_url() {
        $page_id = get_option( 'simple_booking_cancel_page' );
        if ( $page_id ) {
            // Verify page still exists
            $page = get_post( $page_id );
            if ( $page && $page->post_status === 'publish' ) {
                return get_permalink( $page_id );
            }
            // Page was deleted, clear the option
            delete_option( 'simple_booking_cancel_page' );
        }
        // Fallback to home URL with query param
        return home_url( '/' );
    }

    /**
     * Append query parameter to URL
     */
    private function append_query_param( $url, $param, $value ) {
        $separator = ( strpos( $url, '?' ) !== false ) ? '&' : '?';
        return $url . $separator . $param . '=' . $value;
    }
}
