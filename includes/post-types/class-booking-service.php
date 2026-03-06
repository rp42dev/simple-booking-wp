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
                'type'         => 'string',
                'single'       => true,
                'show_in_rest' => true,
                'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox_value' ),
                'default'      => '1',
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
                'type'         => 'string',
                'single'       => true,
                'show_in_rest' => true,
                'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox_value' ),
                'default'      => '1',
            )
        );

        // Available Days (comma-separated: 1-7 for Mon-Sun)
        register_post_meta(
            self::POST_TYPE,
            '_available_days',
            array(
                'type'         => 'string',
                'single'       => true,
                'show_in_rest' => true,
                'sanitize_callback' => 'sanitize_text_field',
                'default'      => '1,2,3,4,5',
            )
        );

        // Available Hours Start (HH:MM format)
        register_post_meta(
            self::POST_TYPE,
            '_available_hours_start',
            array(
                'type'         => 'string',
                'single'       => true,
                'show_in_rest' => true,
                'sanitize_callback' => 'sanitize_text_field',
                'default'      => '09:00',
            )
        );

        // Available Hours End (HH:MM format)
        register_post_meta(
            self::POST_TYPE,
            '_available_hours_end',
            array(
                'type'         => 'string',
                'single'       => true,
                'show_in_rest' => true,
                'sanitize_callback' => 'sanitize_text_field',
                'default'      => '17:00',
            )
        );

        // Buffer Time Between Bookings (minutes)
        register_post_meta(
            self::POST_TYPE,
            '_buffer_time',
            array(
                'type'         => 'integer',
                'single'       => true,
                'show_in_rest' => true,
                'sanitize_callback' => 'absint',
                'default'      => 0,
            )
        );

        // Schedule Mode (inherit global or custom per service)
        register_post_meta(
            self::POST_TYPE,
            '_schedule_mode',
            array(
                'type'         => 'string',
                'single'       => true,
                'show_in_rest' => true,
                'sanitize_callback' => 'sanitize_text_field',
                'default'      => 'inherit',
            )
        );
    }

    /**
     * Sanitize checkbox value to ensure it's always '1' or '0'
     */
    public static function sanitize_checkbox_value( $value ) {
        // Convert boolean true/false to '1'/'0'
        if ( is_bool( $value ) ) {
            return $value ? '1' : '0';
        }
        // Convert truthy values to '1', everything else to '0'
        return ( '1' === $value || 1 === $value || true === $value ) ? '1' : '0';
    }

    /**
     * Enqueue admin scripts and styles
     */
    public static function enqueue_admin_scripts( $hook ) {
        // Only load on service editor page
        if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
            return;
        }

        // Check if we're editing a booking service
        global $post;
        if ( ! $post || self::POST_TYPE !== $post->post_type ) {
            return;
        }

        wp_enqueue_script(
            'simple-booking-admin-service-settings',
            plugin_dir_url( __FILE__ ) . '../../assets/js/admin-service-settings.js',
            array(),
            SIMPLE_BOOKING_VERSION,
            true
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
        $available_days = get_post_meta( $post->ID, '_available_days', true );
        $available_hours_start = get_post_meta( $post->ID, '_available_hours_start', true );
        $available_hours_end = get_post_meta( $post->ID, '_available_hours_end', true );
        $buffer_time = get_post_meta( $post->ID, '_buffer_time', true );
        $schedule_mode = get_post_meta( $post->ID, '_schedule_mode', true );

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
        if ( '' === $available_days ) {
            $available_days = '1,2,3,4,5';
        }
        if ( '' === $available_hours_start ) {
            $available_hours_start = '09:00';
        }
        if ( '' === $available_hours_end ) {
            $available_hours_end = '17:00';
        }
        if ( '' === $buffer_time ) {
            $buffer_time = 0;
        }
        if ( '' === $schedule_mode ) {
            $schedule_mode = 'inherit';
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

            <!-- Availability Settings -->
            <tr style="border-top: 2px solid #ddd; padding-top: 20px;">
                <th colspan="2"><strong><?php _e( '⏰ Availability Settings', 'simple-booking' ); ?></strong></th>
            </tr>

            <tr>
                <th scope="row">
                    <label for="schedule_mode"><?php _e( 'Schedule Mode', 'simple-booking' ); ?></label>
                </th>
                <td>
                    <select id="schedule_mode" name="schedule_mode">
                        <option value="inherit" <?php selected( $schedule_mode, 'inherit' ); ?>><?php _e( 'Inherit Global Schedule', 'simple-booking' ); ?></option>
                        <option value="custom" <?php selected( $schedule_mode, 'custom' ); ?>><?php _e( 'Use Custom Service Schedule', 'simple-booking' ); ?></option>
                    </select>
                    <p class="description"><?php _e( 'Inherit uses plugin Working Schedule. Custom applies day/hour rules below for this service.', 'simple-booking' ); ?></p>
                </td>
            </tr>

            <!-- Custom Availability Settings (shown only when mode is Custom) -->
            <tbody id="custom-availability-section">
            <tr>
                <th scope="row">
                    <label for="available_days"><?php _e( 'Available Days', 'simple-booking' ); ?></label>
                </th>
                <td>
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; max-width: 300px;">
                        <?php
                        $days = array(
                            '1' => __( 'Monday', 'simple-booking' ),
                            '2' => __( 'Tuesday', 'simple-booking' ),
                            '3' => __( 'Wednesday', 'simple-booking' ),
                            '4' => __( 'Thursday', 'simple-booking' ),
                            '5' => __( 'Friday', 'simple-booking' ),
                            '6' => __( 'Saturday', 'simple-booking' ),
                            '7' => __( 'Sunday', 'simple-booking' ),
                        );
                        $available_days_arr = array_map( 'trim', explode( ',', $available_days ) );
                        foreach ( $days as $day_num => $day_name ) {
                            $checked = in_array( $day_num, $available_days_arr, true );
                            ?>
                            <label style="display: flex; align-items: center; gap: 8px;">
                                <input type="checkbox"
                                       name="available_days_check[]"
                                       value="<?php echo esc_attr( $day_num ); ?>"
                                       <?php checked( $checked ); ?> />
                                <?php echo esc_html( $day_name ); ?>
                            </label>
                            <?php
                        }
                        ?>
                    </div>
                    <p class="description"><?php _e( 'Select which days of the week the service is available', 'simple-booking' ); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="available_hours_start"><?php _e( 'Available Hours', 'simple-booking' ); ?></label>
                </th>
                <td>
                    <div style="display: flex; gap: 10px; align-items: center; max-width: 300px;">
                        <input type="time"
                               id="available_hours_start"
                               name="available_hours_start"
                               value="<?php echo esc_attr( $available_hours_start ); ?>"
                               style="flex: 1;" />
                        <span><?php _e( 'to', 'simple-booking' ); ?></span>
                        <input type="time"
                               id="available_hours_end"
                               name="available_hours_end"
                               value="<?php echo esc_attr( $available_hours_end ); ?>"
                               style="flex: 1;" />
                    </div>
                    <p class="description"><?php _e( 'Set the available time window for bookings each day', 'simple-booking' ); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="buffer_time"><?php _e( 'Buffer Time Between Bookings', 'simple-booking' ); ?></label>
                </th>
                <td>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <input type="number"
                               id="buffer_time"
                               name="buffer_time"
                               value="<?php echo esc_attr( $buffer_time ); ?>"
                               min="0"
                               step="5"
                               style="width: 100px;" />
                        <span><?php _e( 'minutes', 'simple-booking' ); ?></span>
                    </div>
                    <p class="description"><?php _e( 'Minimum gap required between the end of one booking and the start of the next', 'simple-booking' ); ?></p>
                </td>
            </tr>
            </tbody>
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

        // Save available days
        if ( isset( $_POST['available_days_check'] ) && is_array( $_POST['available_days_check'] ) ) {
            $days = array_map( 'absint', $_POST['available_days_check'] );
            $days = array_filter( $days );
            if ( ! empty( $days ) ) {
                update_post_meta( $post_id, '_available_days', implode( ',', $days ) );
            }
        } else {
            update_post_meta( $post_id, '_available_days', '1,2,3,4,5' );
        }

        // Save available hours start
        if ( isset( $_POST['available_hours_start'] ) ) {
            update_post_meta( $post_id, '_available_hours_start', sanitize_text_field( $_POST['available_hours_start'] ) );
        }

        // Save available hours end
        if ( isset( $_POST['available_hours_end'] ) ) {
            update_post_meta( $post_id, '_available_hours_end', sanitize_text_field( $_POST['available_hours_end'] ) );
        }

        // Save buffer time
        if ( isset( $_POST['buffer_time'] ) ) {
            update_post_meta( $post_id, '_buffer_time', absint( $_POST['buffer_time'] ) );
        }

        // Save schedule mode
        $schedule_mode = isset( $_POST['schedule_mode'] ) ? sanitize_text_field( $_POST['schedule_mode'] ) : 'inherit';
        if ( ! in_array( $schedule_mode, array( 'inherit', 'custom' ), true ) ) {
            $schedule_mode = 'inherit';
        }
        update_post_meta( $post_id, '_schedule_mode', $schedule_mode );
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
            'id'                   => $post->ID,
            'name'                 => $post->post_title,
            'duration'             => get_post_meta( $post->ID, '_service_duration', true ),
            'stripe_price_id'      => get_post_meta( $post->ID, '_stripe_price_id', true ),
            'meeting_link'         => get_post_meta( $post->ID, '_meeting_link', true ),
            'is_active'            => get_post_meta( $post->ID, '_service_active', true ),
            'create_google_event'  => get_post_meta( $post->ID, '_create_google_event', true ),
            'available_days'       => get_post_meta( $post->ID, '_available_days', true ),
            'available_hours_start' => get_post_meta( $post->ID, '_available_hours_start', true ),
            'available_hours_end'  => get_post_meta( $post->ID, '_available_hours_end', true ),
            'buffer_time'          => absint( get_post_meta( $post->ID, '_buffer_time', true ) ),
            'schedule_mode'        => get_post_meta( $post->ID, '_schedule_mode', true ) ?: 'inherit',
        );
    }

    /**
     * Check if a time slot is available based on service availability settings
     *
     * @param DateTime $start Start datetime
     * @param DateTime $end End datetime
     * @param array $service Service array with availability settings
     * @param array $existing_bookings Array of existing bookings to check for conflicts
     * @return bool True if slot is available
     */
    public static function is_slot_available( DateTime $start, DateTime $end, array $service, array $existing_bookings = array() ) {
        $schedule_mode = isset( $service['schedule_mode'] ) && in_array( $service['schedule_mode'], array( 'inherit', 'custom' ), true )
            ? $service['schedule_mode']
            : 'inherit';

        if ( 'custom' === $schedule_mode ) {
            // Check if the day is available
            $weekday = (int) $start->format( 'N' ); // 1=Mon, 7=Sun
            $available_days = ! empty( $service['available_days'] ) ? $service['available_days'] : '1,2,3,4,5';
            $available_days_arr = array_map( 'intval', explode( ',', trim( $available_days ) ) );

            if ( ! in_array( $weekday, $available_days_arr, true ) ) {
                return false;
            }

            // Check if the time is within available hours
            $available_hours_start = ! empty( $service['available_hours_start'] ) ? $service['available_hours_start'] : '09:00';
            $available_hours_end = ! empty( $service['available_hours_end'] ) ? $service['available_hours_end'] : '17:00';

            $start_time = $start->format( 'H:i' );
            $end_time = $end->format( 'H:i' );

            if ( $start_time < $available_hours_start || $end_time > $available_hours_end ) {
                return false;
            }
        }

        // Check buffer time with existing bookings
        if ( ! empty( $existing_bookings ) ) {
            $buffer_time = isset( $service['buffer_time'] ) ? absint( $service['buffer_time'] ) : 0;
            
            foreach ( $existing_bookings as $booking ) {
                $booking_start = new DateTime( $booking['start_datetime'] );
                $booking_end = new DateTime( $booking['end_datetime'] );

                // Check if new booking starts before existing booking ends + buffer
                if ( $buffer_time > 0 ) {
                    $booking_end->modify( "+{$buffer_time} minutes" );
                }

                // Check for conflict
                if ( $start < $booking_end && $end > $booking_start ) {
                    return false;
                }
            }
        }

        return true;
    }
}

// Save meta on post save
add_action( 'save_post_booking_service', array( 'Simple_Booking_Service', 'save_meta' ), 10, 2 );

// Enqueue admin scripts
add_action( 'admin_enqueue_scripts', array( 'Simple_Booking_Service', 'enqueue_admin_scripts' ) );
