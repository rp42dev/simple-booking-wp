<?php
/**
 * Booking Creator
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Simple_Booking_Booking_Creator
 */
class Simple_Booking_Booking_Creator {

    /**
     * Path to the shared debug file used for Google flow.
     *
     * See Simple_Booking_Google_Calendar for usage notes. This constant is
     * duplicated here so the booking creator can log before instantiating
     * the Google class.
     */
    const DEBUG_FILE = SIMPLE_BOOKING_PATH . 'debug-google.txt';
    const DEBUG_ENABLED = true; // set to false to disable from this class
    // @todo remove DEBUG_FILE/DEBUG_ENABLED and related logging after issue is resolved

    /**
     * Simple logger for this class. Appends timestamped entries to debug file.
     *
     * @param string $message
     * @param string $section
     */
    private static function debug_log( $message, $section = '' ) {
        if ( ! simple_booking()->get_setting( 'debug_mode', false ) ) {
            return;
        }
        $timestamp = current_time( 'mysql' );
        $entry = sprintf( "[%s] %s%s\n", $timestamp, $section ? $section . ': ' : '', $message );
        @file_put_contents( self::DEBUG_FILE, $entry, FILE_APPEND );
    }

    /**
     * Create booking post
     */
    public static function create_booking( $data ) {
        self::debug_log( 'create_booking called with data: ' . json_encode( $data ), 'BOOKING' );
        // before we persist anything check for double-booking
        if ( isset( $data['service_id'], $data['start_datetime'] ) ) {
            $service_id = absint( $data['service_id'] );
            $service_duration = intval( get_post_meta( $service_id, '_service_duration', true ) );
            if ( $service_duration <= 0 ) {
                $service_duration = 60; // fallback
            }

            // Check slot availability if Google Calendar is available
            if ( class_exists( 'Simple_Booking_Google_Calendar' ) ) {
                $google = new Simple_Booking_Google_Calendar();
                $available = $google->is_slot_available( $data['start_datetime'], $service_duration );
                if ( is_wp_error( $available ) ) {
                    self::debug_log( 'Slot availability check error: ' . $available->get_error_message(), 'BOOKING' );
                    // allow booking when we can't verify
                } elseif ( ! $available ) {
                    $requested = ( new DateTime( $data['start_datetime'], wp_timezone() ) )->format( DateTime::ATOM );
                    self::debug_log( 'Requested slot ' . $requested . ' is already occupied', 'BOOKING' );
                    return new WP_Error( 'slot_taken', __( 'Requested time slot is no longer available', 'simple-booking' ) );
                } else {
                    self::debug_log( 'Requested slot is free', 'BOOKING' );
                }
            } else {
                self::debug_log( 'Google Calendar not available - skipping slot availability check', 'BOOKING' );
            }
        }

        // Create booking post
        $booking_id = Simple_Booking_Post::create( $data );

        if ( is_wp_error( $booking_id ) ) {
            return $booking_id;
        }

        // Try to create Google Calendar event
        $google_event_result = self::create_google_event( $data );

        if ( ! is_wp_error( $google_event_result ) && ! empty( $google_event_result ) ) {
            if ( is_array( $google_event_result ) ) {
                if ( ! empty( $google_event_result['event_id'] ) ) {
                    update_post_meta( $booking_id, '_google_event_id', sanitize_text_field( $google_event_result['event_id'] ) );
                }
                if ( ! empty( $google_event_result['meeting_link'] ) ) {
                    update_post_meta( $booking_id, '_meeting_link', esc_url_raw( $google_event_result['meeting_link'] ) );
                }
            } else {
                update_post_meta( $booking_id, '_google_event_id', sanitize_text_field( $google_event_result ) );
            }
        }

        // Send webhook notification (non-blocking, failures won't affect booking)
        if ( class_exists( 'Simple_Booking_Booking_Webhook' ) ) {
            $webhook_result = Simple_Booking_Booking_Webhook::send_booking_created( $data );
            if ( is_wp_error( $webhook_result ) ) {
                self::debug_log( 'Webhook failed: ' . $webhook_result->get_error_message(), 'BOOKING' );
            }
        }

        return $booking_id;
    }

