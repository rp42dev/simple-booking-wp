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

        // Per-Day Schedule (JSON format with per-day time windows and buffer)
        register_post_meta(
            self::POST_TYPE,
            '_service_schedule',
            array(
                'type'         => 'string',
                'single'       => true,
                'show_in_rest' => true,
                'sanitize_callback' => array( __CLASS__, 'sanitize_service_schedule' ),
                'default'      => '',
            )
        );
    }

    /**
     * Get default per-day schedule structure
     *
     * @return array
     */
    public static function get_default_schedule() {
        return array(
            '1' => array( 'enabled' => true,  'start' => '09:00', 'end' => '17:00', 'buffer' => 0 ),
            '2' => array( 'enabled' => true,  'start' => '09:00', 'end' => '17:00', 'buffer' => 0 ),
            '3' => array( 'enabled' => true,  'start' => '09:00', 'end' => '17:00', 'buffer' => 0 ),
            '4' => array( 'enabled' => true,  'start' => '09:00', 'end' => '17:00', 'buffer' => 0 ),
            '5' => array( 'enabled' => true,  'start' => '09:00', 'end' => '17:00', 'buffer' => 0 ),
            '6' => array( 'enabled' => false, 'start' => '09:00', 'end' => '17:00', 'buffer' => 0 ),
            '7' => array( 'enabled' => false, 'start' => '09:00', 'end' => '17:00', 'buffer' => 0 ),
        );
    }

    /**
     * Get global schedule normalized to numeric day keys used by service schedule
     *
     * @return array
     */
    private static function get_global_schedule_for_preview() {
        $default_schedule = self::get_default_schedule();

        if ( ! function_exists( 'simple_booking' ) ) {
            return $default_schedule;
        }

        $global_schedule = simple_booking()->get_setting( 'schedule', array() );
        if ( empty( $global_schedule ) || ! is_array( $global_schedule ) ) {
            return $default_schedule;
        }

        $map = array(
            '1' => 'monday',
            '2' => 'tuesday',
            '3' => 'wednesday',
            '4' => 'thursday',
            '5' => 'friday',
            '6' => 'saturday',
            '7' => 'sunday',
        );

        $normalized = array();
        foreach ( $map as $day_num => $day_name ) {
            $default_day = $default_schedule[ $day_num ];
            $day_data = isset( $global_schedule[ $day_name ] ) && is_array( $global_schedule[ $day_name ] )
                ? $global_schedule[ $day_name ]
                : array();

            $normalized[ $day_num ] = array(
                'enabled' => ! empty( $day_data['enabled'] ),
                'start'   => ! empty( $day_data['start'] ) ? sanitize_text_field( $day_data['start'] ) : $default_day['start'],
                'end'     => ! empty( $day_data['end'] ) ? sanitize_text_field( $day_data['end'] ) : $default_day['end'],
                'buffer'  => isset( $day_data['buffer'] ) ? absint( $day_data['buffer'] ) : $default_day['buffer'],
            );
        }

        return $normalized;
    }

    /**
     * Build effective schedule preview HTML (read-only)
     *
     * @param string $schedule_mode 'inherit' or 'custom'
     * @param array $service_schedule Service schedule (for custom mode)
     * @return string HTML for preview table
     */
    public static function build_schedule_preview( $schedule_mode, $service_schedule = array() ) {
            $days = array(
                '1' => __( 'Monday', 'simple-booking' ),
                '2' => __( 'Tuesday', 'simple-booking' ),
                '3' => __( 'Wednesday', 'simple-booking' ),
                '4' => __( 'Thursday', 'simple-booking' ),
                '5' => __( 'Friday', 'simple-booking' ),
                '6' => __( 'Saturday', 'simple-booking' ),
                '7' => __( 'Sunday', 'simple-booking' ),
            );

            // Use service schedule if custom, otherwise use global admin schedule
            if ( 'custom' === $schedule_mode ) {
                $schedule = ! empty( $service_schedule ) ? $service_schedule : self::get_default_schedule();
            } else {
                $schedule = self::get_global_schedule_for_preview();
            }

            $html = '<table style="width: 100%; border-collapse: collapse; margin-top: 10px;">';
            $html .= '<thead>';
            $html .= '<tr style="background-color: #f5f5f5; border-bottom: 2px solid #ddd;">';
            $html .= '<th style="padding: 10px; text-align: left; width: 15%; border: 1px solid #ddd;">' . __( 'Day', 'simple-booking' ) . '</th>';
            $html .= '<th style="padding: 10px; text-align: center; width: 10%; border: 1px solid #ddd;">' . __( 'Status', 'simple-booking' ) . '</th>';
            $html .= '<th style="padding: 10px; text-align: center; width: 20%; border: 1px solid #ddd;">' . __( 'Hours', 'simple-booking' ) . '</th>';
            $html .= '<th style="padding: 10px; text-align: center; width: 20%; border: 1px solid #ddd;">' . __( 'Buffer', 'simple-booking' ) . '</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody>';

            foreach ( $days as $day_num => $day_name ) {
                $day_data = $schedule[ $day_num ] ?? array();
                $is_enabled = $day_data['enabled'] ?? false;
                $start_time = $day_data['start'] ?? '09:00';
                $end_time = $day_data['end'] ?? '17:00';
                $buffer = $day_data['buffer'] ?? 0;

                if ( $is_enabled ) {
                    $status = '<span style="color: #28a745; font-weight: bold;">✓ Open</span>';
                    $hours = esc_html( $start_time . ' – ' . $end_time );
                    $buffer_text = $buffer > 0 ? esc_html( $buffer . ' min' ) : '—';
                    $row_style = 'background-color: #f9fff9;';
                } else {
                    $status = '<span style="color: #dc3545; font-weight: bold;">✗ Closed</span>';
                    $hours = '—';
                    $buffer_text = '—';
                    $row_style = 'background-color: #fff5f5; opacity: 0.7;';
                }

                $html .= '<tr style="border-bottom: 1px solid #eee; ' . $row_style . '">';
                $html .= '<td style="padding: 10px; border: 1px solid #ddd;"><strong>' . esc_html( $day_name ) . '</strong></td>';
                $html .= '<td style="padding: 10px; text-align: center; border: 1px solid #ddd;">' . $status . '</td>';
                $html .= '<td style="padding: 10px; text-align: center; border: 1px solid #ddd;">' . $hours . '</td>';
                $html .= '<td style="padding: 10px; text-align: center; border: 1px solid #ddd;">' . $buffer_text . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody>';
            $html .= '</table>';

            return $html;
        }

    /**
     * Sanitize service schedule (per-day format)
     *
     * @param mixed $value
     * @return string JSON
     */
    public static function sanitize_service_schedule( $value ) {
        if ( empty( $value ) ) {
            return wp_json_encode( self::get_default_schedule() );
        }

        if ( is_string( $value ) ) {
            $schedule = json_decode( $value, true );
        } else {
            $schedule = $value;
        }

        if ( ! is_array( $schedule ) ) {
            return wp_json_encode( self::get_default_schedule() );
        }

        $default = self::get_default_schedule();
        $sanitized = array();

        foreach ( $default as $day => $defaults ) {
            $day_data = isset( $schedule[ $day ] ) ? $schedule[ $day ] : array();

            $sanitized[ $day ] = array(
                'enabled' => isset( $day_data['enabled'] ) ? (bool) $day_data['enabled'] : $defaults['enabled'],
                'start'   => isset( $day_data['start'] ) ? sanitize_text_field( $day_data['start'] ) : $defaults['start'],
                'end'     => isset( $day_data['end'] ) ? sanitize_text_field( $day_data['end'] ) : $defaults['end'],
                'buffer'  => isset( $day_data['buffer'] ) ? absint( $day_data['buffer'] ) : $defaults['buffer'],
            );
        }

        return wp_json_encode( $sanitized );
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
        $service_schedule_json = get_post_meta( $post->ID, '_service_schedule', true );
        $service_schedule = $service_schedule_json ? json_decode( $service_schedule_json, true ) : null;
        $global_schedule_for_preview = self::get_global_schedule_for_preview();

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
                <th colspan="2">
                    <strong><?php _e( 'Per-Day Schedule Configuration', 'simple-booking' ); ?></strong>
                    <p class="description" style="margin-top: 8px;"><?php _e( 'Set custom hours and buffer for each day of the week', 'simple-booking' ); ?></p>
                </th>
            </tr>
            <tr>
                <td colspan="2">
                    <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
                        <thead>
                            <tr style="background-color: #f5f5f5; border-bottom: 2px solid #ddd;">
                                <th style="padding: 10px; text-align: left; width: 15%;"><?php _e( 'Day', 'simple-booking' ); ?></th>
                                <th style="padding: 10px; text-align: center; width: 10%;"><?php _e( 'Enabled', 'simple-booking' ); ?></th>
                                <th style="padding: 10px; text-align: center; width: 20%;"><?php _e( 'Start Time', 'simple-booking' ); ?></th>
                                <th style="padding: 10px; text-align: center; width: 20%;"><?php _e( 'End Time', 'simple-booking' ); ?></th>
                                <th style="padding: 10px; text-align: center; width: 20%;"><?php _e( 'Buffer (min)', 'simple-booking' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
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

                            // Use per-day schedule if available, otherwise use default
                            if ( ! $service_schedule ) {
                                $service_schedule = self::get_default_schedule();
                            }

                            foreach ( $days as $day_num => $day_name ) {
                                $day_data = $service_schedule[ $day_num ] ?? self::get_default_schedule()[ $day_num ];
                                $is_enabled = $day_data['enabled'] ?? true;
                                $start_time = $day_data['start'] ?? '09:00';
                                $end_time = $day_data['end'] ?? '17:00';
                                $buffer = $day_data['buffer'] ?? 0;
                                $row_style = $is_enabled ? '' : 'opacity: 0.6; background-color: #fafafa;';
                                ?>
                                <tr style="border-bottom: 1px solid #eee; <?php echo esc_attr( $row_style ); ?>">
                                    <td style="padding: 10px;">
                                        <strong><?php echo esc_html( $day_name ); ?></strong>
                                    </td>
                                    <td style="padding: 10px; text-align: center;">
                                        <input type="checkbox"
                                               name="service_schedule[<?php echo esc_attr( $day_num ); ?>][enabled]"
                                               value="1"
                                               class="day-enabled-checkbox"
                                               data-day="<?php echo esc_attr( $day_num ); ?>"
                                               <?php checked( $is_enabled ); ?> />
                                    </td>
                                    <td style="padding: 10px; text-align: center;">
                                        <input type="time"
                                               name="service_schedule[<?php echo esc_attr( $day_num ); ?>][start]"
                                               value="<?php echo esc_attr( $start_time ); ?>"
                                               class="day-start-time"
                                               data-day="<?php echo esc_attr( $day_num ); ?>" />
                                    </td>
                                    <td style="padding: 10px; text-align: center;">
                                        <input type="time"
                                               name="service_schedule[<?php echo esc_attr( $day_num ); ?>][end]"
                                               value="<?php echo esc_attr( $end_time ); ?>"
                                               class="day-end-time"
                                               data-day="<?php echo esc_attr( $day_num ); ?>" />
                                    </td>
                                    <td style="padding: 10px; text-align: center;">
                                        <input type="number"
                                               name="service_schedule[<?php echo esc_attr( $day_num ); ?>][buffer]"
                                               value="<?php echo esc_attr( $buffer ); ?>"
                                               min="0"
                                               step="5"
                                               style="width: 60px; text-align: center;"
                                               class="day-buffer"
                                               data-day="<?php echo esc_attr( $day_num ); ?>" />
                                    </td>
                                </tr>
                                <?php
                            }
                            ?>
                        </tbody>
                    </table>
                </td>
            </tr>
            </tbody>
            <tr style="border-top: 2px solid #ddd;">
                <th colspan="2"><strong><?php _e( '📅 Effective Schedule Preview', 'simple-booking' ); ?></strong></th>
            </tr>
            <tr>
                <td colspan="2" style="padding: 10px;">
                    <input type="hidden" id="preview-global-schedule" value="<?php echo esc_attr( wp_json_encode( $global_schedule_for_preview ) ); ?>" />
                    <div id="schedule-preview-container">
                        <?php
                        echo self::build_schedule_preview( $schedule_mode, $service_schedule );
                        ?>
                    </div>
                    <p id="schedule-preview-note" class="description" style="margin-top: 15px;">
                        <?php
                        if ( 'inherit' === $schedule_mode ) {
                            _e( 'This service uses the global Working Schedule. Effective availability is determined by plugin-level settings.', 'simple-booking' );
                        } else {
                            _e( 'This service uses custom availability. Shows your configured per-day schedule above.', 'simple-booking' );
                        }
                        ?>
                    </p>
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

        // Save per-day schedule (new format)
        if ( isset( $_POST['service_schedule'] ) && is_array( $_POST['service_schedule'] ) ) {
            $schedule = self::sanitize_service_schedule( $_POST['service_schedule'] );
            update_post_meta( $post_id, '_service_schedule', $schedule );
        }
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

        $service_schedule_json = get_post_meta( $post->ID, '_service_schedule', true );
        $service_schedule = $service_schedule_json ? json_decode( $service_schedule_json, true ) : null;

        // If no per-day schedule exists, use default
        if ( ! $service_schedule ) {
            $service_schedule = self::get_default_schedule();
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
            'service_schedule'     => $service_schedule,
        );
    }

    /**
     * Check if a time slot is available based on service availability settings
     *
     * @param DateTime $start Start datetime
     * @param DateTime $end End datetime
     * @param array $service Service array with availability settings
     * @param array $existing_bookings Array of existing bookings to check for conflicts
     * @param array $global_schedule Optional global working schedule
     * @return bool True if slot is available
     */
    public static function is_slot_available( DateTime $start, DateTime $end, array $service, array $existing_bookings = array(), array $global_schedule = array() ) {
        $schedule_mode = isset( $service['schedule_mode'] ) && in_array( $service['schedule_mode'], array( 'inherit', 'custom' ), true )
            ? $service['schedule_mode']
            : 'inherit';

        if ( 'custom' === $schedule_mode ) {
            // Use per-day schedule if available
            $service_schedule = isset( $service['service_schedule'] ) && is_array( $service['service_schedule'] )
                ? $service['service_schedule']
                : self::get_default_schedule();

            // Get weekday (1=Mon, 7=Sun)
            $weekday = (int) $start->format( 'N' );
            $day_schedule = isset( $service_schedule[ $weekday ] ) ? $service_schedule[ $weekday ] : null;

            // If day is not in schedule or not enabled, slot is not available
            if ( ! $day_schedule || ! $day_schedule['enabled'] ) {
                return false;
            }

            // Check if the time is within available hours for this day
            $start_time = $start->format( 'H:i' );
            $end_time = $end->format( 'H:i' );
            $day_start = $day_schedule['start'] ?? '09:00';
            $day_end = $day_schedule['end'] ?? '17:00';

            if ( $start_time < $day_start || $end_time > $day_end ) {
                return false;
            }

            // Use per-day buffer if available
            if ( ! empty( $existing_bookings ) ) {
                $day_buffer = $day_schedule['buffer'] ?? 0;

                foreach ( $existing_bookings as $booking ) {
                    $booking_start = new DateTime( $booking['start_datetime'] );
                    $booking_end = new DateTime( $booking['end_datetime'] );

                    // Check if new booking starts before existing booking ends + buffer
                    if ( $day_buffer > 0 ) {
                        $booking_end_copy = clone $booking_end;
                        $booking_end_copy->modify( "+{$day_buffer} minutes" );
                        if ( $start < $booking_end_copy && $end > $booking_start ) {
                            return false;
                        }
                    } else {
                        // Check for direct conflict
                        if ( $start < $booking_end && $end > $booking_start ) {
                            return false;
                        }
                    }
                }
            }
        } else {
            // Inherit mode: use global schedule with its per-day buffers
            if ( ! empty( $existing_bookings ) && ! empty( $global_schedule ) ) {
                // Map weekday number to day name for lookup
                $state = $start->format( 'w' );
                $weekday_names = array( 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday' );
                $day_name = $weekday_names[ $state ] ?? 'monday';

                // Get global schedule for this day
                if ( isset( $global_schedule[ $day_name ] ) ) {
                    $day_schedule = $global_schedule[ $day_name ];
                    $day_buffer = $day_schedule['buffer'] ?? 0;

                    foreach ( $existing_bookings as $booking ) {
                        $booking_start = new DateTime( $booking['start_datetime'] );
                        $booking_end = new DateTime( $booking['end_datetime'] );

                        // Check if new booking starts before existing booking ends + buffer
                        if ( $day_buffer > 0 ) {
                            $booking_end_copy = clone $booking_end;
                            $booking_end_copy->modify( "+{$day_buffer} minutes" );
                            if ( $start < $booking_end_copy && $end > $booking_start ) {
                                return false;
                            }
                        } else {
                            // Check for direct conflict
                            if ( $start < $booking_end && $end > $booking_start ) {
                                return false;
                            }
                        }
                    }
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
