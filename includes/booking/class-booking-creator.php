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
    const MANAGEMENT_TOKEN_META = '_booking_management_token';
    const MANAGEMENT_TOKEN_CREATED_META = '_booking_management_token_created';
    const MANAGEMENT_TOKEN_CONSUMED_META = '_booking_management_token_consumed_at';
    const MANAGEMENT_TOKEN_CONSUMED_ACTION_META = '_booking_management_token_consumed_action';
    const ACTION_CANCEL_EXECUTED_META = '_cancel_action_executed_at';
    const ACTION_RESCHEDULE_EXECUTED_META = '_reschedule_action_executed_at';
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
     * Get active calendar provider instance.
     *
     * @return Simple_Booking_Calendar_Provider_Interface|WP_Error
     */
    private static function get_active_calendar_provider() {
        if ( ! class_exists( 'Simple_Booking_Calendar_Provider_Manager' ) ) {
            return new WP_Error( 'calendar_provider_manager_missing', __( 'Calendar provider manager not available.', 'simple-booking' ) );
        }

        $manager = new Simple_Booking_Calendar_Provider_Manager();
        return $manager->get_provider();
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

            // Check slot availability via active calendar provider
            $provider = self::get_active_calendar_provider();
            if ( is_wp_error( $provider ) ) {
                self::debug_log( 'Calendar provider manager unavailable - skipping slot check: ' . $provider->get_error_message(), 'BOOKING' );
            } else {
                self::debug_log( 'Checking availability with provider: ' . $provider->get_slug(), 'BOOKING' );
                $staff_availability = $provider->find_available_staff( $service_id, $data['start_datetime'], $service_duration );

                if ( false === $staff_availability ) {
                    $requested = ( new DateTime( $data['start_datetime'], wp_timezone() ) )->format( DateTime::ATOM );
                    self::debug_log( 'Requested slot ' . $requested . ' has no available staff', 'BOOKING' );
                    return new WP_Error( 'slot_taken', __( 'Requested time slot is no longer available', 'simple-booking' ) );
                }

                if ( is_wp_error( $staff_availability ) ) {
                    self::debug_log( 'Provider availability check failed; continuing gracefully: ' . $staff_availability->get_error_message(), 'BOOKING' );
                } else {
                    self::debug_log( 'Slot available with staff_id: ' . ( $staff_availability['staff_id'] ?? 'null' ), 'BOOKING' );
                    $data['assigned_staff_id'] = $staff_availability['staff_id'] ?? null;
                    $data['calendar_id'] = $staff_availability['calendar_id'] ?? null;
                }
            }
        }

        // Create booking post
        $booking_id = Simple_Booking_Post::create( $data );

        if ( is_wp_error( $booking_id ) ) {
            return $booking_id;
        }

        // Store assigned staff ID if available
        if ( isset( $data['assigned_staff_id'] ) && ! empty( $data['assigned_staff_id'] ) ) {
            update_post_meta( $booking_id, '_assigned_staff_id', absint( $data['assigned_staff_id'] ) );
        }

        // Record initial meeting link source from payload
        $initial_meeting_source = ! empty( $data['meeting_link'] ) ? 'static' : 'none';
        update_post_meta( $booking_id, '_meeting_link_source', $initial_meeting_source );

        // Try to create Google Calendar event
        $google_event_result = self::create_google_event( $data );

        if ( ! is_wp_error( $google_event_result ) && ! empty( $google_event_result ) ) {
            if ( is_array( $google_event_result ) ) {
                if ( ! empty( $google_event_result['event_id'] ) ) {
                    update_post_meta( $booking_id, '_google_event_id', sanitize_text_field( $google_event_result['event_id'] ) );
                }
                if ( ! empty( $google_event_result['meeting_link'] ) ) {
                    update_post_meta( $booking_id, '_meeting_link', esc_url_raw( $google_event_result['meeting_link'] ) );
                    update_post_meta( $booking_id, '_meeting_link_source', 'generated' );
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

        // Handle reschedule linkage if request was token-authorized
        if ( ! empty( $data['reschedule_from_booking_id'] ) && ! empty( $data['reschedule_token'] ) ) {
            $from_booking_id = absint( $data['reschedule_from_booking_id'] );
            $reschedule_token = sanitize_text_field( $data['reschedule_token'] );

            // Idempotency check: if source booking already has reschedule executed, skip linkage
            if ( self::is_reschedule_action_executed( $from_booking_id ) ) {
                self::debug_log( 'Reschedule linkage skipped for booking ' . $from_booking_id . '; reschedule action already executed', 'BOOKING' );
            } elseif ( self::verify_booking_management_token( $from_booking_id, $reschedule_token ) ) {
                // Preserve original Stripe payment reference for paid reschedules.
                // This ensures later cancellation/refund checks still work on the new booking.
                $source_payment_id = self::get_stripe_payment_id( $from_booking_id );
                $new_payment_id = self::get_stripe_payment_id( $booking_id );
                if ( empty( $new_payment_id ) && ! empty( $source_payment_id ) ) {
                    update_post_meta( $booking_id, '_stripe_payment_id', sanitize_text_field( $source_payment_id ) );
                    self::debug_log( 'Copied stripe_payment_id from booking ' . $from_booking_id . ' to rescheduled booking ' . $booking_id, 'BOOKING' );
                }

                self::delete_google_event_for_booking( $from_booking_id );
                update_post_meta( $from_booking_id, '_booking_status', 'rescheduled' );
                update_post_meta( $from_booking_id, '_rescheduled_to_booking_id', absint( $booking_id ) );
                update_post_meta( $booking_id, '_rescheduled_from_booking_id', absint( $from_booking_id ) );
                self::mark_reschedule_action_executed( $from_booking_id );
                self::consume_management_token( $from_booking_id, 'reschedule' );
                wp_trash_post( $from_booking_id );
            }
        }

        return $booking_id;
    }

    /**
     * Get existing management token or create a new one.
     *
     * @param int $booking_id
     * @return string
     */
    public static function get_or_create_management_token( $booking_id ) {
        $booking_id = absint( $booking_id );
        if ( ! $booking_id ) {
            return '';
        }

        $existing = get_post_meta( $booking_id, self::MANAGEMENT_TOKEN_META, true );
        if ( ! empty( $existing ) ) {
            return $existing;
        }

        $token = wp_generate_password( 48, false, false );
        update_post_meta( $booking_id, self::MANAGEMENT_TOKEN_META, $token );
        update_post_meta( $booking_id, self::MANAGEMENT_TOKEN_CREATED_META, current_time( 'mysql' ) );

        return $token;
    }

    /**
     * Verify booking management token.
     *
     * @param int    $booking_id
     * @param string $token
     * @return bool
     */
    public static function verify_booking_management_token( $booking_id, $token ) {
        $booking_id = absint( $booking_id );
        $token = (string) $token;

        if ( ! $booking_id || '' === $token ) {
            return false;
        }

        $stored = (string) get_post_meta( $booking_id, self::MANAGEMENT_TOKEN_META, true );
        if ( '' === $stored ) {
            return false;
        }

        if ( self::is_management_token_consumed( $booking_id ) ) {
            return false;
        }

        return hash_equals( $stored, $token );
    }

    /**
     * Check whether booking management token has already been consumed.
     *
     * @param int $booking_id
     * @return bool
     */
    public static function is_management_token_consumed( $booking_id ) {
        $booking_id = absint( $booking_id );
        if ( ! $booking_id ) {
            return false;
        }

        $consumed_at = get_post_meta( $booking_id, self::MANAGEMENT_TOKEN_CONSUMED_META, true );
        return ! empty( $consumed_at );
    }

    /**
     * Mark booking management token as consumed after a successful action.
     *
     * @param int    $booking_id
     * @param string $action
     * @return void
     */
    public static function consume_management_token( $booking_id, $action ) {
        $booking_id = absint( $booking_id );
        $action = sanitize_key( $action );

        if ( ! $booking_id || '' === $action ) {
            return;
        }

        if ( self::is_management_token_consumed( $booking_id ) ) {
            return;
        }

        update_post_meta( $booking_id, self::MANAGEMENT_TOKEN_CONSUMED_META, current_time( 'mysql' ) );
        update_post_meta( $booking_id, self::MANAGEMENT_TOKEN_CONSUMED_ACTION_META, $action );
    }

    /**
     * Get consumed management action for a booking token.
     *
     * @param int $booking_id
     * @return string
     */
    public static function get_management_token_consumed_action( $booking_id ) {
        $booking_id = absint( $booking_id );
        if ( ! $booking_id ) {
            return '';
        }

        return sanitize_key( (string) get_post_meta( $booking_id, self::MANAGEMENT_TOKEN_CONSUMED_ACTION_META, true ) );
    }

    /**
     * Check if cancel action has already been executed for this booking.
     *
     * @param int $booking_id
     * @return bool
     */
    public static function is_cancel_action_executed( $booking_id ) {
        $booking_id = absint( $booking_id );
        if ( ! $booking_id ) {
            return false;
        }

        $executed_at = get_post_meta( $booking_id, self::ACTION_CANCEL_EXECUTED_META, true );
        return ! empty( $executed_at );
    }

    /**
     * Mark cancel action as executed for this booking.
     *
     * @param int $booking_id
     * @return void
     */
    public static function mark_cancel_action_executed( $booking_id ) {
        $booking_id = absint( $booking_id );
        if ( ! $booking_id ) {
            return;
        }

        if ( self::is_cancel_action_executed( $booking_id ) ) {
            return;
        }

        update_post_meta( $booking_id, self::ACTION_CANCEL_EXECUTED_META, current_time( 'mysql' ) );
    }

    /**
     * Check if reschedule action has already been executed for this booking.
     *
     * @param int $booking_id
     * @return bool
     */
    public static function is_reschedule_action_executed( $booking_id ) {
        $booking_id = absint( $booking_id );
        if ( ! $booking_id ) {
            return false;
        }

        $executed_at = get_post_meta( $booking_id, self::ACTION_RESCHEDULE_EXECUTED_META, true );
        return ! empty( $executed_at );
    }

    /**
     * Mark reschedule action as executed for this booking.
     *
     * @param int $booking_id
     * @return void
     */
    public static function mark_reschedule_action_executed( $booking_id ) {
        $booking_id = absint( $booking_id );
        if ( ! $booking_id ) {
            return;
        }

        if ( self::is_reschedule_action_executed( $booking_id ) ) {
            return;
        }

        update_post_meta( $booking_id, self::ACTION_RESCHEDULE_EXECUTED_META, current_time( 'mysql' ) );
    }

    /**
     * Build booking management URL.
     *
     * @param int    $booking_id
     * @param string $action cancel|reschedule
     * @param string $token
     * @return string
     */
    public static function get_management_url( $booking_id, $action, $token ) {
        $booking_id = absint( $booking_id );
        $action = sanitize_key( $action );
        $token = sanitize_text_field( $token );

        return add_query_arg(
            array(
                'sb_action'   => $action,
                'booking_id'  => $booking_id,
                'sb_token'    => $token,
            ),
            home_url( '/' )
        );
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

        $provider = self::get_active_calendar_provider();
        if ( is_wp_error( $provider ) ) {
            self::debug_log( 'Calendar provider manager error: ' . $provider->get_error_message(), 'BOOKING' );
            self::debug_log( '=== Booking Creator: create_google_event END ===', 'BOOKING' );
            return '';
        }

        self::debug_log( 'Active calendar provider: ' . $provider->get_slug(), 'BOOKING' );

        $is_connected = $provider->is_connected();
        self::debug_log( 'Provider is_connected(): ' . ( $is_connected ? 'true' : 'false' ), 'BOOKING' );

        if ( ! $is_connected ) {
            self::debug_log( 'ERROR: Active calendar provider not connected', 'BOOKING' );
            return new WP_Error( 'calendar_not_connected', __( 'Calendar provider not connected', 'simple-booking' ) );
        }

        $result = $provider->create_event( $booking_data );

        if ( is_wp_error( $result ) ) {
            self::debug_log( 'ERROR: create_event returned WP_Error: ' . $result->get_error_code() . ' - ' . $result->get_error_message(), 'BOOKING' );
        } elseif ( empty( $result ) ) {
            self::debug_log( 'WARNING: create_event returned empty (no event ID)', 'BOOKING' );
        } elseif ( is_array( $result ) ) {
            self::debug_log( 'SUCCESS: Calendar event ID: ' . ( isset( $result['event_id'] ) ? $result['event_id'] : '' ), 'BOOKING' );
            if ( ! empty( $result['meeting_link'] ) ) {
                self::debug_log( 'SUCCESS: Google Meet link generated', 'BOOKING' );
            }
        } else {
            self::debug_log( 'SUCCESS: Calendar event ID: ' . $result, 'BOOKING' );
        }

        self::debug_log( '=== Booking Creator: create_google_event END ===', 'BOOKING' );
        return $result;
    }

    /**
     * Resolve calendar ID for an existing booking.
     *
     * @param int $booking_id
     * @return string
     */
    private static function get_booking_calendar_id( $booking_id ) {
        $booking_id = absint( $booking_id );
        $calendar_id = simple_booking()->get_setting( 'google_calendar_id' );

        $assigned_staff_id = absint( get_post_meta( $booking_id, '_assigned_staff_id', true ) );
        if ( $assigned_staff_id ) {
            $staff_calendar_id = get_post_meta( $assigned_staff_id, '_staff_calendar_id', true );
            if ( ! empty( $staff_calendar_id ) ) {
                $calendar_id = $staff_calendar_id;
            }
        }

        return $calendar_id;
    }

    /**
     * Delete Google event associated with a booking.
     *
     * @param int $booking_id
     * @return true|WP_Error
     */
    public static function delete_google_event_for_booking( $booking_id ) {
        $booking_id = absint( $booking_id );
        if ( ! $booking_id ) {
            return new WP_Error( 'invalid_booking', __( 'Invalid booking ID', 'simple-booking' ) );
        }

        $event_id = get_post_meta( $booking_id, '_google_event_id', true );
        if ( empty( $event_id ) ) {
            return true;
        }

        $provider = self::get_active_calendar_provider();
        if ( is_wp_error( $provider ) ) {
            self::debug_log( 'Calendar provider manager error on delete: ' . $provider->get_error_message(), 'BOOKING' );
            return true;
        }

        $calendar_id = self::get_booking_calendar_id( $booking_id );
        $deleted = $provider->delete_event( $event_id, array( 'calendar_id' => $calendar_id ) );

        if ( is_wp_error( $deleted ) ) {
            self::debug_log( 'Failed to delete calendar event for booking ' . $booking_id . ': ' . $deleted->get_error_message(), 'BOOKING' );
            return $deleted;
        }

        delete_post_meta( $booking_id, '_google_event_id' );
        delete_post_meta( $booking_id, '_meeting_link' );

        return true;
    }

    /**
     * Resolve booking ID to the latest booking in a reschedule chain.
     *
     * @param int $booking_id
     * @return int
     */
    private static function resolve_latest_rescheduled_booking_id( $booking_id ) {
        $current_id = absint( $booking_id );
        if ( ! $current_id ) {
            return 0;
        }

        $visited = array();
        while ( $current_id && ! in_array( $current_id, $visited, true ) ) {
            $visited[] = $current_id;
            $next_id = absint( get_post_meta( $current_id, '_rescheduled_to_booking_id', true ) );

            if ( ! $next_id ) {
                break;
            }

            $current_id = $next_id;
        }

        return $current_id;
    }

    /**
     * Cancel a booking by token and remove external calendar event.
     *
     * @param int    $booking_id
     * @param string $token
     * @return true|WP_Error
     */
    public static function cancel_booking( $booking_id, $token ) {
        $booking_id = absint( $booking_id );
        $token = sanitize_text_field( $token );

        // Idempotency check: if cancel was already successfully executed, reject immediately
        if ( self::is_cancel_action_executed( $booking_id ) ) {
            self::debug_log( 'Cancel blocked for booking ' . $booking_id . '; cancel action already executed', 'BOOKING' );
            return new WP_Error( 'already_cancelled', __( 'This booking has already been cancelled', 'simple-booking' ) );
        }

        if ( self::is_management_token_consumed( $booking_id ) ) {
            return new WP_Error( 'link_used', __( 'This booking management link has already been used', 'simple-booking' ) );
        }

        if ( ! self::verify_booking_management_token( $booking_id, $token ) ) {
            return new WP_Error( 'invalid_token', __( 'Invalid booking management token', 'simple-booking' ) );
        }

        $target_booking_id = self::resolve_latest_rescheduled_booking_id( $booking_id );
        if ( ! $target_booking_id ) {
            return new WP_Error( 'invalid_booking', __( 'Invalid booking ID', 'simple-booking' ) );
        }

        if ( $target_booking_id !== $booking_id ) {
            self::debug_log( 'Cancel requested for rescheduled-from booking ' . $booking_id . '; using latest booking ' . $target_booking_id, 'BOOKING' );
            $booking_id = $target_booking_id;
        }

        // Check if booking is already cancelled to prevent duplicate refunds
        $booking_status = get_post_meta( $booking_id, '_booking_status', true );
        if ( 'cancelled' === $booking_status ) {
            self::debug_log( 'Cancel blocked for booking ' . $booking_id . '; already cancelled', 'BOOKING' );
            return new WP_Error( 'already_cancelled', __( 'This booking has already been cancelled', 'simple-booking' ) );
        }

        // Log cancellation start
        $stripe_payment_id = self::get_stripe_payment_id( $booking_id );
        self::debug_log( 'Cancellation initiated for booking ' . $booking_id . '; stripe_payment_id=' . ( $stripe_payment_id ?: 'EMPTY' ), 'BOOKING' );

        // Process refund if booking was paid
        $is_paid = self::is_paid_booking( $booking_id );
        self::debug_log( 'is_paid_booking(' . $booking_id . ') returned: ' . ( $is_paid ? 'TRUE' : 'FALSE' ), 'BOOKING' );

        if ( $is_paid ) {
            $refund_result = self::refund_booking( $booking_id );
            if ( is_wp_error( $refund_result ) ) {
                self::debug_log( 'Refund failed during cancel: ' . $refund_result->get_error_message(), 'BOOKING' );
                // Continue with cancellation even if refund fails
            }
        }

        self::delete_google_event_for_booking( $booking_id );
        update_post_meta( $booking_id, '_booking_status', 'cancelled' );
        update_post_meta( $booking_id, '_booking_cancelled_at', current_time( 'mysql' ) );
        self::mark_cancel_action_executed( $booking_id );
        self::consume_management_token( $booking_id, 'cancel' );
        wp_trash_post( $booking_id );

        return true;
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
        $management_token = self::get_or_create_management_token( $booking_id );
        $reschedule_link = self::get_management_url( $booking_id, 'reschedule', $management_token );
        $cancel_link = self::get_management_url( $booking_id, 'cancel', $management_token );

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
                "Dear %s,\n\nYour booking has been confirmed!\n\nService: %s\nStart: %s\nEnd: %s\nTimezone: %s%s\n\nManage your booking:\nReschedule: %s\nCancel: %s\n\nThank you for your booking.\n\n%s",
                $customer_name,
                $service_name,
                $start_datetime,
                $end_datetime,
                $timezone,
                $meeting_info,
                $reschedule_link,
                $cancel_link,
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
                '{reschedule_link}' => $reschedule_link,
                '{cancel_link}'   => $cancel_link,
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

    /**
     * Check if a booking is paid (has Stripe payment ID)
     *
     * @param int $booking_id
     * @return bool
     */
    public static function is_paid_booking( $booking_id ) {
        $booking_id = absint( $booking_id );
        if ( ! $booking_id ) {
            return false;
        }
        $stripe_payment_id = get_post_meta( $booking_id, '_stripe_payment_id', true );
        return ! empty( $stripe_payment_id );
    }

    /**
     * Get Stripe payment ID for a booking
     *
     * @param int $booking_id
     * @return string|null
     */
    public static function get_stripe_payment_id( $booking_id ) {
        $booking_id = absint( $booking_id );
        if ( ! $booking_id ) {
            return null;
        }
        return get_post_meta( $booking_id, '_stripe_payment_id', true );
    }

    /**
     * Refund a paid booking
     *
     * @param int $booking_id
     * @return true|WP_Error
     */
    public static function refund_booking( $booking_id ) {
        $booking_id = absint( $booking_id );
        if ( ! $booking_id ) {
            return new WP_Error( 'invalid_booking_id', __( 'Invalid booking ID', 'simple-booking' ) );
        }

        $stripe_payment_id = self::get_stripe_payment_id( $booking_id );
        if ( ! $stripe_payment_id ) {
            self::debug_log( 'Booking ' . $booking_id . ' is not paid; no refund needed', 'BOOKING' );
            return true; // Not a paid booking, no refund needed
        }

        // Get refund percentage setting
        $refund_percentage = intval( simple_booking()->get_setting( 'refund_percentage', 100 ) );
        $refund_percentage = min( 100, max( 0, $refund_percentage ) );

        if ( 0 === $refund_percentage ) {
            self::debug_log( 'Refund percentage is 0%; not issuing refund for booking ' . $booking_id, 'BOOKING' );
            update_post_meta( $booking_id, '_refund_status', 'skipped_zero_percentage' );
            return true; // Refunds disabled (0%)
        }

        try {
            // Check if Stripe is configured
            if ( ! class_exists( 'Simple_Booking_Stripe' ) ) {
                self::debug_log( 'Stripe Handler not available; cannot refund booking ' . $booking_id, 'BOOKING' );
                return new WP_Error( 'stripe_handler_missing', __( 'Stripe integration not available', 'simple-booking' ) );
            }

            $stripe_handler = new Simple_Booking_Stripe();
            $refund_result = $stripe_handler->issue_refund( $stripe_payment_id, $refund_percentage );

            if ( is_wp_error( $refund_result ) ) {
                self::debug_log( 'Refund failed for booking ' . $booking_id . ': ' . $refund_result->get_error_message(), 'BOOKING' );
                update_post_meta( $booking_id, '_refund_status', 'failed' );
                update_post_meta( $booking_id, '_refund_error', $refund_result->get_error_message() );
                return $refund_result;
            }

            self::debug_log( 'Refund successful for booking ' . $booking_id . ': ' . $refund_result, 'BOOKING' );
            update_post_meta( $booking_id, '_refund_status', 'completed' );
            update_post_meta( $booking_id, '_refund_id', $refund_result );
            return true;
        } catch ( Exception $e ) {
            self::debug_log( 'Exception during refund for booking ' . $booking_id . ': ' . $e->getMessage(), 'BOOKING' );
            return new WP_Error( 'refund_exception', $e->getMessage() );
        }
    }}