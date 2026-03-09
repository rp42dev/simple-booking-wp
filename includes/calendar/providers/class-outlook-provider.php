<?php
/**
 * Outlook Calendar Provider Adapter (Graph API)
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Simple_Booking_Outlook_Provider implements Simple_Booking_Calendar_Provider_Interface {
    public function get_slug() {
        return 'outlook';
    }

    public function get_label() {
        return __( 'Microsoft Outlook Calendar', 'simple-booking' );
    }

    public function is_connected() {
        return false;
    }

    public function create_event( $booking_data ) {
        return new WP_Error( 'outlook_not_implemented', __( 'Outlook provider is not implemented yet.', 'simple-booking' ) );
    }

    public function update_event( $event_id, $booking_data ) {
        return new WP_Error( 'outlook_not_implemented', __( 'Outlook provider is not implemented yet.', 'simple-booking' ) );
    }

    public function delete_event( $event_id, $context = array() ) {
        return new WP_Error( 'outlook_not_implemented', __( 'Outlook provider is not implemented yet.', 'simple-booking' ) );
    }

    public function fetch_busy_windows( $start_datetime, $end_datetime, $context = array() ) {
        return new WP_Error( 'outlook_not_implemented', __( 'Outlook provider is not implemented yet.', 'simple-booking' ) );
    }
}
