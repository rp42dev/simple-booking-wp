<?php
/**
 * Calendar Provider Interface
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface Simple_Booking_Calendar_Provider_Interface {
    /**
     * Provider slug.
     *
     * @return string
     */
    public function get_slug();

    /**
     * Provider label for admin UI.
     *
     * @return string
     */
    public function get_label();

    /**
     * Check whether provider is configured and connected.
     *
     * @return bool
     */
    public function is_connected();

    /**
     * Create external calendar event.
     *
     * @param array $booking_data
     * @return string|array|WP_Error
     */
    public function create_event( $booking_data );

    /**
     * Update external calendar event.
     *
     * @param string $event_id
     * @param array  $booking_data
     * @return true|WP_Error
     */
    public function update_event( $event_id, $booking_data );

    /**
     * Delete external calendar event.
     *
     * @param string $event_id
     * @param array  $context
     * @return true|WP_Error
     */
    public function delete_event( $event_id, $context = array() );

    /**
     * Fetch busy windows for a datetime range.
     *
     * @param string $start_datetime
     * @param string $end_datetime
     * @param array  $context
     * @return array|WP_Error
     */
    public function fetch_busy_windows( $start_datetime, $end_datetime, $context = array() );

    /**
     * Resolve staff availability for a requested slot.
     *
     * @param int    $service_id
     * @param string $start_datetime
     * @param int    $duration_minutes
     * @param array  $context
     * @return array|false|WP_Error
     */
    public function find_available_staff( $service_id, $start_datetime, $duration_minutes, $context = array() );
}
