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

        if ( ! empty( $data['google_event_id'] ) ) {
            update_post_meta( $post_id, '_google_event_id', sanitize_text_field( $data['google_event_id'] ) );
        }

        return $post_id;
    }
}
