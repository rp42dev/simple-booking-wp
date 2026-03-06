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
     * Create calendar event
     */
    public function create_event( $booking_data ) {
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

        $calendar_id = simple_booking()->get_setting( 'google_calendar_id' );
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

        $this->debug_log( 'Event payload: ' . json_encode( $event ), 'EVENT' );

        $url = sprintf(
            'https://www.googleapis.com/calendar/v3/calendars/%s/events',
            urlencode( $calendar_id )
        );

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
            return new WP_Error( 'google_api_error', $body['error']['message'] );
        }

        $event_id = isset( $body['id'] ) ? $body['id'] : null;
        $this->debug_log( 'Created event ID: ' . $event_id, 'EVENT' );
        $this->debug_log( '=== create_event END ===', 'EVENT' );

        return $event_id;
    }

    /**
     * Fetch all Google Calendar events on a given date.
     *
     * Returns array of arrays with 'start' and 'end' ISO8601 strings (site timezone)
     * or WP_Error on failure.
     *
     * @param string $date YYYY-MM-DD
     * @return array|WP_Error
     */
    public function fetch_events_on_date( $date ) {
        $access_token = $this->get_access_token();
        if ( empty( $access_token ) ) {
            return new WP_Error( 'not_connected', __( 'Google Calendar not connected', 'simple-booking' ) );
        }

        $calendar_id = simple_booking()->get_setting( 'google_calendar_id' );
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
            return new WP_Error( 'google_api_error', isset( $body['error']['message'] ) ? $body['error']['message'] : 'HTTP ' . $code );
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
     * @return bool|WP_Error True if slot free, false if overlapping, WP_Error on failure
     */
    public function is_slot_available( $start_datetime, $service_duration ) {
        $tz = new DateTimeZone( wp_timezone_string() );
        $start = new DateTime( $start_datetime, $tz );
        $end   = clone $start;
        $end->modify( "+{$service_duration} minutes" );

        $date = $start->format( 'Y-m-d' );
        $events = $this->fetch_events_on_date( $date );
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
     * Format event description
     */
    private function format_event_description( $booking_data ) {
        $description  = sprintf( "Customer: %s\n", $booking_data['customer_name'] );
        $description .= sprintf( "Email: %s\n", $booking_data['customer_email'] );
        $description .= sprintf( "Phone: %s\n", $booking_data['customer_phone'] );
        $description .= sprintf( "Service: %s\n", $booking_data['service_name'] );
        $description .= sprintf( "Time: %s - %s", $booking_data['start_datetime'], $booking_data['end_datetime'] );

        // Add meeting link if available
        if ( isset( $booking_data['meeting_link'] ) && ! empty( $booking_data['meeting_link'] ) ) {
            $description .= sprintf( "\n\nMeeting Link:\n%s", $booking_data['meeting_link'] );
        }

        return $description;
    }
}

// Initialize Google Calendar
new Simple_Booking_Google_Calendar();
