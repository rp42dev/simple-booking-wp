<?php
/**
 * Booking Service Post Type
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Simple_Booking_Service
 */
class Simple_Booking_Service {

    /**
     * Post type name
     */
    const POST_TYPE = 'booking_service';

    /**
     * Register custom post type
     */
    public static function register() {
        register_post_type(
            self::POST_TYPE,
            array(
                'labels'             => array(
                    'name'               => __( 'Services', 'simple-booking' ),
                    'singular_name'      => __( 'Service', 'simple-booking' ),
                    'add_new'            => __( 'Add New', 'simple-booking' ),
                    'add_new_item'       => __( 'Add New Service', 'simple-booking' ),
                    'edit_item'          => __( 'Edit Service', 'simple-booking' ),
                    'new_item'           => __( 'New Service', 'simple-booking' ),
                    'view_item'          => __( 'View Service', 'simple-booking' ),
                    'search_items'       => __( 'Search Services', 'simple-booking' ),
                    'not_found'          => __( 'No services found', 'simple-booking' ),
                    'not_found_in_trash' => __( 'No services found in Trash', 'simple-booking' ),
                ),
                'public'             => false,
                'show_ui'            => true,
                'show_in_menu'       => true,
                'menu_position'      => 30,
                'menu_icon'          => 'dashicons-calendar-alt',
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
        // Duration in minutes
        register_post_meta(
            self::POST_TYPE,
            '_service_duration',
            array(
                'type'         => 'integer',
                'single'       => true,
                'show_in_rest' => true,
                'sanitize_callback' => 'absint',
            )
        );

        // Stripe Price ID
        register_post_meta(
            self::POST_TYPE,
            '_stripe_price_id',
            array(
                'type'         => 'string',
                'single'       => true,
                'show_in_rest' => true,
                'sanitize_callback' => 'sanitize_text_field',
            )
        );

        // Service Active
        register_post_meta(
            self::POST_TYPE,
            '_service_active',
            array(
                'type'         => 'boolean',
                'single'       => true,
                'show_in_rest' => true,
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default'      => true,
            )
        );

        // Meeting Link
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

        // Create Google Event Toggle
        register_post_meta(
            self::POST_TYPE,
            '_create_google_event',
            array(
                'type'         => 'boolean',
                'single'       => true,
                'show_in_rest' => true,
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default'      => true,
            )
        );
    }

    /**
     * Add meta boxes
     */
    public static function add_meta_boxes() {
        add_meta_box(
            'booking_service_details',
            __( 'Service Details', 'simple-booking' ),
            array( __CLASS__, 'render_meta_box' ),
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    /**
     * Render meta box
     */
    public static function render_meta_box( $post ) {
        // Add nonce for security
        wp_nonce_field( 'simple_booking_service_save', 'simple_booking_service_nonce' );

        // Get values
        $duration     = get_post_meta( $post->ID, '_service_duration', true );
        $price_id     = get_post_meta( $post->ID, '_stripe_price_id', true );
        $meeting_link = get_post_meta( $post->ID, '_meeting_link', true );
        $is_active    = get_post_meta( $post->ID, '_service_active', true );
        $create_google_event = get_post_meta( $post->ID, '_create_google_event', true );

        // Default values
        if ( '' === $duration ) {
            $duration = 60;
        }
        if ( '' === $is_active ) {
            $is_active = '1';
        }
        if ( '' === $create_google_event ) {
            $create_google_event = '1';
        }
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="service_duration"><?php _e( 'Duration (minutes)', 'simple-booking' ); ?></label>
                </th>
                <td>
                    <input type="number"
                           id="service_duration"
                           name="service_duration"
                           value="<?php echo esc_attr( $duration ); ?>"
                           min="15"
                           step="15"
                           class="small-text" />
                    <p class="description"><?php _e( 'Duration of the service in minutes', 'simple-booking' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="stripe_price_id"><?php _e( 'Stripe Price ID', 'simple-booking' ); ?></label>
                </th>
                <td>
                    <input type="text"
                           id="stripe_price_id"
                           name="stripe_price_id"
                           value="<?php echo esc_attr( $price_id ); ?>"
                           class="regular-text"
                           placeholder="price_xxxxxxxxxxxxxx" />
                    <p class="description"><?php _e( 'Enter the Stripe Price ID (e.g., price_1234567890)', 'simple-booking' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="meeting_link"><?php _e( 'Meeting Link', 'simple-booking' ); ?></label>
                </th>
                <td>
                    <input type="url"
                           id="meeting_link"
                           name="meeting_link"
                           value="<?php echo esc_attr( $meeting_link ); ?>"
                           class="regular-text"
                           placeholder="https://zoom.us/j/xxxxx or https://meet.google.com/xxxxx" />
                    <p class="description"><?php _e( 'Optional: Zoom, Google Meet, or other meeting URL', 'simple-booking' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="service_active"><?php _e( 'Active', 'simple-booking' ); ?></label>
                </th>
                <td>
                    <input type="checkbox"
                           id="service_active"
                           name="service_active"
                           value="1"
                           <?php checked( $is_active, '1' ); ?> />
                    <label for="service_active"><?php _e( 'Service is available for booking', 'simple-booking' ); ?></label>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="create_google_event"><?php _e( 'Create Google Calendar Event', 'simple-booking' ); ?></label>
                </th>
                <td>
                    <input type="checkbox"
                           id="create_google_event"
                           name="create_google_event"
                           value="1"
                           <?php checked( $create_google_event, '1' ); ?> />
                    <label for="create_google_event"><?php _e( 'Automatically create Google Calendar event for bookings', 'simple-booking' ); ?></label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e( 'Service Shortcode', 'simple-booking' ); ?></th>
                <td>
                    <code>[simple_booking_form service_id="<?php echo esc_attr( $post->ID ); ?>"]</code>
                    <p class="description"><?php _e( 'Use this shortcode to show a booking form for this service only.', 'simple-booking' ); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save meta fields
     */
    public static function save_meta( $post_id, $post ) {
        // Check nonce
        if ( ! isset( $_POST['simple_booking_service_nonce'] ) ||
             ! wp_verify_nonce( $_POST['simple_booking_service_nonce'], 'simple_booking_service_save' ) ) {
            return;
        }

        // Check autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Check permissions
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Save duration
        if ( isset( $_POST['service_duration'] ) ) {
            update_post_meta( $post_id, '_service_duration', absint( $_POST['service_duration'] ) );
        }

        // Save Stripe Price ID
        if ( isset( $_POST['stripe_price_id'] ) ) {
            update_post_meta( $post_id, '_stripe_price_id', sanitize_text_field( $_POST['stripe_price_id'] ) );
        }

        // Save meeting link
        if ( isset( $_POST['meeting_link'] ) ) {
            update_post_meta( $post_id, '_meeting_link', esc_url_raw( $_POST['meeting_link'] ) );
        }

        // Save active status
        $is_active = isset( $_POST['service_active'] ) ? '1' : '0';
        update_post_meta( $post_id, '_service_active', $is_active );

        // Save Google event creation toggle
        $create_google_event = isset( $_POST['create_google_event'] ) ? '1' : '0';
        update_post_meta( $post_id, '_create_google_event', $create_google_event );
    }

    /**
     * Get all active services
     */
    public static function get_active_services() {
        $args = array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'     => '_service_active',
                    'value'   => '1',
                    'compare' => '=',
                ),
            ),
        );

        $query = new WP_Query( $args );
        return $query->posts;
    }

    /**
     * Get service by ID
     */
    public static function get_service( $service_id ) {
        $post = get_post( $service_id );
        if ( ! $post || self::POST_TYPE !== $post->post_type ) {
            return null;
        }

        return array(
            'id'           => $post->ID,
            'name'         => $post->post_title,
            'duration'     => get_post_meta( $post->ID, '_service_duration', true ),
            'stripe_price_id' => get_post_meta( $post->ID, '_stripe_price_id', true ),
            'meeting_link' => get_post_meta( $post->ID, '_meeting_link', true ),
            'is_active'    => get_post_meta( $post->ID, '_service_active', true ),
            'create_google_event' => get_post_meta( $post->ID, '_create_google_event', true ),
        );
    }
}

// Save meta on post save
add_action( 'save_post_booking_service', array( 'Simple_Booking_Service', 'save_meta' ), 10, 2 );
