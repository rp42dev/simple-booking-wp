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

// Load the API client
require_once dirname( __FILE__ ) . '/class-license-api-client.php';

/**
 * Class Simple_Booking_License_Manager
 * 
 * Manages licensing for Free vs Pro feature gating.
 */
class Simple_Booking_License_Manager {

    /**
     * Normalize common truthy values from wp-config constants.
     *
     * @param mixed $value Raw value.
     * @return bool
     */
    private function to_bool( $value ) {
        if ( is_bool( $value ) ) {
            return $value;
        }

        if ( is_int( $value ) || is_float( $value ) ) {
            return (bool) $value;
        }

        if ( is_string( $value ) ) {
            return in_array( strtolower( trim( $value ) ), array( '1', 'true', 'yes', 'on' ), true );
        }

        return false;
    }

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
        $key = sanitize_text_field( $key );

        if ( empty( $key ) ) {
            return new WP_Error( 'empty_key', 'License key is required.' );
        }

        // Call Lemon Squeezy API to activate
        $result = Simple_Booking_License_API_Client::activate_license( $key );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        // Validate the license after activation
        $validation = Simple_Booking_License_API_Client::validate_license( $key );

        if ( is_wp_error( $validation ) ) {
            return $validation;
        }

        // Store license data
        $license = array(
            'key'           => $key,
            'status'        => $validation['valid'] ? 'active' : 'invalid',
            'expires_at'    => $validation['expires_at'],
            'expires_soon'  => $validation['expires_soon'],
            'activated_at'  => current_time( 'mysql' ),
            'last_validated' => current_time( 'mysql' ),
        );

        $updated = update_option( self::LICENSE_OPTION, $license );
        delete_transient( self::CACHE_KEY );

        return $updated ? true : new WP_Error( 'storage_failed', 'Could not store license data.' );
    }

    /**
     * Deactivate license with remote API
     * 
     * @return true|WP_Error
     */
    public function deactivate_license() {
        $key = $this->get_license_key();

        if ( empty( $key ) ) {
            return new WP_Error( 'no_license', 'No license key to deactivate.' );
        }

        // Call Lemon Squeezy API to deactivate
        $result = Simple_Booking_License_API_Client::deactivate_license( $key );

        if ( is_wp_error( $result ) ) {
            // Still clear local license even if API fails
            delete_option( self::LICENSE_OPTION );
            delete_transient( self::CACHE_KEY );
            return $result;
        }

        // Clear local license data
        delete_option( self::LICENSE_OPTION );
        delete_transient( self::CACHE_KEY );

        return true;
    }

    /**
     * Check license status with remote API (cached)
     * 
     * @return array Status data
     */
    public function check_license_status() {
        // Dev/test override for local environments.
        $constants = array(
            'SIMPLE_BOOKING_FORCE_PRO',
            'SIMPLE_BOOKING_PRO_MODE',
            'SIMPLE_BOOKING_PRO',
            'SIMPLE_BOOKING_IS_PRO',
        );

        foreach ( $constants as $constant_name ) {
            if ( defined( $constant_name ) && $this->to_bool( constant( $constant_name ) ) ) {
                return array(
                    'valid'   => true,
                    'status'  => 'active',
                    'plan'    => 'pro',
                    'expires' => null,
                );
            }
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

        // API validation
        $validation = Simple_Booking_License_API_Client::validate_license( $key );

        if ( is_wp_error( $validation ) ) {
            // API failed; check grace period on stored license
            $license = get_option( self::LICENSE_OPTION, array() );
            $expires_at = isset( $license['expires_at'] ) ? $license['expires_at'] : null;

            if ( $expires_at ) {
                $grace_start = strtotime( $expires_at );
                $grace_end   = $grace_start + ( self::GRACE_PERIOD_DAYS * DAY_IN_SECONDS );

                if ( time() <= $grace_end ) {
                    // Within grace period
                    $status = array(
                        'valid'   => false,
                        'status'  => 'grace_period',
                        'plan'    => 'pro',
                        'expires' => $expires_at,
                    );

                    set_transient( self::CACHE_KEY, $status, HOUR_IN_SECONDS );
                    return $status;
                }
            }

            // API error and no grace period = free
            return array(
                'valid'   => false,
                'status'  => 'unknown',
                'plan'    => 'free',
                'expires' => null,
            );
        }

        // Build status from Lemon Squeezy response
        $status = array(
            'valid'   => $validation['valid'],
            'status'  => $validation['valid'] ? 'active' : 'expired',
            'plan'    => $validation['valid'] ? 'pro' : 'free',
            'expires' => $validation['expires_at'],
        );

        // Cache for 24 hours
        set_transient( self::CACHE_KEY, $status, self::CACHE_DURATION );
        
        return $status;
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
        // TODO: Implement notices

        // Welcome notice (free)
        // Grace period warning
        // Grace period expired
        // Activation success
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
