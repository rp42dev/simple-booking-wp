<?php
/**
 * Outlook Calendar Provider Adapter (Graph API)
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Simple_Booking_Outlook_Provider implements Simple_Booking_Calendar_Provider_Interface {
    
    /**
     * Microsoft Graph API base URL
     */
    const GRAPH_API_BASE = 'https://graph.microsoft.com/v1.0';
    
    /**
     * OAuth URLs
     */
    const AUTH_URL = 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize';
    const TOKEN_URL = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
    
    /**
     * Option key for tokens
     */
    const TOKEN_OPTION = 'simple_booking_outlook_tokens';

    /**
     * Optional setting key for target Outlook calendar ID.
     */
    const CALENDAR_ID_OPTION = 'outlook_calendar_id';

    /**
     * Check debug mode setting.
     *
     * @return bool
     */
    private function is_debug_enabled() {
        return (bool) simple_booking()->get_setting( 'debug_mode', false );
    }

    /**
     * Provider-specific debug logger.
     *
     * @param string $message
     * @return void
     */
    private function debug_log( $message ) {
        if ( ! $this->is_debug_enabled() ) {
            return;
        }

        error_log( '[SIMPLE_BOOKING_OUTLOOK] ' . $message );
    }

    /**
     * Get configured Outlook calendar ID from settings.
     *
     * @return string
     */
    private function get_configured_calendar_id() {
        $calendar_id = simple_booking()->get_setting( self::CALENDAR_ID_OPTION, '' );
        return is_string( $calendar_id ) ? trim( $calendar_id ) : '';
    }

    /**
     * Build calendarView URL for default or specific calendar.
     *
     * @param string $start_iso
     * @param string $end_iso
     * @param string $calendar_id (not used in current implementation, reserved for future multi-calendar support)
     * @return string
     */
    private function build_calendar_view_url( $start_iso, $end_iso, $calendar_id = '' ) {
        // Always use /me/calendarView which is the most reliable endpoint for querying events
        // It queries the primary calendar by default, which is where manual events typically live
        $base = self::GRAPH_API_BASE . '/me/calendarView';

        return add_query_arg(
            array(
                'startDateTime' => $start_iso,
                'endDateTime'   => $end_iso,
                '$top'          => 100,
            ),
            $base
        );
    }

    /**
     * Resolve calendars used for availability checks.
     *
     * @param string $access_token
     * @return array
     */
    private function get_availability_calendar_ids( $access_token ) {
        $configured_calendar_id = $this->get_configured_calendar_id();
        if ( '' !== $configured_calendar_id ) {
            $this->debug_log( 'Using configured calendar ID: ' . $configured_calendar_id );
            return array( $configured_calendar_id );
        }

        $response = wp_remote_get(
            self::GRAPH_API_BASE . '/me/calendars?$select=id,name,isDefaultCalendar&$top=50',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                ),
                'timeout' => 30,
            )
        );

        if ( is_wp_error( $response ) ) {
            $this->debug_log( 'get_availability_calendar_ids error: ' . $response->get_error_code() . ' - ' . $response->get_error_message() );
            return array( '' );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        $body = json_decode( $response_body, true );

        $this->debug_log( 'get_availability_calendar_ids status=' . $status_code . ' response_body_length=' . strlen( $response_body ) );

        if ( 200 !== $status_code ) {
            $this->debug_log( 'get_availability_calendar_ids non-200 status, error=' . ( isset( $body['error']['message'] ) ? $body['error']['message'] : 'unknown' ) );
            return array( '' );
        }

        if ( empty( $body['value'] ) || ! is_array( $body['value'] ) ) {
            $this->debug_log( 'get_availability_calendar_ids empty or invalid calendar list' );
            return array( '' );
        }

        $ids = array();
        foreach ( $body['value'] as $calendar ) {
            if ( empty( $calendar['id'] ) ) {
                continue;
            }

            $ids[] = (string) $calendar['id'];
            $this->debug_log( 'Found calendar: id=' . (string) $calendar['id'] . ' name=' . ( $calendar['name'] ?? 'N/A' ) . ' isDefault=' . ( $calendar['isDefaultCalendar'] ? 'true' : 'false' ) );
        }

        if ( empty( $ids ) ) {
            $this->debug_log( 'get_availability_calendar_ids found no valid calendar IDs' );
            return array( '' );
        }

        $this->debug_log( 'get_availability_calendar_ids found ' . count( $ids ) . ' calendars' );
        return array_values( array_unique( $ids ) );
    }
    
    /**
     * Get provider slug
     */
    public function get_slug() {
        return 'outlook';
    }

    /**
     * Get provider label
     */
    public function get_label() {
        return __( 'Microsoft Outlook Calendar', 'simple-booking' );
    }

    /**
     * Check if provider is connected
     */
    public function is_connected() {
        $tokens = get_option( self::TOKEN_OPTION, array() );
        return ! empty( $tokens['access_token'] );
    }

    /**
     * Get OAuth URL for authorization
     */
    public function get_oauth_url( $save_state = true ) {
        $client_id = simple_booking()->get_setting( 'outlook_client_id' );
        if ( empty( $client_id ) ) {
            return null;
        }

        $redirect_uri = rest_url( 'simple-booking/v1/outlook/oauth' );

        if ( $save_state ) {
            $state = wp_generate_uuid4();
            update_option( 'simple_booking_outlook_oauth_state', $state );
        } else {
            $state = get_option( 'simple_booking_outlook_oauth_state' );
            if ( ! $state ) {
                return null;
            }
        }

        $params = array(
            'client_id'     => $client_id,
            'response_type' => 'code',
            'redirect_uri'  => $redirect_uri,
            'response_mode' => 'query',
            'scope'         => 'offline_access Calendars.ReadWrite',
            'state'         => $state,
        );

        return add_query_arg( $params, self::AUTH_URL );
    }

    /**
     * Exchange authorization code for access token
     */
    public function exchange_code( $code ) {
        $client_id = simple_booking()->get_setting( 'outlook_client_id' );
        $client_secret = simple_booking()->get_setting( 'outlook_client_secret' );
        
        if ( empty( $client_id ) || empty( $client_secret ) ) {
            return new WP_Error( 'outlook_config_missing', __( 'Outlook API credentials not configured', 'simple-booking' ) );
        }

        $redirect_uri = rest_url( 'simple-booking/v1/outlook/oauth' );

        $response = wp_remote_post(
            self::TOKEN_URL,
            array(
                'body' => array(
                    'client_id'     => $client_id,
                    'client_secret' => $client_secret,
                    'code'          => $code,
                    'redirect_uri'  => $redirect_uri,
                    'grant_type'    => 'authorization_code',
                ),
                'timeout' => 30,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! isset( $body['access_token'] ) ) {
            return new WP_Error( 'outlook_token_error', $body['error_description'] ?? __( 'Token exchange failed', 'simple-booking' ) );
        }

        $tokens = array(
            'access_token'  => $body['access_token'],
            'refresh_token' => $body['refresh_token'] ?? '',
            'expires_at'    => time() + ( $body['expires_in'] ?? 3600 ),
        );

        update_option( self::TOKEN_OPTION, $tokens );

        return true;
    }

    /**
     * Refresh access token
     */
    private function refresh_token() {
        $tokens = get_option( self::TOKEN_OPTION, array() );
        
        if ( empty( $tokens['refresh_token'] ) ) {
            return new WP_Error( 'outlook_no_refresh_token', __( 'No refresh token available', 'simple-booking' ) );
        }

        $client_id = simple_booking()->get_setting( 'outlook_client_id' );
        $client_secret = simple_booking()->get_setting( 'outlook_client_secret' );

        $response = wp_remote_post(
            self::TOKEN_URL,
            array(
                'body' => array(
                    'client_id'     => $client_id,
                    'client_secret' => $client_secret,
                    'refresh_token' => $tokens['refresh_token'],
                    'grant_type'    => 'refresh_token',
                ),
                'timeout' => 30,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! isset( $body['access_token'] ) ) {
            return new WP_Error( 'outlook_refresh_failed', $body['error_description'] ?? __( 'Token refresh failed', 'simple-booking' ) );
        }

        $tokens = array(
            'access_token'  => $body['access_token'],
            'refresh_token' => $body['refresh_token'] ?? $tokens['refresh_token'],
            'expires_at'    => time() + ( $body['expires_in'] ?? 3600 ),
        );

        update_option( self::TOKEN_OPTION, $tokens );

        return $tokens['access_token'];
    }

    /**
     * Get valid access token (refresh if needed)
     */
    private function get_access_token() {
        $tokens = get_option( self::TOKEN_OPTION, array() );
        
        if ( empty( $tokens['access_token'] ) ) {
            return new WP_Error( 'outlook_not_connected', __( 'Outlook Calendar not connected', 'simple-booking' ) );
        }

        // Check if token expired
        if ( isset( $tokens['expires_at'] ) && time() >= $tokens['expires_at'] ) {
            $refreshed = $this->refresh_token();
            if ( is_wp_error( $refreshed ) ) {
                return $refreshed;
            }
            return $refreshed;
        }

        return $tokens['access_token'];
    }

    /**
     * Resolve a normalized slot range from requested start + duration.
     *
     * @param string $start_datetime
     * @param int    $duration_minutes
     * @return array|WP_Error
     */
    private function resolve_slot_range( $start_datetime, $duration_minutes ) {
        $start_ts = strtotime( (string) $start_datetime );
        $duration_minutes = absint( $duration_minutes );
        if ( $duration_minutes <= 0 ) {
            $duration_minutes = 60;
        }

        if ( false === $start_ts ) {
            return new WP_Error( 'outlook_invalid_start', __( 'Invalid start datetime for availability check.', 'simple-booking' ) );
        }

        $end_ts = $start_ts + ( $duration_minutes * 60 );

        return array(
            'start_ts' => $start_ts,
            'end_ts'   => $end_ts,
            'start'    => gmdate( 'c', $start_ts ),
            'end'      => gmdate( 'c', $end_ts ),
        );
    }

    /**
     * Check if requested slot overlaps any busy windows.
     *
     * @param string $start_datetime
     * @param int    $duration_minutes
     * @return bool|WP_Error True when slot is available.
     */
    private function is_slot_available( $start_datetime, $duration_minutes ) {
        $range = $this->resolve_slot_range( $start_datetime, $duration_minutes );
        if ( is_wp_error( $range ) ) {
            return $range;
        }

        $busy_windows = $this->fetch_busy_windows( $range['start'], $range['end'] );
        if ( is_wp_error( $busy_windows ) ) {
            return $busy_windows;
        }

        return empty( $busy_windows );
    }

    /**
     * Check overlap against existing local booking posts for this service.
     *
     * @param int    $service_id
     * @param string $start_datetime
     * @param int    $duration_minutes
     * @return bool True when overlap exists.
     */
    private function has_local_booking_overlap( $service_id, $start_datetime, $duration_minutes ) {
        $service_id = absint( $service_id );
        if ( ! $service_id ) {
            return false;
        }

        $range = $this->resolve_slot_range( $start_datetime, $duration_minutes );
        if ( is_wp_error( $range ) ) {
            return false;
        }

        $booking_ids = get_posts(
            array(
                'post_type'      => 'booking',
                'post_status'    => array( 'publish', 'private' ),
                'fields'         => 'ids',
                'posts_per_page' => -1,
                'meta_query'     => array(
                    array(
                        'key'     => '_service_id',
                        'value'   => $service_id,
                        'compare' => '=',
                    ),
                ),
            )
        );

        foreach ( $booking_ids as $booking_id ) {
            $booking_status = (string) get_post_meta( $booking_id, '_booking_status', true );
            if ( in_array( $booking_status, array( 'cancelled', 'rescheduled' ), true ) ) {
                continue;
            }

            // Only treat confirmed (or blank legacy) statuses as blocking.
            if ( '' !== $booking_status && ! in_array( $booking_status, array( 'confirmed', 'reschedule_requested' ), true ) ) {
                continue;
            }

            $existing_start_raw = (string) get_post_meta( $booking_id, '_start_datetime', true );
            $existing_end_raw = (string) get_post_meta( $booking_id, '_end_datetime', true );

            $existing_start_ts = strtotime( $existing_start_raw );
            $existing_end_ts = strtotime( $existing_end_raw );

            if ( false === $existing_start_ts ) {
                continue;
            }

            if ( false === $existing_end_ts ) {
                $existing_end_ts = $existing_start_ts + ( max( 1, absint( $duration_minutes ) ) * 60 );
            }

            if ( $range['start_ts'] < $existing_end_ts && $range['end_ts'] > $existing_start_ts ) {
                $this->debug_log(
                    'local overlap detected for service_id=' . $service_id .
                    ' booking_id=' . $booking_id .
                    ' booking_status=' . ( '' === $booking_status ? 'legacy-empty' : $booking_status ) .
                    ' range=' . gmdate( 'c', $range['start_ts'] ) . ' -> ' . gmdate( 'c', $range['end_ts'] )
                );
                return true;
            }
        }

        return false;
    }

    /**
     * Fallback busy window lookup via getSchedule.
     *
     * @param string $access_token
     * @param string $start_iso
     * @param string $end_iso
     * @return array|WP_Error
     */
    private function fetch_busy_windows_via_get_schedule( $access_token, $start_iso, $end_iso ) {
        $body = array(
            'schedules' => array( 'me' ),
            'startTime' => array(
                'dateTime' => gmdate( 'Y-m-d\TH:i:s', strtotime( $start_iso ) ),
                'timeZone' => 'UTC',
            ),
            'endTime' => array(
                'dateTime' => gmdate( 'Y-m-d\TH:i:s', strtotime( $end_iso ) ),
                'timeZone' => 'UTC',
            ),
            'availabilityViewInterval' => 30,
        );

        $response = wp_remote_post(
            self::GRAPH_API_BASE . '/me/calendar/getSchedule',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type'  => 'application/json',
                    'Prefer'        => 'outlook.timezone="UTC"',
                ),
                'body'    => wp_json_encode( $body ),
                'timeout' => 30,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $response_body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 !== $status_code ) {
            return new WP_Error( 'outlook_schedule_fallback_failed', $response_body['error']['message'] ?? __( 'Schedule fetch failed', 'simple-booking' ) );
        }

        $busy_windows = array();
        if ( isset( $response_body['value'][0]['scheduleItems'] ) && is_array( $response_body['value'][0]['scheduleItems'] ) ) {
            foreach ( $response_body['value'][0]['scheduleItems'] as $item ) {
                $status = isset( $item['status'] ) ? strtolower( (string) $item['status'] ) : '';
                if ( 'free' === $status ) {
                    continue;
                }

                if ( ! empty( $item['start']['dateTime'] ) && ! empty( $item['end']['dateTime'] ) ) {
                    $busy_windows[] = array(
                        'start' => $item['start']['dateTime'],
                        'end'   => $item['end']['dateTime'],
                    );
                }
            }
        }

        return $busy_windows;
    }

    /**
     * Resolve booking start/end datetimes for provider payloads.
     *
     * Supports current payload keys (`start_datetime`, `end_datetime`) and
     * legacy keys (`booking_datetime`, `duration_minutes`).
     *
     * @param array $booking_data Booking payload.
     * @return array|WP_Error
     */
    private function resolve_booking_range( $booking_data ) {
        $start_raw = '';
        $end_raw = '';

        if ( ! empty( $booking_data['start_datetime'] ) ) {
            $start_raw = (string) $booking_data['start_datetime'];
        } elseif ( ! empty( $booking_data['booking_datetime'] ) ) {
            $start_raw = (string) $booking_data['booking_datetime'];
        }

        if ( ! empty( $booking_data['end_datetime'] ) ) {
            $end_raw = (string) $booking_data['end_datetime'];
        } elseif ( ! empty( $booking_data['booking_datetime'] ) ) {
            $duration_minutes = ! empty( $booking_data['duration_minutes'] ) ? absint( $booking_data['duration_minutes'] ) : 60;
            $start_ts_legacy = strtotime( (string) $booking_data['booking_datetime'] );
            if ( false !== $start_ts_legacy ) {
                $end_raw = gmdate( 'c', $start_ts_legacy + ( $duration_minutes * 60 ) );
            }
        }

        $start_ts = strtotime( $start_raw );
        $end_ts = strtotime( $end_raw );

        if ( false === $start_ts || false === $end_ts ) {
            return new WP_Error( 'outlook_invalid_datetime', __( 'Invalid booking date/time provided for Outlook event.', 'simple-booking' ) );
        }

        if ( $end_ts <= $start_ts ) {
            return new WP_Error( 'outlook_invalid_range', __( 'Booking end time must be after start time.', 'simple-booking' ) );
        }

        return array(
            'start' => gmdate( 'Y-m-d\TH:i:s', $start_ts ),
            'end'   => gmdate( 'Y-m-d\TH:i:s', $end_ts ),
        );
    }

    /**
     * Create calendar event
     */
    public function create_event( $booking_data ) {
        $access_token = $this->get_access_token();
        if ( is_wp_error( $access_token ) ) {
            return $access_token;
        }

        $range = $this->resolve_booking_range( $booking_data );
        if ( is_wp_error( $range ) ) {
            return $range;
        }

        $event = array(
            'subject' => sprintf(
                __( 'Booking: %s with %s', 'simple-booking' ),
                $booking_data['service_name'],
                $booking_data['customer_name']
            ),
            'body' => array(
                'contentType' => 'HTML',
                'content'     => $this->build_event_description( $booking_data ),
            ),
            'start' => array(
                'dateTime' => $range['start'],
                'timeZone' => 'UTC',
            ),
            'end' => array(
                'dateTime' => $range['end'],
                'timeZone' => 'UTC',
            ),
        );

        // Add online meeting if available
        if ( ! empty( $booking_data['meeting_link'] ) ) {
            $event['location'] = array(
                'displayName' => __( 'Online Meeting', 'simple-booking' ),
            );
            $event['onlineMeetingUrl'] = $booking_data['meeting_link'];
        }

        $configured_calendar_id = $this->get_configured_calendar_id();
        $create_endpoint = self::GRAPH_API_BASE . '/me/events';
        if ( '' !== $configured_calendar_id ) {
            $create_endpoint = self::GRAPH_API_BASE . '/me/calendars/' . rawurlencode( $configured_calendar_id ) . '/events';
        }

        $response = wp_remote_post(
            $create_endpoint,
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => wp_json_encode( $event ),
                'timeout' => 30,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status_code !== 201 ) {
            return new WP_Error( 'outlook_create_failed', $body['error']['message'] ?? __( 'Event creation failed', 'simple-booking' ) );
        }

        return $body['id'] ?? '';
    }

    /**
     * Update calendar event
     */
    public function update_event( $event_id, $booking_data ) {
        $access_token = $this->get_access_token();
        if ( is_wp_error( $access_token ) ) {
            return $access_token;
        }

        $range = $this->resolve_booking_range( $booking_data );
        if ( is_wp_error( $range ) ) {
            return $range;
        }

        $event = array(
            'subject' => sprintf(
                __( 'Booking: %s with %s', 'simple-booking' ),
                $booking_data['service_name'],
                $booking_data['customer_name']
            ),
            'body' => array(
                'contentType' => 'HTML',
                'content'     => $this->build_event_description( $booking_data ),
            ),
            'start' => array(
                'dateTime' => $range['start'],
                'timeZone' => 'UTC',
            ),
            'end' => array(
                'dateTime' => $range['end'],
                'timeZone' => 'UTC',
            ),
        );

        $configured_calendar_id = $this->get_configured_calendar_id();
        $update_endpoint = self::GRAPH_API_BASE . '/me/events/' . rawurlencode( $event_id );
        if ( '' !== $configured_calendar_id ) {
            $update_endpoint = self::GRAPH_API_BASE . '/me/calendars/' . rawurlencode( $configured_calendar_id ) . '/events/' . rawurlencode( $event_id );
        }

        $response = wp_remote_request(
            $update_endpoint,
            array(
                'method'  => 'PATCH',
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => wp_json_encode( $event ),
                'timeout' => 30,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );

        if ( $status_code !== 200 ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            return new WP_Error( 'outlook_update_failed', $body['error']['message'] ?? __( 'Event update failed', 'simple-booking' ) );
        }

        return true;
    }

    /**
     * Delete calendar event
     */
    public function delete_event( $event_id, $context = array() ) {
        $access_token = $this->get_access_token();
        if ( is_wp_error( $access_token ) ) {
            return $access_token;
        }

        $configured_calendar_id = $this->get_configured_calendar_id();
        $delete_endpoint = self::GRAPH_API_BASE . '/me/events/' . rawurlencode( $event_id );
        if ( '' !== $configured_calendar_id ) {
            $delete_endpoint = self::GRAPH_API_BASE . '/me/calendars/' . rawurlencode( $configured_calendar_id ) . '/events/' . rawurlencode( $event_id );
        }

        $response = wp_remote_request(
            $delete_endpoint,
            array(
                'method'  => 'DELETE',
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                ),
                'timeout' => 30,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );

        if ( $status_code !== 204 ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            return new WP_Error( 'outlook_delete_failed', $body['error']['message'] ?? __( 'Event deletion failed', 'simple-booking' ) );
        }

        return true;
    }

    /**
     * Fetch busy windows for availability checking
     */
    public function fetch_busy_windows( $start_datetime, $end_datetime, $context = array() ) {
        $access_token = $this->get_access_token();
        if ( is_wp_error( $access_token ) ) {
            $this->debug_log( 'fetch_busy_windows token error: ' . $access_token->get_error_code() . ' - ' . $access_token->get_error_message() );
            return $access_token;
        }

        $start_ts = strtotime( (string) $start_datetime );
        $end_ts = strtotime( (string) $end_datetime );
        if ( false === $start_ts || false === $end_ts ) {
            $this->debug_log( 'fetch_busy_windows invalid range: start=' . (string) $start_datetime . ' end=' . (string) $end_datetime );
            return new WP_Error( 'outlook_schedule_invalid_range', __( 'Invalid date range for Outlook schedule lookup.', 'simple-booking' ) );
        }

        // Format as ISO 8601 UTC (with Z suffix, not +00:00 which gets URL-encoded as space)
        $start_iso = gmdate( 'Y-m-d\TH:i:s\Z', $start_ts );
        $end_iso = gmdate( 'Y-m-d\TH:i:s\Z', $end_ts );

        $this->debug_log( 'fetch_busy_windows: querying /me/calendarView for range ' . $start_iso . ' -> ' . $end_iso );

        // Query primary calendar via /me/calendarView (most reliable endpoint)
        $url = $this->build_calendar_view_url( $start_iso, $end_iso );
        $response = wp_remote_get(
            $url,
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Prefer'        => 'outlook.timezone="UTC"',
                ),
                'timeout' => 30,
            )
        );

        if ( is_wp_error( $response ) ) {
            $this->debug_log( 'calendarView request error: ' . $response->get_error_code() . ' - ' . $response->get_error_message() );
            $this->debug_log( 'Attempting getSchedule fallback' );
            $fallback = $this->fetch_busy_windows_via_get_schedule( $access_token, $start_iso, $end_iso );
            if ( is_wp_error( $fallback ) ) {
                $this->debug_log( 'getSchedule fallback error: ' . $fallback->get_error_code() . ' - ' . $fallback->get_error_message() );
                return $fallback;
            }
            $this->debug_log( 'getSchedule fallback returned ' . count( $fallback ) . ' busy windows' );
            return $fallback;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $response_body = json_decode( wp_remote_retrieve_body( $response ), true );
        
        $this->debug_log( 'calendarView response: status=' . $status_code );

        if ( 200 !== $status_code ) {
            $error_msg = '';
            if ( isset( $response_body['error']['message'] ) ) {
                $error_msg = ' error=' . $response_body['error']['message'];
            }
            $this->debug_log( 'calendarView non-200 status' . $error_msg . '; attempting getSchedule fallback' );
            
            $fallback = $this->fetch_busy_windows_via_get_schedule( $access_token, $start_iso, $end_iso );
            if ( is_wp_error( $fallback ) ) {
                $this->debug_log( 'getSchedule fallback error: ' . $fallback->get_error_code() . ' - ' . $fallback->get_error_message() );
                return $fallback;
            }
            $this->debug_log( 'getSchedule fallback returned ' . count( $fallback ) . ' busy windows' );
            return $fallback;
        }

        // Parse events and collect busy windows
        $busy_windows = array();
        if ( isset( $response_body['value'] ) && is_array( $response_body['value'] ) ) {
            foreach ( $response_body['value'] as $item ) {
                $show_as = isset( $item['showAs'] ) ? strtolower( (string) $item['showAs'] ) : '';
                
                // Skip free events and events with unknown status (treat unknown as busy)
                if ( 'free' === $show_as ) {
                    $this->debug_log( 'Skipping free event: ' . ( $item['subject'] ?? 'N/A' ) );
                    continue;
                }

                if ( ! empty( $item['start']['dateTime'] ) && ! empty( $item['end']['dateTime'] ) ) {
                    $window = array(
                        'start'   => $item['start']['dateTime'],
                        'end'     => $item['end']['dateTime'],
                        'subject' => $item['subject'] ?? 'Unknown',
                        'showAs'  => $show_as,
                    );
                    
                    // Deduplicate: skip if we already have identical start/end times with same subject
                    $is_duplicate = false;
                    foreach ( $busy_windows as $existing ) {
                        if ( $existing['start'] === $window['start'] && 
                             $existing['end'] === $window['end'] && 
                             $existing['subject'] === $window['subject'] ) {
                            $is_duplicate = true;
                            $this->debug_log( 'Skipping duplicate event: ' . $window['subject'] );
                            break;
                        }
                    }
                    
                    if ( ! $is_duplicate ) {
                        $busy_windows[] = $window;
                        $this->debug_log( 'Found busy event: ' . ( $item['subject'] ?? 'N/A' ) . ' (' . $show_as . ') ' . $item['start']['dateTime'] . ' -> ' . $item['end']['dateTime'] );
                    }
                }
            }
        }

        $this->debug_log( 'calendarView returned ' . count( $busy_windows ) . ' busy windows' );
        return $busy_windows;
    }

    /**
     * Admin diagnostics helper for probing slot availability.
     *
     * @param string $start_datetime
     * @param int    $duration_minutes
     * @return array|WP_Error
     */
    public function probe_slot( $start_datetime, $duration_minutes = 60 ) {
        $range = $this->resolve_slot_range( $start_datetime, $duration_minutes );
        if ( is_wp_error( $range ) ) {
            return $range;
        }

        $access_token = $this->get_access_token();
        if ( is_wp_error( $access_token ) ) {
            return $access_token;
        }

        // Get calendar IDs for diagnostics
        $calendar_ids = $this->get_availability_calendar_ids( $access_token );
        
        $busy_windows = $this->fetch_busy_windows( $range['start'], $range['end'] );
        if ( is_wp_error( $busy_windows ) ) {
            return $busy_windows;
        }

        return array(
            'start'        => $range['start'],
            'end'          => $range['end'],
            'duration'     => absint( $duration_minutes ),
            'busy_count'   => count( $busy_windows ),
            'available'    => empty( $busy_windows ),
            'busy_windows' => array_slice( $busy_windows, 0, 10 ),
            'calendar_ids' => $calendar_ids,  // For diagnostics
            'calendars_count' => count( $calendar_ids ),  // For diagnostics
        );
    }

    /**
     * Find available staff for a requested slot.
     *
     * Outlook provider currently validates availability against the connected
     * Outlook calendar. For services with assigned staff, we still return an
     * active staff ID to preserve downstream assignment behavior.
     */
    public function find_available_staff( $service_id, $start_datetime, $duration_minutes, $context = array() ) {
        if ( ! $this->is_connected() ) {
            return false;
        }

        if ( $this->has_local_booking_overlap( $service_id, $start_datetime, $duration_minutes ) ) {
            return false;
        }

        $available = $this->is_slot_available( $start_datetime, $duration_minutes );
        if ( is_wp_error( $available ) ) {
            return $available;
        }

        if ( true !== $available ) {
            return false;
        }

        $assigned_staff = get_post_meta( absint( $service_id ), '_assigned_staff', true );
        $assigned_staff = ! empty( $assigned_staff ) ? json_decode( $assigned_staff, true ) : array();

        if ( is_array( $assigned_staff ) && ! empty( $assigned_staff ) ) {
            foreach ( $assigned_staff as $staff_id ) {
                $staff_id = absint( $staff_id );
                if ( ! $staff_id ) {
                    continue;
                }

                $is_active = get_post_meta( $staff_id, '_staff_active', true );
                if ( '1' !== (string) $is_active ) {
                    continue;
                }

                return array(
                    'staff_id'    => $staff_id,
                    'calendar_id' => null,
                );
            }

            return false;
        }

        return array(
            'staff_id'    => null,
            'calendar_id' => null,
        );
    }

    /**
     * Build event description HTML
     */
    private function build_event_description( $booking_data ) {
        $description = sprintf(
            '<strong>%s:</strong> %s<br>',
            __( 'Service', 'simple-booking' ),
            esc_html( $booking_data['service_name'] )
        );

        $description .= sprintf(
            '<strong>%s:</strong> %s<br>',
            __( 'Customer', 'simple-booking' ),
            esc_html( $booking_data['customer_name'] )
        );

        $description .= sprintf(
            '<strong>%s:</strong> %s<br>',
            __( 'Email', 'simple-booking' ),
            esc_html( $booking_data['customer_email'] )
        );

        if ( ! empty( $booking_data['customer_phone'] ) ) {
            $description .= sprintf(
                '<strong>%s:</strong> %s<br>',
                __( 'Phone', 'simple-booking' ),
                esc_html( $booking_data['customer_phone'] )
            );
        }

        if ( ! empty( $booking_data['meeting_link'] ) ) {
            $description .= sprintf(
                '<br><strong>%s:</strong><br><a href="%s">%s</a>',
                __( 'Meeting Link', 'simple-booking' ),
                esc_url( $booking_data['meeting_link'] ),
                esc_html( $booking_data['meeting_link'] )
            );
        }

        return $description;
    }
}
