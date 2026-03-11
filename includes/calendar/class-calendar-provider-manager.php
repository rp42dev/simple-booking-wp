<?php
/**
 * Calendar Provider Manager
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Simple_Booking_Calendar_Provider_Manager {
    /**
     * Registry of available provider classes.
     *
     * @var array
     */
    private $provider_classes = array();

    /**
     * Constructor.
     */
    public function __construct() {
        $this->provider_classes = array(
            'google'  => 'Simple_Booking_Google_Provider',
            'outlook' => 'Simple_Booking_Outlook_Provider',
            'ics'     => 'Simple_Booking_Ics_Provider',
        );
    }

    /**
     * Return active provider slug from settings.
     *
     * @return string
     */
    public function get_active_provider_slug() {
        $slug = simple_booking()->get_setting( 'calendar_provider', 'ics' );
        $slug = sanitize_key( $slug );

        if ( ! isset( $this->provider_classes[ $slug ] ) ) {
            return 'ics';
        }

        $this->maybe_load_provider_class( $slug );

        // If selected provider class is not loaded in this request (e.g. Pro
        // provider selected while current runtime is Free), gracefully fall
        // back to ICS instead of returning hard errors to frontend slot checks.
        $selected_class = $this->provider_classes[ $slug ];
        if ( ! class_exists( $selected_class ) ) {
            return 'ics';
        }

        return $slug;
    }

    /**
     * Get provider instance by slug.
     *
     * @param string|null $slug
     * @return Simple_Booking_Calendar_Provider_Interface|WP_Error
     */
    public function get_provider( $slug = null ) {
        if ( null === $slug ) {
            $slug = $this->get_active_provider_slug();
        }

        $slug = sanitize_key( $slug );

        $this->maybe_load_provider_class( $slug );

        if ( ! isset( $this->provider_classes[ $slug ] ) ) {
            return new WP_Error( 'calendar_provider_invalid', __( 'Invalid calendar provider.', 'simple-booking' ) );
        }

        $class_name = $this->provider_classes[ $slug ];
        if ( ! class_exists( $class_name ) ) {
            // Final safety: retry with ICS before returning an error.
            if ( 'ics' !== $slug && isset( $this->provider_classes['ics'] ) && class_exists( $this->provider_classes['ics'] ) ) {
                $class_name = $this->provider_classes['ics'];
            } else {
                return new WP_Error( 'calendar_provider_missing', __( 'Calendar provider is not available.', 'simple-booking' ) );
            }
        }

        $provider = new $class_name();

        if ( ! $provider instanceof Simple_Booking_Calendar_Provider_Interface ) {
            return new WP_Error( 'calendar_provider_contract', __( 'Calendar provider has invalid contract.', 'simple-booking' ) );
        }

        return $provider;
    }

    /**
     * Lazy-load provider classes and dependencies when they were not preloaded.
     *
     * @param string $slug
     * @return void
     */
    private function maybe_load_provider_class( $slug ) {
        if ( ! isset( $this->provider_classes[ $slug ] ) ) {
            return;
        }

        $class_name = $this->provider_classes[ $slug ];
        if ( class_exists( $class_name ) ) {
            return;
        }

        if ( ! defined( 'SIMPLE_BOOKING_INCLUDES' ) ) {
            return;
        }

        if ( 'google' === $slug ) {
            if ( file_exists( SIMPLE_BOOKING_INCLUDES . 'google/class-google-calendar.php' ) ) {
                require_once SIMPLE_BOOKING_INCLUDES . 'google/class-google-calendar.php';
            }
            if ( file_exists( SIMPLE_BOOKING_INCLUDES . 'calendar/providers/class-google-provider.php' ) ) {
                require_once SIMPLE_BOOKING_INCLUDES . 'calendar/providers/class-google-provider.php';
            }
            return;
        }

        if ( 'outlook' === $slug ) {
            if ( file_exists( SIMPLE_BOOKING_INCLUDES . 'outlook/class-outlook-calendar.php' ) ) {
                require_once SIMPLE_BOOKING_INCLUDES . 'outlook/class-outlook-calendar.php';
            }
            if ( file_exists( SIMPLE_BOOKING_INCLUDES . 'calendar/providers/class-outlook-provider.php' ) ) {
                require_once SIMPLE_BOOKING_INCLUDES . 'calendar/providers/class-outlook-provider.php';
            }
            return;
        }

        if ( 'ics' === $slug ) {
            if ( file_exists( SIMPLE_BOOKING_INCLUDES . 'calendar/providers/class-ics-provider.php' ) ) {
                require_once SIMPLE_BOOKING_INCLUDES . 'calendar/providers/class-ics-provider.php';
            }
        }
    }

    /**
     * List available providers that are currently loaded.
     *
     * @return array
     */
    public function get_available_providers() {
        $providers = array();

        foreach ( $this->provider_classes as $slug => $class_name ) {
            if ( ! class_exists( $class_name ) ) {
                continue;
            }

            $provider = new $class_name();
            if ( ! $provider instanceof Simple_Booking_Calendar_Provider_Interface ) {
                continue;
            }

            $providers[ $slug ] = array(
                'slug'        => $provider->get_slug(),
                'label'       => $provider->get_label(),
                'is_connected'=> $provider->is_connected(),
            );
        }

        return $providers;
    }
}
