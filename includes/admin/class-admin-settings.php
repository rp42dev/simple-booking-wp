<?php
/**
 * Admin Settings
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Simple_Booking_Admin_Settings
 */
class Simple_Booking_Admin_Settings {

    /**
     * Settings page slug
     */
    const PAGE_SLUG = 'simple-booking-settings';

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Add menu page
     */
    public function add_menu_page() {
        add_options_page(
            __( 'Simple Booking Settings', 'simple-booking' ),
            __( 'Simple Booking', 'simple-booking' ),
            'manage_options',
            self::PAGE_SLUG,
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'simple_booking_settings',
            'simple_booking_settings',
            array( $this, 'sanitize_settings' )
        );

        // Stripe Settings
        add_settings_section(
            'simple_booking_stripe',
            __( 'Stripe Settings', 'simple-booking' ),
            array( $this, 'render_stripe_section' ),
            self::PAGE_SLUG
        );

        add_settings_field(
            'stripe_publishable_key',
            __( 'Publishable Key', 'simple-booking' ),
            array( $this, 'render_text_field' ),
            self::PAGE_SLUG,
            'simple_booking_stripe',
            array(
                'name'        => 'stripe_publishable_key',
                'placeholder' => 'pk_test_xxxxxxxxxxxxxx',
            )
        );

        add_settings_field(
            'stripe_secret_key',
            __( 'Secret Key', 'simple-booking' ),
            array( $this, 'render_text_field' ),
            self::PAGE_SLUG,
            'simple_booking_stripe',
            array(
                'name'        => 'stripe_secret_key',
                'placeholder' => 'sk_test_xxxxxxxxxxxxxx',
                'type'        => 'password',
            )
        );

        add_settings_field(
            'stripe_webhook_secret',
            __( 'Webhook Secret', 'simple-booking' ),
            array( $this, 'render_text_field' ),
            self::PAGE_SLUG,
            'simple_booking_stripe',
            array(
                'name'        => 'stripe_webhook_secret',
                'placeholder' => 'whsec_xxxxxxxxxxxxxx',
            )
        );

        // Calendar Provider Selection
        add_settings_section(
            'simple_booking_calendar_provider',
            __( 'Calendar Provider', 'simple-booking' ),
            array( $this, 'render_calendar_provider_section' ),
            self::PAGE_SLUG
        );

        add_settings_field(
            'calendar_provider',
            __( 'Calendar Provider', 'simple-booking' ),
            array( $this, 'render_calendar_provider_select' ),
            self::PAGE_SLUG,
            'simple_booking_calendar_provider'
        );

        // Google Calendar Settings
        add_settings_section(
            'simple_booking_google',
            __( 'Google Calendar Settings', 'simple-booking' ),
            array( $this, 'render_google_section' ),
            self::PAGE_SLUG
        );

        add_settings_field(
            'google_client_id',
            __( 'Client ID', 'simple-booking' ),
            array( $this, 'render_text_field' ),
            self::PAGE_SLUG,
            'simple_booking_google',
            array(
                'name'        => 'google_client_id',
                'placeholder' => 'xxxxxxxxxxxxxx.apps.googleusercontent.com',
            )
        );

        add_settings_field(
            'google_client_secret',
            __( 'Client Secret', 'simple-booking' ),
            array( $this, 'render_text_field' ),
            self::PAGE_SLUG,
            'simple_booking_google',
            array(
                'name'        => 'google_client_secret',
                'placeholder' => 'xxxxxxxxxxxxxx',
                'type'        => 'password',
            )
        );

        add_settings_field(
            'google_calendar_id',
            __( 'Calendar ID', 'simple-booking' ),
            array( $this, 'render_text_field' ),
            self::PAGE_SLUG,
            'simple_booking_google',
            array(
                'name'        => 'google_calendar_id',
                'placeholder' => 'xxxxxxxxxxxxxx@group.calendar.google.com',
            )
        );

        add_settings_field(
            'google_redirect_uri',
            __( 'Redirect URI', 'simple-booking' ),
            array( $this, 'render_google_redirect' ),
            self::PAGE_SLUG,
            'simple_booking_google'
        );

        // General Settings
        add_settings_section(
            'simple_booking_general',
            __( 'General Settings', 'simple-booking' ),
            array( $this, 'render_general_section' ),
            self::PAGE_SLUG
        );

        add_settings_field(
            'site_timezone',
            __( 'Site Timezone', 'simple-booking' ),
            array( $this, 'render_timezone' ),
            self::PAGE_SLUG,
            'simple_booking_general'
        );

        add_settings_field(
            'debug_mode',
            __( 'Debug Mode', 'simple-booking' ),
            array( $this, 'render_checkbox_field' ),
            self::PAGE_SLUG,
            'simple_booking_general',
            array(
                'name' => 'debug_mode',
                'description' => __( 'Enable verbose debugging output to log files. Disable for production.', 'simple-booking' ),
            )
        );

        // Working schedule (per-day)
        add_settings_section(
            'simple_booking_hours',
            __( 'Working Schedule', 'simple-booking' ),
            array( $this, 'render_hours_section' ),
            self::PAGE_SLUG
        );

        add_settings_field(
            'schedule',
            __( 'Weekly Schedule', 'simple-booking' ),
            array( $this, 'render_schedule' ),
            self::PAGE_SLUG,
            'simple_booking_hours'
        );

        // Email Customization
        add_settings_section(
            'simple_booking_email',
            __( 'Email Customization', 'simple-booking' ),
            array( $this, 'render_email_section' ),
            self::PAGE_SLUG
        );

        add_settings_field(
            'email_subject',
            __( 'Email Subject', 'simple-booking' ),
            array( $this, 'render_text_field' ),
            self::PAGE_SLUG,
            'simple_booking_email',
            array(
                'name'        => 'email_subject',
                'placeholder' => 'Booking Confirmed - {service_name}',
            )
        );

        add_settings_field(
            'email_body',
            __( 'Email Body Template', 'simple-booking' ),
            array( $this, 'render_textarea_field' ),
            self::PAGE_SLUG,
            'simple_booking_email',
            array(
                'name'        => 'email_body',
                'placeholder' => "Dear {customer_name},\n\nYour booking has been confirmed!\n\nService: {service_name}\nDate: {booking_date}\nTime: {booking_time}\n\nMeeting Link: {meeting_link}\n\nManage your booking:\nReschedule: {reschedule_link}\nCancel: {cancel_link}\n\nThank you!\n\n{site_name}",
                'rows'        => 10,
            )
        );

        // Refund Settings
        add_settings_section(
            'simple_booking_refunds',
            __( 'Refund Settings', 'simple-booking' ),
            array( $this, 'render_refund_section' ),
            self::PAGE_SLUG
        );

        add_settings_field(
            'refund_percentage',
            __( 'Refund Percentage', 'simple-booking' ),
            array( $this, 'render_number_field' ),
            self::PAGE_SLUG,
            'simple_booking_refunds',
            array(
                'name'        => 'refund_percentage',
                'min'         => 0,
                'max'         => 100,
                'default'     => 100,
                'description' => __( 'Percentage of booking amount to refund when customer cancels (0-100)', 'simple-booking' ),
            )
        );

        add_settings_field(
            'refund_policy',
            __( 'Refund Policy Text', 'simple-booking' ),
            array( $this, 'render_textarea_field' ),
            self::PAGE_SLUG,
            'simple_booking_refunds',
            array(
                'name'        => 'refund_policy',
                'placeholder' => 'Describe your refund policy for customers (optional)',
                'rows'        => 5,
            )
        );

        // Webhook Settings
        add_settings_section(
            'simple_booking_webhook',
            __( 'Webhook Settings', 'simple-booking' ),
            array( $this, 'render_webhook_section' ),
            self::PAGE_SLUG
        );

        add_settings_field(
            'webhook_url',
            __( 'Webhook URL', 'simple-booking' ),
            array( $this, 'render_text_field' ),
            self::PAGE_SLUG,
            'simple_booking_webhook',
            array(
                'name'        => 'webhook_url',
                'placeholder' => 'https://example.com/webhook',
                'description' => __( 'Optional endpoint for future booking.created automation.', 'simple-booking' ),
            )
        );
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings( $input ) {
        $sanitized = array();
        $existing = get_option( 'simple_booking_settings', array() );

        // Stripe
        $sanitized['stripe_publishable_key'] = isset( $input['stripe_publishable_key'] ) ?
            sanitize_text_field( $input['stripe_publishable_key'] ) : '';
        $sanitized['stripe_secret_key'] = isset( $input['stripe_secret_key'] ) ?
            sanitize_text_field( $input['stripe_secret_key'] ) : '';
        $sanitized['stripe_webhook_secret'] = isset( $input['stripe_webhook_secret'] ) ?
            sanitize_text_field( $input['stripe_webhook_secret'] ) : '';

        // Google
        $sanitized['google_client_id'] = isset( $input['google_client_id'] ) ?
            sanitize_text_field( $input['google_client_id'] ) : ( isset( $existing['google_client_id'] ) ? $existing['google_client_id'] : '' );
        $sanitized['google_client_secret'] = isset( $input['google_client_secret'] ) ?
            sanitize_text_field( $input['google_client_secret'] ) : ( isset( $existing['google_client_secret'] ) ? $existing['google_client_secret'] : '' );
        $sanitized['google_calendar_id'] = isset( $input['google_calendar_id'] ) ?
            sanitize_text_field( $input['google_calendar_id'] ) : ( isset( $existing['google_calendar_id'] ) ? $existing['google_calendar_id'] : '' );

        // Calendar Provider Selection (with Pro gating)
        $allowed_providers = array( 'google', 'outlook', 'ics' );
        $provider = isset( $input['calendar_provider'] ) ? sanitize_text_field( $input['calendar_provider'] ) : 'ics';
        
        if ( ! in_array( $provider, $allowed_providers, true ) ) {
            $provider = 'ics'; // Default to ICS if invalid
        }
        
        // Check if trying to set Pro provider without Pro license
        $is_pro = ( defined( 'SIMPLE_BOOKING_FORCE_PRO' ) && SIMPLE_BOOKING_FORCE_PRO );
        if ( ! $is_pro && class_exists( 'Simple_Booking_License_Manager' ) ) {
            $license = new Simple_Booking_License_Manager();
            $is_pro = $license->is_pro_active();
        }

        // If not Pro, only allow ICS
        if ( ! $is_pro && in_array( $provider, array( 'google', 'outlook' ), true ) ) {
            $provider = 'ics';
            add_settings_error(
                'simple_booking_settings',
                'pro_required',
                __( 'Pro license required for Google Calendar and Outlook. Reverting to ICS.', 'simple-booking' ),
                'warning'
            );
        }
        
        $sanitized['calendar_provider'] = $provider;

        // Debug toggle
        $sanitized['debug_mode'] = ! empty( $input['debug_mode'] ) ? 1 : 0;

        // Email customization
        $sanitized['email_subject'] = isset( $input['email_subject'] ) ?
            sanitize_text_field( $input['email_subject'] ) : '';
        $sanitized['email_body'] = isset( $input['email_body'] ) ?
            sanitize_textarea_field( $input['email_body'] ) : '';

        // Refund settings
        $sanitized['refund_percentage'] = isset( $input['refund_percentage'] ) ?
            intval( $input['refund_percentage'] ) : 100;
        // Ensure percentage is between 0-100
        $sanitized['refund_percentage'] = min( 100, max( 0, $sanitized['refund_percentage'] ) );
        $sanitized['refund_policy'] = isset( $input['refund_policy'] ) ?
            sanitize_textarea_field( $input['refund_policy'] ) : '';

        // Webhook
        $sanitized['webhook_url'] = isset( $input['webhook_url'] ) ?
            esc_url_raw( $input['webhook_url'] ) : '';

        // Working schedule: per-day enabled, start/end times, and buffer
        $sanitized['schedule'] = array();
        $days = array( 'monday','tuesday','wednesday','thursday','friday','saturday','sunday' );
        if ( isset( $input['schedule'] ) && is_array( $input['schedule'] ) ) {
            foreach ( $days as $day ) {
                $item = array(
                    'enabled' => 0,
                    'start'   => '',
                    'end'     => '',
                    'buffer'  => 0,
                );
                if ( isset( $input['schedule'][ $day ] ) && is_array( $input['schedule'][ $day ] ) ) {
                    $item['enabled'] = ! empty( $input['schedule'][ $day ]['enabled'] ) ? 1 : 0;
                    if ( ! empty( $input['schedule'][ $day ]['start'] ) && preg_match( '/^\d{2}:\d{2}$/', $input['schedule'][ $day ]['start'] ) ) {
                        $item['start'] = sanitize_text_field( $input['schedule'][ $day ]['start'] );
                    }
                    if ( ! empty( $input['schedule'][ $day ]['end'] ) && preg_match( '/^\d{2}:\d{2}$/', $input['schedule'][ $day ]['end'] ) ) {
                        $item['end'] = sanitize_text_field( $input['schedule'][ $day ]['end'] );
                    }
                    if ( ! empty( $input['schedule'][ $day ]['buffer'] ) ) {
                        $item['buffer'] = absint( $input['schedule'][ $day ]['buffer'] );
                    }
                }
                $sanitized['schedule'][ $day ] = $item;
            }
        }

        return $sanitized;
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e( 'Simple Booking Settings', 'simple-booking' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'simple_booking_settings' );
                do_settings_sections( self::PAGE_SLUG );
                submit_button( __( 'Save Settings', 'simple-booking' ) );
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render Stripe section
     */
    public function render_stripe_section() {
        echo '<p>' . __( 'Enter your Stripe API keys. You can find these in your Stripe Dashboard under Developers > API keys.', 'simple-booking' ) . '</p>';
    }

    /**
     * Render Google section
     */
    public function render_google_section() {
        $options = get_option( 'simple_booking_settings', array() );
        $client_id = isset( $options['google_client_id'] ) ? $options['google_client_id'] : '';
        $client_secret = isset( $options['google_client_secret'] ) ? $options['google_client_secret'] : '';

        // Check for success message from OAuth redirect
        if ( isset( $_GET['google'] ) && $_GET['google'] === 'connected' ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __( 'Google Calendar connected successfully!', 'simple-booking' ) . '</p></div>';
        }

        // Check if Client ID is missing
        if ( empty( $client_id ) ) {
            echo '<p style="color: #d32626; font-weight: bold;">' . __( 'Please enter your Google Client ID first and save settings.', 'simple-booking' ) . '</p>';
            return;
        }

        echo '<p>' . __( 'Enter your Google Cloud credentials. You need to create a project in Google Cloud Console and enable the Google Calendar API.', 'simple-booking' ) . '</p>';

        // Check if connected (access token exists)
        $access_token = get_option( 'simple_booking_google_tokens', array() );

        if ( ! empty( $access_token ) && isset( $access_token['access_token'] ) ) {
            // Connected - show status and disconnect button
            echo '<p style="color: #2ecc71; font-weight: bold; font-size: 16px; margin-bottom: 15px;">' . __( 'Connected ✅', 'simple-booking' ) . '</p>';

            // Handle disconnect action
            if ( isset( $_GET['page'] ) && $_GET['page'] === self::PAGE_SLUG &&
                 isset( $_GET['google_disconnect'] ) && isset( $_GET['_wpnonce'] ) ) {

                if ( wp_verify_nonce( $_GET['_wpnonce'], 'google_disconnect_' . get_current_user_id() ) ) {
                    delete_option( 'simple_booking_google_tokens' );
                    echo '<div class="notice notice-success is-dismissible"><p>' . __( 'Google Calendar disconnected successfully.', 'simple-booking' ) . '</p></div>';
                    // Redirect to remove query args
                    echo '<script>window.location.href = "' . esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) ) . '";</script>';
                    return;
                }
            }

            // Show disconnect button with nonce
            $disconnect_url = add_query_arg(
                array(
                    'page'            => self::PAGE_SLUG,
                    'google_disconnect' => '1',
                    '_wpnonce'        => wp_create_nonce( 'google_disconnect_' . get_current_user_id() ),
                ),
                admin_url( 'options-general.php' )
            );
            ?>
            <a href="<?php echo esc_url( $disconnect_url ); ?>"
               class="button"
               style="background: #d32626; border-color: #b91c1c; color: white;"
               onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to disconnect Google Calendar?', 'simple-booking' ) ); ?>');">
                <?php _e( 'Disconnect Google Calendar', 'simple-booking' ); ?>
            </a>
            <?php
        } else {
            // Not connected - show connect button
            // Use Google Calendar class to generate OAuth URL with proper state handling
            if ( class_exists( 'Simple_Booking_Google_Calendar' ) ) {
                $google_calendar = new Simple_Booking_Google_Calendar();
                $oauth_url = $google_calendar->get_oauth_url( true );
            } else {
                $oauth_url = null;
            }

            if ( ! $oauth_url ) {
                echo '<p style="color: #d32626;">' . __( 'Unable to generate OAuth URL. Please check your Client ID settings.', 'simple-booking' ) . '</p>';
                return;
            }
            ?>
            <a href="<?php echo esc_url( $oauth_url ); ?>"
               class="button button-primary"
               style="margin-top: 10px;">
                <?php _e( 'Connect / Authorize Google Calendar', 'simple-booking' ); ?>
            </a>
            <p class="description" style="margin-top: 10px;">
                <?php _e( 'Click the button above to authorize the plugin to create calendar events.', 'simple-booking' ); ?>
            </p>
            <?php
        }
    }

    /**
     * Render Calendar Provider section
     */
    public function render_calendar_provider_section() {
        echo '<p>' . __( 'Select which calendar system to use for booking sync. Google Calendar and Outlook require a Pro license.', 'simple-booking' ) . '</p>';
    }

    /**
     * Render Calendar Provider selection dropdown
     */
    public function render_calendar_provider_select() {
        $options = get_option( 'simple_booking_settings', array() );
        $current_provider = isset( $options['calendar_provider'] ) ? $options['calendar_provider'] : 'ics';
        
        // Check Pro status
        $is_pro = ( defined( 'SIMPLE_BOOKING_FORCE_PRO' ) && SIMPLE_BOOKING_FORCE_PRO );
        if ( ! $is_pro && class_exists( 'Simple_Booking_License_Manager' ) ) {
            $license = new Simple_Booking_License_Manager();
            $is_pro = $license->is_pro_active();
        }
        
        ?>
        <select name="simple_booking_settings[calendar_provider]" id="simple-booking-calendar-provider-select">
            <option value="ics" <?php selected( $current_provider, 'ics' ); ?>>
                <?php _e( 'ICS Feed (Free)', 'simple-booking' ); ?>
            </option>
            <option value="google" <?php selected( $current_provider, 'google' ); ?> <?php disabled( $is_pro, false ); ?>>
                <?php _e( 'Google Calendar', 'simple-booking' ); ?> <?php if ( ! $is_pro ) { echo '(Pro)'; } ?>
            </option>
            <option value="outlook" <?php selected( $current_provider, 'outlook' ); ?> <?php disabled( $is_pro, false ); ?>>
                <?php _e( 'Outlook Calendar', 'simple-booking' ); ?> <?php if ( ! $is_pro ) { echo '(Pro)'; } ?>
            </option>
        </select>
        <p class="description">
            <?php 
            if ( ! $is_pro ) {
                _e( 'Google Calendar and Outlook are Pro features. Upgrade your license to enable them.', 'simple-booking' );
            } else {
                _e( 'Your Pro license enables all calendar options.', 'simple-booking' );
            }
            ?>
        </p>
        <script>
        (function() {
            function toggleGoogleSection() {
                var providerSelect = document.getElementById('simple-booking-calendar-provider-select');
                if (!providerSelect) {
                    return;
                }

                var headings = document.querySelectorAll('.wrap h2');
                var googleHeading = null;
                for (var i = 0; i < headings.length; i++) {
                    if (headings[i].textContent && headings[i].textContent.indexOf('Google Calendar Settings') !== -1) {
                        googleHeading = headings[i];
                        break;
                    }
                }

                if (!googleHeading) {
                    return;
                }

                var showGoogle = providerSelect.value === 'google';
                googleHeading.style.display = showGoogle ? '' : 'none';

                var node = googleHeading.nextElementSibling;
                while (node && node.tagName !== 'H2') {
                    node.style.display = showGoogle ? '' : 'none';
                    node = node.nextElementSibling;
                }
            }

            document.addEventListener('DOMContentLoaded', function() {
                var providerSelect = document.getElementById('simple-booking-calendar-provider-select');
                if (!providerSelect) {
                    return;
                }
                providerSelect.addEventListener('change', toggleGoogleSection);
                toggleGoogleSection();
            });
        })();
        </script>
        <?php
    }

    /**
     * Render General section
     */
    public function render_general_section() {
        echo '<p>' . __( 'General plugin settings.', 'simple-booking' ) . '</p>';
    }

    /**
     * Render Hours section
     */
    public function render_hours_section() {
        echo '<p>' . __( 'Specify the days and hours during which bookings may begin.', 'simple-booking' ) . '</p>';
    }

    /**
     * Render Email section
     */
    public function render_email_section() {
        echo '<p>' . __( 'Customize the confirmation email sent to customers after booking.', 'simple-booking' ) . '</p>';
        echo '<p>' . __( 'Available template variables:', 'simple-booking' ) . ' <code>{customer_name}</code>, <code>{service_name}</code>, <code>{booking_date}</code>, <code>{booking_time}</code>, <code>{meeting_link}</code>, <code>{reschedule_link}</code>, <code>{cancel_link}</code>, <code>{timezone}</code>, <code>{site_name}</code></p>';
    }

    /**
     * Render Webhook section
     */
    public function render_webhook_section() {
        echo '<p>' . __( 'Configure automation endpoint settings.', 'simple-booking' ) . '</p>';
    }

    /**
     * Render Refund section
     */
    public function render_refund_section() {
        echo '<p>' . __( 'Configure refund settings for cancelled paid bookings.', 'simple-booking' ) . '</p>';
    }

    /**
     * Render working days checkboxes (unused but kept for backward compatibility)
     */
    public function render_working_days() {
        $options = get_option( 'simple_booking_settings', array() );
        $selected = isset( $options['working_days'] ) ? (array) $options['working_days'] : array();
        $days = array(
            '1' => __( 'Monday', 'simple-booking' ),
            '2' => __( 'Tuesday', 'simple-booking' ),
            '3' => __( 'Wednesday', 'simple-booking' ),
            '4' => __( 'Thursday', 'simple-booking' ),
            '5' => __( 'Friday', 'simple-booking' ),
            '6' => __( 'Saturday', 'simple-booking' ),
            '7' => __( 'Sunday', 'simple-booking' ),
        );
        foreach ( $days as $num => $label ) {
            $checked = in_array( $num, $selected, true ) ? 'checked' : '';
            echo '<label style="margin-right:10px;">';
            echo '<input type="checkbox" name="simple_booking_settings[working_days][]" value="' . esc_attr( $num ) . '" ' . $checked . '> ' . esc_html( $label );
            echo '</label>';
        }
    }

    /**
     * Render weekly schedule table
     */
    public function render_schedule() {
        $options = get_option( 'simple_booking_settings', array() );
        $schedule = isset( $options['schedule'] ) ? $options['schedule'] : array();
        $days = array(
            'monday'    => __( 'Monday', 'simple-booking' ),
            'tuesday'   => __( 'Tuesday', 'simple-booking' ),
            'wednesday' => __( 'Wednesday', 'simple-booking' ),
            'thursday'  => __( 'Thursday', 'simple-booking' ),
            'friday'    => __( 'Friday', 'simple-booking' ),
            'saturday'  => __( 'Saturday', 'simple-booking' ),
            'sunday'    => __( 'Sunday', 'simple-booking' ),
        );
        echo '<table class="form-table" style="width: 100%; border-collapse: collapse;"><thead>';
        echo '<tr style="background-color: #f5f5f5; border-bottom: 2px solid #ddd;">';
        echo '<th style="padding: 10px; text-align: left; width: 15%;">' . esc_html( __( 'Day', 'simple-booking' ) ) . '</th>';
        echo '<th style="padding: 10px; text-align: center; width: 10%;">' . esc_html( __( 'Open', 'simple-booking' ) ) . '</th>';
        echo '<th style="padding: 10px; text-align: center; width: 20%;">' . esc_html( __( 'Start Time', 'simple-booking' ) ) . '</th>';
        echo '<th style="padding: 10px; text-align: center; width: 20%;">' . esc_html( __( 'End Time', 'simple-booking' ) ) . '</th>';
        echo '<th style="padding: 10px; text-align: center; width: 20%;">' . esc_html( __( 'Buffer (min)', 'simple-booking' ) ) . '</th>';
        echo '</tr>';
        echo '</thead><tbody>';
        foreach ( $days as $key => $label ) {
            $item = isset( $schedule[ $key ] ) ? $schedule[ $key ] : array();
            $enabled = ! empty( $item['enabled'] );
            $start = isset( $item['start'] ) ? $item['start'] : '';
            $end   = isset( $item['end'] ) ? $item['end'] : '';
            $buffer = isset( $item['buffer'] ) ? absint( $item['buffer'] ) : 0;
            $row_style = $enabled ? '' : 'opacity: 0.6; background-color: #fafafa;';
            echo '<tr style="border-bottom: 1px solid #eee; ' . esc_attr( $row_style ) . '">';
            echo '<td style="padding: 10px;"><strong>' . esc_html( $label ) . '</strong></td>';
            echo '<td style="padding: 10px; text-align: center;"><input type="checkbox" name="simple_booking_settings[schedule][' . esc_attr( $key ) . '][enabled]" value="1" class="day-enabled-checkbox" data-day="' . esc_attr( $key ) . '" ' . checked( $enabled, true, false ) . '></td>';
            echo '<td style="padding: 10px; text-align: center;"><input type="time" name="simple_booking_settings[schedule][' . esc_attr( $key ) . '][start]" value="' . esc_attr( $start ) . '" class="day-start-time" data-day="' . esc_attr( $key ) . '" style="width: 100px;"></td>';
            echo '<td style="padding: 10px; text-align: center;"><input type="time" name="simple_booking_settings[schedule][' . esc_attr( $key ) . '][end]" value="' . esc_attr( $end ) . '" class="day-end-time" data-day="' . esc_attr( $key ) . '" style="width: 100px;"></td>';
            echo '<td style="padding: 10px; text-align: center;"><input type="number" name="simple_booking_settings[schedule][' . esc_attr( $key ) . '][buffer]" value="' . esc_attr( $buffer ) . '" min="0" step="5" class="day-buffer" data-day="' . esc_attr( $key ) . '" style="width: 80px; text-align: center;"></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        ?>
        <script>
        ( function() {
            const enabledCheckboxes = document.querySelectorAll( '.day-enabled-checkbox' );
            enabledCheckboxes.forEach( ( checkbox ) => {
                function updateDayRow() {
                    const day = checkbox.getAttribute( 'data-day' );
                    const row = checkbox.closest( 'tr' );
                    const startInput = document.querySelector( `.day-start-time[data-day="${day}"]` );
                    const endInput = document.querySelector( `.day-end-time[data-day="${day}"]` );
                    const bufferInput = document.querySelector( `.day-buffer[data-day="${day}"]` );

                    if ( checkbox.checked ) {
                        row.style.opacity = '1';
                        row.style.backgroundColor = '';
                        if ( startInput ) startInput.disabled = false;
                        if ( endInput ) endInput.disabled = false;
                        if ( bufferInput ) bufferInput.disabled = false;
                    } else {
                        row.style.opacity = '0.6';
                        row.style.backgroundColor = '#fafafa';
                        if ( startInput ) startInput.disabled = true;
                        if ( endInput ) endInput.disabled = true;
                        if ( bufferInput ) bufferInput.disabled = true;
                    }
                }

                updateDayRow();
                checkbox.addEventListener( 'change', updateDayRow );
            } );
        } )();
        </script>
        <?php
    }

    /**
     * Render a time input field (wrapper for render_text_field)
     */
    public function render_time_field( $args ) {
        // reuse the existing text-field renderer but ensure type is "time"
        $args['type'] = 'time';
        $this->render_text_field( $args );
    }

    /**
     * Render a generic checkbox field
     */
    public function render_checkbox_field( $args ) {
        $options = get_option( 'simple_booking_settings', array() );
        $checked = ! empty( $options[ $args['name'] ] ) ? 'checked' : '';
        ?>
        <label>
            <input type="checkbox" name="simple_booking_settings[<?php echo esc_attr( $args['name'] ); ?>]" value="1" <?php echo $checked; ?>>
            <?php if ( ! empty( $args['description'] ) ) : ?>
                <span class="description"><?php echo esc_html( $args['description'] ); ?></span>
            <?php endif; ?>
        </label>
        <?php
    }

    /**
     * Render text field
     */
    public function render_text_field( $args ) {
        $options = get_option( 'simple_booking_settings', array() );
        $value   = isset( $options[ $args['name'] ] ) ? $options[ $args['name'] ] : '';
        $type    = isset( $args['type'] ) ? $args['type'] : 'text';
        ?>
        <input type="<?php echo esc_attr( $type ); ?>"
               name="simple_booking_settings[<?php echo esc_attr( $args['name'] ); ?>]"
               value="<?php echo esc_attr( $value ); ?>"
               placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>"
               class="regular-text" />
        <?php if ( ! empty( $args['description'] ) ) : ?>
            <p class="description"><?php echo esc_html( $args['description'] ); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render textarea field
     */
    public function render_textarea_field( $args ) {
        $options = get_option( 'simple_booking_settings', array() );
        $value   = isset( $options[ $args['name'] ] ) ? $options[ $args['name'] ] : '';
        $rows    = isset( $args['rows'] ) ? $args['rows'] : 5;
        ?>
        <textarea name="simple_booking_settings[<?php echo esc_attr( $args['name'] ); ?>]"
                  rows="<?php echo esc_attr( $rows ); ?>"
                  class="large-text code"
                  placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
        <?php
    }

    /**
     * Render number field
     */
    public function render_number_field( $args ) {
        $options = get_option( 'simple_booking_settings', array() );
        $value   = isset( $options[ $args['name'] ] ) ? $options[ $args['name'] ] : ( isset( $args['default'] ) ? $args['default'] : 0 );
        $min     = isset( $args['min'] ) ? $args['min'] : 0;
        $max     = isset( $args['max'] ) ? $args['max'] : 100;
        ?>
        <input type="number"
               name="simple_booking_settings[<?php echo esc_attr( $args['name'] ); ?>]"
               value="<?php echo esc_attr( $value ); ?>"
               min="<?php echo esc_attr( $min ); ?>"
               max="<?php echo esc_attr( $max ); ?>"
               class="small-text" />
        <?php if ( ! empty( $args['description'] ) ) : ?>
            <p class="description"><?php echo esc_html( $args['description'] ); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render Google redirect URI
     */
    public function render_google_redirect() {
        $redirect_uri = rest_url( 'simple-booking/v1/google/oauth' );
        ?>
        <code><?php echo esc_url( $redirect_uri ); ?></code>
        <p class="description">
            <?php _e( 'Add this URL to your Google Cloud Console OAuth redirect URIs.', 'simple-booking' ); ?>
        </p>
        <?php
    }

    /**
     * Render timezone
     */
    public function render_timezone() {
        $timezone = wp_timezone_string();
        ?>
        <code><?php echo esc_html( $timezone ); ?></code>
        <p class="description">
            <?php _e( 'This is your WordPress site timezone. Bookings will be created in this timezone.', 'simple-booking' ); ?>
        </p>
        <?php
    }
}

// Initialize admin settings
new Simple_Booking_Admin_Settings();
