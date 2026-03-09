<?php
/**
 * License Manager
 * 
 * Handles Pro license activation, validation, and feature gating.
 * 
 * @package Simple_Booking
 * @subpackage License
 * @since 3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Simple_Booking_License_Manager
 * 
 * Manages licensing for Free vs Pro feature gating.
 */
class Simple_Booking_License_Manager {

    /**
     * License option key
     */
    const LICENSE_OPTION = 'simple_booking_license';

    /**
     * License cache transient key
     */
    const CACHE_KEY = 'simple_booking_license_cache';

    /**
     * Cache duration (24 hours)
     */
    const CACHE_DURATION = DAY_IN_SECONDS;

    /**
     * Grace period days
     */
    const GRACE_PERIOD_DAYS = 30;

    /**
     * API base URL (TODO: Replace with your license server)
     */
    const API_URL = 'https://yourdomain.com/api/v1/licenses';

    /**
     * Constructor
     */
    public function __construct() {
        // TODO: Add hooks for admin notices
        add_action( 'admin_notices', array( $this, 'show_admin_notices' ) );
    }

    /**
     * Get stored license key
     * 
     * @return string License key or empty string
     */
    public function get_license_key() {
        // TODO: Implement
        $license = get_option( self::LICENSE_OPTION, array() );
        return isset( $license['key'] ) ? $license['key'] : '';
    }

    /**
     * Set license key
     * 
     * @param string $key License key
     * @return bool Success
     */
    public function set_license_key( $key ) {
        // TODO: Implement
        $license = get_option( self::LICENSE_OPTION, array() );
        $license['key'] = sanitize_text_field( $key );
        return update_option( self::LICENSE_OPTION, $license );
    }

    /**
     * Activate license with remote API
     * 
     * @param string $key License key
     * @return true|WP_Error
     */
    public function activate_license( $key ) {
        // TODO: Implement API call
        
        // Example API request structure:
        /*
        $response = wp_remote_post( self::API_URL . '/activate', array(
            'body' => array(
                'license_key' => $key,
                'site_url'    => home_url(),
                'product'     => 'simple-booking',
            ),
            'timeout' => 15,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        
        if ( ! isset( $body['success'] ) || ! $body['success'] ) {
            return new WP_Error( 'activation_failed', $body['message'] ?? 'Activation failed' );
        }

        // Store license data
        $license = array(
            'key'          => $key,
            'status'       => $body['license']['status'],
            'plan'         => $body['license']['plan'],
            'expires'      => $body['license']['expires'],
            'activated_at' => current_time( 'mysql' ),
            'last_check'   => current_time( 'timestamp' ),
        );

        update_option( self::LICENSE_OPTION, $license );
        delete_transient( self::CACHE_KEY );

        return true;
        */

        return new WP_Error( 'not_implemented', 'License activation not yet implemented' );
    }

    /**
     * Deactivate license with remote API
     * 
     * @return true|WP_Error
     */
    public function deactivate_license() {
        // TODO: Implement API call
        
        /*
        $key = $this->get_license_key();
        
        $response = wp_remote_post( self::API_URL . '/deactivate', array(
            'body' => array(
                'license_key' => $key,
                'site_url'    => home_url(),
            ),
            'timeout' => 15,
        ) );

        delete_option( self::LICENSE_OPTION );
        delete_transient( self::CACHE_KEY );

        return true;
        */

        return new WP_Error( 'not_implemented', 'License deactivation not yet implemented' );
    }

    /**
     * Check license status with remote API (cached)
     * 
     * @return array Status data
     */
    public function check_license_status() {
        // TODO: Implement with caching

        // Dev/test override for local environments.
        if ( defined( 'SIMPLE_BOOKING_FORCE_PRO' ) && SIMPLE_BOOKING_FORCE_PRO ) {
            return array(
                'valid'   => true,
                'status'  => 'active',
                'plan'    => 'pro',
                'expires' => null,
            );
        }

        $override = apply_filters( 'simple_booking_license_status_override', null );
        if ( is_array( $override ) ) {
            return wp_parse_args(
                $override,
                array(
                    'valid'   => false,
                    'status'  => 'free',
                    'plan'    => 'free',
                    'expires' => null,
                )
            );
        }
        
        // Check cache first
        $cached = get_transient( self::CACHE_KEY );
        if ( false !== $cached ) {
            return $cached;
        }

        // If no license key, return free status
        $key = $this->get_license_key();
        if ( empty( $key ) ) {
            return array(
                'valid'   => false,
                'status'  => 'free',
                'plan'    => 'free',
                'expires' => null,
            );
        }

        /*
        // API check
        $response = wp_remote_get( self::API_URL . '/check', array(
            'body' => array(
                'license_key' => $key,
                'site_url'    => home_url(),
            ),
            'timeout' => 10,
        ) );

        if ( is_wp_error( $response ) ) {
            // On API failure, use local data
            $license = get_option( self::LICENSE_OPTION, array() );
            $status = $this->get_local_status( $license );
        } else {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            $status = array(
                'valid'   => $body['valid'] ?? false,
                'status'  => $body['status'] ?? 'unknown',
                'plan'    => $body['plan'] ?? 'free',
                'expires' => $body['expires'] ?? null,
            );
        }

        // Cache for 24 hours
        set_transient( self::CACHE_KEY, $status, self::CACHE_DURATION );
        
        return $status;
        */

        // Temporary: Return free status
        return array(
            'valid'   => false,
            'status'  => 'free',
            'plan'    => 'free',
            'expires' => null,
        );
    }

