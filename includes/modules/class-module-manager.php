<?php
/**
 * Module Manager
 *
 * Central feature/module availability checks for admin UI and runtime gates.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Simple_Booking_Module_Manager {

    /**
     * Normalize common truthy constant values from wp-config.
     *
     * @param mixed $value Raw constant value.
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
     * Module registry.
     *
     * @return array
     */
    public function get_modules() {
        $modules = array(
            'payments_stripe' => array(
                'slug'            => 'payments_stripe',
                'label'           => __( 'Stripe Payments', 'simple-booking' ),
                'requires_pro'    => true,
                'required_class'  => 'Simple_Booking_Stripe',
                'required_file'   => SIMPLE_BOOKING_INCLUDES . 'stripe/class-stripe-handler.php',
            ),
            'calendar_ics' => array(
                'slug'            => 'calendar_ics',
                'label'           => __( 'ICS Feed', 'simple-booking' ),
                'requires_pro'    => false,
                'required_class'  => 'Simple_Booking_Ics_Provider',
                'required_file'   => SIMPLE_BOOKING_INCLUDES . 'calendar/providers/class-ics-provider.php',
                'provider_slug'   => 'ics',
            ),
            'calendar_google' => array(
                'slug'            => 'calendar_google',
                'label'           => __( 'Google Calendar', 'simple-booking' ),
                'requires_pro'    => true,
                'required_class'  => 'Simple_Booking_Google_Provider',
                'required_file'   => SIMPLE_BOOKING_INCLUDES . 'calendar/providers/class-google-provider.php',
                'provider_slug'   => 'google',
            ),
            'calendar_outlook' => array(
                'slug'            => 'calendar_outlook',
                'label'           => __( 'Outlook Calendar', 'simple-booking' ),
                'requires_pro'    => true,
                'required_class'  => 'Simple_Booking_Outlook_Provider',
                'required_file'   => SIMPLE_BOOKING_INCLUDES . 'calendar/providers/class-outlook-provider.php',
                'provider_slug'   => 'outlook',
            ),
            'staff_management' => array(
                'slug'            => 'staff_management',
                'label'           => __( 'Staff Management', 'simple-booking' ),
                'requires_pro'    => true,
                'required_class'  => 'Simple_Booking_Staff',
                'required_file'   => SIMPLE_BOOKING_INCLUDES . 'post-types/class-staff.php',
            ),
            'booking_webhooks' => array(
                'slug'            => 'booking_webhooks',
                'label'           => __( 'Booking Webhooks', 'simple-booking' ),
                'requires_pro'    => true,
                'required_class'  => 'Simple_Booking_Booking_Webhook',
                'required_file'   => SIMPLE_BOOKING_INCLUDES . 'webhook/class-booking-webhook.php',
            ),
        );

        return apply_filters( 'simple_booking_modules_registry', $modules );
    }

    /**
     * Return one module definition.
     *
     * @param string $slug Module slug.
     * @return array|null
     */
    public function get_module( $slug ) {
        $modules = $this->get_modules();
        return isset( $modules[ $slug ] ) ? $modules[ $slug ] : null;
    }

    /**
     * Is module code present and loadable.
     *
     * @param string $slug Module slug.
     * @return bool
     */
    public function is_module_installed( $slug ) {
        $module = $this->get_module( $slug );
        if ( ! is_array( $module ) ) {
            return false;
        }

        $required_class = isset( $module['required_class'] ) ? $module['required_class'] : '';
        if ( $required_class && class_exists( $required_class ) ) {
            return true;
        }

        $required_file = isset( $module['required_file'] ) ? $module['required_file'] : '';
        if ( $required_file && file_exists( $required_file ) ) {
            return true;
        }

        // If no explicit class/file requirement is defined, treat as installed.
        return empty( $required_class ) && empty( $required_file );
    }

    /**
     * Check if current site has Pro capability.
     *
     * @return bool
     */
    public function is_pro_active() {
        $constants = array(
            'SIMPLE_BOOKING_FORCE_PRO',
            'SIMPLE_BOOKING_PRO_MODE',
            'SIMPLE_BOOKING_PRO',
            'SIMPLE_BOOKING_IS_PRO',
        );

        foreach ( $constants as $constant_name ) {
            if ( defined( $constant_name ) && $this->to_bool( constant( $constant_name ) ) ) {
                return true;
            }
        }

        $override = apply_filters( 'simple_booking_is_pro_active', null );
        if ( is_bool( $override ) ) {
            return $override;
        }

        if ( class_exists( 'Simple_Booking_License_Manager' ) ) {
            $license = new Simple_Booking_License_Manager();
            if ( $license->is_pro_active() ) {
                return true;
            }
        }

        // Fallback to locally stored license payload if present.
        $stored = get_option( 'simple_booking_license', array() );
        if ( is_array( $stored ) ) {
            $status = isset( $stored['status'] ) ? sanitize_key( $stored['status'] ) : '';
            $plan   = isset( $stored['plan'] ) ? sanitize_key( $stored['plan'] ) : '';
            $valid  = ! empty( $stored['valid'] );

            if ( $valid || 'active' === $status || ( $plan && 'free' !== $plan ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is module available for actual use.
     *
     * @param string $slug Module slug.
     * @return bool
     */
    public function is_module_available( $slug ) {
        $module = $this->get_module( $slug );
        if ( ! is_array( $module ) ) {
            return false;
        }

        if ( ! $this->is_module_installed( $slug ) ) {
            return false;
        }

        $requires_pro = ! empty( $module['requires_pro'] );
        if ( $requires_pro && ! $this->is_pro_active() ) {
            return false;
        }

        return true;
    }

    /**
     * Explain why module is unavailable.
     *
     * @param string $slug Module slug.
     * @return string
     */
    public function get_unavailable_reason( $slug ) {
        $module = $this->get_module( $slug );
        if ( ! is_array( $module ) ) {
            return __( 'Unknown module.', 'simple-booking' );
        }

        if ( ! $this->is_module_installed( $slug ) ) {
            return __( 'Module files are not installed.', 'simple-booking' );
        }

        if ( ! empty( $module['requires_pro'] ) && ! $this->is_pro_active() ) {
            return __( 'Requires an active Pro license.', 'simple-booking' );
        }

        return '';
    }

    /**
     * Build calendar provider option states for admin select.
     *
     * @return array
     */
    public function get_calendar_provider_options() {
        $provider_to_module = array(
            'ics'     => 'calendar_ics',
            'google'  => 'calendar_google',
            'outlook' => 'calendar_outlook',
        );

        $options = array();
        foreach ( $provider_to_module as $provider_slug => $module_slug ) {
            $module = $this->get_module( $module_slug );
            if ( ! is_array( $module ) ) {
                continue;
            }

            $options[ $provider_slug ] = array(
                'provider_slug' => $provider_slug,
                'module_slug'   => $module_slug,
                'label'         => isset( $module['label'] ) ? $module['label'] : ucfirst( $provider_slug ),
                'available'     => $this->is_module_available( $module_slug ),
                'requires_pro'  => ! empty( $module['requires_pro'] ),
                'installed'     => $this->is_module_installed( $module_slug ),
                'reason'        => $this->get_unavailable_reason( $module_slug ),
            );
        }

        return $options;
    }

    /**
     * Return status metadata for every registered module.
     *
     * @return array
     */
    public function get_modules_status() {
        $modules = $this->get_modules();
        $status_rows = array();

        foreach ( $modules as $slug => $module ) {
            $label = isset( $module['label'] ) ? $module['label'] : $slug;
            $installed = $this->is_module_installed( $slug );
            $available = $this->is_module_available( $slug );
            $requires_pro = ! empty( $module['requires_pro'] );
            $reason = $available ? '' : $this->get_unavailable_reason( $slug );

            $status_rows[ $slug ] = array(
                'slug'         => $slug,
                'label'        => $label,
                'installed'    => $installed,
                'requires_pro' => $requires_pro,
                'available'    => $available,
                'reason'       => $reason,
            );
        }

        return $status_rows;
    }
}
