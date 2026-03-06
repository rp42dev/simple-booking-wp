<?php
/**
 * Plugin Name: Simple Booking
 * Plugin URI: https://example.com/simple-booking
 * Description: A lightweight, modular booking engine with Stripe and Google Calendar integration
 * Version: 2.0
 * Author: Grow Smart Online
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: simple-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'SIMPLE_BOOKING_VERSION', '2.0' );
define( 'SIMPLE_BOOKING_PATH', plugin_dir_path( __FILE__ ) );
define( 'SIMPLE_BOOKING_URL', plugin_dir_url( __FILE__ ) );
define( 'SIMPLE_BOOKING_INCLUDES', SIMPLE_BOOKING_PATH . 'includes/' );
define( 'SIMPLE_BOOKING_VENDOR', SIMPLE_BOOKING_PATH . 'vendor/' );

/**
 * Main plugin class
 */
class Simple_Booking {

    /**
     * Constructor
     */
    public function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies() {
        // Post Types
        require_once SIMPLE_BOOKING_INCLUDES . 'post-types/class-booking-service.php';
        require_once SIMPLE_BOOKING_INCLUDES . 'post-types/class-booking.php';

        // Admin
        require_once SIMPLE_BOOKING_INCLUDES . 'admin/class-admin-settings.php';

        // Frontend
        require_once SIMPLE_BOOKING_INCLUDES . 'frontend/class-booking-form.php';

        // Stripe
        require_once SIMPLE_BOOKING_INCLUDES . 'stripe/class-stripe-handler.php';

        // Webhook
        require_once SIMPLE_BOOKING_INCLUDES . 'webhook/class-stripe-webhook.php';
        $booking_webhook_file = SIMPLE_BOOKING_INCLUDES . 'webhook/class-booking-webhook.php';
        if ( file_exists( $booking_webhook_file ) ) {
            require_once $booking_webhook_file;
        }

        // Google Calendar
        require_once SIMPLE_BOOKING_INCLUDES . 'google/class-google-calendar.php';

        // Booking Creator
        require_once SIMPLE_BOOKING_INCLUDES . 'booking/class-booking-creator.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Activation/Deactivation
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

        // Init
        add_action( 'init', array( $this, 'init' ) );
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Flush rewrite rules for custom post types
        Simple_Booking_Service::register();
        Simple_Booking_Post::register();
        flush_rewrite_rules();

        // Create success and cancel pages
        $this->create_default_pages();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Create default pages for success/cancel redirects
     */
    private function create_default_pages() {
        // Create Booking Confirmed page
        $success_page_id = $this->create_page_if_not_exists(
            __( 'Booking Confirmed', 'simple-booking' ),
            'booking-confirmed',
            __( 'Thank you for your booking! Your payment has been processed successfully.', 'simple-booking' )
        );

        // Create Booking Cancelled page
        $cancel_page_id = $this->create_page_if_not_exists(
            __( 'Booking Cancelled', 'simple-booking' ),
            'booking-cancelled',
            __( 'Your booking has been cancelled. No charges were made to your account.', 'simple-booking' )
        );

        // Store page IDs in options
        if ( $success_page_id ) {
            update_option( 'simple_booking_success_page', $success_page_id );
        }
        if ( $cancel_page_id ) {
            update_option( 'simple_booking_cancel_page', $cancel_page_id );
        }
    }

    /**
     * Create a page if it doesn't exist
     */
    private function create_page_if_not_exists( $title, $slug, $content = '' ) {
        // Check if page already exists
        $existing_page = get_page_by_path( $slug );
        if ( $existing_page ) {
            return $existing_page->ID;
        }

        // Create new page
        $page_id = wp_insert_post( array(
            'post_title'     => $title,
            'post_name'      => $slug,
            'post_content'   => $content,
            'post_status'    => 'publish',
            'post_type'      => 'page',
            'post_author'    => 1,
            'comment_status' => 'closed',
            'ping_status'    => 'closed',
        ) );

        return $page_id && ! is_wp_error( $page_id ) ? $page_id : false;
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Register custom post types
        Simple_Booking_Service::register();
        Simple_Booking_Post::register();

        // Load plugin text domain
        load_plugin_textdomain( 'simple-booking', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }

    /**
     * Get plugin setting
     */
    public static function get_setting( $key, $default = '' ) {
        $settings = get_option( 'simple_booking_settings', array() );
        return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
    }
}

// Initialize plugin
function simple_booking() {
    return new Simple_Booking();
}

// Start the plugin
simple_booking();
