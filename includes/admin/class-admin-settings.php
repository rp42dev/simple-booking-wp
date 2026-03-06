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
                'placeholder' => "Dear {customer_name},\n\nYour booking has been confirmed!\n\nService: {service_name}\nDate: {booking_date}\nTime: {booking_time}\n\nMeeting Link: {meeting_link}\n\nThank you!",
                'rows'        => 10,
            )
        );
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings( $input ) {
        $sanitized = array();

        // Stripe
        $sanitized['stripe_publishable_key'] = isset( $input['stripe_publishable_key'] ) ?
            sanitize_text_field( $input['stripe_publishable_key'] ) : '';
        $sanitized['stripe_secret_key'] = isset( $input['stripe_secret_key'] ) ?
            sanitize_text_field( $input['stripe_secret_key'] ) : '';
        $sanitized['stripe_webhook_secret'] = isset( $input['stripe_webhook_secret'] ) ?
            sanitize_text_field( $input['stripe_webhook_secret'] ) : '';

        // Google
        $sanitized['google_client_id'] = isset( $input['google_client_id'] ) ?
            sanitize_text_field( $input['google_client_id'] ) : '';
        $sanitized['google_client_secret'] = isset( $input['google_client_secret'] ) ?
            sanitize_text_field( $input['google_client_secret'] ) : '';
        $sanitized['google_calendar_id'] = isset( $input['google_calendar_id'] ) ?
            sanitize_text_field( $input['google_calendar_id'] ) : '';

        // Debug toggle
        $sanitized['debug_mode'] = ! empty( $input['debug_mode'] ) ? 1 : 0;

        // Email customization
        $sanitized['email_subject'] = isset( $input['email_subject'] ) ?
            sanitize_text_field( $input['email_subject'] ) : '';
        $sanitized['email_body'] = isset( $input['email_body'] ) ?
            sanitize_textarea_field( $input['email_body'] ) : '';

        // Working schedule: per-day enabled and start/end times
        $sanitized['schedule'] = array();
        $days = array( 'monday','tuesday','wednesday','thursday','friday','saturday','sunday' );
        if ( isset( $input['schedule'] ) && is_array( $input['schedule'] ) ) {
            foreach ( $days as $day ) {
                $item = array(
                    'enabled' => 0,
                    'start'   => '',
                    'end'     => '',
                );
                if ( isset( $input['schedule'][ $day ] ) && is_array( $input['schedule'][ $day ] ) ) {
                    $item['enabled'] = ! empty( $input['schedule'][ $day ]['enabled'] ) ? 1 : 0;
                    if ( ! empty( $input['schedule'][ $day ]['start'] ) && preg_match( '/^\d{2}:\d{2}$/', $input['schedule'][ $day ]['start'] ) ) {
                        $item['start'] = sanitize_text_field( $input['schedule'][ $day ]['start'] );
                    }
                    if ( ! empty( $input['schedule'][ $day ]['end'] ) && preg_match( '/^\d{2}:\d{2}$/', $input['schedule'][ $day ]['end'] ) ) {
                        $item['end'] = sanitize_text_field( $input['schedule'][ $day ]['end'] );
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
        echo '<p>' . __( 'Available template variables:', 'simple-booking' ) . ' <code>{customer_name}</code>, <code>{service_name}</code>, <code>{booking_date}</code>, <code>{booking_time}</code>, <code>{meeting_link}</code>, <code>{timezone}</code></p>';
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
        echo '<table class="form-table" id="schedule-table"><tbody>';
        foreach ( $days as $key => $label ) {
            $item = isset( $schedule[ $key ] ) ? $schedule[ $key ] : array();
            $enabled = ! empty( $item['enabled'] );
            $start = isset( $item['start'] ) ? $item['start'] : '';
            $end   = isset( $item['end'] ) ? $item['end'] : '';
            echo '<tr>';
            echo '<th scope="row">' . esc_html( $label ) . '</th>';
            echo '<td>';
            echo '<label><input type="checkbox" name="simple_booking_settings[schedule][' . esc_attr( $key ) . '][enabled]" value="1" ' . checked( $enabled, true, false ) . '> ' . __( 'Open', 'simple-booking' ) . '</label> ';
            echo '<input type="time" name="simple_booking_settings[schedule][' . esc_attr( $key ) . '][start]" value="' . esc_attr( $start ) . '" class="small-text" placeholder="08:00"> &ndash; ';
            echo '<input type="time" name="simple_booking_settings[schedule][' . esc_attr( $key ) . '][end]" value="' . esc_attr( $end ) . '" class="small-text" placeholder="17:00">';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
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