    /**
     * Create Google Calendar event
     */
    public static function create_google_event( $booking_data ) {
        self::debug_log( '=== Booking Creator: create_google_event START ===', 'BOOKING' );
        self::debug_log( 'Booking data: ' . json_encode( $booking_data ), 'BOOKING' );

        // Check if service has Google event creation enabled
        if ( isset( $booking_data['service_id'] ) ) {
            $service_id = absint( $booking_data['service_id'] );
            $create_google_event = get_post_meta( $service_id, '_create_google_event', true );
            
            // Default to '1' (enabled) if not set for backward compatibility
            if ( '' === $create_google_event ) {
                $create_google_event = '1';
            }
            
            if ( '1' !== $create_google_event ) {
                self::debug_log( 'Google Calendar event creation disabled for this service - skipping', 'BOOKING' );
                self::debug_log( '=== Booking Creator: create_google_event END ===', 'BOOKING' );
                return ''; // Return empty string to indicate event creation was skipped
            }
        }

        // Check if Google Calendar class is available
        if ( ! class_exists( 'Simple_Booking_Google_Calendar' ) ) {
            self::debug_log( 'Google Calendar class not available - skipping event creation', 'BOOKING' );
            self::debug_log( '=== Booking Creator: create_google_event END ===', 'BOOKING' );
            return ''; // Return empty string to indicate no event was created
        }

        $google = new Simple_Booking_Google_Calendar();

        $is_connected = $google->is_connected();
        self::debug_log( 'Google is_connected(): ' . ( $is_connected ? 'true' : 'false' ), 'BOOKING' );

        if ( ! $is_connected ) {
            self::debug_log( 'ERROR: Google Calendar not connected', 'BOOKING' );
            return new WP_Error( 'not_connected', __( 'Google Calendar not connected', 'simple-booking' ) );
        }

        $result = $google->create_event( $booking_data );

        if ( is_wp_error( $result ) ) {
            self::debug_log( 'ERROR: create_event returned WP_Error: ' . $result->get_error_code() . ' - ' . $result->get_error_message(), 'BOOKING' );
        } elseif ( empty( $result ) ) {
            self::debug_log( 'WARNING: create_event returned empty (no event ID)', 'BOOKING' );
        } elseif ( is_array( $result ) ) {
            self::debug_log( 'SUCCESS: Google event ID: ' . ( isset( $result['event_id'] ) ? $result['event_id'] : '' ), 'BOOKING' );
            if ( ! empty( $result['meeting_link'] ) ) {
                self::debug_log( 'SUCCESS: Google Meet link generated', 'BOOKING' );
            }
        } else {
            self::debug_log( 'SUCCESS: Google event ID: ' . $result, 'BOOKING' );
        }

        self::debug_log( '=== Booking Creator: create_google_event END ===', 'BOOKING' );
        return $result;
    }

    /**
     * Send confirmation email
     */
    public static function send_confirmation_email( $booking_id ) {
        $to = get_post_meta( $booking_id, '_customer_email', true );
        if ( empty( $to ) ) {
            return;
        }

        $customer_name   = get_post_meta( $booking_id, '_customer_name', true );
        $service_id      = get_post_meta( $booking_id, '_service_id', true );
        $start_datetime = get_post_meta( $booking_id, '_start_datetime', true );
        $end_datetime   = get_post_meta( $booking_id, '_end_datetime', true );

        $service = get_post( $service_id );
        $service_name = $service ? $service->post_title : '';
        $meeting_link = get_post_meta( $booking_id, '_meeting_link', true );
        if ( empty( $meeting_link ) ) {
            $meeting_link = get_post_meta( $service_id, '_meeting_link', true );
        }

        $timezone = wp_timezone_string();

        // Parse datetime for template variables (fail-safe)
        $booking_date = $start_datetime;
        $booking_time = $start_datetime;
        try {
            $timezone_obj = wp_timezone();
            $start_dt = new DateTime( $start_datetime, $timezone_obj );
            $booking_date = $start_dt->format( 'F j, Y' ); // e.g., "March 6, 2026"
            $booking_time = $start_dt->format( 'g:i A' );  // e.g., "2:30 PM"
        } catch ( Exception $e ) {
            self::debug_log( 'Email template datetime parse failed: ' . $e->getMessage(), 'EMAIL' );
        }

        // Get custom templates from settings (or use defaults)
        $email_subject = simple_booking()->get_setting( 'email_subject', '' );
        $email_body = simple_booking()->get_setting( 'email_body', '' );

        // Default templates if not set
        if ( empty( $email_subject ) ) {
            $email_subject = get_bloginfo( 'name' ) . ' - Booking Confirmed';
        }

        if ( empty( $email_body ) ) {
            $meeting_info = '';
            if ( ! empty( $meeting_link ) ) {
                $meeting_info = sprintf( "\n\nMeeting Link:\n%s", $meeting_link );
            }

            $email_body = sprintf(
                "Dear %s,\n\nYour booking has been confirmed!\n\nService: %s\nStart: %s\nEnd: %s\nTimezone: %s%s\n\nThank you for your booking.\n\n%s",
                $customer_name,
                $service_name,
                $start_datetime,
                $end_datetime,
                $timezone,
                $meeting_info,
                get_bloginfo( 'name' )
            );
        } else {
            // Replace template variables
            $variables = array(
                '{customer_name}' => $customer_name,
                '{service_name}'  => $service_name,
                '{booking_date}'  => $booking_date,
                '{booking_time}'  => $booking_time,
                '{meeting_link}'  => ! empty( $meeting_link ) ? $meeting_link : '',
                '{timezone}'      => $timezone,
                '{site_name}'     => get_bloginfo( 'name' ),
            );

            $email_subject = str_replace( array_keys( $variables ), array_values( $variables ), $email_subject );
            $email_body = str_replace( array_keys( $variables ), array_values( $variables ), $email_body );

            // Remove meeting link line if empty
            if ( empty( $meeting_link ) ) {
                $email_body = preg_replace( '/Meeting Link:\s*\n/', '', $email_body );
            }
        }

        // Final guardrails: never send empty subject/body
        if ( empty( trim( $email_subject ) ) ) {
            $email_subject = get_bloginfo( 'name' ) . ' - Booking Confirmed';
        }
        if ( empty( trim( $email_body ) ) ) {
            $email_body = sprintf(
                "Dear %s,\n\nYour booking has been confirmed!\n\nService: %s\nStart: %s\nEnd: %s\nTimezone: %s\n\nThank you for your booking.\n\n%s",
                $customer_name,
                $service_name,
                $start_datetime,
                $end_datetime,
                $timezone,
                get_bloginfo( 'name' )
            );
        }

        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
        );

        wp_mail( $to, $email_subject, $email_body, $headers );
    }
}
