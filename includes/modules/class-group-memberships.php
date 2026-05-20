<?php
/**
 * Group Memberships Module
 * 
 * Handles the registration of the booking_membership CPT and the addition 
 * of capacity/type fields to the booking_service CPT.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Simple_Booking_Group_Memberships {

    const CPT_MEMBERSHIP = 'booking_membership';

    /**
     * Register hooks and CPTs
     */
    public static function register() {
        require_once dirname( __FILE__ ) . '/class-membership-sync.php';
        Simple_Booking_Membership_Sync::register();

        // Register daily sync cron
        if ( ! wp_next_scheduled( 'simple_booking_daily_membership_sync' ) ) {
            wp_schedule_event( time(), 'daily', 'simple_booking_daily_membership_sync' );
        }
        add_action( 'simple_booking_daily_membership_sync', array( __CLASS__, 'daily_sync_all_groups' ) );

        require_once dirname( dirname( __FILE__ ) ) . '/frontend/class-membership-dashboard.php';
        new Simple_Booking_Membership_Dashboard();

        self::register_cpt();
        self::register_service_meta();

        // Hook into the Service meta box to add our fields
        add_action( 'add_meta_boxes_' . Simple_Booking_Service::POST_TYPE, array( __CLASS__, 'add_service_meta_box' ) );
        add_action( 'save_post_' . Simple_Booking_Service::POST_TYPE, array( __CLASS__, 'save_service_meta' ) );

        // Hook into the Membership meta box
        add_action( 'add_meta_boxes_' . self::CPT_MEMBERSHIP, array( __CLASS__, 'add_membership_meta_box' ) );
        add_action( 'save_post_' . self::CPT_MEMBERSHIP, array( __CLASS__, 'save_membership_meta' ) );

        // Hook into webhook creation to send welcome email
        add_action( 'simple_booking_membership_created', array( __CLASS__, 'send_welcome_email' ), 10, 3 );

        // Custom columns for Memberships CPT
        add_filter( 'manage_' . self::CPT_MEMBERSHIP . '_posts_columns', array( __CLASS__, 'set_membership_columns' ) );
        add_action( 'manage_' . self::CPT_MEMBERSHIP . '_posts_custom_column', array( __CLASS__, 'render_membership_columns' ), 10, 2 );

        // Export functionality
        add_action( 'restrict_manage_posts', array( __CLASS__, 'add_export_button' ) );
        add_action( 'admin_init', array( __CLASS__, 'handle_export' ) );
    }

    /**
     * Register the Membership Custom Post Type
     */
    private static function register_cpt() {
        register_post_type(
            self::CPT_MEMBERSHIP,
            array(
                'labels' => array(
                    'name'               => __( 'Memberships', 'simple-booking' ),
                    'singular_name'      => __( 'Membership', 'simple-booking' ),
                    'add_new'            => __( 'Add New', 'simple-booking' ),
                    'add_new_item'       => __( 'Add New Membership', 'simple-booking' ),
                    'edit_item'          => __( 'Edit Membership', 'simple-booking' ),
                    'new_item'           => __( 'New Membership', 'simple-booking' ),
                    'view_item'          => __( 'View Membership', 'simple-booking' ),
                    'search_items'       => __( 'Search Memberships', 'simple-booking' ),
                    'not_found'          => __( 'No memberships found', 'simple-booking' ),
                    'not_found_in_trash' => __( 'No memberships found in Trash', 'simple-booking' ),
                    'menu_name'          => __( 'Memberships', 'simple-booking' ),
                ),
                'public'       => false,
                'show_ui'      => true,
                'show_in_menu' => 'edit.php?post_type=booking_service',
                'supports'     => array( 'title' ),
                'has_archive'  => false,
                'show_in_rest' => false,
            )
        );

        // Register meta fields
        register_post_meta( self::CPT_MEMBERSHIP, '_customer_name', array( 'type' => 'string', 'single' => true, 'sanitize_callback' => 'sanitize_text_field' ) );
        register_post_meta( self::CPT_MEMBERSHIP, '_customer_email', array( 'type' => 'string', 'single' => true, 'sanitize_callback' => 'sanitize_email' ) );
        register_post_meta( self::CPT_MEMBERSHIP, '_service_id', array( 'type' => 'integer', 'single' => true, 'sanitize_callback' => 'absint' ) );
        register_post_meta( self::CPT_MEMBERSHIP, '_stripe_subscription_id', array( 'type' => 'string', 'single' => true, 'sanitize_callback' => 'sanitize_text_field' ) );
        register_post_meta( self::CPT_MEMBERSHIP, '_stripe_customer_id', array( 'type' => 'string', 'single' => true, 'sanitize_callback' => 'sanitize_text_field' ) );
        register_post_meta( self::CPT_MEMBERSHIP, '_status', array( 'type' => 'string', 'single' => true, 'sanitize_callback' => 'sanitize_text_field', 'default' => 'active' ) );
    }

    /**
     * Define custom columns for Memberships
     */
    public static function set_membership_columns( $columns ) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['customer_name'] = __( 'Customer', 'simple-booking' );
        $new_columns['customer_email'] = __( 'Email', 'simple-booking' );
        $new_columns['service'] = __( 'Group', 'simple-booking' );
        $new_columns['status'] = __( 'Status', 'simple-booking' );
        $new_columns['date'] = $columns['date'];
        return $new_columns;
    }

    /**
     * Render custom columns for Memberships
     */
    public static function render_membership_columns( $column, $post_id ) {
        switch ( $column ) {
            case 'customer_name':
                echo esc_html( get_post_meta( $post_id, '_customer_name', true ) );
                break;
            case 'customer_email':
                echo esc_html( get_post_meta( $post_id, '_customer_email', true ) );
                break;
            case 'service':
                $service_id = get_post_meta( $post_id, '_service_id', true );
                if ( $service_id ) {
                    $service = get_post( $service_id );
                    echo $service ? esc_html( $service->post_title ) : __( 'Unknown', 'simple-booking' );
                } else {
                    echo '-';
                }
                break;
            case 'status':
                $status = get_post_meta( $post_id, '_status', true ) ?: 'active';
                $color = 'active' === $status ? 'green' : ( 'canceled' === $status ? 'red' : 'orange' );
                echo '<span style="color: ' . esc_attr( $color ) . '; font-weight: bold;">' . esc_html( ucfirst( $status ) ) . '</span>';
                break;
        }
    }

    /**
     * Add Export button to Memberships list
     */
    public static function add_export_button( $post_type ) {
        if ( self::CPT_MEMBERSHIP === $post_type ) {
            echo '<input type="submit" name="export_memberships" id="export_memberships" class="button button-primary" style="margin-left:5px;" value="' . esc_attr__( 'Export to CSV', 'simple-booking' ) . '">';
        }
    }

    /**
     * Handle CSV Export request
     */
    public static function handle_export() {
        if ( ! isset( $_GET['export_memberships'] ) ) {
            return;
        }

        if ( ! isset( $_GET['post_type'] ) || self::CPT_MEMBERSHIP !== $_GET['post_type'] ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to export memberships.', 'simple-booking' ) );
        }

        $filename = 'memberships-export-' . date( 'Y-m-d' ) . '.csv';
        
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=' . $filename );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );
        
        // Add UTF-8 BOM for proper Excel rendering
        fputs( $output, $bom = ( chr(0xEF) . chr(0xBB) . chr(0xBF) ) );

        fputcsv( $output, array(
            __( 'Name', 'simple-booking' ),
            __( 'Email', 'simple-booking' ),
            __( 'Phone', 'simple-booking' ),
            __( 'Group', 'simple-booking' ),
            __( 'Status', 'simple-booking' ),
            __( 'Sign-up Date', 'simple-booking' ),
        ) );

        $args = array(
            'post_type'      => self::CPT_MEMBERSHIP,
            'posts_per_page' => -1,
            'post_status'    => 'any',
        );

        $memberships = get_posts( $args );

        foreach ( $memberships as $membership ) {
            $name    = get_post_meta( $membership->ID, '_customer_name', true );
            $email   = get_post_meta( $membership->ID, '_customer_email', true );
            $phone   = get_post_meta( $membership->ID, '_customer_phone', true );
            $status  = get_post_meta( $membership->ID, '_status', true ) ?: 'active';
            $date    = get_the_date( 'Y-m-d H:i:s', $membership );
            
            $service_id = get_post_meta( $membership->ID, '_service_id', true );
            $service_name = '';
            if ( $service_id ) {
                $service = get_post( $service_id );
                $service_name = $service ? $service->post_title : 'Unknown';
            }

            fputcsv( $output, array(
                $name,
                $email,
                $phone,
                $service_name,
                ucfirst( $status ),
                $date,
            ) );
        }

        fclose( $output );
        exit;
    }

    /**
     * Register new meta fields on the booking_service CPT
     */
    private static function register_service_meta() {
        register_post_meta(
            Simple_Booking_Service::POST_TYPE,
            '_service_type',
            array(
                'type'              => 'string',
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => 'one_off',
            )
        );

        register_post_meta(
            Simple_Booking_Service::POST_TYPE,
            '_group_capacity',
            array(
                'type'              => 'integer',
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => 'absint',
                'default'           => 0,
            )
        );

        register_post_meta(
            Simple_Booking_Service::POST_TYPE,
            '_meeting_schedules',
            array(
                'type'              => 'array',
                'single'            => true,
                'show_in_rest'      => false,
            )
        );
    }

    /**
     * Add the Membership Settings meta box to the Service CPT
     */
    public static function add_service_meta_box() {
        add_meta_box(
            'booking_service_membership',
            __( 'Group Membership Settings', 'simple-booking' ),
            array( __CLASS__, 'render_service_meta_box' ),
            Simple_Booking_Service::POST_TYPE,
            'normal',
            'high'
        );
    }

    /**
     * Render the Membership Settings meta box
     */
    public static function render_service_meta_box( $post ) {
        wp_nonce_field( 'simple_booking_membership_save', 'simple_booking_membership_nonce' );

        $service_type     = get_post_meta( $post->ID, '_service_type', true ) ?: 'one_off';
        $group_capacity   = get_post_meta( $post->ID, '_group_capacity', true ) ?: 0;
        $meeting_schedule = get_post_meta( $post->ID, '_meeting_schedule', true );

        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="service_type"><?php _e( 'Service Type', 'simple-booking' ); ?></label>
                </th>
                <td>
                    <select name="service_type" id="service_type">
                        <option value="one_off" <?php selected( $service_type, 'one_off' ); ?>><?php _e( 'One-off Booking (Standard)', 'simple-booking' ); ?></option>
                        <option value="recurring_group" <?php selected( $service_type, 'recurring_group' ); ?>><?php _e( 'Recurring Group Membership', 'simple-booking' ); ?></option>
                    </select>
                    <p class="description"><?php _e( 'Determines whether this service creates standard one-off bookings or recurring subscription memberships.', 'simple-booking' ); ?></p>
                </td>
            </tr>
            <tr class="membership-field" <?php echo 'one_off' === $service_type ? 'style="display:none;"' : ''; ?>>
                <th scope="row">
                    <label for="group_capacity"><?php _e( 'Group Capacity', 'simple-booking' ); ?></label>
                </th>
                <td>
                    <input type="number" name="group_capacity" id="group_capacity" value="<?php echo esc_attr( $group_capacity ); ?>" min="0" class="small-text" />
                    <p class="description"><?php _e( 'Maximum number of active members allowed. Enter 0 for unlimited.', 'simple-booking' ); ?></p>
                </td>
            </tr>
            <tr class="membership-field" <?php echo 'one_off' === $service_type ? 'style="display:none;"' : ''; ?>>
                <th scope="row">
                    <label for="meeting_schedule"><?php _e( 'Meeting Schedule (Text)', 'simple-booking' ); ?></label>
                </th>
                <td>
                    <input type="text" name="meeting_schedule" id="meeting_schedule" value="<?php echo esc_attr( $meeting_schedule ); ?>" class="regular-text" placeholder="e.g., Every Tuesday at 10:00 AM" />
                    <p class="description"><?php _e( 'A human-readable description displayed on the booking form.', 'simple-booking' ); ?></p>
                </td>
            </tr>
            <?php
            $meeting_schedules = get_post_meta( $post->ID, '_meeting_schedules', true );
            if ( ! is_array( $meeting_schedules ) ) {
                $meeting_schedules = array();
            }
            ?>
            <tr class="membership-field" <?php echo 'one_off' === $service_type ? 'style="display:none;"' : ''; ?>>
                <th scope="row">
                    <label><?php _e( 'Multiple Schedule Rules', 'simple-booking' ); ?></label>
                </th>
                <td>
                    <div id="sb-schedule-repeater" style="margin-bottom: 15px;">
                        <!-- JS injected rows -->
                    </div>
                    <button type="button" id="sb-add-schedule-rule" class="button"><?php _e( '+ Add Schedule Rule', 'simple-booking' ); ?></button>
                    <p class="description" style="margin-top: 10px;"><?php _e( 'Add as many rules as you need. "Week/Day" follows Google Calendar standards. "Day of Month" allows exact dates.', 'simple-booking' ); ?></p>
                    
                    <input type="hidden" id="sb_meeting_schedules_data" name="meeting_schedules_json" value="<?php echo esc_attr( wp_json_encode( $meeting_schedules ) ); ?>" />
                </td>
            </tr>
        </table>
        
        <style>
            .sb-rule-box {
                background: #f9f9f9;
                border: 1px solid #ccd0d4;
                padding: 15px;
                margin-bottom: 10px;
                border-radius: 4px;
                position: relative;
            }
            .sb-rule-box select, .sb-rule-box input { margin-bottom: 10px; }
            .sb-rule-remove {
                position: absolute;
                top: 10px;
                right: 10px;
                color: #a00;
                cursor: pointer;
                text-decoration: none;
            }
            .sb-rule-remove:hover { color: #f00; }
        </style>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var typeSelect = document.getElementById('service_type');
                var membershipFields = document.querySelectorAll('.membership-field');
                
                typeSelect.addEventListener('change', function() {
                    var isMembership = this.value === 'recurring_group';
                    membershipFields.forEach(function(field) {
                        field.style.display = isMembership ? 'table-row' : 'none';
                    });
                });

                // Repeater Logic
                var container = document.getElementById('sb-schedule-repeater');
                var addButton = document.getElementById('sb-add-schedule-rule');
                var dataInput = document.getElementById('sb_meeting_schedules_data');
                var rules = [];
                
                try {
                    rules = JSON.parse(dataInput.value) || [];
                } catch(e) {
                    rules = [];
                }

                function renderRules() {
                    container.innerHTML = '';
                    rules.forEach(function(rule, index) {
                        var box = document.createElement('div');
                        box.className = 'sb-rule-box';
                        
                        var html = '<a href="#" class="sb-rule-remove" data-index="'+index+'">Remove</a>';
                        
                        html += '<select class="rule-type" data-index="'+index+'" style="font-weight:bold; margin-right: 10px;">';
                        html += '<option value="week_day" '+(rule.type === 'week_day' ? 'selected' : '')+'>Rule: By Week & Day (Recommended)</option>';
                        html += '<option value="date" '+(rule.type === 'date' ? 'selected' : '')+'>Rule: By Day of the Month</option>';
                        html += '</select>';

                        html += '<input type="time" class="rule-time" data-index="'+index+'" value="'+(rule.time || '18:00')+'" required />';
                        html += '<hr style="margin: 10px 0; border: 0; border-top: 1px solid #eee;" />';

                        if (rule.type === 'week_day' || !rule.type) {
                            html += '<p style="margin:0 0 5px 0;"><strong>Select Weeks:</strong></p>';
                            var weeks = ['1', '2', '3', '4', 'last', 'every'];
                            var weekLabels = ['1st', '2nd', '3rd', '4th', 'Last', 'Every'];
                            var ruleWeeks = rule.weeks || ['every'];
                            
                            weeks.forEach(function(w, i) {
                                var checked = ruleWeeks.includes(w) ? 'checked' : '';
                                html += '<label style="margin-right: 10px;"><input type="checkbox" class="rule-week" data-index="'+index+'" value="'+w+'" '+checked+'> '+weekLabels[i]+'</label>';
                            });
                            
                            html += '<p style="margin:10px 0 5px 0;"><strong>Select Day:</strong></p>';
                            html += '<select class="rule-day" data-index="'+index+'">';
                            var days = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
                            days.forEach(function(d) {
                                var selected = rule.day === d ? 'selected' : '';
                                html += '<option value="'+d+'" '+selected+'>'+d.charAt(0).toUpperCase() + d.slice(1)+'</option>';
                            });
                            html += '</select>';
                        } else {
                            html += '<p style="margin:0 0 5px 0;"><strong>Select Day of the Month:</strong></p>';
                            html += '<select class="rule-date" data-index="'+index+'">';
                            for(var i=1; i<=31; i++) {
                                var selected = (rule.date == i) ? 'selected' : '';
                                html += '<option value="'+i+'" '+selected+'>'+i+'</option>';
                            }
                            html += '<option value="last" '+(rule.date === 'last' ? 'selected' : '')+'>Last Day of Month</option>';
                            html += '</select>';
                            
                            var skipChecked = rule.skip_weekends ? 'checked' : '';
                            html += '<p style="margin-top:10px;"><label><input type="checkbox" class="rule-skip" data-index="'+index+'" '+skipChecked+'> If this lands on a Saturday or Sunday, automatically move to Friday.</label></p>';
                        }

                        box.innerHTML = html;
                        container.appendChild(box);
                    });
                    
                    dataInput.value = JSON.stringify(rules);
                }

                container.addEventListener('change', function(e) {
                    var idx = e.target.getAttribute('data-index');
                    if (idx === null) return;
                    
                    if (e.target.classList.contains('rule-type')) rules[idx].type = e.target.value;
                    if (e.target.classList.contains('rule-time')) rules[idx].time = e.target.value;
                    if (e.target.classList.contains('rule-day')) rules[idx].day = e.target.value;
                    if (e.target.classList.contains('rule-date')) rules[idx].date = e.target.value;
                    if (e.target.classList.contains('rule-skip')) rules[idx].skip_weekends = e.target.checked;
                    
                    if (e.target.classList.contains('rule-week')) {
                        var checkboxes = container.querySelectorAll('.rule-week[data-index="'+idx+'"]:checked');
                        rules[idx].weeks = Array.from(checkboxes).map(cb => cb.value);
                    }
                    
                    renderRules();
                });

                container.addEventListener('click', function(e) {
                    if (e.target.classList.contains('sb-rule-remove')) {
                        e.preventDefault();
                        var idx = e.target.getAttribute('data-index');
                        rules.splice(idx, 1);
                        renderRules();
                    }
                });

                addButton.addEventListener('click', function() {
                    rules.push({ type: 'week_day', weeks: ['every'], day: 'monday', time: '18:00' });
                    renderRules();
                });

                renderRules();
            });
        </script>
        <?php
    }

    /**
     * Save the Membership Settings meta fields
     */
    public static function save_service_meta( $post_id ) {
        if ( ! isset( $_POST['simple_booking_membership_nonce'] ) || ! wp_verify_nonce( $_POST['simple_booking_membership_nonce'], 'simple_booking_membership_save' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        if ( isset( $_POST['service_type'] ) ) {
            update_post_meta( $post_id, '_service_type', sanitize_text_field( $_POST['service_type'] ) );
        }

        if ( isset( $_POST['group_capacity'] ) ) {
            update_post_meta( $post_id, '_group_capacity', absint( $_POST['group_capacity'] ) );
        }

        if ( isset( $_POST['meeting_schedule'] ) ) {
            update_post_meta( $post_id, '_meeting_schedule', sanitize_text_field( $_POST['meeting_schedule'] ) );
        }

        if ( isset( $_POST['meeting_schedules_json'] ) ) {
            $schedules = json_decode( stripslashes( $_POST['meeting_schedules_json'] ), true );
            if ( is_array( $schedules ) ) {
                update_post_meta( $post_id, '_meeting_schedules', $schedules );
            }
        }

        // Trigger sync to Google if enabled
        if ( 'recurring_group' === get_post_meta( $post_id, '_service_type', true ) ) {
            self::sync_service_to_google( $post_id );
        }
    }

    /**
     * Add the Membership Data meta box to the Membership CPT
     */
    public static function add_membership_meta_box() {
        add_meta_box(
            'booking_membership_data',
            __( 'Membership Details', 'simple-booking' ),
            array( __CLASS__, 'render_membership_meta_box' ),
            self::CPT_MEMBERSHIP,
            'normal',
            'high'
        );
    }

    /**
     * Render the Membership Data meta box
     */
    public static function render_membership_meta_box( $post ) {
        wp_nonce_field( 'simple_booking_membership_data_save', 'simple_booking_membership_data_nonce' );

        $customer_name  = get_post_meta( $post->ID, '_customer_name', true );
        $customer_email = get_post_meta( $post->ID, '_customer_email', true );
        $service_id     = get_post_meta( $post->ID, '_service_id', true );
        $sub_id         = get_post_meta( $post->ID, '_stripe_subscription_id', true );
        $cus_id         = get_post_meta( $post->ID, '_stripe_customer_id', true );
        $status         = get_post_meta( $post->ID, '_status', true ) ?: 'active';

        // Get all group services to populate dropdown
        $services = get_posts( array(
            'post_type' => Simple_Booking_Service::POST_TYPE,
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_service_type',
                    'value' => 'recurring_group',
                ),
            ),
        ) );
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="customer_name"><?php _e( 'Customer Name', 'simple-booking' ); ?></label></th>
                <td><input type="text" name="customer_name" id="customer_name" value="<?php echo esc_attr( $customer_name ); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="customer_email"><?php _e( 'Customer Email', 'simple-booking' ); ?></label></th>
                <td><input type="email" name="customer_email" id="customer_email" value="<?php echo esc_attr( $customer_email ); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="service_id"><?php _e( 'Group Service', 'simple-booking' ); ?></label></th>
                <td>
                    <select name="service_id" id="service_id">
                        <option value=""><?php _e( '&mdash; Select Group &mdash;', 'simple-booking' ); ?></option>
                        <?php foreach ( $services as $svc ) : ?>
                            <option value="<?php echo esc_attr( $svc->ID ); ?>" <?php selected( $service_id, $svc->ID ); ?>>
                                <?php echo esc_html( $svc->post_title ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="status"><?php _e( 'Status', 'simple-booking' ); ?></label></th>
                <td>
                    <select name="status" id="status">
                        <option value="active" <?php selected( $status, 'active' ); ?>><?php _e( 'Active', 'simple-booking' ); ?></option>
                        <option value="past_due" <?php selected( $status, 'past_due' ); ?>><?php _e( 'Past Due', 'simple-booking' ); ?></option>
                        <option value="canceled" <?php selected( $status, 'canceled' ); ?>><?php _e( 'Canceled', 'simple-booking' ); ?></option>
                    </select>
                    <p class="description"><?php _e( 'Stripe automatically updates this via Webhooks.', 'simple-booking' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="stripe_subscription_id"><?php _e( 'Stripe Subscription ID', 'simple-booking' ); ?></label></th>
                <td><input type="text" name="stripe_subscription_id" id="stripe_subscription_id" value="<?php echo esc_attr( $sub_id ); ?>" class="regular-text" readonly /></td>
            </tr>
            <tr>
                <th scope="row"><label for="stripe_customer_id"><?php _e( 'Stripe Customer ID', 'simple-booking' ); ?></label></th>
                <td><input type="text" name="stripe_customer_id" id="stripe_customer_id" value="<?php echo esc_attr( $cus_id ); ?>" class="regular-text" readonly /></td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save the Membership Data meta fields
     */
    public static function save_membership_meta( $post_id ) {
        if ( ! isset( $_POST['simple_booking_membership_data_nonce'] ) || ! wp_verify_nonce( $_POST['simple_booking_membership_data_nonce'], 'simple_booking_membership_data_save' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        if ( isset( $_POST['customer_name'] ) ) update_post_meta( $post_id, '_customer_name', sanitize_text_field( $_POST['customer_name'] ) );
        if ( isset( $_POST['customer_email'] ) ) update_post_meta( $post_id, '_customer_email', sanitize_email( $_POST['customer_email'] ) );
        if ( isset( $_POST['service_id'] ) ) update_post_meta( $post_id, '_service_id', absint( $_POST['service_id'] ) );
        if ( isset( $_POST['status'] ) ) update_post_meta( $post_id, '_status', sanitize_text_field( $_POST['status'] ) );
    }

    /**
     * Send welcome email when a membership is created
     */
    public static function send_welcome_email( $membership_id, $metadata, $service ) {
        if ( ! $membership_id || empty( $metadata->customer_email ) ) {
            return;
        }

        $customer_name  = sanitize_text_field( $metadata->customer_name );
        $customer_email = sanitize_email( $metadata->customer_email );
        $service_name   = sanitize_text_field( $service['name'] );
        $meeting_link   = get_post_meta( $service['id'], '_meeting_link', true );
        $schedule       = get_post_meta( $service['id'], '_meeting_schedule', true );

        $site_name = get_bloginfo( 'name' );

        $subject = sprintf( __( 'Welcome to %1$s at %2$s', 'simple-booking' ), $service_name, $site_name );

        $password_to_send = '';
        $user = get_user_by( 'email', $customer_email );
        if ( ! $user ) {
            $password = wp_generate_password( 12, false );
            $user_id = wp_create_user( $customer_email, $password, $customer_email );
            if ( ! is_wp_error( $user_id ) ) {
                wp_update_user( array(
                    'ID'         => $user_id,
                    'first_name' => $customer_name,
                ) );
                $password_to_send = $password;
            }
        }

        $customer_tz = get_post_meta( $membership_id, '_customer_timezone', true );
        $site_tz = wp_timezone();
        
        $body = sprintf( __( "Dear %s,\n\nThank you for subscribing to %s!\n\nYour membership is now active.\n", 'simple-booking' ), $customer_name, $service_name );
        
        $body .= "\n" . __( "Meeting Access:", 'simple-booking' ) . "\n";
        $body .= __( "Unique meeting links will be sent to you automatically 24 hours and 1 hour before each session starts.", 'simple-booking' ) . "\n";
        $body .= __( "You can also find the 'Join' button in your member portal 10 minutes before the session begins.", 'simple-booking' ) . "\n";

        if ( ! empty( $password_to_send ) ) {
            $body .= "\n" . __( "Manage Your Membership:", 'simple-booking' ) . "\n";
            $body .= sprintf( __( "Dashboard Link: %s", 'simple-booking' ), home_url( '/member-portal/' ) );
            $body .= sprintf( __( "\nUsername: %s", 'simple-booking' ), $customer_email );
            $body .= sprintf( __( "\nPassword: %s\n", 'simple-booking' ), $password_to_send );
            $body .= __( "Please log in to view your upcoming sessions and manage your billing.\n", 'simple-booking' );
        } else {
            $body .= sprintf( __( "\nYou can manage your membership anytime from the Dashboard:\n%s\n", 'simple-booking' ), home_url( '/member-portal/' ) );
        }

        $body .= sprintf( __( "\nWe look forward to seeing you!\n\nBest regards,\n%s", 'simple-booking' ), $site_name );

        $from_name  = simple_booking()->get_setting( 'email_from_name', $site_name );
        
        // Fallback default sender
        $from_email = simple_booking()->get_setting( 'email_from_email', '' );
        if ( empty( $from_email ) ) {
            $sitename = strtolower( $_SERVER['SERVER_NAME'] );
            if ( substr( $sitename, 0, 4 ) === 'www.' ) {
                $sitename = substr( $sitename, 4 );
            }
            $from_email = 'wordpress@' . $sitename;
        }

        $reply_to_email = simple_booking()->get_setting( 'notification_email', get_option( 'admin_email' ) );

        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            sprintf( 'From: %s <%s>', $from_name, $from_email ),
            sprintf( 'Reply-To: %s', $reply_to_email ),
        );

        // Optional: generate ICS calendar
        $attachments = array();
        if ( ! empty( $schedule ) && class_exists( 'Simple_Booking_Ics_Provider' ) ) {
            $ics_path = self::generate_membership_ics( $service_name, $schedule, $meeting_link );
            if ( $ics_path && file_exists( $ics_path ) ) {
                $attachments[] = $ics_path;
            }
        }

        wp_mail( $customer_email, $subject, $body, $headers, $attachments );
        Simple_Booking::log_email( $customer_email, $subject, 'welcome' );

        // Clean up temporary ICS file if created
        if ( ! empty( $attachments ) && file_exists( $attachments[0] ) ) {
            wp_delete_file( $attachments[0] );
        }

        // --- Send Admin Notification ---
        $admin_email = simple_booking()->get_setting( 'notification_email', get_option( 'admin_email' ) );
        if ( ! empty( $admin_email ) ) {
            $admin_subject = sprintf( __( 'New Membership: %1$s joined %2$s', 'simple-booking' ), $customer_name, $service_name );
            $admin_body    = sprintf( __( "A new user has subscribed to a group membership.\n\nCustomer: %s\nEmail: %s\nGroup: %s\nStatus: Active\n\nLog in to the WordPress dashboard to view details.", 'simple-booking' ), $customer_name, $customer_email, $service_name );
            $admin_headers = array( 'Content-Type: text/plain; charset=UTF-8' );
            wp_mail( $admin_email, $admin_subject, $admin_body, $admin_headers );
        }
    }

    /**
     * Generate an ICS file for the recurring schedule
     */
    private static function generate_membership_ics( $service_name, $schedule, $meeting_link ) {
        $upload_dir = wp_upload_dir();
        if ( ! empty( $upload_dir['error'] ) ) {
            return false;
        }

        // Create a generic ICS event that represents the recurring group.
        // For MVP, we will just create a single non-expiring recurring rule 
        // starting from today. More complex recurrence parsing can be added later.
        
        $tz = wp_timezone_string();
        $now = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
        $dtstamp = $now->format( 'Ymd\THis\Z' );

        $uid = md5( uniqid( mt_rand(), true ) ) . '@' . parse_url( home_url(), PHP_URL_HOST );

        $ics = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//Simple Booking//Group Membership//EN\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        $ics .= "METHOD:PUBLISH\r\n";

        // VTIMEZONE is usually required for strict clients, but for MVP we use floating time or skip it
        $ics .= "BEGIN:VEVENT\r\n";
        $ics .= "UID:{$uid}\r\n";
        $ics .= "DTSTAMP:{$dtstamp}\r\n";
        
        // Start time today
        $ics .= "DTSTART;TZID={$tz}:" . date( 'Ymd\THis', strtotime( '+1 day' ) ) . "\r\n";
        // End time 1 hour later
        $ics .= "DTEND;TZID={$tz}:" . date( 'Ymd\THis', strtotime( '+1 day +1 hour' ) ) . "\r\n";
        
        // Let user know this is just a placeholder recurrence since natural language parsing is hard
        $ics .= "SUMMARY:" . self::escape_ics( $service_name ) . "\r\n";
        
        $desc = "Schedule: " . $schedule;
        if ( $meeting_link ) {
            $desc .= "\\nLink: " . $meeting_link;
            $ics .= "URL:" . self::escape_ics( $meeting_link ) . "\r\n";
            $ics .= "LOCATION:" . self::escape_ics( $meeting_link ) . "\r\n";
        }
        $ics .= "DESCRIPTION:" . self::escape_ics( $desc ) . "\r\n";
        
        $ics .= "STATUS:CONFIRMED\r\n";
        $ics .= "END:VEVENT\r\n";
        $ics .= "END:VCALENDAR\r\n";

        $filename = 'membership_' . wp_generate_password( 8, false ) . '.ics';
        $filepath = wp_normalize_path( trailingslashit( $upload_dir['basedir'] ) . $filename );

        if ( file_put_contents( $filepath, $ics ) ) {
            return $filepath;
        }

        return false;
    }

    /**
     * Escape ICS text
     */
    private static function escape_ics( $text ) {
        $text = str_replace( "\\", "\\\\", $text );
        $text = str_replace( ",", "\\,", $text );
        $text = str_replace( ";", "\\;", $text );
        $text = str_replace( "\n", "\\n", $text );
        return $text;
    }
    /**
     * Sync a group service's recurring schedule to Google Calendar for the next 30 days.
     */
    public static function sync_service_to_google( $service_id ) {
        if ( ! class_exists( 'Simple_Booking_Google_Calendar' ) ) {
            return;
        }

        $google = new Simple_Booking_Google_Calendar();
        if ( ! $google->is_connected() ) {
            return;
        }

        $service_name = get_the_title( $service_id );
        $schedules = get_post_meta( $service_id, '_meeting_schedules', true );
        if ( ! is_array( $schedules ) || empty( $schedules ) ) {
            return;
        }

        // Signature to identify synced group events
        $signature = "[SB-SYNC-GROUP-{$service_id}]";

        // Iterate next 30 days
        $start = new DateTime( 'today', wp_timezone() );
        $end = clone $start;
        $end->modify( '+30 days' );

        // 1. Purge existing synced events using stored IDs for 100% accuracy
        $old_ids = get_post_meta( $service_id, '_synced_event_ids', true );
        if ( is_array( $old_ids ) && ! empty( $old_ids ) ) {
            foreach ( $old_ids as $old_id ) {
                $google->delete_event( $old_id );
            }
        }
        delete_post_meta( $service_id, '_synced_event_ids' );

        $new_ids = array();
        $duration = intval( get_post_meta( $service_id, '_service_duration', true ) ) ?: 60;

        $current = clone $start;
        while ( $current <= $end ) {
            $date_str = $current->format( 'Y-m-d' );
            foreach ( $schedules as $rule ) {
                if ( Simple_Booking_Membership_Sync::does_rule_match_date( $rule, $date_str ) ) {
                    // Create the event in Google
                    $date_str = $current->format( 'Y-m-d' );
                    $time_str = ! empty( $rule['time'] ) ? $rule['time'] : '18:00';
                    
                    $start_dt = $date_str . ' ' . $time_str . ':00';
                    $end_dt = date( 'Y-m-d H:i:s', strtotime( "$start_dt + {$duration} minutes" ) );

                    $result = $google->create_event( array(
                        'service_name'     => $service_name . ' (Group Session)',
                        'customer_name'    => 'Members',
                        'start_datetime'   => $start_dt,
                        'end_datetime'     => $end_dt,
                        'description'      => "Recurring Group Session Sync. $signature",
                        'auto_google_meet' => true,
                    ) );

                    if ( ! is_wp_error( $result ) && ! empty( $result['meeting_link'] ) ) {
                        $new_ids[] = $result['event_id'];
                        $links = get_post_meta( $service_id, '_synced_session_links', true ) ?: array();
                        $links[ $date_str . '_' . $time_str ] = $result['meeting_link'];
                        update_post_meta( $service_id, '_synced_session_links', $links );
                    }
                }
            }
            $current->modify( '+1 day' );
        }

        // Store new IDs for the next purge
        if ( ! empty( $new_ids ) ) {
            update_post_meta( $service_id, '_synced_event_ids', $new_ids );
        }
    }

    /**
     * Daily cron to sync all group services
     */
    public static function daily_sync_all_groups() {
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

        foreach ( $services as $service ) {
            self::sync_service_to_google( $service->ID );
        }
    }
}
