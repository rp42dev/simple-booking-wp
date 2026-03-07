<?php
/**
 * Staff Post Type
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Simple_Booking_Staff
 */
class Simple_Booking_Staff {

    /**
     * Post type name
     */
    const POST_TYPE = 'booking_staff';

    /**
     * Register custom post type
     */
    public static function register() {
        register_post_type(
            self::POST_TYPE,
            array(
                'labels'             => array(
                    'name'               => __( 'Staff', 'simple-booking' ),
                    'singular_name'      => __( 'Staff Member', 'simple-booking' ),
                    'add_new'            => __( 'Add New', 'simple-booking' ),
                    'add_new_item'       => __( 'Add New Staff Member', 'simple-booking' ),
                    'edit_item'          => __( 'Edit Staff Member', 'simple-booking' ),
                    'new_item'           => __( 'New Staff Member', 'simple-booking' ),
                    'view_item'          => __( 'View Staff Member', 'simple-booking' ),
                    'search_items'       => __( 'Search Staff', 'simple-booking' ),
                    'not_found'          => __( 'No staff members found', 'simple-booking' ),
                    'not_found_in_trash' => __( 'No staff members found in Trash', 'simple-booking' ),
                ),
                'public'             => false,
                'show_ui'            => true,
                'show_in_menu'       => 'edit.php?post_type=booking_service',
                'menu_position'      => 31,
                'menu_icon'          => 'dashicons-groups',
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
        // Staff Email
        register_post_meta(
            self::POST_TYPE,
            '_staff_email',
            array(
                'type'         => 'string',
                'single'       => true,
                'show_in_rest' => true,
                'sanitize_callback' => 'sanitize_email',
            )
        );

        // Staff Google Calendar ID (optional override)
        register_post_meta(
            self::POST_TYPE,
            '_staff_calendar_id',
            array(
                'type'         => 'string',
                'single'       => true,
                'show_in_rest' => true,
                'sanitize_callback' => 'sanitize_text_field',
            )
        );

        // Staff availability status
        register_post_meta(
            self::POST_TYPE,
            '_staff_active',
            array(
                'type'         => 'string',
                'single'       => true,
                'show_in_rest' => true,
                'sanitize_callback' => 'sanitize_text_field',
                'default'      => '1',
            )
        );
    }

    /**
     * Add meta boxes
     */
    public static function add_meta_boxes() {
        add_meta_box(
            'staff_details',
            __( 'Staff Details', 'simple-booking' ),
            array( __CLASS__, 'render_details_meta_box' ),
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    /**
     * Render staff details meta box
     */
    public static function render_details_meta_box( $post ) {
        $staff_email       = get_post_meta( $post->ID, '_staff_email', true );
        $staff_calendar_id = get_post_meta( $post->ID, '_staff_calendar_id', true );
        $staff_active      = get_post_meta( $post->ID, '_staff_active', true );

        // Default active to checked
        if ( '' === $staff_active ) {
            $staff_active = '1';
        }

        wp_nonce_field( 'simple_booking_staff_meta', 'simple_booking_staff_nonce' );
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="staff_email"><?php _e( 'Email', 'simple-booking' ); ?></label>
                </th>
                <td>
                    <input type="email"
                           id="staff_email"
                           name="staff_email"
                           value="<?php echo esc_attr( $staff_email ); ?>"
                           class="regular-text" />
                    <p class="description"><?php _e( 'Staff member email address', 'simple-booking' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="staff_calendar_id"><?php _e( 'Google Calendar ID', 'simple-booking' ); ?></label>
                </th>
                <td>
                    <input type="text"
                           id="staff_calendar_id"
                           name="staff_calendar_id"
                           value="<?php echo esc_attr( $staff_calendar_id ); ?>"
                           class="regular-text"
                           placeholder="example@group.calendar.google.com" />
                    <p class="description"><?php _e( 'Optional: Override calendar for this staff member. Leave blank to use global calendar.', 'simple-booking' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="staff_active"><?php _e( 'Active', 'simple-booking' ); ?></label>
                </th>
                <td>
                    <input type="checkbox"
                           id="staff_active"
                           name="staff_active"
                           value="1"
                           <?php checked( $staff_active, '1' ); ?> />
                    <label for="staff_active"><?php _e( 'Staff member is active and available for bookings', 'simple-booking' ); ?></label>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save staff meta
     */
    public static function save_staff_meta( $post_id ) {
        // Verify nonce
        if ( ! isset( $_POST['simple_booking_staff_nonce'] ) || 
             ! wp_verify_nonce( $_POST['simple_booking_staff_nonce'], 'simple_booking_staff_meta' ) ) {
            return;
        }

        // Check autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Check user permissions
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Save staff email
        if ( isset( $_POST['staff_email'] ) ) {
            update_post_meta( $post_id, '_staff_email', sanitize_email( $_POST['staff_email'] ) );
        }

        // Save staff calendar ID
        if ( isset( $_POST['staff_calendar_id'] ) ) {
            update_post_meta( $post_id, '_staff_calendar_id', sanitize_text_field( $_POST['staff_calendar_id'] ) );
        }

        // Save staff active status
        $staff_active = isset( $_POST['staff_active'] ) ? '1' : '0';
        update_post_meta( $post_id, '_staff_active', $staff_active );
    }

    /**
     * Get all active staff members
     */
    public static function get_active_staff() {
        $args = array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'     => '_staff_active',
                    'value'   => '1',
                    'compare' => '=',
                ),
            ),
            'orderby'        => 'title',
            'order'          => 'ASC',
        );

        return get_posts( $args );
    }
}

// Register save post hook
add_action( 'save_post_booking_staff', array( 'Simple_Booking_Staff', 'save_staff_meta' ), 10, 1 );
