<?php
/**
 * Booking Post Type
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Simple_Booking_Post
 */
class Simple_Booking_Post {

    /**
     * Post type name
     */
    const POST_TYPE = 'booking';

    /**
     * Register custom post type
     */
    public static function register() {
        register_post_type(
            self::POST_TYPE,
            array(
                'labels'             => array(
                    'name'               => __( 'Bookings', 'simple-booking' ),
                    'singular_name'      => __( 'Booking', 'simple-booking' ),
                    'add_new'            => __( 'Add New', 'simple-booking' ),
                    'add_new_item'       => __( 'Add New Booking', 'simple-booking' ),
                    'edit_item'          => __( 'Edit Booking', 'simple-booking' ),
                    'new_item'           => __( 'New Booking', 'simple-booking' ),
                    'view_item'          => __( 'View Booking', 'simple-booking' ),
                    'search_items'       => __( 'Search Bookings', 'simple-booking' ),
                    'not_found'          => __( 'No bookings found', 'simple-booking' ),
                    'not_found_in_trash' => __( 'No bookings found in Trash', 'simple-booking' ),
                ),
                'public'             => false,
                'show_ui'            => true,
                'show_in_menu'       => 'edit.php?post_type=booking_service',
                'menu_position'      => 30,
                'menu_icon'          => 'dashicons-calendar',
                'supports'           => array( 'title' ),
                'has_archive'        => false,
                'show_in_rest'       => false,
                'register_meta_box_cb' => array( __CLASS__, 'add_meta_boxes' ),
            )
        );

        // Register meta fields
        self::register_meta();

        // Register admin hooks
        self::register_admin_hooks();
    }

