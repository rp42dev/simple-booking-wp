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

    /** Custom license API base URL (adapter mode). */
    const API_URL = 'https://yourdomain.com/api/v1/licenses';

    /** Lemon Squeezy License API base URL. */
    const LEMONSQUEEZY_API_URL = 'https://api.lemonsqueezy.com/v1/licenses';

    public function __construct() {
        add_action( 'admin_notices', array( $this, 'show_admin_notices' ) );
    }

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
     * Activate a license key.
     *
     * @param string $key
     * @return true|WP_Error
     */
    public function activate_license( $key ) {
        $key = sanitize_text_field( $key );
        if ( '' === $key ) {
            return new WP_Error( 'empty_key', __( 'Please enter a license key.', 'simple-booking' ) );
        }

        if ( 'lemonsqueezy' === $this->get_provider() ) {
            return $this->activate_lemonsqueezy( $key );
        }

        return $this->activate_custom( $key );
    }

    /**
     * Deactivate the current license.
     *
     * @return true|WP_Error
     */
    public function deactivate_license() {
        if ( 'lemonsqueezy' === $this->get_provider() ) {
            return $this->deactivate_lemonsqueezy();
        }

        return $this->deactivate_custom();
    }

    /**
     * Return current license status, using a 24-hour transient cache.
     *
     * @return array { valid: bool, status: string, plan: string, expires: string|null }
     */
    public function check_license_status() {
        if ( defined( 'SIMPLE_BOOKING_FORCE_PRO' ) && true === SIMPLE_BOOKING_FORCE_PRO ) {
            return array( 'valid' => true, 'status' => 'active', 'plan' => 'pro', 'expires' => null );
        }

        $override = apply_filters( 'simple_booking_license_status_override', null );
        if ( is_array( $override ) ) {
            return wp_parse_args( $override, array( 'valid' => false, 'status' => 'free', 'plan' => 'free', 'expires' => null ) );
        }

        $cached = get_transient( self::CACHE_KEY );
        if ( false !== $cached && is_array( $cached ) ) {
            return $cached;
        }

        $key = $this->get_license_key();
        if ( '' === $key ) {
            return array( 'valid' => false, 'status' => 'free', 'plan' => 'free', 'expires' => null );
        }

        if ( 'lemonsqueezy' === $this->get_provider() ) {
            $status = $this->validate_lemonsqueezy( $key );
        } else {
            $status = $this->check_custom( $key );
        }

        if ( is_wp_error( $status ) ) {
            $fallback = $this->get_local_status( get_option( self::LICENSE_OPTION, array() ) );
            set_transient( self::CACHE_KEY, $fallback, HOUR_IN_SECONDS );
            return $fallback;
        }

        $local = get_option( self::LICENSE_OPTION, array() );
        $local = array_merge(
            $local,
            array(
                'status'     => $status['status'],
                'plan'       => $status['plan'],
                'expires'    => $status['expires'],
                'last_check' => time(),
            )
        );
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
     * Check if a feature is available on current plan.
     *
     * @param string $feature
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

        return true;
    }

    /**
     * Return days remaining in grace period (0 when not applicable).
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
     * Clear cached license status.
     *
     * @return void
     */
    public function clear_cache() {
        delete_transient( self::CACHE_KEY );
    }

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

    /**
     * Custom adapter: activate.
     *
     * @param string $key
     * @return true|WP_Error
     */
    private function activate_custom( $key ) {
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
            'provider'     => 'custom',
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
     * Custom adapter: deactivate.
     *
     * @return true
     */
    private function deactivate_custom() {
        $key = $this->get_license_key();

        if ( '' !== $key ) {
            wp_remote_post(
                $this->get_api_url() . '/deactivate',
                array(
                    'body'    => array(
                        'license_key' => $key,
                        'site_url'    => home_url(),
                    ),
                    'timeout' => 15,
                )
            );
        }

        delete_option( self::LICENSE_OPTION );
        delete_transient( self::CACHE_KEY );

        return true;
    }

    /**
     * Custom adapter: check.
     *
     * @param string $key
     * @return array|WP_Error
     */
    private function check_custom( $key ) {
        $response = wp_remote_get(
            add_query_arg(
                array(
                    'license_key' => $key,
                    'site_url'    => rawurlencode( home_url() ),
                ),
                $this->get_api_url() . '/check'
            ),
            array( 'timeout' => 10 )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        return array(
            'valid'   => ! empty( $body['valid'] ),
            'status'  => isset( $body['status'] ) ? sanitize_text_field( $body['status'] ) : 'unknown',
            'plan'    => isset( $body['plan'] ) ? sanitize_text_field( $body['plan'] ) : 'free',
            'expires' => isset( $body['expires'] ) ? sanitize_text_field( $body['expires'] ) : null,
        );
    }

    /**
     * Lemon Squeezy: activate.
     *
     * @param string $key
     * @return true|WP_Error
     */
    private function activate_lemonsqueezy( $key ) {
        $response = wp_remote_post(
            self::LEMONSQUEEZY_API_URL . '/activate',
            array(
                'headers' => array(
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ),
                'body'    => array(
                    'license_key'   => $key,
                    'instance_name' => $this->get_instance_name(),
                ),
                'timeout' => 15,
            )
        );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'ls_unreachable', __( 'Could not reach Lemon Squeezy license API.', 'simple-booking' ) );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $body['activated'] ) ) {
            $message = isset( $body['error'] ) ? sanitize_text_field( $body['error'] ) : __( 'License activation failed.', 'simple-booking' );
            return new WP_Error( 'ls_activation_failed', $message );
        }

        $license_key = isset( $body['license_key'] ) && is_array( $body['license_key'] ) ? $body['license_key'] : array();
        $instance    = isset( $body['instance'] ) && is_array( $body['instance'] ) ? $body['instance'] : array();
        $meta        = isset( $body['meta'] ) && is_array( $body['meta'] ) ? $body['meta'] : array();

        $license = array(
            'provider'     => 'lemonsqueezy',
            'key'          => $key,
            'instance_id'  => isset( $instance['id'] ) ? sanitize_text_field( $instance['id'] ) : '',
            'status'       => isset( $license_key['status'] ) ? sanitize_text_field( $license_key['status'] ) : 'active',
            'plan'         => isset( $meta['variant_name'] ) ? sanitize_text_field( $meta['variant_name'] ) : 'pro',
            'expires'      => $this->normalize_expires_value( $license_key ),
            'activated_at' => current_time( 'mysql' ),
            'last_check'   => time(),
        );

        update_option( self::LICENSE_OPTION, $license, false );
        delete_transient( self::CACHE_KEY );

        return true;
    }

    /**
     * Lemon Squeezy: deactivate current instance.
     *
     * @return true|WP_Error
     */
    private function deactivate_lemonsqueezy() {
        $license = get_option( self::LICENSE_OPTION, array() );
        $key     = isset( $license['key'] ) ? (string) $license['key'] : '';
        $inst_id = isset( $license['instance_id'] ) ? (string) $license['instance_id'] : '';

        if ( '' !== $key && '' !== $inst_id ) {
            $response = wp_remote_post(
                self::LEMONSQUEEZY_API_URL . '/deactivate',
                array(
                    'headers' => array(
                        'Accept'       => 'application/json',
                        'Content-Type' => 'application/x-www-form-urlencoded',
                    ),
                    'body'    => array(
                        'license_key' => $key,
                        'instance_id' => $inst_id,
                    ),
                    'timeout' => 15,
                )
            );

            if ( is_wp_error( $response ) ) {
                return new WP_Error( 'ls_deactivate_failed', __( 'Could not reach Lemon Squeezy to deactivate this instance.', 'simple-booking' ) );
            }
        }

        delete_option( self::LICENSE_OPTION );
        delete_transient( self::CACHE_KEY );

        return true;
    }

    /**
     * Lemon Squeezy: validate key or instance.
     *
     * @param string $key
     * @return array|WP_Error
     */
    private function validate_lemonsqueezy( $key ) {
        $license = get_option( self::LICENSE_OPTION, array() );

        $body = array( 'license_key' => $key );
        if ( ! empty( $license['instance_id'] ) ) {
            $body['instance_id'] = sanitize_text_field( $license['instance_id'] );
        }

        $response = wp_remote_post(
            self::LEMONSQUEEZY_API_URL . '/validate',
            array(
                'headers' => array(
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ),
                'body'    => $body,
                'timeout' => 10,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $res_body    = json_decode( wp_remote_retrieve_body( $response ), true );
        $license_key = isset( $res_body['license_key'] ) && is_array( $res_body['license_key'] ) ? $res_body['license_key'] : array();
        $meta        = isset( $res_body['meta'] ) && is_array( $res_body['meta'] ) ? $res_body['meta'] : array();

        $status = isset( $license_key['status'] ) ? sanitize_text_field( $license_key['status'] ) : 'inactive';
        $valid  = ! empty( $res_body['valid'] ) && 'active' === $status;

        if ( 'disabled' === $status || 'inactive' === $status ) {
            $valid = false;
        }

        return array(
            'valid'   => $valid,
            'status'  => $status,
            'plan'    => isset( $meta['variant_name'] ) ? sanitize_text_field( $meta['variant_name'] ) : 'pro',
            'expires' => $this->normalize_expires_value( $license_key ),
        );
    }

    /**
     * Local status fallback used when remote validation is unavailable.
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
     * Return active provider: custom | lemonsqueezy.
     *
     * @return string
     */
    private function get_provider() {
        if ( defined( 'SIMPLE_BOOKING_LICENSE_PROVIDER' ) ) {
            $provider = strtolower( trim( (string) SIMPLE_BOOKING_LICENSE_PROVIDER ) );
        } else {
            $provider = 'custom';
        }

        $provider = apply_filters( 'simple_booking_license_provider', $provider );
        return in_array( $provider, array( 'custom', 'lemonsqueezy' ), true ) ? $provider : 'custom';
    }

    /**
     * Return custom adapter base URL.
     *
     * @return string
     */
    private function get_api_url() {
        if ( defined( 'SIMPLE_BOOKING_LICENSE_API_URL' ) ) {
            return rtrim( SIMPLE_BOOKING_LICENSE_API_URL, '/' );
        }

        return apply_filters( 'simple_booking_license_api_url', rtrim( self::API_URL, '/' ) );
    }

    /**
     * Return readable Lemon Squeezy instance name for this site.
     *
     * @return string
     */
    private function get_instance_name() {
        if ( defined( 'SIMPLE_BOOKING_LICENSE_INSTANCE_NAME' ) && '' !== SIMPLE_BOOKING_LICENSE_INSTANCE_NAME ) {
            return sanitize_text_field( SIMPLE_BOOKING_LICENSE_INSTANCE_NAME );
        }

        $host = wp_parse_url( home_url(), PHP_URL_HOST );
        if ( ! is_string( $host ) || '' === $host ) {
            $host = 'wordpress-site';
        }

        return sanitize_text_field( $host );
    }

    /**
     * Normalize Lemon API expires field to Y-m-d or null.
     *
     * @param array $license_key
     * @return string|null
     */
    private function normalize_expires_value( $license_key ) {
        if ( empty( $license_key['expires_at'] ) || ! is_string( $license_key['expires_at'] ) ) {
            return null;
        }

        $ts = strtotime( $license_key['expires_at'] );
        if ( false === $ts ) {
            return null;
        }

        return gmdate( 'Y-m-d', $ts );
    }
}
