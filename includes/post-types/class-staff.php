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

        // Register REST endpoints
        add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
    }

    /**
     * Register REST routes
     */
    public static function register_rest_routes() {
        register_rest_route(
            'simple-booking/v1',
            'calendars/list',
            array(
                'methods'             => 'GET',
                'callback'            => array( __CLASS__, 'rest_list_calendars' ),
                'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
            )
        );
    }

    /**
     * Check if user has permission to manage calendars
     */
    public static function check_admin_permission() {
        return current_user_can( 'manage_options' );
    }

    /**
     * REST endpoint to list available calendars
     */
    public static function rest_list_calendars() {
        try {
            if ( ! class_exists( 'Simple_Booking_Calendar_Provider_Manager' ) ) {
                return array(
                    'success' => false,
                    'message' => __( 'Calendar provider manager not available', 'simple-booking' ),
                );
            }

            $manager = new Simple_Booking_Calendar_Provider_Manager();
            $provider = $manager->get_provider();

            if ( ! $provider || is_wp_error( $provider ) ) {
                return array(
                    'success' => false,
                    'message' => __( 'No calendar provider configured', 'simple-booking' ),
                );
            }

            if ( ! $provider->is_connected() ) {
                return array(
                    'success' => false,
                    'message' => __( 'Calendar provider not connected', 'simple-booking' ),
                );
            }

            $calendars = $provider->list_calendars();

            if ( is_wp_error( $calendars ) ) {
                return array(
                    'success' => false,
                    'message' => $calendars->get_error_message(),
                );
            }

            return array(
                'success'   => true,
                'calendars' => $calendars,
            );
        } catch ( Exception $e ) {
            return array(
                'success' => false,
                'message' => $e->getMessage(),
            );
        }
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

        // Staff Calendar ID (optional override)
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
        <div style="display: none;" data-rest-nonce="<?php echo wp_create_nonce( 'wp_rest' ); ?>"></div>
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
                    <label for="staff_calendar_id"><?php _e( 'Calendar', 'simple-booking' ); ?></label>
                </th>
                <td>
                    <div style="display: flex; gap: 8px; align-items: flex-start; flex-wrap: wrap;">
                        <select id="staff_calendar_id"
                                name="staff_calendar_id"
                                style="flex: 1; min-width: 200px;">
                            <option value=""><?php _e( '-- Use Global Calendar --', 'simple-booking' ); ?></option>
                            <option value="" disabled>---</option>
                            <option value="loading" disabled><?php _e( 'Loading calendars...', 'simple-booking' ); ?></option>
                        </select>
                        <button type="button" 
                                id="reload_calendars_btn" 
                                class="button button-secondary"
                                style="white-space: nowrap;">
                            <?php _e( '↻ Load Calendars', 'simple-booking' ); ?>
                        </button>
                    </div>
                    <p class="description">
                        <?php _e( 'Optional: Select a specific calendar for this staff member. Click "Load Calendars" to fetch available calendars from your connected provider.', 'simple-booking' ); ?>
                    </p>
                    <p style="color: #666; font-size: 12px; margin-top: 8px;">
                        <span id="calendar_load_status"></span>
                    </p>
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
        <script type="text/javascript">
        (function() {
            const selectElement = document.getElementById('staff_calendar_id');
            const reloadBtn = document.getElementById('reload_calendars_btn');
            const statusSpan = document.getElementById('calendar_load_status');
            const currentValue = <?php echo json_encode( $staff_calendar_id ); ?>;
            
            // Get nonce from data attribute
            const nonceElement = document.querySelector('[data-rest-nonce]');
            const nonce = nonceElement ? nonceElement.getAttribute('data-rest-nonce') : '';

            // Load calendars on button click
            reloadBtn.addEventListener('click', function(e) {
                e.preventDefault();
                loadCalendars();
            });

            // Auto-load calendars on page load
            window.addEventListener('load', function() {
                // Small delay to ensure DOM is fully ready
                setTimeout(loadCalendars, 100);
            });

            function loadCalendars() {
                if (!nonce) {
                    statusSpan.textContent = '<?php _e( 'Error: Security token missing', 'simple-booking' ); ?>';
                    statusSpan.style.color = '#a90000';
                    console.error('Nonce not found');
                    reloadBtn.disabled = false;
                    return;
                }
                
                reloadBtn.disabled = true;
                statusSpan.textContent = '<?php _e( 'Loading...', 'simple-booking' ); ?>';
                statusSpan.style.color = '#0073aa';

                fetch('/wp-json/simple-booking/v1/calendars/list', {
                    method: 'GET',
                    headers: {
                        'X-WP-Nonce': nonce,
                        'Content-Type': 'application/json',
                    },
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status + ': ' + response.statusText);
                    }
                    return response.json();
                })
                .then(data => {
                    reloadBtn.disabled = false;
                    
                    if (!data.success) {
                        statusSpan.textContent = '<?php _e( 'Error:', 'simple-booking' ); ?> ' + (data.message || '<?php _e( 'Unknown error', 'simple-booking' ); ?>');
                        statusSpan.style.color = '#a90000';
                        console.error('API error:', data);
                        return;
                    }

                    // Clear existing options (keep the first one)
                    while (selectElement.options.length > 2) {
                        selectElement.remove(2);
                    }

                    if (!data.calendars || data.calendars.length === 0) {
                        statusSpan.textContent = '<?php _e( 'No calendars found', 'simple-booking' ); ?>';
                        statusSpan.style.color = '#666';
                        return;
                    }

                    // Add calendar options
                    data.calendars.forEach(calendar => {
                        const option = document.createElement('option');
                        option.value = calendar.id;
                        option.textContent = calendar.name;
                        if (calendar.isDefault || calendar.primary) {
                            option.textContent += ' (Primary)';
                        }
                        selectElement.appendChild(option);
                    });

                    // Restore previous selection if it exists
                    if (currentValue && Array.from(selectElement.options).some(opt => opt.value === currentValue)) {
                        selectElement.value = currentValue;
                    }

                    statusSpan.textContent = '<?php _e( 'Calendars loaded successfully', 'simple-booking' ); ?>';
                    statusSpan.style.color = '#007015';
                })
                .catch(error => {
                    reloadBtn.disabled = false;
                    statusSpan.textContent = '<?php _e( 'Error:', 'simple-booking' ); ?> ' + error.message;
                    statusSpan.style.color = '#a90000';
                    console.error('Error fetching calendars:', error);
                });
            }
        })();
        </script>
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
                'relation' => 'OR',
                array(
                    'key'     => '_staff_active',
                    'value'   => '1',
                    'compare' => '=',
                ),
                array(
                    'key'     => '_staff_active',
                    'compare' => 'NOT EXISTS',
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
