<?php
/**
 * Google Calendar Handler
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Simple_Booking_Google_Calendar
 */
class Simple_Booking_Google_Calendar {

    /**
     * REST namespace
     */
    const REST_NAMESPACE = 'simple-booking/v1';

    /**
     * Option key for tokens
     */
    const TOKEN_OPTION = 'simple_booking_google_tokens';

    /**
     * Google OAuth URLs
     */
    const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /**
     * Debug file path - TEMPORARY FOR TESTING ONLY
     *
     * TO USE:
     * 1. Create an empty file named 'debug-google.txt' in the plugin folder
     * 2. Make sure it's writable by the web server (775/777 may be required)
     * 3. Clear existing contents or call debug_clear() before running a test
     * 4. Trigger a booking which should tap the Google code path
     * 5. Inspect the file for step‑by‑step information and JSON payloads
     *
     * TO DISABLE AFTER TESTING OR IN PRODUCTION:
     * - Set DEBUG_ENABLED to false, or
     * - Remove/comment out debug_log() calls, or
     * - Delete the debug file entirely
     *
     * The debug helper is intentionally minimal and scoped only to the
     * Google Calendar flow; nothing else in the plugin writes here.
     *
     * @todo Remove this debug logging after resolving the Google Calendar issue
     */
    const DEBUG_FILE = SIMPLE_BOOKING_PATH . 'debug-google.txt';
    // debug enabled controlled via plugin setting 'debug_mode'
    const DEBUG_ENABLED = false; // no longer used - kept for legacy reference

    private function debug_log( $message, $section = '' ) {
        // TEMPORARILY DISABLED: Debug logging to fix critical error
        // TODO: Re-enable debug logging after fixing the crash
        return;
    }

