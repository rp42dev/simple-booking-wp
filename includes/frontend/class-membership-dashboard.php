<?php
/**
 * Frontend Membership Dashboard
 * 
 * Provides the [simple_booking_dashboard] shortcode for users to view
 * their active memberships, schedules, and manage billing.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Simple_Booking_Membership_Dashboard {

    /**
     * Shortcode tag
     */
    const SHORTCODE = 'simple_booking_dashboard';

    /**
     * Constructor
     */
    public function __construct() {
        add_shortcode( self::SHORTCODE, array( $this, 'render_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
    }

    /**
     * Enqueue styles
     */
    public function enqueue_styles() {
        // We will inline some basic premium styles if the shortcode is present
        // or we can reuse booking-form.css. Let's just output it in the shortcode for simplicity.
    }

    /**
     * Render the shortcode
     */
    public function render_shortcode( $atts ) {
        if ( ! is_user_logged_in() ) {
            return $this->render_login_prompt();
        }

        $current_user = wp_get_current_user();
        $site_tz_obj = wp_timezone();
        $site_tz_label = $site_tz_obj->getName(); // Fallback label
        // Try to get a short abbreviation (like CET)
        $now_site = new DateTime( 'now', $site_tz_obj );
        $site_abbr = $now_site->format( 'T' );
        
        // Output the conversion script once
        ?>
        <script>
        function sbConvertTimezones() {
            const siteAbbr = "<?php echo esc_js( $site_abbr ); ?>";
            document.querySelectorAll('.sb-session-time').forEach(function(el) {
                const ts = parseInt(el.getAttribute('data-ts'), 10);
                if (!ts) return;
                
                const date = new Date(ts * 1000);
                const options = { weekday: 'long', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' };
                const localStr = date.toLocaleString(undefined, options);
                
                // Show both if different
                const siteStr = el.getAttribute('data-site-time');
                if (siteStr && !el.classList.contains('converted')) {
                    el.innerHTML = '📅 ' + localStr + ' <span style="font-size:0.8em; opacity:0.7;">(' + siteStr + ' ' + siteAbbr + ')</span>';
                    el.classList.add('converted');
                }
            });

            // Start Countdowns for all memberships
            function updateAllCountdowns() {
                const now = new Date().getTime();
                document.querySelectorAll('.sb-countdown').forEach(function(countdownEl) {
                    const targetTs = parseInt(countdownEl.getAttribute('data-ts'), 10) * 1000;
                    const diff = targetTs - now;
                    
                    if (diff <= 0) {
                        countdownEl.innerHTML = "Session is Live!";
                        // Force refresh join button state in this card
                        const card = countdownEl.closest('.sb-membership-card');
                        if (card) {
                            const joinBtn = card.querySelector('.sb-join-button');
                            if (joinBtn) joinBtn.style.display = 'inline-block';
                        }
                        return;
                    }
                    
                    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const mins = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                    const secs = Math.floor((diff % (1000 * 60)) / 1000);
                    
                    let timeStr = "";
                    if (days > 0) timeStr += days + "d ";
                    timeStr += hours.toString().padStart(2, '0') + "h " + 
                              mins.toString().padStart(2, '0') + "m " + 
                              secs.toString().padStart(2, '0') + "s";
                    
                    countdownEl.innerHTML = timeStr;
                });
            }
            
            setInterval(updateAllCountdowns, 1000);
            updateAllCountdowns();
        }
        document.addEventListener('DOMContentLoaded', sbConvertTimezones);
        window.addEventListener('load', sbConvertTimezones);
        // Call immediately in case DOM is already ready
        sbConvertTimezones();
        </script>
        <?php

        // Find memberships matching this user's email
        $memberships = get_posts( array(
            'post_type'      => Simple_Booking_Group_Memberships::CPT_MEMBERSHIP,
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'     => '_customer_email',
                    'value'   => $current_user->user_email,
                ),
                array(
                    'key'     => '_status',
                    'value'   => array( 'active', 'past_due' ),
                    'compare' => 'IN',
                ),
            ),
        ) );

        ob_start();
        $this->render_css();

        echo '<div class="sb-dashboard-wrapper">';
        
        if ( empty( $memberships ) ) {
            echo '<div class="sb-dashboard-empty">';
            echo '<h2>' . __( 'Welcome back!', 'simple-booking' ) . '</h2>';
            echo '<p>' . __( 'You do not have any active group memberships at the moment.', 'simple-booking' ) . '</p>';
            echo '</div>';
        } else {
            echo '<h2 class="sb-dashboard-title">' . __( 'Your Active Memberships', 'simple-booking' ) . '</h2>';
            echo '<div class="sb-dashboard-grid">';

            foreach ( $memberships as $membership ) {
                $this->render_membership_card( $membership );
            }

            echo '</div>';
        }

        echo '</div>';

        return ob_get_clean();
    }

    /**
     * Render a single membership card
     */
    private function render_membership_card( $membership ) {
        $service_id   = get_post_meta( $membership->ID, '_service_id', true );
        $service      = get_post( $service_id );
        $status       = get_post_meta( $membership->ID, '_status', true );
        $schedule     = get_post_meta( $service_id, '_meeting_schedule', true );
        $meeting_link = get_post_meta( $service_id, '_meeting_link', true );
        
        $service_name = $service ? $service->post_title : __( 'Unknown Group', 'simple-booking' );

        $status_label = 'active' === $status ? __( 'Active', 'simple-booking' ) : __( 'Past Due', 'simple-booking' );
        $status_class = 'active' === $status ? 'sb-status-active' : 'sb-status-past-due';

        echo '<div class="sb-dashboard-card">';
        echo '<div class="sb-card-header">';
        echo '<h3>' . esc_html( $service_name ) . '</h3>';
        echo '<span class="sb-status-badge ' . esc_attr( $status_class ) . '">' . esc_html( $status_label ) . '</span>';
        echo '</div>';

        echo '<div class="sb-card-body">';
        if ( ! empty( $schedule ) ) {
            echo '<p class="sb-schedule"><strong>' . __( 'Schedule:', 'simple-booking' ) . '</strong> ' . esc_html( $schedule ) . '</p>';
        }

        $is_canceling = get_post_meta( $membership->ID, '_cancel_at_period_end', true ) === '1';
        if ( $is_canceling && 'active' === $status ) {
            echo '<p class="sb-warning" style="background:#FFF3E0; color:#E65100;">' . __( 'Your subscription is set to cancel at the end of the current billing cycle.', 'simple-booking' ) . '</p>';
        }

        if ( 'past_due' === $status ) {
            echo '<p class="sb-warning">' . __( 'Your last payment failed. Please update your billing details to maintain access.', 'simple-booking' ) . '</p>';
        }

        // Calculate upcoming sessions
        $upcoming = $this->get_upcoming_sessions( $service_id );
        
        if ( ! empty( $upcoming ) ) {
            $next_session = $upcoming[0];
            echo '<div class="sb-next-session-hero" style="margin: 15px 0; padding: 20px; background: #1F3A2E; color: #E8E2D6; border-radius: 8px; text-align: center;">';
            echo '<h4 style="margin: 0 0 5px 0; font-size: 14px; opacity: 0.8; text-transform: uppercase; letter-spacing: 1px;">' . __( 'Next Session Starts In:', 'simple-booking' ) . '</h4>';
            echo '<div class="sb-countdown" style="font-size: 32px; font-weight: bold; font-family: serif; margin-bottom: 10px;" data-ts="' . esc_attr( $next_session['timestamp'] ) . '">--:--:--:--</div>';
            echo '<p class="sb-session-time" style="margin: 0; font-size: 14px; opacity: 0.9;" data-ts="' . esc_attr( $next_session['timestamp'] ) . '" data-site-time="' . esc_attr( wp_date( 'H:i', $next_session['timestamp'] ) ) . '">📅 ' . wp_date( 'l, j M \a\t H:i', $next_session['timestamp'] ) . '</p>';
            echo '</div>';

            echo '<div class="sb-upcoming-sessions-list" style="margin: 15px 0; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">';
            echo '<h4 style="margin: 0 0 10px 0; font-size: 14px;">' . __( 'Future Sessions:', 'simple-booking' ) . '</h4>';
            echo '<ul style="margin: 0; padding: 0; list-style: none; font-size: 13px;">';
            foreach ( array_slice( $upcoming, 1 ) as $session ) {
                $site_time = wp_date( 'H:i', $session['timestamp'] );
                echo '<li class="sb-session-time" style="margin-bottom: 5px;" data-ts="' . esc_attr( $session['timestamp'] ) . '" data-site-time="' . esc_attr( $site_time ) . '">📅 ' . wp_date( 'l, j M \a\t H:i', $session['timestamp'] ) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }

        echo '<div class="sb-card-actions">';
        if ( ! empty( $meeting_link ) ) {
            $next_session_ts = ! empty( $upcoming ) ? $upcoming[0]['timestamp'] : 0;
            $now = time();
            $time_to_start = $next_session_ts - $now;

            // Check for specific synced link for the next session
            if ( ! empty( $upcoming ) ) {
                $next_session = $upcoming[0];
                $session_key = date( 'Y-m-d', $next_session['timestamp'] ) . '_' . $next_session['time'];
                $synced_links = get_post_meta( $service_id, '_synced_session_links', true );
                if ( is_array( $synced_links ) && isset( $synced_links[ $session_key ] ) ) {
                    $meeting_link = $synced_links[ $session_key ];
                }
            }
            
            // Allow joining 10 minutes before (600 seconds)
            $is_joinable = ( $time_to_start <= 600 && $time_to_start > -3600 ); // Allowed 10m before and up to 1h after start
            
            if ( $is_joinable ) {
                echo '<a href="' . esc_url( $meeting_link ) . '" target="_blank" class="sb-btn sb-btn-primary">' . __( 'Join Meeting Now', 'simple-booking' ) . '</a>';
            } else {
                $label = __( 'Join Meeting', 'simple-booking' );
                if ( $next_session_ts ) {
                    $label = sprintf( __( 'Join (Available %s)', 'simple-booking' ), human_time_diff( $next_session_ts - 600, $now ) );
                }
                echo '<button class="sb-btn sb-btn-primary" disabled style="opacity: 0.5; cursor: not-allowed;">' . esc_html( $label ) . '</button>';
            }
        }

        // Generate Billing Portal Link
        $customer_id = get_post_meta( $membership->ID, '_stripe_customer_id', true );
        
        // Ensure pro dependencies are loaded
        if ( function_exists( 'simple_booking' ) ) {
            simple_booking()->load_pro_dependencies();
        }

        if ( $customer_id && class_exists( 'Simple_Booking_Stripe' ) ) {
            $stripe = new Simple_Booking_Stripe();
            $portal_url = $stripe->create_billing_portal_session( $customer_id, get_permalink() );
            if ( ! is_wp_error( $portal_url ) ) {
                echo '<a href="' . esc_url( $portal_url ) . '" class="sb-btn sb-btn-secondary">' . __( 'Manage Billing', 'simple-booking' ) . '</a>';
            } else {
                echo '<p style="color:red; font-size:12px;">Billing Error: ' . esc_html( $portal_url->get_error_message() ) . '</p>';
            }
        } elseif ( ! $customer_id ) {
            echo '<p style="color:orange; font-size:12px;">Missing Stripe Customer ID</p>';
        }
        
        echo '</div>'; // .sb-card-actions
        echo '</div>'; // .sb-card-body
        echo '</div>'; // .sb-dashboard-card
    }

    /**
     * Render login prompt
     */
    private function render_login_prompt() {
        ob_start();
        $this->render_css();
        echo '<div class="sb-dashboard-wrapper sb-login-prompt">';
        echo '<h2>' . __( 'Member Dashboard', 'simple-booking' ) . '</h2>';
        echo '<p>' . __( 'Please log in to view your group memberships and upcoming meetings.', 'simple-booking' ) . '</p>';
        wp_login_form( array( 'redirect' => get_permalink() ) );
        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Render inline CSS for the dashboard to match the premium styling
     */
    private function render_css() {
        ?>
        <style>
            .sb-dashboard-wrapper {
                max-width: 800px;
                margin: 0 auto;
                font-family: var(--e-global-typography-primary-font-family), "Lato", sans-serif;
                color: #2B2B2B;
            }
            .sb-dashboard-title {
                font-family: var(--e-global-typography-primary-font-family), "Playfair Display", serif;
                color: #1F3A2E;
                margin-bottom: 30px;
                font-size: 2.2rem;
            }
            .sb-dashboard-grid {
                display: grid;
                grid-template-columns: 1fr;
                gap: 20px;
            }
            .sb-dashboard-card {
                background: #FFFFFF;
                border: 1px solid #EAE6DF;
                border-radius: 12px;
                padding: 25px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.03);
                transition: transform 0.2s ease, box-shadow 0.2s ease;
            }
            .sb-dashboard-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(0,0,0,0.06);
            }
            .sb-card-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                border-bottom: 1px solid #F5F1E8;
                padding-bottom: 15px;
                margin-bottom: 15px;
            }
            .sb-card-header h3 {
                margin: 0;
                font-size: 1.4rem;
                color: #1F3A2E;
                font-family: "Playfair Display", serif;
            }
            .sb-status-badge {
                padding: 5px 12px;
                border-radius: 20px;
                font-size: 0.85rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .sb-status-active {
                background: #E8F5E9;
                color: #2E7D32;
            }
            .sb-status-past-due {
                background: #FFEBEE;
                color: #C62828;
            }
            .sb-schedule {
                font-size: 1.05rem;
                margin-bottom: 20px;
            }
            .sb-warning {
                color: #C62828;
                font-weight: 600;
                background: #FFEBEE;
                padding: 10px 15px;
                border-radius: 6px;
                margin-bottom: 20px;
            }
            .sb-card-actions {
                display: flex;
                gap: 15px;
                flex-wrap: wrap;
            }
            .sb-btn {
                display: inline-block;
                padding: 12px 24px;
                border-radius: 6px;
                font-weight: 600;
                text-decoration: none;
                transition: all 0.3s ease;
                text-align: center;
            }
            .sb-btn-primary {
                background: #1F3A2E;
                color: #F5F1E8 !important;
            }
            .sb-btn-primary:hover {
                background: #2b503f;
                color: #ffffff !important;
                transform: translateY(-1px);
            }
            .sb-btn-secondary {
                background: transparent;
                color: #1F3A2E !important;
                border: 1px solid #1F3A2E;
            }
            .sb-btn-secondary:hover {
                background: #F5F1E8;
            }
            .sb-login-prompt {
                background: #F5F1E8;
                padding: 40px;
                border-radius: 12px;
                text-align: center;
            }
            .sb-login-prompt h2 {
                color: #1F3A2E;
                font-family: "Playfair Display", serif;
            }
            #loginform {
                max-width: 400px;
                margin: 20px auto 0;
                text-align: left;
            }
            #loginform input[type="text"],
            #loginform input[type="password"] {
                width: 100%;
                padding: 10px;
                margin-bottom: 15px;
                border: 1px solid #ccc;
                border-radius: 4px;
            }
            #loginform input[type="submit"] {
                background: #1F3A2E;
                color: #fff;
                border: none;
                padding: 12px 20px;
                border-radius: 4px;
                cursor: pointer;
                width: 100%;
                font-weight: bold;
            }
        </style>

        </style>
        <?php
    }
    /**
     * Get upcoming sessions for a service
     */
    private function get_upcoming_sessions( $service_id, $limit = 3 ) {
        $schedules = get_post_meta( $service_id, '_meeting_schedules', true );
        if ( ! is_array( $schedules ) || empty( $schedules ) ) {
            return array();
        }

        $upcoming = array();
        $start = new DateTime( 'today', wp_timezone() );
        $end = clone $start;
        $end->modify( '+30 days' );

        $current = clone $start;
        while ( $current <= $end && count( $upcoming ) < $limit ) {
            foreach ( $schedules as $rule ) {
                if ( class_exists( 'Simple_Booking_Membership_Sync' ) ) {
                    if ( Simple_Booking_Membership_Sync::does_rule_match_date( $rule, $current->format( 'Y-m-d' ) ) ) {
                        $time = ! empty( $rule['time'] ) ? $rule['time'] : '18:00';
                        $dt = new DateTime( $current->format( 'Y-m-d' ) . ' ' . $time, wp_timezone() );
                        $ts = $dt->getTimestamp();
                        
                        // Only add if it hasn't passed yet today
                        if ( $ts > ( time() - 3600 ) ) {
                            $upcoming[] = array(
                                'timestamp' => $ts,
                                'time'      => $time
                            );
                        }
                    }
                }
            }
            $current->modify( '+1 day' );
        }

        // Sort by timestamp
        usort( $upcoming, function( $a, $b ) {
            return $a['timestamp'] - $b['timestamp'];
        } );

        return array_slice( $upcoming, 0, $limit );
    }
}
