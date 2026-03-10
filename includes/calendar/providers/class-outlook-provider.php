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

        foreach ( $busy_windows as $window ) {
            if ( empty( $window['start'] ) || empty( $window['end'] ) ) {
                continue;
            }

            $busy_start_ts = strtotime( (string) $window['start'] );
            $busy_end_ts = strtotime( (string) $window['end'] );
            if ( false === $busy_start_ts || false === $busy_end_ts ) {
                continue;
            }

            if ( $range['start_ts'] < $busy_end_ts && $range['end_ts'] > $busy_start_ts ) {
                return false;
            }
        }

        return true;
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

        $response = wp_remote_post(
            self::GRAPH_API_BASE . '/me/events',
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

        $response = wp_remote_request(
            self::GRAPH_API_BASE . '/me/events/' . $event_id,
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

        $response = wp_remote_request(
            self::GRAPH_API_BASE . '/me/events/' . $event_id,
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
            return $access_token;
        }

        $body = array(
            'schedules' => array( 'me' ),
            'startTime' => array(
                'dateTime' => gmdate( 'Y-m-d\TH:i:s', strtotime( $start_datetime ) ),
                'timeZone' => 'UTC',
            ),
            'endTime' => array(
                'dateTime' => gmdate( 'Y-m-d\TH:i:s', strtotime( $end_datetime ) ),
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

        if ( $status_code !== 200 ) {
            return new WP_Error( 'outlook_schedule_failed', $response_body['error']['message'] ?? __( 'Schedule fetch failed', 'simple-booking' ) );
        }

        $busy_windows = array();
        
        if ( isset( $response_body['value'][0]['scheduleItems'] ) ) {
            foreach ( $response_body['value'][0]['scheduleItems'] as $item ) {
                if ( in_array( $item['status'], array( 'busy', 'tentative', 'oof', 'workingElsewhere' ), true ) ) {
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
     * Find available staff (placeholder for multi-staff support)
     */
    public function find_available_staff( $service_id, $start_datetime, $duration_minutes, $context = array() ) {
        if ( ! $this->is_connected() ) {
            return false;
        }

        $available = $this->is_slot_available( $start_datetime, $duration_minutes );
        if ( is_wp_error( $available ) ) {
            return false;
        }

        if ( true !== $available ) {
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