    /**
     * Check if Pro is active (considering grace period)
     * 
     * @return bool
     */
    public function is_pro_active() {
        // TODO: Implement
        
        $status = $this->check_license_status();
        
        // Valid license = active
        if ( $status['valid'] && $status['status'] === 'active' ) {
            return true;
        }

        // Check grace period for expired licenses
        if ( $status['status'] === 'expired' ) {
            $grace_remaining = $this->get_grace_period_remaining();
            if ( $grace_remaining > 0 ) {
                return true; // Still in grace period
            }
        }

        return false;
    }

    /**
     * Check if specific feature is available
     * 
     * @param string $feature Feature name (stripe, google, staff, etc)
     * @return bool
     */
    public function is_feature_available( $feature ) {
        // TODO: Implement
        
        // All pro features require pro license
        $pro_features = array(
            'stripe',
            'google',
            'staff',
            'refunds',
            'reschedule',
            'cancel',
            'webhooks',
            'advanced_scheduling',
        );

        if ( in_array( $feature, $pro_features, true ) ) {
            return $this->is_pro_active();
        }

        // Free features always available
        return true;
    }

    /**
     * Get remaining grace period days
     * 
     * @return int Days remaining (0 if expired or not applicable)
     */
    public function get_grace_period_remaining() {
        // TODO: Implement
        
        $license = get_option( self::LICENSE_OPTION, array() );
        
        // No license or not expired = no grace period
        if ( empty( $license['expires'] ) || $license['status'] !== 'expired' ) {
            return 0;
        }

        $expires_timestamp = strtotime( $license['expires'] );
        $grace_end = $expires_timestamp + ( self::GRACE_PERIOD_DAYS * DAY_IN_SECONDS );
        $now = current_time( 'timestamp' );

        if ( $now >= $grace_end ) {
            return 0; // Grace period ended
        }

        $seconds_remaining = $grace_end - $now;
        return ceil( $seconds_remaining / DAY_IN_SECONDS );
    }

    /**
     * Show admin notices
     */
    public function show_admin_notices() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( ! defined( 'SIMPLE_BOOKING_FORCE_PRO' ) || ! SIMPLE_BOOKING_FORCE_PRO ) {
            return;
        }

        if ( function_exists( 'get_current_screen' ) ) {
            $screen = get_current_screen();
            if ( ! $screen || 'settings_page_simple-booking-settings' !== $screen->id ) {
                return;
            }
        }

        echo '<div class="notice notice-warning is-dismissible"><p>';
        echo esc_html__( 'Simple Booking: Pro is currently forced ON via SIMPLE_BOOKING_FORCE_PRO (development override).', 'simple-booking' );
        echo '</p></div>';
    }

    /**
     * Get local license status (fallback when API unavailable)
     * 
     * @param array $license License data from options
     * @return array Status data
     */
    private function get_local_status( $license ) {
        if ( empty( $license ) ) {
            return array(
                'valid'   => false,
                'status'  => 'free',
                'plan'    => 'free',
                'expires' => null,
            );
        }

        // Check if expired
        $now = current_time( 'timestamp' );
        $expires = isset( $license['expires'] ) ? strtotime( $license['expires'] ) : 0;

        $status = 'active';
        if ( $expires > 0 && $now >= $expires ) {
            $status = 'expired';
        }

        return array(
            'valid'   => ( $status === 'active' ),
            'status'  => $status,
            'plan'    => $license['plan'] ?? 'free',
            'expires' => $license['expires'] ?? null,
        );
    }
}
