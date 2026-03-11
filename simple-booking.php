<?php
/**
 * Plugin Name: Simple Booking
 * Plugin URI: https://example.com/simple-booking
 * Description: A lightweight, modular booking engine with Stripe, Google Calendar, and Outlook integration
 * Version: 3.3.0
 * Author: Grow Smart Online
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: simple-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'SIMPLE_BOOKING_VERSION', '3.3.0' );
define( 'SIMPLE_BOOKING_PATH', plugin_dir_path( __FILE__ ) );
define( 'SIMPLE_BOOKING_URL', plugin_dir_url( __FILE__ ) );
define( 'SIMPLE_BOOKING_INCLUDES', SIMPLE_BOOKING_PATH . 'includes/' );
define( 'SIMPLE_BOOKING_VENDOR', SIMPLE_BOOKING_PATH . 'vendor/' );

// Optional wp-config.php overrides:
// define( 'SIMPLE_BOOKING_LICENSE_PROVIDER', 'lemonsqueezy' ); // or 'custom'
// define( 'SIMPLE_BOOKING_LICENSE_INSTANCE_NAME', 'my-site-label' );
// define( 'SIMPLE_BOOKING_FORCE_PRO', true ); // Override license status for testing

/**
 * Main plugin class
 */
class Simple_Booking {

    /** Singleton instance. */
    private static $instance = null;

    /** @var Simple_Booking_License_Manager */
    private $license_manager = null;

    /**
     * Private constructor — use simple_booking().
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Return (and lazily create) the singleton instance.
     *
     * @return self
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load required files.
     *
     * FREE CORE is always loaded.
     * PRO files are only required when a valid license is active.
     */
    private function load_dependencies() {
        // --- FREE CORE (always loaded) ---
        require_once SIMPLE_BOOKING_INCLUDES . 'post-types/class-booking-service.php';
        require_once SIMPLE_BOOKING_INCLUDES . 'post-types/class-booking.php';
        require_once SIMPLE_BOOKING_INCLUDES . 'admin/class-admin-settings.php';
        require_once SIMPLE_BOOKING_INCLUDES . 'frontend/class-booking-form.php';
        require_once SIMPLE_BOOKING_INCLUDES . 'webhook/class-booking-webhook.php';
        require_once SIMPLE_BOOKING_INCLUDES . 'calendar/interface-calendar-provider.php';
        require_once SIMPLE_BOOKING_INCLUDES . 'calendar/class-calendar-provider-manager.php';
        require_once SIMPLE_BOOKING_INCLUDES . 'calendar/providers/class-ics-provider.php';
        if ( file_exists( SIMPLE_BOOKING_INCLUDES . 'calendar/providers/class-google-provider.php' ) ) {
            require_once SIMPLE_BOOKING_INCLUDES . 'calendar/providers/class-google-provider.php';
        }
        if ( file_exists( SIMPLE_BOOKING_INCLUDES . 'calendar/providers/class-outlook-provider.php' ) ) {
            require_once SIMPLE_BOOKING_INCLUDES . 'calendar/providers/class-outlook-provider.php';
        }
        require_once SIMPLE_BOOKING_INCLUDES . 'booking/class-booking-creator.php';

        // OAuth callback handlers must always be loaded so REST routes exist
        // during connect/reconnect flows.
        if ( file_exists( SIMPLE_BOOKING_INCLUDES . 'google/class-google-calendar.php' ) ) {
            require_once SIMPLE_BOOKING_INCLUDES . 'google/class-google-calendar.php';
        }
        if ( file_exists( SIMPLE_BOOKING_INCLUDES . 'outlook/class-outlook-calendar.php' ) ) {
            require_once SIMPLE_BOOKING_INCLUDES . 'outlook/class-outlook-calendar.php';
        }

        // Load Staff class definition when present; actual CPT registration is
        // gated in init() by license status.
        if ( file_exists( SIMPLE_BOOKING_INCLUDES . 'post-types/class-staff.php' ) ) {
            require_once SIMPLE_BOOKING_INCLUDES . 'post-types/class-staff.php';
        }

        // License Manager (needed before Pro gate check)
        require_once SIMPLE_BOOKING_INCLUDES . 'license/class-license-manager.php';
        $this->license_manager = new Simple_Booking_License_Manager();

        // --- PRO (only when licensed) ---
        if ( $this->license_manager->is_pro_active() ) {
            require_once SIMPLE_BOOKING_INCLUDES . 'stripe/class-stripe-handler.php';
            require_once SIMPLE_BOOKING_INCLUDES . 'webhook/class-stripe-webhook.php';
        }
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

        // Mail failure diagnostics
        add_action( 'wp_mail_failed', array( $this, 'log_mail_failure' ) );
    }

    /**
     * Log wp_mail failures when debug mode is enabled.
     *
     * @param WP_Error $error
     * @return void
     */
    public function log_mail_failure( $error ) {
        if ( ! self::get_setting( 'debug_mode', false ) ) {
            return;
        }

        if ( ! is_wp_error( $error ) ) {
            return;
        }

        $message = $error->get_error_message();
        $data = $error->get_error_data();
        $context = is_array( $data ) ? wp_json_encode( $data ) : '';

        error_log( '[SIMPLE_BOOKING_EMAIL] wp_mail_failed: ' . $message . ( $context ? ' data=' . $context : '' ) );
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
        update_option( 'simple_booking_pages_initialized', '1' );
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

        // Create Booking Management page (used for tokenized reschedule links)
        $manage_page_id = $this->create_page_if_not_exists(
            __( 'Manage Booking', 'simple-booking' ),
            'booking-manage',
            __( 'Use the form below to manage your booking.', 'simple-booking' ) . "\n\n" . '[simple_booking_form]'
        );

        // Store page IDs in options
        if ( $success_page_id ) {
            update_option( 'simple_booking_success_page', $success_page_id );
        }
        if ( $cancel_page_id ) {
            update_option( 'simple_booking_cancel_page', $cancel_page_id );
        }
        if ( $manage_page_id ) {
            update_option( 'simple_booking_manage_page', $manage_page_id );
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
     * Return the license manager instance.
     *
     * @return Simple_Booking_License_Manager
     */
    public function get_license_manager() {
        return $this->license_manager;
    }

    /**
     * Return true when a valid Pro license is active.
     *
     * @return bool
     */
    public function is_pro_active() {
        return $this->license_manager instanceof Simple_Booking_License_Manager
            && $this->license_manager->is_pro_active();
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Register custom post types
        Simple_Booking_Service::register();
        Simple_Booking_Post::register();
        if ( class_exists( 'Simple_Booking_Staff' ) ) {
            Simple_Booking_Staff::register();
        }
        Simple_Booking_Booking_Webhook::register_hooks();

        // Ensure default pages exist after plugin upgrades (without requiring reactivation)
        if ( '1' !== get_option( 'simple_booking_pages_initialized', '' ) ) {
            $this->create_default_pages();
            update_option( 'simple_booking_pages_initialized', '1' );
        }

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

// Global accessor — always returns the same instance.
function simple_booking() {
    return Simple_Booking::instance();
}

// Boot.
simple_booking();
