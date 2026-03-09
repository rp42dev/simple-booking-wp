<?php
/**
 * Google Calendar Provider Adapter
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Simple_Booking_Google_Provider implements Simple_Booking_Calendar_Provider_Interface {
    /**
     * @var Simple_Booking_Google_Calendar|null
     */
    private $google;

    public function __construct() {
        $this->google = class_exists( 'Simple_Booking_Google_Calendar' ) ? new Simple_Booking_Google_Calendar() : null;
    }

    public function get_slug() {
        return 'google';
    }

    public function get_label() {
        return __( 'Google Calendar', 'simple-booking' );
    }

    public function is_connected() {
        if ( ! $this->google ) {
            return false;
        }

        return (bool) $this->google->is_connected();
    }

    public function create_event( $booking_data ) {
        if ( ! $this->google ) {
            return new WP_Error( 'google_provider_unavailable', __( 'Google provider unavailable.', 'simple-booking' ) );
        }

        return $this->google->create_event( $booking_data );
    }

    public function update_event( $event_id, $booking_data ) {
        return new WP_Error( 'google_update_not_implemented', __( 'Google event update adapter is not implemented yet.', 'simple-booking' ) );
    }

    public function delete_event( $event_id, $context = array() ) {
        if ( ! $this->google ) {
            return new WP_Error( 'google_provider_unavailable', __( 'Google provider unavailable.', 'simple-booking' ) );
        }

        $calendar_id = isset( $context['calendar_id'] ) ? $context['calendar_id'] : null;
        $result = $this->google->delete_event( $event_id, $calendar_id );

        return is_wp_error( $result ) ? $result : true;
    }

    public function fetch_busy_windows( $start_datetime, $end_datetime, $context = array() ) {
        if ( ! $this->google ) {
            return new WP_Error( 'google_provider_unavailable', __( 'Google provider unavailable.', 'simple-booking' ) );
        }

        return new WP_Error( 'google_busy_windows_not_implemented', __( 'Busy-window adapter for Google provider is not implemented yet.', 'simple-booking' ) );
    }
}
