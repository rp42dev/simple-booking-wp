<?php
/**
 * Stripe Membership Sync & Dunning Cron
 * 
 * Handles daily syncing of active memberships against Stripe 
 * and sends dunning emails for failed payments.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Simple_Booking_Membership_Sync {

    const CRON_HOOK     = 'simple_booking_daily_membership_sync';
    const REMINDER_HOOK = 'simple_booking_check_reminders';

    /**
     * Register cron hooks
     */
    public static function register() {
        add_action( self::CRON_HOOK, array( __CLASS__, 'sync_active_memberships' ) );
        add_action( self::REMINDER_HOOK, array( __CLASS__, 'check_meeting_reminders' ) );
        add_action( 'simple_booking_membership_updated', array( __CLASS__, 'handle_status_change' ), 10, 2 );

        // Register custom cron interval
        add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_intervals' ) );

        // Ensure the daily cron is scheduled
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'daily', self::CRON_HOOK );
        }

        // Ensure the reminder cron is scheduled (every 30 mins)
        if ( ! wp_next_scheduled( self::REMINDER_HOOK ) ) {
            wp_schedule_event( time(), 'thirty_minutes', self::REMINDER_HOOK );
        }
    }

    /**
     * Add custom cron intervals
     */
    public static function add_cron_intervals( $schedules ) {
        $schedules['thirty_minutes'] = array(
            'interval' => 1800,
            'display'  => __( 'Every 30 Minutes', 'simple-booking' )
        );
        return $schedules;
    }

    /**
     * Clear the cron when plugin is deactivated (optional, should ideally be in unregister block)
     */
    public static function unregister() {
        wp_clear_scheduled_hook( self::CRON_HOOK );
        wp_clear_scheduled_hook( self::REMINDER_HOOK );
    }

    /**
     * Core Cron Job: Sync all Active memberships with Stripe
     */
    public static function sync_active_memberships() {
        // Only run if Stripe class is available
        if ( ! class_exists( 'Simple_Booking_Stripe' ) ) {
            return;
        }

        $stripe_handler = new Simple_Booking_Stripe();
        // Since Stripe PHP lib might not be loaded if we didn't init properly
        if ( function_exists( 'simple_booking' ) ) {
            simple_booking()->load_pro_dependencies(); // Ensure stripe is loaded
        }

        // 1. Get all memberships where status is 'active' or 'past_due'
        $args = array(
            'post_type'      => Simple_Booking_Group_Memberships::CPT_MEMBERSHIP,
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'     => '_status',
                    'value'   => array( 'active', 'past_due' ),
                    'compare' => 'IN',
                ),
            ),
        );
        
        $memberships = get_posts( $args );
        
        if ( empty( $memberships ) ) {
            return; // Nothing to sync
        }

        $issues_found = 0;
        $report_details = array();

        foreach ( $memberships as $membership ) {
            $sub_id = get_post_meta( $membership->ID, '_stripe_subscription_id', true );
            $current_status = get_post_meta( $membership->ID, '_status', true );
            
            if ( empty( $sub_id ) ) {
                continue;
            }

            try {
                // Call Stripe API directly to check sub status
                $subscription = \Stripe\Subscription::retrieve( $sub_id );
                
                // Track pending cancellations
                if ( isset( $subscription->cancel_at_period_end ) ) {
                    update_post_meta( $membership->ID, '_cancel_at_period_end', $subscription->cancel_at_period_end ? '1' : '0' );
                }

                if ( $subscription->status !== $current_status ) {
                    // There is a discrepancy! Fix WP DB.
                    update_post_meta( $membership->ID, '_status', sanitize_text_field( $subscription->status ) );
                    
                    // Trigger the hook so dunning emails can fire
                    do_action( 'simple_booking_membership_updated', $membership->ID, $subscription->status );
                    
                    $issues_found++;
                    $customer_name = get_post_meta( $membership->ID, '_customer_name', true );
                    $report_details[] = sprintf( "- %s: Status changed from %s to %s", $customer_name, $current_status, $subscription->status );
                }
            } catch ( \Exception $e ) {
                // Log error or assume sub was deleted directly from Stripe dashboard
                error_log( 'Simple Booking Sync Error: ' . $e->getMessage() );
            }
        }

        // Send Daily Admin Report if issues were found
        if ( $issues_found > 0 ) {
            self::send_admin_daily_report( $issues_found, $report_details );
        }
    }

    /**
     * Send daily digest to admin
     */
    private static function send_admin_daily_report( $count, $details ) {
        $admin_email = simple_booking()->get_setting( 'notification_email', get_option( 'admin_email' ) );
        if ( empty( $admin_email ) ) {
            return;
        }

        $site_name = get_bloginfo( 'name' );
        $subject = sprintf( __( '[%s] Daily Membership Sync Report', 'simple-booking' ), $site_name );
        
        $body = sprintf( __( "The daily Stripe sync has completed.\n\n%d memberships were automatically updated because their status in Stripe did not match WordPress.\n\nDetails:\n%s\n\nIf any payments became 'past_due', the customers have been emailed automatically.", 'simple-booking' ), $count, implode( "\n", $details ) );

        wp_mail( $admin_email, $subject, $body );
    }

    /**
     * Hook listener for when a membership changes status
     */
    public static function handle_status_change( $membership_id, $new_status ) {
        if ( 'past_due' === $new_status ) {
            self::send_dunning_email( $membership_id );
        } elseif ( 'canceled' === $new_status ) {
            self::send_cancellation_email( $membership_id );
        }
    }

    /**
     * Send "Payment Failed" Dunning Email
     */
    private static function send_dunning_email( $membership_id ) {
        $customer_email = get_post_meta( $membership_id, '_customer_email', true );
        $customer_name  = get_post_meta( $membership_id, '_customer_name', true );
        $service_id     = get_post_meta( $membership_id, '_service_id', true );
        
        $service = get_post( $service_id );
        $service_name = $service ? $service->post_title : 'Your Group Membership';

        if ( empty( $customer_email ) ) {
            return;
        }

        $site_name = get_bloginfo( 'name' );
        $subject = sprintf( __( 'Action Required: Payment Failed for %s', 'simple-booking' ), $service_name );
        
        $body = sprintf( __( "Hi %s,\n\nWe were unable to process the latest payment for your %s subscription.\n\nPlease update your payment method to ensure you don't lose access to the group.\n\nYou can update your billing details by contacting us or logging into your billing portal.\n\nThank you,\n%s", 'simple-booking' ), $customer_name, $service_name, $site_name );

        wp_mail( $customer_email, $subject, $body );
    }

    /**
     * Send Cancellation Email
     */
    private static function send_cancellation_email( $membership_id ) {
        $customer_email = get_post_meta( $membership_id, '_customer_email', true );
        $customer_name  = get_post_meta( $membership_id, '_customer_name', true );
        $service_id     = get_post_meta( $membership_id, '_service_id', true );
        
        $service = get_post( $service_id );
        $service_name = $service ? $service->post_title : 'Your Group Membership';

        if ( empty( $customer_email ) ) {
            return;
        }

        $site_name = get_bloginfo( 'name' );
        $subject = sprintf( __( 'Subscription Canceled: %s', 'simple-booking' ), $service_name );
        
        $body = sprintf( __( "Hi %s,\n\nThis is a confirmation that your subscription to %s has been canceled.\n\nIf this was a mistake, or if you would like to rejoin, please visit our website to subscribe again.\n\nThank you,\n%s", 'simple-booking' ), $customer_name, $service_name, $site_name );

        wp_mail( $customer_email, $subject, $body );
    }

    /**
     * Check for meetings starting in the next hour and send reminders
     */
    public static function check_meeting_reminders() {
        self::check_group_reminders();
        self::check_individual_reminders();
    }

    /**
     * Check for group membership reminders
     */
    private static function check_group_reminders() {
        // Use wp_date to respect the WordPress timezone settings instead of server UTC
        $current_day  = strtolower( wp_date( 'l' ) ); // e.g. 'monday'
        $current_time = wp_date( 'H:i' );
        
        // Find all recurring group services
        $services = get_posts( array(
            'post_type'      => Simple_Booking_Service::POST_TYPE,
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'   => '_service_type',
                    'value' => 'recurring_group',
                ),
            ),
        ) );

        if ( empty( $services ) ) {
            return;
        }

        foreach ( $services as $service ) {
            $schedules = get_post_meta( $service->ID, '_meeting_schedules', true );
            
            if ( empty( $schedules ) || ! is_array( $schedules ) ) {
                continue;
            }

            $meeting_time_to_trigger = null;

            foreach ( $schedules as $rule ) {
                if ( empty( $rule['time'] ) ) {
                    continue;
                }

                if ( self::does_rule_match_date( $rule, wp_date( 'Y-m-d' ) ) ) {
                    $meeting_time_to_trigger = $rule['time'];
                    break; // One rule matched for today, no need to check others
                }
            }

            if ( ! $meeting_time_to_trigger ) {
                // No meeting today, check for tomorrow (1-day reminder)
                $tomorrow = date( 'Y-m-d', strtotime( '+1 day' ) );
                foreach ( $schedules as $rule ) {
                    if ( ! empty( $rule['time'] ) && self::does_rule_match_date( $rule, $tomorrow ) ) {
                        self::send_group_reminders( $service->ID, $rule['time'], '1-day', $tomorrow );
                        break;
                    }
                }
                continue;
            }

            // Calculate time difference for the matched time (today)
            $now_ts = strtotime( "today $current_time" );
            $meeting_ts = strtotime( "today $meeting_time_to_trigger" );
            
            // If meeting is exactly in the next hour window (45-75 mins from now)
            $diff_mins = ( $meeting_ts - $now_ts ) / 60;
            
            if ( $diff_mins > 40 && $diff_mins < 80 ) {
                self::send_group_reminders( $service->ID, $meeting_time_to_trigger, '1-hour', wp_date('Y-m-d') );
            }
        }
    }

    /**
     * Check for individual booking reminders
     */
    private static function check_individual_reminders() {
        $bookings = get_posts( array(
            'post_type'      => 'booking',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'   => '_booking_status',
                    'value' => 'confirmed',
                ),
            ),
        ) );

        if ( empty( $bookings ) ) {
            return;
        }

        $now = time();
        foreach ( $bookings as $booking ) {
            $start = get_post_meta( $booking->ID, '_start_datetime', true );
            if ( ! $start ) continue;

            $start_ts = strtotime( $start );
            $diff_mins = ( $start_ts - $now ) / 60;

            // 1-day reminder: ~24 hours ahead
            if ( $diff_mins > 1400 && $diff_mins < 1480 ) {
                self::send_individual_reminder( $booking->ID, '1-day' );
            }
            // 1-hour reminder: ~1 hour ahead
            elseif ( $diff_mins > 40 && $diff_mins < 80 ) {
                self::send_individual_reminder( $booking->ID, '1-hour' );
            }
        }
    }

    /**
     * Send individual reminder
     */
    private static function send_individual_reminder( $booking_id, $type ) {
        $today_key = wp_date( 'Y-m-d' );
        $last_sent = get_post_meta( $booking_id, "_last_reminder_{$type}_date", true );
        
        if ( $last_sent === $today_key ) {
            return;
        }

        $customer_email = get_post_meta( $booking_id, '_customer_email', true );
        $customer_name  = get_post_meta( $booking_id, '_customer_name', true );
        $service_name   = get_the_title( get_post_meta( $booking_id, '_service_id', true ) );
        $meeting_link   = get_post_meta( $booking_id, '_meeting_link', true );
        $start_datetime = get_post_meta( $booking_id, '_start_datetime', true );
        $customer_tz    = get_post_meta( $booking_id, '_customer_timezone', true );

        if ( empty( $customer_email ) ) return;

        // Calculate dual time
        $site_tz = wp_timezone();
        $site_abbr = ( new DateTime( 'now', $site_tz ) )->format( 'T' );
        $base_time = wp_date( 'g:i A', strtotime( $start_datetime ) );
        $time_display = $base_time . ' ' . $site_abbr;

        if ( ! empty( $customer_tz ) ) {
            try {
                $user_tz = new DateTimeZone( $customer_tz );
                $dt = new DateTime( $start_datetime, $site_tz );
                $dt->setTimezone( $user_tz );
                $u_time = $dt->format( 'g:i A' );
                if ( $u_time !== $base_time ) {
                    $time_display .= ' / ' . $u_time . ' Local';
                }
            } catch ( Exception $e ) { }
        }

        if ( '1-day' === $type ) {
            $subject = sprintf( __( 'Reminder: Your appointment tomorrow - %s', 'simple-booking' ), $service_name );
            $body    = sprintf( 
                __( "Hi %s,\n\nJust a reminder that you have a booking for '%s' tomorrow at %s.\n\nWe look forward to seeing you!", 'simple-booking' ), 
                $customer_name, 
                $service_name, 
                $time_display
            );
        } else {
            $subject = sprintf( __( 'Starting Soon: %s', 'simple-booking' ), $service_name );
            $body    = sprintf( 
                __( "Hi %s,\n\nYour session '%s' is starting in about 1 hour (at %s).\n\nJoin here: %s\n\nSee you soon!", 'simple-booking' ), 
                $customer_name, 
                $service_name, 
                $time_display,
                $meeting_link 
            );
        }

        if ( wp_mail( $customer_email, $subject, $body ) ) {
            update_post_meta( $booking_id, "_last_reminder_{$type}_date", $today_key );
            Simple_Booking::log_email( $customer_email, $subject, "1to1-$type" );
        }
    }

    /**
     * Send reminders to all active members of a service
     */
    private static function send_group_reminders( $service_id, $meeting_time, $type = '1-hour', $meeting_date = '' ) {
        if ( empty( $meeting_date ) ) {
            $meeting_date = date( 'Y-m-d' );
        }
        $memberships = get_posts( array(
            'post_type'      => Simple_Booking_Group_Memberships::CPT_MEMBERSHIP,
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'   => '_service_id',
                    'value' => $service_id,
                ),
                array(
                    'key'   => '_status',
                    'value' => 'active',
                ),
            ),
        ) );

        if ( empty( $memberships ) ) {
            return;
        }

        $service_name = get_the_title( $service_id );
        $meeting_link = get_post_meta( $service_id, '_meeting_link', true );

        // Check for specific synced link for this session
        $synced_links = get_post_meta( $service_id, '_synced_session_links', true );
        $session_key  = $meeting_date . '_' . $meeting_time;
        if ( is_array( $synced_links ) && isset( $synced_links[ $session_key ] ) ) {
            $meeting_link = $synced_links[ $session_key ];
        }

        $today_key    = date( 'Y-m-d' );

        foreach ( $memberships as $membership ) {
            // Prevent double-sending for the same day and same type
            $last_sent = get_post_meta( $membership->ID, "_last_reminder_{$type}_date", true );
            if ( $last_sent === $today_key ) {
                continue;
            }

            $customer_email = get_post_meta( $membership->ID, '_customer_email', true );
            $customer_name  = get_post_meta( $membership->ID, '_customer_name', true );

            if ( empty( $customer_email ) ) {
                continue;
            }

            $customer_tz = get_post_meta( $membership->ID, '_customer_timezone', true );
            $time_display = $meeting_time . ' Lux';
            
            if ( ! empty( $customer_tz ) ) {
                try {
                    $site_tz = wp_timezone();
                    $user_tz = new DateTimeZone( $customer_tz );
                    $dt = new DateTime( $meeting_date . ' ' . $meeting_time, $site_tz );
                    $dt->setTimezone( $user_tz );
                    $user_time = $dt->format( 'H:i' );
                    
                    if ( $user_time !== $meeting_time ) {
                        $time_display .= ' / ' . $user_time . ' Local';
                    }
                } catch ( Exception $e ) {
                    // Fallback to site time only
                }
            }

            if ( '1-day' === $type ) {
                $subject = sprintf( __( 'Reminder: %s tomorrow', 'simple-booking' ), $service_name );
                $body    = sprintf( 
                    __( "Hi %s,\n\nJust a reminder that you have a group session '%s' tomorrow at %s.\n\nWe look forward to seeing you there!", 'simple-booking' ), 
                    $customer_name, 
                    $service_name, 
                    $time_display
                );
            } else {
                $subject = sprintf( __( 'Starting Soon: %s', 'simple-booking' ), $service_name );
                $body    = sprintf( 
                    __( "Hi %s,\n\nYour group session '%s' is starting in about 1 hour (at %s).\n\nJoin here: %s\n\nSee you soon!", 'simple-booking' ), 
                    $customer_name, 
                    $service_name, 
                    $time_display,
                    $meeting_link 
                );
            }

            // Attempt to send email up to 3 times
            $sent = false;
            $attempts = 0;
            
            while ( ! $sent && $attempts < 3 ) {
                $sent = wp_mail( $customer_email, $subject, $body );
                $attempts++;
                
                if ( ! $sent && $attempts < 3 ) {
                    sleep( 1 ); // Wait 1 second before retrying
                }
            }

            if ( $sent ) {
                update_post_meta( $membership->ID, "_last_reminder_{$type}_date", $today_key );
                Simple_Booking::log_email( $customer_email, $subject, 'reminder' );
            } else {
                error_log( "Simple Booking: Failed to send $type reminder to $customer_email after 3 attempts." );
            }
        }
    }

    /**
     * Check if a specific date matches a schedule rule
     */
    public static function does_rule_match_date( $rule, $date_ymd ) {
        $check_timestamp = strtotime( $date_ymd );
        $check_day  = strtolower( wp_date( 'l', $check_timestamp ) ); // e.g. 'monday'
        
        $rule_type = isset( $rule['type'] ) ? $rule['type'] : 'week_day';

        if ( 'week_day' === $rule_type ) {
            $rule_day   = isset( $rule['day'] ) ? $rule['day'] : 'monday';
            $rule_weeks = isset( $rule['weeks'] ) ? $rule['weeks'] : array( 'every' );

            if ( $rule_day === $check_day ) {
                if ( in_array( 'every', (array) $rule_weeks, true ) ) {
                    return true;
                } else {
                    $week_num = ceil( wp_date( 'j', $check_timestamp ) / 7 );
                    $is_last = ( wp_date( 'm', $check_timestamp ) !== wp_date( 'm', strtotime( '+1 week', $check_timestamp ) ) );

                    if ( in_array( (string)$week_num, (array)$rule_weeks, true ) ) {
                        return true;
                    }
                    if ( $is_last && in_array( 'last', (array)$rule_weeks, true ) ) {
                        return true;
                    }
                }
            }
        } elseif ( 'date' === $rule_type ) {
            $rule_date = isset( $rule['date'] ) ? $rule['date'] : '1';
            $skip_weekends = isset( $rule['skip_weekends'] ) ? $rule['skip_weekends'] : false;
            
            // Determine the target date for the month of the checked date
            $target_timestamp = null;
            if ( 'last' === $rule_date ) {
                $target_timestamp = strtotime( 'last day of this month', $check_timestamp );
            } else {
                $target_d = (int) $rule_date;
                $days_in_month = (int) wp_date( 't', $check_timestamp );
                if ( $target_d > $days_in_month ) {
                    $target_d = $days_in_month; // auto-round down
                }
                $target_timestamp = strtotime( wp_date( 'Y-m-', $check_timestamp ) . sprintf( "%02d", $target_d ) );
            }

            // Handle weekend skipping
            if ( $skip_weekends ) {
                $day_of_week = (int) wp_date( 'N', $target_timestamp ); // 1 = Mon, 6 = Sat, 7 = Sun
                if ( $day_of_week === 6 ) { // Saturday
                    $target_timestamp = strtotime( '-1 day', $target_timestamp );
                } elseif ( $day_of_week === 7 ) { // Sunday
                    $target_timestamp = strtotime( '-2 days', $target_timestamp );
                }
            }

            if ( wp_date( 'Y-m-d', $check_timestamp ) === wp_date( 'Y-m-d', $target_timestamp ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if ANY group is scheduled at a specific date and time.
     * Used by the Native Blocker to prevent 1-on-1 double bookings.
     */
    public static function is_group_scheduled_at( $date_ymd, $time_hi ) {
        $services = get_posts( array(
            'post_type'      => 'booking_service',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'   => '_service_type',
                    'value' => 'recurring_group',
                ),
            ),
        ) );

        if ( empty( $services ) ) {
            return false;
        }

        foreach ( $services as $service ) {
            $schedules = get_post_meta( $service->ID, '_meeting_schedules', true );
            if ( empty( $schedules ) || ! is_array( $schedules ) ) {
                continue;
            }

            foreach ( $schedules as $rule ) {
                $rule_time = isset( $rule['time'] ) ? $rule['time'] : '18:00';
                
                // For a 60-min group, we should block the exact time slot.
                if ( $rule_time === $time_hi ) {
                    if ( self::does_rule_match_date( $rule, $date_ymd ) ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