    private function debug_clear() {
        // TEMPORARILY DISABLED: Debug clearing to fix critical error
        return;
    }

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
    }

    /**
     * Register REST routes
     */
    public function register_rest_routes() {
        // OAuth callback
        register_rest_route(
            self::REST_NAMESPACE,
            'google/oauth',
            array(
                'methods'  => 'GET',
                'callback' => array( $this, 'handle_oauth_callback' ),
                'permission_callback' => '__return_true',
            )
        );

        // Check auth status
        register_rest_route(
            self::REST_NAMESPACE,
            'google/status',
            array(
                'methods'  => 'GET',
                'callback' => array( $this, 'get_auth_status' ),
                'permission_callback' => array( $this, 'check_admin_permission' ),
            )
        );

        // Disconnect
        register_rest_route(
            self::REST_NAMESPACE,
            'google/disconnect',
            array(
                'methods'  => 'POST',
                'callback' => array( $this, 'disconnect' ),
                'permission_callback' => array( $this, 'check_admin_permission' ),
            )
        );
    }

    /**
     * Check admin permission
     */
    public function check_admin_permission() {
        return current_user_can( 'manage_options' );
    }

    /**
     * Get OAuth URL
     */
    public function get_oauth_url( $save_state = true ) {

        $client_id = simple_booking()->get_setting( 'google_client_id' );
        if ( empty( $client_id ) ) {
            return null;
        }

        $redirect_uri = rest_url( 'simple-booking/v1/google/oauth' );

        if ( $save_state ) {
            $state = wp_generate_uuid4();
            update_option( 'simple_booking_google_oauth_state', $state );
        } else {
            $state = get_option( 'simple_booking_google_oauth_state' );
            if ( ! $state ) {
                return null;
            }
        }

        $params = array(
            'client_id'     => $client_id,
            'redirect_uri'  => $redirect_uri,
            'response_type' => 'code',
            'scope'         => 'https://www.googleapis.com/auth/calendar.events',
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'state'         => $state,
        );

        return add_query_arg( $params, self::AUTH_URL );
    }

    /**
     * Handle OAuth callback
     */
    public function handle_oauth_callback( WP_REST_Request $request ) {
        $code  = $request->get_param( 'code' );
        $returned_state = $request->get_param( 'state' );

        // Retrieve stored state from option
        $saved_state = get_option( 'simple_booking_google_oauth_state' );

        // Debug logging - write to file instead of system error log
        $this->debug_log( 'OAuth state verification', 'DEBUG' );
        $this->debug_log( 'Saved state: ' . $saved_state );
        $this->debug_log( 'Returned state: ' . $returned_state );

        // Verify state with strict comparison
        if ( ! $saved_state || $returned_state !== $saved_state ) {
            wp_die( __( 'Invalid state parameter', 'simple-booking' ) );
        }

        // Delete stored state immediately to prevent reuse
        delete_option( 'simple_booking_google_oauth_state' );

        if ( empty( $code ) ) {
            wp_die( __( 'No authorization code received', 'simple-booking' ) );
        }

        // Exchange code for tokens
        $tokens = $this->exchange_code_for_tokens( $code );

        if ( is_wp_error( $tokens ) ) {
            wp_die( $tokens->get_error_message() );
        }

        // Save tokens
        update_option( self::TOKEN_OPTION, $tokens );

        // Redirect to settings page
        wp_redirect( add_query_arg(
            array(
                'page'    => 'simple-booking-settings',
                'google'  => 'connected',
            ),
            admin_url( 'options-general.php' )
        ) );
        exit;
    }

    /**
     * Exchange code for tokens
     */
    private function exchange_code_for_tokens( $code ) {
        $client_id     = simple_booking()->get_setting( 'google_client_id' );
        $client_secret = simple_booking()->get_setting( 'google_client_secret' );
        $redirect_uri  = rest_url( 'simple-booking/v1/google/oauth' );

        // Debug logging
        $this->debug_log( 'Starting token exchange', 'DEBUG' );
        $this->debug_log( 'Client ID: ' . substr( $client_id, 0, 20 ) . '...' );

        $response = wp_remote_post(
            self::TOKEN_URL,
            array(
                'body' => array(
                    'client_id'     => $client_id,
                    'client_secret' => $client_secret,
                    'code'          => $code,
                    'grant_type'    => 'authorization_code',
                    'redirect_uri'  => $redirect_uri,
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            $this->debug_log( 'WP_Error during token exchange: ' . $response->get_error_message(), 'TOKEN' );
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $this->debug_log( 'Token response keys: ' . implode( ', ', array_keys( $body ) ), 'TOKEN' );
        $this->debug_log( 'Token response: ' . json_encode( $body ), 'TOKEN' );

        if ( isset( $body['error'] ) ) {
            $this->debug_log( 'Token exchange error: ' . json_encode( $body['error'] ), 'TOKEN' );
            return new WP_Error( 'google_error', $body['error'] );
        }

        // Add created timestamp for expiry tracking
        if ( isset( $body['expires_in'] ) && is_numeric( $body['expires_in'] ) ) {
            $body['created'] = time();
            $this->debug_log( 'Set token created timestamp: ' . time(), 'TOKEN' );
        }

        $this->debug_log( '=== exchange_code_for_tokens END ===', 'TOKEN' );
        return $body;
    }

    /**
     * Refresh access token
     */
    public function refresh_token() {
        $tokens = get_option( self::TOKEN_OPTION, array() );

        if ( empty( $tokens['refresh_token'] ) ) {
            return new WP_Error( 'no_refresh_token', __( 'No refresh token available', 'simple-booking' ) );
        }

        $client_id     = simple_booking()->get_setting( 'google_client_id' );
        $client_secret = simple_booking()->get_setting( 'google_client_secret' );

        $response = wp_remote_post(
            self::TOKEN_URL,
            array(
                'body' => array(
                    'client_id'     => $client_id,
                    'client_secret' => $client_secret,
                    'refresh_token' => $tokens['refresh_token'],
                    'grant_type'    => 'refresh_token',
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['error'] ) ) {
            return new WP_Error( 'google_error', $body['error'] );
        }

        // Update tokens (keep refresh token)
        $tokens['access_token'] = $body['access_token'];
        if ( isset( $body['expires_in'] ) && is_numeric( $body['expires_in'] ) ) {
            $tokens['created'] = time();
        }
        update_option( self::TOKEN_OPTION, $tokens );

        return $tokens['access_token'];
    }

    /**
     * Get access token
     */
    public function get_access_token() {
        $tokens = get_option( self::TOKEN_OPTION, array() );

        $this->debug_log( '=== get_access_token START ===', 'TOKEN' );
        $this->debug_log( 'Token option keys: ' . implode( ', ', array_keys( $tokens ) ), 'TOKEN' );

        if ( empty( $tokens['access_token'] ) ) {
            $this->debug_log( 'No access_token in stored tokens', 'TOKEN' );
            return null;
        }

        // TEMPORARILY DISABLED: Token expiry checking to fix critical error
        // TODO: Re-enable token expiry logic after fixing the crash

        $this->debug_log( '=== get_access_token END ===', 'TOKEN' );
        return $tokens['access_token'];
    }

    /**
     * Check if connected
     */
    public function is_connected() {
        $tokens = get_option( self::TOKEN_OPTION, array() );
        return ! empty( $tokens['access_token'] );
    }

    /**
     * Get auth status
     */
    public function get_auth_status() {
        return array(
            'connected' => $this->is_connected(),
            'auth_url' => $this->get_oauth_url( false ),
        );
    }

    /**
     * Disconnect Google
     */
    public function disconnect() {
        delete_option( self::TOKEN_OPTION );
        return array( 'success' => true );
    }

    /**
     * Get list of available calendars with names (for dropdown).
     *
     * @return array|WP_Error Array of calendars with 'id' and 'summary', or WP_Error.
     */
    public function list_calendars() {
        if ( ! $this->is_connected() ) {
            return new WP_Error( 'not_connected', __( 'Google Calendar not connected', 'simple-booking' ) );
        }

        $access_token = $this->get_access_token();
        if ( empty( $access_token ) ) {
            return new WP_Error( 'no_token', __( 'No access token available', 'simple-booking' ) );
        }

        $response = wp_remote_get(
            'https://www.googleapis.com/calendar/v3/calendarList?maxResults=50',
            array(
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
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 !== $status_code ) {
            $error_msg = isset( $body['error']['message'] ) ? $body['error']['message'] : 'Unknown error';
            return new WP_Error( 'api_error', $error_msg );
        }

        if ( empty( $body['items'] ) || ! is_array( $body['items'] ) ) {
            return array();
        }

        $calendars = array();
        foreach ( $body['items'] as $calendar ) {
            if ( empty( $calendar['id'] ) ) {
                continue;
            }

            $calendars[] = array(
                'id'      => (string) $calendar['id'],
                'name'    => isset( $calendar['summary'] ) ? (string) $calendar['summary'] : 'Calendar',
                'primary' => isset( $calendar['primary'] ) ? $calendar['primary'] : false,
            );
        }

        return $calendars;
    }

    /**
     * Create calendar event
     */
    public function create_event( $booking_data, $calendar_id = null ) {
        // validate required fields
        if ( ! isset( $booking_data['start_datetime'] ) || ! isset( $booking_data['end_datetime'] ) ) {
            return new WP_Error( 'missing_data', 'Booking data incomplete' );
        }
        $access_token = $this->get_access_token();

        // Debug logging
        $this->debug_log( '=== create_event START ===', 'EVENT' );
        $this->debug_log( 'Access token retrieved: ' . ( empty( $access_token ) ? 'EMPTY' : substr( $access_token, 0, 20 ) . '...' ), 'EVENT' );

        if ( empty( $access_token ) ) {
            $this->debug_log( 'ERROR: No access token - calling is_connected(): ' . ( $this->is_connected() ? 'true' : 'false' ), 'EVENT' );
            $tokens = get_option( self::TOKEN_OPTION, array() );
            $this->debug_log( 'Stored tokens keys: ' . implode( ', ', array_keys( $tokens ) ), 'EVENT' );
            return new WP_Error( 'not_connected', __( 'Google Calendar not connected', 'simple-booking' ) );
        }

        if ( empty( $calendar_id ) ) {
            $calendar_id = simple_booking()->get_setting( 'google_calendar_id' );
        }
        $this->debug_log( 'Calendar ID: ' . $calendar_id, 'EVENT' );

        if ( empty( $calendar_id ) ) {
            if ( simple_booking()->get_setting( 'debug_mode' ) ) {
                error_log( 'ERROR: No calendar ID configured' );
            }
            return new WP_Error( 'no_calendar_id', __( 'No Calendar ID configured', 'simple-booking' ) );
        }

        // Format times for Google Calendar
        $timezone = wp_timezone();
        $start    = new DateTime( $booking_data['start_datetime'], $timezone );
        $end      = new DateTime( $booking_data['end_datetime'], $timezone );

        $event = array(
            'summary'     => sprintf( '%s – %s', $booking_data['service_name'], $booking_data['customer_name'] ),
            'description' => $this->format_event_description( $booking_data ),
            'start'       => array(
                'dateTime' => $start->format( 'c' ),
                'timeZone' => $timezone->getName(),
            ),
            'end'         => array(
                'dateTime' => $end->format( 'c' ),
                'timeZone' => $timezone->getName(),
            ),
        );

        $auto_google_meet_enabled = isset( $booking_data['auto_google_meet'] ) && '1' === (string) $booking_data['auto_google_meet'];
        if ( $auto_google_meet_enabled ) {
            $event['conferenceData'] = array(
                'createRequest' => array(
                    'requestId' => wp_generate_uuid4(),
                    'conferenceSolutionKey' => array(
                        'type' => 'hangoutsMeet',
                    ),
                ),
            );
        }

        $this->debug_log( 'Event payload: ' . json_encode( $event ), 'EVENT' );

        $url = sprintf(
            'https://www.googleapis.com/calendar/v3/calendars/%s/events',
            urlencode( $calendar_id )
        );
        if ( $auto_google_meet_enabled ) {
            $url = add_query_arg( 'conferenceDataVersion', '1', $url );
        }

        $this->debug_log( 'API URL: ' . $url, 'EVENT' );
        $this->debug_log( 'Authorization header: Bearer ' . substr( $access_token, 0, 20 ) . '...', 'EVENT' );

        $response = wp_remote_post(
            $url,
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => json_encode( $event ),
            )
        );

        // Log response details
        if ( is_wp_error( $response ) ) {
            $this->debug_log( 'WP_Error: ' . $response->get_error_message(), 'EVENT' );
            return $response;
        }

        $http_code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        $this->debug_log( 'HTTP Status: ' . $http_code, 'EVENT' );
        $this->debug_log( 'Response body: ' . json_encode( $body ), 'EVENT' );

        if ( isset( $body['error'] ) ) {
            $this->debug_log( 'Google API error: ' . json_encode( $body['error'] ), 'EVENT' );
            
            // If authentication error, try refreshing token and retry once
            if ( 401 === $http_code || ( isset( $body['error']['code'] ) && 401 === $body['error']['code'] ) ) {
                $this->debug_log( 'Authentication error detected (401), attempting token refresh', 'EVENT' );
                $refreshed = $this->refresh_token();
                
                if ( ! is_wp_error( $refreshed ) ) {
                    $this->debug_log( 'Token refreshed successfully, retrying API call', 'EVENT' );
                    $access_token = $this->get_access_token();
                    
                    // Retry the API call with fresh token
                    $response = wp_remote_post(
                        $url,
                        array(
                            'headers' => array(
                                'Authorization' => 'Bearer ' . $access_token,
                                'Content-Type'  => 'application/json',
                            ),
                            'body'    => json_encode( $event ),
                        )
                    );
                    
                    if ( is_wp_error( $response ) ) {
                        $this->debug_log( 'Retry WP_Error: ' . $response->get_error_message(), 'EVENT' );
                        return $response;
                    }
                    
                    $http_code = wp_remote_retrieve_response_code( $response );
                    $body = json_decode( wp_remote_retrieve_body( $response ), true );
                    
                    $this->debug_log( 'Retry HTTP Status: ' . $http_code, 'EVENT' );
                    $this->debug_log( 'Retry Response body: ' . json_encode( $body ), 'EVENT' );
                    
                    // If still error after refresh, return error
                    if ( isset( $body['error'] ) ) {
                        $this->debug_log( 'Retry failed with error: ' . json_encode( $body['error'] ), 'EVENT' );
                        return new WP_Error( 'google_api_error', $body['error']['message'] );
                    }
                    
                    // Success after retry - continue to process event_id below
                } else {
                    $this->debug_log( 'Token refresh failed: ' . $refreshed->get_error_message(), 'EVENT' );
                    return new WP_Error( 'google_api_error', $body['error']['message'] );
                }
            } else {
                // Non-auth error, return immediately
                return new WP_Error( 'google_api_error', $body['error']['message'] );
            }
        }

        $event_id = isset( $body['id'] ) ? $body['id'] : null;
        $meeting_link = '';

        if ( ! empty( $body['hangoutLink'] ) ) {
            $meeting_link = esc_url_raw( $body['hangoutLink'] );
        } elseif ( ! empty( $body['conferenceData']['entryPoints'] ) && is_array( $body['conferenceData']['entryPoints'] ) ) {
            foreach ( $body['conferenceData']['entryPoints'] as $entry_point ) {
                if ( isset( $entry_point['entryPointType'], $entry_point['uri'] ) && 'video' === $entry_point['entryPointType'] ) {
                    $meeting_link = esc_url_raw( $entry_point['uri'] );
                    break;
                }
            }
        }

        $this->debug_log( 'Created event ID: ' . $event_id, 'EVENT' );
        if ( ! empty( $meeting_link ) ) {
            $this->debug_log( 'Generated Meet link: ' . $meeting_link, 'EVENT' );
        }
        $this->debug_log( '=== create_event END ===', 'EVENT' );

        return array(
            'event_id'     => $event_id,
            'meeting_link' => $meeting_link,
        );
    }

    /**
     * Delete calendar event.
     *
     * @param string $event_id
     * @param string $calendar_id Optional. Specific calendar ID. Falls back to global setting.
     * @return true|WP_Error
     */
    public function delete_event( $event_id, $calendar_id = null ) {
        $event_id = sanitize_text_field( $event_id );
        if ( empty( $event_id ) ) {
            return new WP_Error( 'missing_event_id', __( 'Missing Google event ID', 'simple-booking' ) );
        }

        $access_token = $this->get_access_token();
        if ( empty( $access_token ) ) {
            return new WP_Error( 'not_connected', __( 'Google Calendar not connected', 'simple-booking' ) );
        }

        if ( empty( $calendar_id ) ) {
            $calendar_id = simple_booking()->get_setting( 'google_calendar_id' );
        }
        if ( empty( $calendar_id ) ) {
            return new WP_Error( 'no_calendar_id', __( 'No Calendar ID configured', 'simple-booking' ) );
        }

        $url = sprintf(
            'https://www.googleapis.com/calendar/v3/calendars/%s/events/%s',
            urlencode( $calendar_id ),
            urlencode( $event_id )
        );

        $response = wp_remote_request(
            $url,
            array(
                'method'  => 'DELETE',
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( in_array( $code, array( 200, 204, 404 ), true ) ) {
            // 404 means event already gone, treat as success
            return true;
        }

        // Check for auth error and retry with token refresh
        if ( 401 === $code ) {
            $this->debug_log( 'Delete event: Authentication error (401), attempting token refresh', 'EVENT' );
            $refreshed = $this->refresh_token();
            
            if ( ! is_wp_error( $refreshed ) ) {
                $this->debug_log( 'Delete event: Token refreshed, retrying delete', 'EVENT' );
                $access_token = $this->get_access_token();
                
                // Retry the delete
                $response = wp_remote_request(
                    $url,
                    array(
                        'method'  => 'DELETE',
                        'headers' => array(
                            'Authorization' => 'Bearer ' . $access_token,
                        ),
                    )
                );
                
                if ( is_wp_error( $response ) ) {
                    return $response;
                }
                
                $code = wp_remote_retrieve_response_code( $response );
                if ( in_array( $code, array( 200, 204, 404 ), true ) ) {
                    return true;
                }
                // Fall through to error handling below
            }
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $message = isset( $body['error']['message'] ) ? $body['error']['message'] : ( 'HTTP ' . $code );

        return new WP_Error( 'google_api_error', $message );
    }

    /**
     * Fetch all Google Calendar events on a given date.
     *
     * Returns array of arrays with 'start' and 'end' ISO8601 strings (site timezone)
     * or WP_Error on failure.
     *
     * @param string $date YYYY-MM-DD
     * @param string $calendar_id Optional. Specific calendar ID. Falls back to global setting.
     * @return array|WP_Error
     */
    public function fetch_events_on_date( $date, $calendar_id = null ) {
        $access_token = $this->get_access_token();
        if ( empty( $access_token ) ) {
            return new WP_Error( 'not_connected', __( 'Google Calendar not connected', 'simple-booking' ) );
        }

        if ( empty( $calendar_id ) ) {
            $calendar_id = simple_booking()->get_setting( 'google_calendar_id' );
        }
        if ( empty( $calendar_id ) ) {
            return new WP_Error( 'no_calendar_id', __( 'No Calendar ID configured', 'simple-booking' ) );
        }

        $timezone = wp_timezone_string();
        $tz = new DateTimeZone( $timezone );
        $start = new DateTime( $date . ' 00:00:00', $tz );
        $end   = new DateTime( $date . ' 23:59:59', $tz );

        $url = sprintf(
            'https://www.googleapis.com/calendar/v3/calendars/%s/events?timeMin=%s&timeMax=%s&singleEvents=true&orderBy=startTime',
            urlencode( $calendar_id ),
            urlencode( $start->format( DateTime::ATOM ) ),
            urlencode( $end->format( DateTime::ATOM ) )
        );

        $response = wp_remote_get(
            $url,
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        
        if ( $code !== 200 ) {
            // Check for auth error and retry with token refresh
            if ( 401 === $code ) {
                $this->debug_log( 'Fetch events: Authentication error (401), attempting token refresh', 'SLOTS' );
                $refreshed = $this->refresh_token();
                
                if ( ! is_wp_error( $refreshed ) ) {
                    $this->debug_log( 'Fetch events: Token refreshed, retrying fetch', 'SLOTS' );
                    $access_token = $this->get_access_token();
                    
                    // Retry the fetch
                    $response = wp_remote_get(
                        $url,
                        array(
                            'headers' => array(
                                'Authorization' => 'Bearer ' . $access_token,
                            ),
                        )
                    );
                    
                    if ( is_wp_error( $response ) ) {
                        return $response;
                    }
                    
                    $code = wp_remote_retrieve_response_code( $response );
                    $body = json_decode( wp_remote_retrieve_body( $response ), true );
                    
                    if ( $code !== 200 ) {
                        return new WP_Error( 'google_api_error', isset( $body['error']['message'] ) ? $body['error']['message'] : 'HTTP ' . $code );
                    }
                    // Success after retry - continue to process events below
                } else {
                    $this->debug_log( 'Fetch events: Token refresh failed', 'SLOTS' );
                    return new WP_Error( 'google_api_error', isset( $body['error']['message'] ) ? $body['error']['message'] : 'HTTP ' . $code );
                }
            } else {
                // Non-auth error, return immediately
                return new WP_Error( 'google_api_error', isset( $body['error']['message'] ) ? $body['error']['message'] : 'HTTP ' . $code );
            }
        }

        $events = array();
        if ( isset( $body['items'] ) && is_array( $body['items'] ) ) {
            foreach ( $body['items'] as $item ) {
                if ( isset( $item['start']['dateTime'] ) && isset( $item['end']['dateTime'] ) ) {
                    $events[] = array(
                        'start' => $item['start']['dateTime'],
                        'end'   => $item['end']['dateTime'],
                    );
                }
            }
        }

        $this->debug_log( 'Fetched ' . count( $events ) . ' events for ' . $date, 'SLOTS' );
        foreach ( $events as $e ) {
            $this->debug_log( 'Existing event: ' . $e['start'] . ' - ' . $e['end'], 'SLOTS' );
        }

        return $events;
    }

    /**
     * Compute available start slots on a given date given the service duration.
     *
     * @param string $date YYYY-MM-DD
     * @param int $service_duration Minutes
     * @return array|WP_Error Array of ISO8601 start times in site timezone.
     */
    public function get_available_slots( $date, $service_duration ) {
        $events = $this->fetch_events_on_date( $date );
        if ( is_wp_error( $events ) ) {
            return $events;
        }

        $timezone = wp_timezone_string();
        $tz = new DateTimeZone( $timezone );
        $dayStart = new DateTime( $date . ' 00:00:00', $tz );
        $dayEnd   = new DateTime( $date . ' 23:59:59', $tz );

        $slots = array();
        $pointer = clone $dayStart;

        foreach ( $events as $event ) {
            $evStart = new DateTime( $event['start'], $tz );
            $evEnd   = new DateTime( $event['end'], $tz );

            // advance pointer while there is room before event start
            while ( $pointer->getTimestamp() + $service_duration * 60 <= $evStart->getTimestamp() ) {
                $slotEnd = clone $pointer;
                $slotEnd->modify( "+{$service_duration} minutes" );
                $this->debug_log( 'Checking slot ' . $pointer->format( DateTime::ATOM ) . ' - ' . $slotEnd->format( DateTime::ATOM ), 'SLOTS' );
                $slots[] = $pointer->format( DateTime::ATOM );
                $pointer->modify( "+{$service_duration} minutes" );
            }

            // skip over the event itself
            if ( $pointer < $evEnd ) {
                $pointer = clone $evEnd;
            }
        }

        // fill remaining
        while ( $pointer->getTimestamp() + $service_duration * 60 <= $dayEnd->getTimestamp() ) {
            $slotEnd = clone $pointer;
            $slotEnd->modify( "+{$service_duration} minutes" );
            $this->debug_log( 'Checking slot ' . $pointer->format( DateTime::ATOM ) . ' - ' . $slotEnd->format( DateTime::ATOM ), 'SLOTS' );
            $slots[] = $pointer->format( DateTime::ATOM );
            $pointer->modify( "+{$service_duration} minutes" );
        }

        // if no slots were computed, log a warning
        if ( empty( $slots ) ) {
            $this->debug_log( 'No available slots computed for ' . $date . ' (duration ' . $service_duration . 'm)', 'SLOTS' );
        } else {
            $this->debug_log( 'Total available slots: ' . count( $slots ), 'SLOTS' );
        }

        return $slots;
    }

    /**
     * Determine if a particular start time is available (no overlap with existing events).
     *
     * @param string $start_datetime ISO string or datetime parsable by DateTime
     * @param int    $service_duration Minutes
     * @param string $calendar_id Optional. Specific calendar ID. Falls back to global setting.
     * @return bool|WP_Error True if slot free, false if overlapping, WP_Error on failure
     */
    public function is_slot_available( $start_datetime, $service_duration, $calendar_id = null ) {
        $tz = new DateTimeZone( wp_timezone_string() );
        $start = new DateTime( $start_datetime, $tz );
        $end   = clone $start;
        $end->modify( "+{$service_duration} minutes" );

        $date = $start->format( 'Y-m-d' );
        $events = $this->fetch_events_on_date( $date, $calendar_id );
        if ( is_wp_error( $events ) ) {
            return $events;
        }

        $this->debug_log( 'Checking availability for ' . $start->format( DateTime::ATOM ) . ' - ' . $end->format( DateTime::ATOM ), 'SLOTS' );

        foreach ( $events as $e ) {
            $evStart = new DateTime( $e['start'], $tz );
            $evEnd   = new DateTime( $e['end'], $tz );
            $this->debug_log( 'Existing event: ' . $evStart->format( DateTime::ATOM ) . ' - ' . $evEnd->format( DateTime::ATOM ), 'SLOTS' );

            // check overlap: start < evEnd && end > evStart
            if ( $start < $evEnd && $end > $evStart ) {
                $this->debug_log( 'Slot overlaps existing event', 'SLOTS' );
                return false;
            }
        }

        $this->debug_log( 'Slot is available', 'SLOTS' );
        return true;
    }

    /**
     * Find an available staff member for a given service and time slot.
     *
     * @param int    $service_id Service post ID
     * @param string $start_datetime ISO string or datetime parsable by DateTime
     * @param int    $service_duration Minutes
     * @return array|false Array with 'staff_id' and 'calendar_id' if available, false if none available
     */
    public function find_available_staff( $service_id, $start_datetime, $service_duration ) {
        // Get assigned staff for this service
        $assigned_staff = get_post_meta( $service_id, '_assigned_staff', true );
        $assigned_staff = ! empty( $assigned_staff ) ? json_decode( $assigned_staff, true ) : array();
        $global_calendar_id = simple_booking()->get_setting( 'google_calendar_id' );

        // If no staff assigned, use global calendar
        if ( empty( $assigned_staff ) || ! is_array( $assigned_staff ) ) {
            $available = $this->is_slot_available( $start_datetime, $service_duration, $global_calendar_id );
            
            // If we get an error from Google API, log it but don't fail - allow booking
            if ( is_wp_error( $available ) ) {
                $this->debug_log( 'Global calendar availability check error: ' . $available->get_error_message(), 'SLOTS' );
                // Graceful fallback: assume available when we can't verify
                return array(
                    'staff_id'    => null,
                    'calendar_id' => $global_calendar_id,
                );
            }
            
            if ( true === $available ) {
                return array(
                    'staff_id'    => null,
                    'calendar_id' => $global_calendar_id,
                );
            }
            
            return false;
        }

        // Check each assigned staff member's calendar
        $active_staff_count = 0;
        $error_staff_count = 0;
        foreach ( $assigned_staff as $staff_id ) {
            $staff_id = absint( $staff_id );
            if ( ! $staff_id ) {
                continue;
            }

            // Check if staff is active
            $is_active = get_post_meta( $staff_id, '_staff_active', true );
            if ( '1' !== $is_active ) {
                continue;
            }
            $active_staff_count++;

            // Get staff's calendar ID (may be custom or fallback to global)
            $staff_calendar_id = get_post_meta( $staff_id, '_staff_calendar_id', true );
            if ( empty( $staff_calendar_id ) ) {
                $staff_calendar_id = simple_booking()->get_setting( 'google_calendar_id' );
            }

            // Check if this staff member has the slot available
            $available = $this->is_slot_available( $start_datetime, $service_duration, $staff_calendar_id );
            
            // If we get an error from Google API for this staff, skip them and try next
            if ( is_wp_error( $available ) ) {
                $this->debug_log( 'Staff member ' . $staff_id . ' availability check error: ' . $available->get_error_message() . ', skipping', 'SLOTS' );
                $error_staff_count++;
                continue;
            }
            
            if ( true === $available ) {
                $this->debug_log( 'Staff member ' . $staff_id . ' available for slot ' . $start_datetime, 'SLOTS' );
                return array(
                    'staff_id'    => $staff_id,
                    'calendar_id' => $staff_calendar_id,
                );
            }

            $this->debug_log( 'Staff member ' . $staff_id . ' not available for slot ' . $start_datetime, 'SLOTS' );
        }

        // If all active staff had API errors, use graceful fallback (assume available)
        if ( $active_staff_count > 0 && $error_staff_count === $active_staff_count ) {
            $this->debug_log( 'All ' . $active_staff_count . ' active staff had availability check errors; using graceful fallback', 'SLOTS' );
            $staff_calendar_id = get_post_meta( $assigned_staff[0], '_staff_calendar_id', true );
            if ( empty( $staff_calendar_id ) ) {
                $staff_calendar_id = simple_booking()->get_setting( 'google_calendar_id' );
            }
            return array(
                'staff_id'    => $assigned_staff[0],
                'calendar_id' => $staff_calendar_id,
            );
        }

        // If all assigned staff are inactive, fallback to global calendar availability.
        if ( 0 === $active_staff_count ) {
            $this->debug_log( 'No active assigned staff found; falling back to global calendar', 'SLOTS' );
            $available = $this->is_slot_available( $start_datetime, $service_duration, $global_calendar_id );

            // If we get an error on fallback, log it but allow booking
            if ( is_wp_error( $available ) ) {
                $this->debug_log( 'Global calendar fallback availability check error: ' . $available->get_error_message(), 'SLOTS' );
                // Graceful fallback: assume available when we can't verify
                return array(
                    'staff_id'    => null,
                    'calendar_id' => $global_calendar_id,
                );
            }

            if ( true === $available ) {
                return array(
                    'staff_id'    => null,
                    'calendar_id' => $global_calendar_id,
                );
            }
        }

        // No staff available
        return false;
    }

    /**
     * Format event description
     */
    private function format_event_description( $booking_data ) {
        $description  = sprintf( "Booking: %s\n\n", $booking_data['service_name'] );
        $description .= sprintf( "Client: %s\n", $booking_data['customer_name'] );
        $description .= sprintf( "Email: %s", $booking_data['customer_email'] );

        // Add meeting link if available
        if ( isset( $booking_data['meeting_link'] ) && ! empty( $booking_data['meeting_link'] ) ) {
            $description .= sprintf( "\n\nMeeting:\n%s", $booking_data['meeting_link'] );
        }

        return $description;
    }
}

// Initialize Google Calendar
new Simple_Booking_Google_Calendar();
