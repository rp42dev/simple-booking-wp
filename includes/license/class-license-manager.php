<?php
/**
 * License Manager
 *
 * Handles Pro license activation, validation, and feature gating.
 *
 * @package Simple_Booking
 * @since 3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Simple_Booking_License_Manager {

    /** WordPress option key storing persisted license data. */
    const LICENSE_OPTION = 'simple_booking_license';

    /** Transient key for the 24-hour remote-check cache. */
    const CACHE_KEY = 'simple_booking_license_cache';

    /** How long to cache a remote status response (seconds). */
    const CACHE_DURATION = DAY_IN_SECONDS;

    /** Days Pro stays active after expiry before being shut off. */
    const GRACE_PERIOD_DAYS = 30;

    /** License server base URL — set via constant or filter. */
    const API_URL = 'https://yourdomain.com/api/v1/licenses';

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    public function __construct() {
        add_action( 'admin_notices', array( $this, 'show_admin_notices' ) );
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Return the stored license key.
     *
     * @return string
     */
    public function get_license_key() {
        $license = get_option( self::LICENSE_OPTION, array() );
        return isset( $license['key'] ) ? (string) $license['key'] : '';
    }

    /**
     * Persist a license key without activating it.
     *
     * @param string $key
     * @return bool
     */
    public function set_license_key( $key ) {
        $license        = get_option( self::LICENSE_OPTION, array() );
        $license['key'] = sanitize_text_field( $key );
        return update_option( self::LICENSE_OPTION, $license, false );
    }

    /**
     * Activate a license key against the remote API.
     *
     * @param string $key
     * @return true|WP_Error
     */
    public function activate_license( $key ) {
        $key = sanitize_text_field( $key );
        if ( empty( $key ) ) {
            return new WP_Error( 'empty_key', __( 'Please enter a license key.', 'simple-booking' ) );
        }

        $api_url = $this->get_api_url();

        $response = wp_remote_post(
            $api_url . '/activate',
            array(
                'body'    => array(
                    'license_key' => $key,
                    'site_url'    => home_url(),
                    'product'     => 'simple-booking',
                ),
                'timeout' => 15,
            )
        );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'api_unreachable', __( 'Could not reach the license server. Please try again.', 'simple-booking' ) );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 !== (int) $status_code || empty( $body['success'] ) ) {
            $message = isset( $body['message'] ) ? sanitize_text_field( $body['message'] ) : __( 'Activation failed.', 'simple-booking' );
            return new WP_Error( 'activation_failed', $message );
        }

        $lic = isset( $body['license'] ) && is_array( $body['license'] ) ? $body['license'] : array();

        $license = array(
            'key'          => $key,
            'status'       => isset( $lic['status'] ) ? sanitize_text_field( $lic['status'] ) : 'active',
            'plan'         => isset( $lic['plan'] ) ? sanitize_text_field( $lic['plan'] ) : 'pro',
            'expires'      => isset( $lic['expires'] ) ? sanitize_text_field( $lic['expires'] ) : null,
            'activated_at' => current_time( 'mysql' ),
            'last_check'   => time(),
        );

        update_option( self::LICENSE_OPTION, $license, false );
        delete_transient( self::CACHE_KEY );

        return true;
    }

    /**
     * Deactivate the current license against the remote API and clear local data.
     *
     * @return true|WP_Error
     */
    public function deactivate_license() {
        $key = $this->get_license_key();

        if ( ! empty( $key ) ) {
            $api_url = $this->get_api_url();
            wp_remote_post(
                $api_url . '/deactivate',
                array(
                    'body'    => array(
                        'license_key' => $key,
                        'site_url'    => home_url(),
                    ),
                    'timeout' => 15,
                )
            );
            // Proceed even if the remote call fails — still clear local state.
        }

        delete_option( self::LICENSE_OPTION );
        delete_transient( self::CACHE_KEY );

        return true;
    }

    /**
     * Return current license status, using a 24-hour transient cache.
     *
     * @return array { valid: bool, status: string, plan: string, expires: string|null }
     */
    public function check_license_status() {
        // Hard override for local/test environments.
        if ( defined( 'SIMPLE_BOOKING_FORCE_PRO' ) && true === SIMPLE_BOOKING_FORCE_PRO ) {
            return array( 'valid' => true, 'status' => 'active', 'plan' => 'pro', 'expires' => null );
        }

        // Allow test suites / staging to inject a status without a real API.
        $override = apply_filters( 'simple_booking_license_status_override', null );
        if ( is_array( $override ) ) {
            return wp_parse_args( $override, array( 'valid' => false, 'status' => 'free', 'plan' => 'free', 'expires' => null ) );
        }

        // Cache hit.
        $cached = get_transient( self::CACHE_KEY );
        if ( false !== $cached && is_array( $cached ) ) {
            return $cached;
        }

        $key = $this->get_license_key();
        if ( empty( $key ) ) {
            return array( 'valid' => false, 'status' => 'free', 'plan' => 'free', 'expires' => null );
        }

        // Remote check.
        $api_url  = $this->get_api_url();
        $response = wp_remote_get(
            add_query_arg(
                array(
                    'license_key' => $key,
                    'site_url'    => rawurlencode( home_url() ),
                ),
                $api_url . '/check'
            ),
            array( 'timeout' => 10 )
        );

        if ( is_wp_error( $response ) ) {
            // API down — fall back to local data so the site keeps running.
            $status = $this->get_local_status( get_option( self::LICENSE_OPTION, array() ) );
            set_transient( self::CACHE_KEY, $status, HOUR_IN_SECONDS ); // short cache on failure
            return $status;
        }

        $body   = json_decode( wp_remote_retrieve_body( $response ), true );
        $status = array(
            'valid'   => ! empty( $body['valid'] ),
            'status'  => isset( $body['status'] ) ? sanitize_text_field( $body['status'] ) : 'unknown',
            'plan'    => isset( $body['plan'] ) ? sanitize_text_field( $body['plan'] ) : 'free',
            'expires' => isset( $body['expires'] ) ? sanitize_text_field( $body['expires'] ) : null,
        );

        // Persist remote result into local option so offline fallback stays fresh.
        $local = get_option( self::LICENSE_OPTION, array() );
        $local = array_merge( $local, array(
            'status'     => $status['status'],
            'plan'       => $status['plan'],
            'expires'    => $status['expires'],
            'last_check' => time(),
        ) );
        update_option( self::LICENSE_OPTION, $local, false );

        set_transient( self::CACHE_KEY, $status, self::CACHE_DURATION );

        return $status;
    }

    /**
     * Return true when a valid Pro license (or active grace period) is present.
     *
     * @return bool
     */
    public function is_pro_active() {
        $status = $this->check_license_status();

        if ( ! empty( $status['valid'] ) && 'active' === $status['status'] ) {
            return true;
        }

        if ( 'expired' === $status['status'] && $this->get_grace_period_remaining() > 0 ) {
            return true;
        }

        return false;
    }

    /**
     * Return true when a specific feature is available on the current plan.
     *
     * @param string $feature  stripe | google | outlook | staff | refunds | webhooks | advanced_scheduling
     * @return bool
     */
    public function is_feature_available( $feature ) {
        static $pro_features = array(
            'stripe', 'google', 'outlook', 'staff',
            'refunds', 'reschedule_tokens', 'webhooks', 'advanced_scheduling',
        );

        if ( in_array( $feature, $pro_features, true ) ) {
            return $this->is_pro_active();
        }

        return true; // Free features always available.
    }

    /**
     * Return days remaining in the grace period (0 when not applicable).
     *
     * @return int
     */
    public function get_grace_period_remaining() {
        $license = get_option( self::LICENSE_OPTION, array() );

        if ( empty( $license['expires'] ) || 'expired' !== ( $license['status'] ?? '' ) ) {
            return 0;
        }

        $grace_end = strtotime( $license['expires'] ) + ( self::GRACE_PERIOD_DAYS * DAY_IN_SECONDS );
        $remaining = $grace_end - time();

        return $remaining > 0 ? (int) ceil( $remaining / DAY_IN_SECONDS ) : 0;
    }

    /**
     * Force-clear the remote-check cache (e.g. after activate/deactivate).
     *
     * @return void
     */
    public function clear_cache() {
        delete_transient( self::CACHE_KEY );
    }

    // -------------------------------------------------------------------------
    // Admin notices
    // -------------------------------------------------------------------------

    /**
     * Show relevant license notices in wp-admin.
     *
     * @return void
     */
    public function show_admin_notices() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $status = $this->check_license_status();

        // Grace period warning (≤7 days left).
        if ( 'expired' === $status['status'] ) {
            $days = $this->get_grace_period_remaining();
            if ( $days > 0 && $days <= 7 ) {
                echo '<div class="notice notice-warning"><p>';
                printf(
                    /* translators: %d days remaining in grace period */
                    esc_html__( 'Simple Booking Pro: Your license has expired. Pro features will stop working in %d day(s). Please renew to avoid interruption.', 'simple-booking' ),
                    (int) $days
                );
                echo '</p></div>';
            } elseif ( 0 === $days ) {
                echo '<div class="notice notice-error"><p>';
                esc_html_e( 'Simple Booking Pro: Your license has expired and the grace period has ended. Pro features are disabled. Please renew your license.', 'simple-booking' );
                echo '</p></div>';
            }
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Derive status from locally-stored license data (used when API is unreachable).
     *
     * @param array $license
     * @return array
     */
    private function get_local_status( $license ) {
        if ( empty( $license ) || empty( $license['key'] ) ) {
            return array( 'valid' => false, 'status' => 'free', 'plan' => 'free', 'expires' => null );
        }

        $status  = isset( $license['status'] ) ? $license['status'] : 'active';
        $expires = isset( $license['expires'] ) ? $license['expires'] : null;

        if ( $expires && time() >= strtotime( $expires ) ) {
            $status = 'expired';
        }

        return array(
            'valid'   => ( 'active' === $status ),
            'status'  => $status,
            'plan'    => isset( $license['plan'] ) ? $license['plan'] : 'pro',
            'expires' => $expires,
        );
    }

    /**
     * Return the API base URL, allowing override via constant or filter.
     *
     * @return string
     */
    private function get_api_url() {
        if ( defined( 'SIMPLE_BOOKING_LICENSE_API_URL' ) ) {
            return rtrim( SIMPLE_BOOKING_LICENSE_API_URL, '/' );
        }
        return apply_filters( 'simple_booking_license_api_url', rtrim( self::API_URL, '/' ) );
    }
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
        if ( defined( 'SIMPLE_BOOKING_FORCE_PRO' ) && true === SIMPLE_BOOKING_FORCE_PRO ) {
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
