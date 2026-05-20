<?php
/**
 * Admin Dashboard Widgets
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Simple_Booking_Dashboard_Widget {

    /**
     * Register hooks
     */
    public static function register() {
        add_action( 'wp_dashboard_setup', array( __CLASS__, 'add_dashboard_widgets' ) );
    }

    /**
     * Add the widget
     */
    public static function add_dashboard_widgets() {
        wp_add_dashboard_widget(
            'simple_booking_mrr_widget',
            __( 'Simple Booking Analytics', 'simple-booking' ),
            array( __CLASS__, 'render_widget' )
        );
    }

    /**
     * Render the widget content
     */
    public static function render_widget() {
        // 1. Get Member Counts
        $active_members_query = new WP_Query( array(
            'post_type'      => 'booking_membership',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'   => '_status',
                    'value' => 'active',
                ),
            ),
        ) );
        $active_count = $active_members_query->found_posts;

        $waitlist_members_query = new WP_Query( array(
            'post_type'      => 'booking_membership',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'   => '_status',
                    'value' => 'waitlist',
                ),
            ),
        ) );
        $waitlist_count = $waitlist_members_query->found_posts;

        // 2. Calculate MRR
        $mrr_formatted = self::get_cached_mrr();

        ?>
        <div class="sb-analytics-dashboard" style="display: flex; gap: 20px; flex-wrap: wrap;">
            <div class="sb-stat-box" style="flex: 1; min-width: 120px; background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 4px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                <h4 style="margin: 0 0 10px 0; color: #646970; font-weight: 500; font-size: 14px;"><?php _e('Active Members', 'simple-booking'); ?></h4>
                <div style="font-size: 32px; font-weight: 600; color: #2271b1;"><?php echo esc_html( $active_count ); ?></div>
            </div>

            <div class="sb-stat-box" style="flex: 1; min-width: 120px; background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 4px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                <h4 style="margin: 0 0 10px 0; color: #646970; font-weight: 500; font-size: 14px;"><?php _e('Waitlist', 'simple-booking'); ?></h4>
                <div style="font-size: 32px; font-weight: 600; color: #d63638;"><?php echo esc_html( $waitlist_count ); ?></div>
            </div>

            <div class="sb-stat-box" style="flex: 1; min-width: 120px; background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 4px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                <h4 style="margin: 0 0 10px 0; color: #646970; font-weight: 500; font-size: 14px;"><?php _e('Estimated MRR', 'simple-booking'); ?></h4>
                <div style="font-size: 32px; font-weight: 600; color: #00a32a;"><?php echo esc_html( $mrr_formatted ); ?></div>
            </div>
        </div>
        <p style="margin-top: 15px; color: #646970; font-style: italic; font-size: 12px;">
            <?php _e('MRR is estimated by calculating the Stripe price of all active subscriptions. It caches for 1 hour.', 'simple-booking'); ?>
        </p>

        <div class="sb-recent-activity" style="margin-top: 25px; border-top: 1px solid #ccd0d4; padding-top: 15px;">
            <h4 style="margin: 0 0 10px 0; font-size: 14px;"><?php _e('Recent Email Activity', 'simple-booking'); ?></h4>
            <?php 
            $logs = get_option( 'simple_booking_email_log', array() );
            if ( ! empty( $logs ) ) : ?>
                <table class="wp-list-table widefat fixed striped" style="border: none; box-shadow: none;">
                    <thead>
                        <tr>
                            <th style="padding-left: 0;"><?php _e('Time', 'simple-booking'); ?></th>
                            <th><?php _e('Recipient', 'simple-booking'); ?></th>
                            <th><?php _e('Type', 'simple-booking'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( array_slice( $logs, 0, 5 ) as $log ) : ?>
                            <tr>
                                <td style="padding-left: 0; color: #646970; font-size: 11px;"><?php echo date( 'M j, H:i', strtotime( $log['time'] ) ); ?></td>
                                <td style="font-size: 11px;"><?php echo esc_html( $log['to'] ); ?></td>
                                <td>
                                    <span class="status-tag" style="padding: 2px 6px; border-radius: 3px; font-size: 10px; background: <?php echo $log['type'] === 'welcome' ? '#e7f5ec' : '#f0f6fb'; ?>; color: <?php echo $log['type'] === 'welcome' ? '#00a32a' : '#2271b1'; ?>;">
                                        <?php echo esc_html( ucfirst( $log['type'] ) ); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p style="font-size: 12px; color: #646970;"><?php _e('No email activity logged yet.', 'simple-booking'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Get MRR using caching to prevent hitting Stripe API limits
     */
    private static function get_cached_mrr() {
        $transient_key = 'simple_booking_mrr_cache';
        $cached_mrr = get_transient( $transient_key );

        if ( false !== $cached_mrr ) {
            return $cached_mrr;
        }

        $mrr = self::calculate_mrr();
        
        // Cache for 1 hour
        set_transient( $transient_key, $mrr, HOUR_IN_SECONDS );

        return $mrr;
    }

    /**
     * Calculate MRR by contacting Stripe
     */
    private static function calculate_mrr() {
        if ( ! function_exists( 'simple_booking' ) || ! simple_booking()->is_pro_active() ) {
            return 'Pro Required';
        }

        simple_booking()->load_pro_dependencies();

        if ( ! class_exists( '\Stripe\StripeClient' ) || ! class_exists( 'Simple_Booking_Stripe' ) ) {
            return '$0.00';
        }

        $secret_key = simple_booking()->get_setting( 'stripe_secret_key' );
        if ( empty( $secret_key ) ) {
            return 'Stripe Not Configured';
        }

        try {
            $stripe = new \Stripe\StripeClient( $secret_key );
            
            // Get all active memberships
            $active_members_query = new WP_Query( array(
                'post_type'      => 'booking_membership',
                'posts_per_page' => -1,
                'meta_query'     => array(
                    array(
                        'key'   => '_status',
                        'value' => 'active',
                    ),
                ),
            ) );

            $total_cents = 0;
            $currency = 'usd';

            // To avoid hitting Stripe for every single member, group them by service
            $service_counts = array();
            foreach ( $active_members_query->posts as $membership ) {
                $service_id = get_post_meta( $membership->ID, '_service_id', true );
                if ( $service_id ) {
                    if ( ! isset( $service_counts[ $service_id ] ) ) {
                        $service_counts[ $service_id ] = 0;
                    }
                    $service_counts[ $service_id ]++;
                }
            }

            // Loop through each unique service and fetch the price
            foreach ( $service_counts as $service_id => $count ) {
                $price_id = get_post_meta( $service_id, '_stripe_price_id', true );
                if ( ! empty( $price_id ) ) {
                    $price = $stripe->prices->retrieve( $price_id );
                    if ( $price && isset( $price->unit_amount ) ) {
                        // For MRR, we assume the price is monthly.
                        $total_cents += ( $price->unit_amount * $count );
                        $currency = strtoupper( $price->currency ); // Just use the currency of the last processed item
                    }
                }
            }

            if ( $total_cents > 0 ) {
                $symbol = '$';
                if ( 'EUR' === $currency ) {
                    $symbol = '€';
                } elseif ( 'GBP' === $currency ) {
                    $symbol = '£';
                }
                return $symbol . number_format( $total_cents / 100, 2 );
            }

        } catch ( Exception $e ) {
            return 'Stripe Error';
        }

        return '$0.00';
    }
}