    /**
     * Register admin hooks
     */
    private static function register_admin_hooks() {
        add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'add_custom_columns' ) );
        add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'render_custom_columns' ), 10, 2 );
        add_filter( 'manage_edit-' . self::POST_TYPE . '_sortable_columns', array( __CLASS__, 'add_sortable_columns' ) );
        add_action( 'restrict_manage_posts', array( __CLASS__, 'add_admin_filters' ) );
        add_filter( 'parse_query', array( __CLASS__, 'filter_bookings_query' ) );
    }

    /**
     * Add custom columns to booking list
     */
    public static function add_custom_columns( $columns ) {
        // Remove date column
        unset( $columns['date'] );

        // Add custom columns
        $columns['service']        = __( 'Service', 'simple-booking' );
        $columns['customer']       = __( 'Customer', 'simple-booking' );
        $columns['booking_date']   = __( 'Booking Date', 'simple-booking' );
        $columns['meeting_source'] = __( 'Meeting Link Source', 'simple-booking' );
        $columns['payment_status'] = __( 'Payment', 'simple-booking' );

        return $columns;
    }

    /**
     * Render custom columns
     */
    public static function render_custom_columns( $column, $post_id ) {
        switch ( $column ) {
            case 'service':
                $service_id = get_post_meta( $post_id, '_service_id', true );
                if ( $service_id ) {
                    $service = get_post( $service_id );
                    if ( $service ) {
                        echo '<strong>' . esc_html( $service->post_title ) . '</strong>';
                    } else {
                        echo '—';
                    }
                } else {
                    echo '—';
                }
                break;

            case 'customer':
                $customer_name  = get_post_meta( $post_id, '_customer_name', true );
                $customer_email = get_post_meta( $post_id, '_customer_email', true );
                if ( $customer_name ) {
                    echo '<strong>' . esc_html( $customer_name ) . '</strong>';
                    if ( $customer_email ) {
                        echo '<br><small>' . esc_html( $customer_email ) . '</small>';
                    }
                } else {
                    echo '—';
                }
                break;

            case 'booking_date':
                $start_datetime = get_post_meta( $post_id, '_start_datetime', true );
                if ( $start_datetime ) {
                    try {
                        $timezone = wp_timezone();
                        $date     = new DateTime( $start_datetime, $timezone );
                        echo '<strong>' . $date->format( 'M j, Y' ) . '</strong>';
                        echo '<br><small>' . $date->format( 'g:i A' ) . '</small>';
                    } catch ( Exception $e ) {
                        echo esc_html( $start_datetime );
                    }
                } else {
                    echo '—';
                }
                break;

            case 'payment_status':
                $stripe_payment_id = get_post_meta( $post_id, '_stripe_payment_id', true );
                if ( ! empty( $stripe_payment_id ) ) {
                    echo '<span style="color: #46b450;">● ' . __( 'Paid', 'simple-booking' ) . '</span>';
                } else {
                    echo '<span style="color: #00a0d2;">● ' . __( 'Free', 'simple-booking' ) . '</span>';
                }
                break;

            case 'meeting_source':
                $meeting_source = get_post_meta( $post_id, '_meeting_link_source', true );
                if ( 'generated' === $meeting_source ) {
                    echo '<span style="color: #46b450;">● ' . __( 'Generated (Google Meet)', 'simple-booking' ) . '</span>';
                } elseif ( 'static' === $meeting_source ) {
                    echo '<span style="color: #00a0d2;">● ' . __( 'Static Service Link', 'simple-booking' ) . '</span>';
                } else {
                    echo '<span style="color: #666;">● ' . __( 'None', 'simple-booking' ) . '</span>';
                }
                break;
        }
    }

    /**
     * Make columns sortable
     */
    public static function add_sortable_columns( $columns ) {
        $columns['booking_date'] = 'booking_date';
        return $columns;
    }

    /**
     * Register meta fields
     */
    private static function register_meta() {
        // Customer Name
        register_post_meta(
            self::POST_TYPE,
            '_customer_name',
            array(
                'type'         => 'string',
                'single'       => true,
                'show_in_rest' => true,
                'sanitize_callback' => 'sanitize_text_field',
            )
        );

        // Customer Email
        register_post_meta(
            self::POST_TYPE,
            '_customer_email',
            array(
                'type'         => 'string',
                'single'       => true,
                'show_in_rest' => true,
                'sanitize_callback' => 'sanitize_email',
            )
        );

        // Customer Phone
        register_post_meta(
            self::POST_TYPE,
            '_customer_phone',
            array(
                'type'         => 'string',
                'single'       => true,
                'show_in_rest' => true,
                'sanitize_callback' => 'sanitize_text_field',
            )
        );

        // Service ID
        register_post_meta(
            self::POST_TYPE,
            '_service_id',
            array(
                'type'         => 'integer',
                'single'       => true,
                'show_in_rest' => true,
                'sanitize_callback' => 'absint',
            )
        );

        // Start Datetime
        register_post_meta(
            self::POST_TYPE,
            '_start_datetime',
            array(
                'type'         => 'string',
                'single'       => true,
                'show_in_rest' => true,
                'sanitize_callback' => 'sanitize_text_field',
            )
        );

        // End Datetime
        register_post_meta(
            self::POST_TYPE,
            '_end_datetime',
            array(
                'type'         => 'string',
                'single'       => true,
                'show_in_rest' => true,
                'sanitize_callback' => 'sanitize_text_field',
            )
        );

        // Booking Meeting Link
        register_post_meta(
            self::POST_TYPE,
            '_meeting_link',
            array(
                'type'         => 'string',
                'single'       => true,
                'show_in_rest' => true,
                'sanitize_callback' => 'esc_url_raw',
            )
        );

        // Booking Meeting Link Source: generated|static|none
        register_post_meta(
            self::POST_TYPE,
            '_meeting_link_source',
            array(
                'type'         => 'string',
                'single'       => true,
                'show_in_rest' => true,
                'sanitize_callback' => 'sanitize_text_field',
            )
        );

        // Stripe Payment ID
        register_post_meta(
            self::POST_TYPE,
            '_stripe_payment_id',
            array(
                'type'         => 'string',
                'single'       => true,
                'show_in_rest' => true,
                'sanitize_callback' => 'sanitize_text_field',
            )
        );

        // Google Event ID
        register_post_meta(
            self::POST_TYPE,
            '_google_event_id',
            array(
                'type'         => 'string',
                'single'       => true,
                'show_in_rest' => true,
                'sanitize_callback' => 'sanitize_text_field',
            )
        );
    }

    /**
     * Add meta boxes
     */
    public static function add_meta_boxes() {
        add_meta_box(
            'booking_details',
            __( 'Booking Details', 'simple-booking' ),
            array( __CLASS__, 'render_details_meta_box' ),
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    /**
     * Render details meta box
     */
    public static function render_details_meta_box( $post ) {
        // Get values
        $customer_name    = get_post_meta( $post->ID, '_customer_name', true );
        $customer_email   = get_post_meta( $post->ID, '_customer_email', true );
        $customer_phone   = get_post_meta( $post->ID, '_customer_phone', true );
        $service_id       = get_post_meta( $post->ID, '_service_id', true );
        $start_datetime  = get_post_meta( $post->ID, '_start_datetime', true );
        $end_datetime    = get_post_meta( $post->ID, '_end_datetime', true );
        $stripe_payment_id = get_post_meta( $post->ID, '_stripe_payment_id', true );
        $google_event_id  = get_post_meta( $post->ID, '_google_event_id', true );
        $meeting_link_source = get_post_meta( $post->ID, '_meeting_link_source', true );

        // Get service name if available
        $service_name = '';
        if ( $service_id ) {
            $service = get_post( $service_id );
            if ( $service ) {
                $service_name = $service->post_title;
            }
        }

        // Get timezone
        $timezone = wp_timezone_string();
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e( 'Customer Name', 'simple-booking' ); ?></th>
                <td><?php echo esc_html( $customer_name ); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php _e( 'Customer Email', 'simple-booking' ); ?></th>
                <td><?php echo esc_html( $customer_email ); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php _e( 'Customer Phone', 'simple-booking' ); ?></th>
                <td><?php echo esc_html( $customer_phone ); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php _e( 'Service', 'simple-booking' ); ?></th>
                <td><?php echo esc_html( $service_name ); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php _e( 'Start Time', 'simple-booking' ); ?></th>
                <td><?php echo esc_html( $start_datetime . ' (' . $timezone . ')' ); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php _e( 'End Time', 'simple-booking' ); ?></th>
                <td><?php echo esc_html( $end_datetime . ' (' . $timezone . ')' ); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php _e( 'Stripe Payment ID', 'simple-booking' ); ?></th>
                <td><?php echo esc_html( $stripe_payment_id ); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php _e( 'Google Event ID', 'simple-booking' ); ?></th>
                <td><?php echo esc_html( $google_event_id ); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php _e( 'Meeting Link Source', 'simple-booking' ); ?></th>
                <td>
                    <?php
                    if ( 'generated' === $meeting_link_source ) {
                        echo esc_html__( 'Generated (Google Meet)', 'simple-booking' );
                    } elseif ( 'static' === $meeting_link_source ) {
                        echo esc_html__( 'Static Service Link', 'simple-booking' );
                    } else {
                        echo esc_html__( 'None', 'simple-booking' );
                    }
                    ?>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Get booking by Stripe Payment ID
     */
    public static function get_by_payment_id( $payment_id ) {
        $args = array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_query'     => array(
                array(
                    'key'     => '_stripe_payment_id',
                    'value'   => $payment_id,
                    'compare' => '=',
                ),
            ),
        );

        $query = new WP_Query( $args );
        return $query->posts ? $query->posts[0] : null;
    }

    /**
     * Create booking post
     */
    public static function create( $data ) {
        $title = sprintf(
            '%s – %s',
            $data['service_name'],
            $data['customer_name']
        );

        $post_id = wp_insert_post(
            array(
                'post_type'   => self::POST_TYPE,
                'post_title'  => $title,
                'post_status' => 'publish',
            )
        );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        // Save meta
        update_post_meta( $post_id, '_customer_name', sanitize_text_field( $data['customer_name'] ) );
        update_post_meta( $post_id, '_customer_email', sanitize_email( $data['customer_email'] ) );
        update_post_meta( $post_id, '_customer_phone', sanitize_text_field( $data['customer_phone'] ) );
        update_post_meta( $post_id, '_service_id', absint( $data['service_id'] ) );
        update_post_meta( $post_id, '_start_datetime', sanitize_text_field( $data['start_datetime'] ) );
        update_post_meta( $post_id, '_end_datetime', sanitize_text_field( $data['end_datetime'] ) );
        update_post_meta( $post_id, '_stripe_payment_id', sanitize_text_field( $data['stripe_payment_id'] ) );
        if ( ! empty( $data['meeting_link'] ) ) {
            update_post_meta( $post_id, '_meeting_link', esc_url_raw( $data['meeting_link'] ) );
        }

        if ( ! empty( $data['google_event_id'] ) ) {
            update_post_meta( $post_id, '_google_event_id', sanitize_text_field( $data['google_event_id'] ) );
        }

        return $post_id;
    }

    /**
     * Add admin filters
     */
    public static function add_admin_filters( $post_type ) {
        if ( self::POST_TYPE !== $post_type ) {
            return;
        }

        // Service filter
        $services = get_posts( array(
            'post_type'      => 'booking_service',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'post_status'    => 'publish',
        ) );

        if ( ! empty( $services ) ) {
            $selected_service = isset( $_GET['filter_service'] ) ? absint( $_GET['filter_service'] ) : '';
            echo '<select name="filter_service" id="filter-service-dropdown">';
            echo '<option value="">' . __( 'All Services', 'simple-booking' ) . '</option>';
            foreach ( $services as $service ) {
                $stripe_price_id = get_post_meta( $service->ID, '_stripe_price_id', true );
                $has_price       = ! empty( $stripe_price_id ) ? '1' : '0';
                printf(
                    '<option value="%d" data-has-price="%s" %s>%s</option>',
                    $service->ID,
                    esc_attr( $has_price ),
                    selected( $selected_service, $service->ID, false ),
                    esc_html( $service->post_title )
                );
            }
            echo '</select>';
        }

        // Date filter (month/year)
        global $wpdb;
        $months = $wpdb->get_results( "
            SELECT DISTINCT YEAR(meta_value) as year, MONTH(meta_value) as month
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_start_datetime'
            AND meta_value != ''
            ORDER BY meta_value DESC
        " );

        if ( ! empty( $months ) ) {
            $selected_date = isset( $_GET['filter_date'] ) ? sanitize_text_field( $_GET['filter_date'] ) : '';
            echo '<select name="filter_date">';
            echo '<option value="">' . __( 'All Dates', 'simple-booking' ) . '</option>';
            foreach ( $months as $month ) {
                $value = sprintf( '%d-%02d', $month->year, $month->month );
                $label = date_i18n( 'F Y', strtotime( $value . '-01' ) );
                printf(
                    '<option value="%s" %s>%s</option>',
                    esc_attr( $value ),
                    selected( $selected_date, $value, false ),
                    esc_html( $label )
                );
            }
            echo '</select>';
        }

        // Payment status filter
        $selected_payment = isset( $_GET['filter_payment'] ) ? sanitize_text_field( $_GET['filter_payment'] ) : '';
        echo '<select name="filter_payment" id="filter-payment-dropdown">';
        echo '<option value="">' . __( 'All Payments', 'simple-booking' ) . '</option>';
        echo '<option value="paid" ' . selected( $selected_payment, 'paid', false ) . '>' . __( 'Paid', 'simple-booking' ) . '</option>';
        echo '<option value="free" ' . selected( $selected_payment, 'free', false ) . '>' . __( 'Free', 'simple-booking' ) . '</option>';
        echo '</select>';

        // JavaScript for dynamic filter control
        echo '<script>
        (function() {
            const serviceDropdown = document.getElementById("filter-service-dropdown");
            const paymentDropdown = document.getElementById("filter-payment-dropdown");
            
            if (!serviceDropdown || !paymentDropdown) return;
            
            function updatePaymentOptions() {
                const selected = serviceDropdown.options[serviceDropdown.selectedIndex];
                const hasPrice = selected ? selected.getAttribute("data-has-price") : "";
                const paidOption = paymentDropdown.querySelector("option[value=\"paid\"]");
                
                if (paidOption) {
                    if (hasPrice === "0") {
                        paidOption.disabled = true;
                        paidOption.textContent = "' . __( 'Paid', 'simple-booking' ) . ' (N/A for free service)";
                    } else {
                        paidOption.disabled = false;
                        paidOption.textContent = "' . __( 'Paid', 'simple-booking' ) . '";
                    }
                }
            }
            
            serviceDropdown.addEventListener("change", updatePaymentOptions);
            updatePaymentOptions();
        })();
        </script>';
    }

    /**
     * Filter bookings query based on selected filters
     */
    public static function filter_bookings_query( $query ) {
        global $pagenow;

        if ( ! is_admin() || 'edit.php' !== $pagenow || ! isset( $query->query_vars['post_type'] ) || self::POST_TYPE !== $query->query_vars['post_type'] ) {
            return;
        }

        $meta_query = array();

        // Service filter
        if ( ! empty( $_GET['filter_service'] ) ) {
            $meta_query[] = array(
                'key'     => '_service_id',
                'value'   => absint( $_GET['filter_service'] ),
                'compare' => '=',
            );
        }

        // Date filter
        if ( ! empty( $_GET['filter_date'] ) ) {
            $date_parts = explode( '-', sanitize_text_field( $_GET['filter_date'] ) );
            if ( count( $date_parts ) === 2 ) {
                $year  = intval( $date_parts[0] );
                $month = intval( $date_parts[1] );
                $start = sprintf( '%d-%02d-01', $year, $month );
                $end   = date( 'Y-m-t', strtotime( $start ) );

                $meta_query[] = array(
                    'key'     => '_start_datetime',
                    'value'   => array( $start, $end . ' 23:59:59' ),
                    'compare' => 'BETWEEN',
                    'type'    => 'DATETIME',
                );
            }
        }

        // Payment status filter
        if ( ! empty( $_GET['filter_payment'] ) ) {
            $payment_status = sanitize_text_field( $_GET['filter_payment'] );
            if ( 'paid' === $payment_status ) {
                $meta_query[] = array(
                    'key'     => '_stripe_payment_id',
                    'value'   => '',
                    'compare' => '!=',
                );
            } elseif ( 'free' === $payment_status ) {
                $meta_query[] = array(
                    'key'     => '_stripe_payment_id',
                    'value'   => '',
                    'compare' => '=',
                );
            }
        }

        // Apply meta query
        if ( ! empty( $meta_query ) ) {
            $meta_query['relation'] = 'AND';
            $query->set( 'meta_query', $meta_query );
        }

        // Handle sorting by booking date
        if ( isset( $query->query_vars['orderby'] ) && 'booking_date' === $query->query_vars['orderby'] ) {
            $query->set( 'meta_key', '_start_datetime' );
            $query->set( 'orderby', 'meta_value' );
        }
    }
}
