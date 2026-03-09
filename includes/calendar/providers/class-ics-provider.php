<?php
/**
 * ICS Feed Provider Adapter
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Simple_Booking_Ics_Provider implements Simple_Booking_Calendar_Provider_Interface {
    public function get_slug() {
        return 'ics';
    }

    public function get_label() {
        return __( 'ICS Feed (Fallback)', 'simple-booking' );
    }

    public function is_connected() {
        return true;
    }

    public function create_event( $booking_data ) {
        return array(
            'provider' => 'ics',
            'status'   => 'queued_for_feed',
        );
    }

    public function update_event( $event_id, $booking_data ) {
        return true;
    }

    public function delete_event( $event_id, $context = array() ) {
        return true;
    }

    public function fetch_busy_windows( $start_datetime, $end_datetime, $context = array() ) {
        return array();
    }
}
