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

        if ( ! empty( $booking_data['reschedule_from_booking_id'] ) ) {
            $success_url = $this->append_query_param( $success_url, 'booking', 'success' );
        }

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

    /**
     * Issue a refund for a Stripe Checkout session
     *
     * @param string $session_id Stripe Checkout session ID
     * @param int    $refund_percentage Percentage to refund (0-100)
     * @return string|WP_Error Refund ID on success, WP_Error on failure
     */
    public function issue_refund( $session_id, $refund_percentage = 100 ) {
        if ( ! $this->stripe ) {
            return new WP_Error( 'stripe_not_initialized', __( 'Stripe not initialized', 'simple-booking' ) );
        }

        $refund_percentage = intval( $refund_percentage );
        $refund_percentage = min( 100, max( 0, $refund_percentage ) );

        if ( 0 === $refund_percentage ) {
            return new WP_Error( 'refund_percentage_zero', __( 'Refund percentage is 0%', 'simple-booking' ) );
        }

        try {
            // Fetch the checkout session
            $session = $this->stripe->checkout->sessions->retrieve( $session_id );
            if ( ! isset( $session->payment_intent ) ) {
                return new WP_Error( 'no_payment_intent', __( 'No payment intent found for session', 'simple-booking' ) );
            }

            // Get the payment intent to find the charge
            $payment_intent = $this->stripe->paymentIntents->retrieve( $session->payment_intent );
            
            // Use latest_charge property (more reliable than charges->data array)
            $charge_id = null;
            if ( ! empty( $payment_intent->latest_charge ) ) {
                $charge_id = $payment_intent->latest_charge;
            } elseif ( ! empty( $payment_intent->charges->data[0]->id ) ) {
                $charge_id = $payment_intent->charges->data[0]->id;
            }

            if ( ! $charge_id ) {
                return new WP_Error( 'no_charges', __( 'No charges found for payment intent', 'simple-booking' ) );
            }

            // Get the charge to determine refund amount
            $charge = $this->stripe->charges->retrieve( $charge_id );
            $amount_to_refund = intval( $charge->amount * $refund_percentage / 100 );

            // Create refund
            $refund = $this->stripe->refunds->create( array(
                'charge' => $charge_id,
                'amount' => $amount_to_refund,
            ) );

            return $refund->id;
        } catch ( \Exception $e ) {
            return new WP_Error( 'stripe_refund_error', $e->getMessage() );
        }
    }}