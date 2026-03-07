<?php
/**
 * Booking Form Frontend
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Simple_Booking_Form
 */
class Simple_Booking_Form {

    /**
     * Shortcode tag
     */
    const SHORTCODE = 'simple_booking_form';

    /**
     * Constructor
     */
    public function __construct() {
        add_shortcode( self::SHORTCODE, array( $this, 'render_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_ajax_simple_booking_submit', array( $this, 'handle_submission' ) );
        add_action( 'wp_ajax_nopriv_simple_booking_submit', array( $this, 'handle_submission' ) );

        // AJAX endpoint for fetching available time slots
        add_action( 'wp_ajax_simple_booking_get_slots', array( $this, 'ajax_get_slots' ) );
        add_action( 'wp_ajax_nopriv_simple_booking_get_slots', array( $this, 'ajax_get_slots' ) );
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        wp_enqueue_style(
            'simple-booking-form',
            SIMPLE_BOOKING_URL . 'assets/css/booking-form.css',
            array(),
            SIMPLE_BOOKING_VERSION
        );

        wp_enqueue_script(
            'jquery-ui-datepicker'
        );
        wp_enqueue_style(
            'jquery-ui-datepicker',
            '//ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/base/jquery-ui.css',
            array(),
            '1.12.1'
        );
        wp_enqueue_script(
            'simple-booking-form',
            SIMPLE_BOOKING_URL . 'assets/js/booking-form.js',
            array( 'jquery', 'jquery-ui-datepicker' ),
            time(), // always bust cache for debugging
            true
        );

        // compute schedule with fallback for legacy settings
        $schedule = simple_booking()->get_setting( 'schedule', array() );
        if ( empty( $schedule ) ) {
            $old_days = simple_booking()->get_setting( 'working_days', array() );
            $old_start = simple_booking()->get_setting( 'work_start', '' );
            $old_end   = simple_booking()->get_setting( 'work_end', '' );
            if ( $old_days || $old_start || $old_end ) {
                $names = array( 'monday','tuesday','wednesday','thursday','friday','saturday','sunday' );
                foreach ( $names as $i => $name ) {
                    $schedule[ $name ] = array(
                        'enabled' => in_array( (string) ( $i + 1 ), (array) $old_days, true ) ? 1 : 0,
                        'start'   => $old_start,
                        'end'     => $old_end,
                        'buffer'  => 0,
                    );
                }
            }
        } else {
            // Ensure buffer field exists for all days
            $names = array( 'monday','tuesday','wednesday','thursday','friday','saturday','sunday' );
            foreach ( $names as $name ) {
                if ( ! isset( $schedule[ $name ]['buffer'] ) ) {
                    $schedule[ $name ]['buffer'] = 0;
                }
            }
        }

        $minDate = date( 'Y-m-d', strtotime( $this->get_min_datetime() ) );
        wp_localize_script(
            'simple-booking-form',
            'simpleBooking',
            array(
                'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
                'nonce'     => wp_create_nonce( 'simple_booking_form_nonce' ),
                'publishableKey' => $this->get_stripe_publishable_key(),
                'schedule'   => $schedule,
                'timezone'   => wp_timezone_string(),
                'minDate'    => $minDate,
                'i18n'      => array(
                    'selectService' => __( 'Please select a service', 'simple-booking' ),
                    'selectDateTime' => __( 'Please select a date and time', 'simple-booking' ),
                    'selectTime'     => __( 'Select Time', 'simple-booking' ),
                    'enterName'     => __( 'Please enter your name', 'simple-booking' ),
                    'enterEmail'    => __( 'Please enter a valid email', 'simple-booking' ),
                    'enterPhone'    => __( 'Please enter your phone number', 'simple-booking' ),
                    'error'         => __( 'An error occurred. Please try again.', 'simple-booking' ),
                    'submitText'    => __( 'Proceed to Payment', 'simple-booking' ),
                    'submitFreeText' => __( 'Book Now', 'simple-booking' ),
                ),
            )
        );
    }

    /**
     * Get Stripe publishable key
     */
    private function get_stripe_publishable_key() {
        return simple_booking()->get_setting( 'stripe_publishable_key' );
    }

    /**
     * Render shortcode
     */
    public function render_shortcode( $atts = array() ) {
        $atts = shortcode_atts(
            array(
                'service'    => '',
                'service_id' => 0,
            ),
            $atts,
            self::SHORTCODE
        );

        // Get active services
        $services = Simple_Booking_Service::get_active_services();

        // Optional service filtering via shortcode attributes
        $selected_service_id = 0;

        if ( ! empty( $atts['service_id'] ) ) {
            $selected_service_id = absint( $atts['service_id'] );
        } elseif ( ! empty( $atts['service'] ) ) {
            $selected_service_id = $this->find_service_id_by_slug_or_title( $atts['service'], $services );
        }

        if ( $selected_service_id ) {
            $services = array_values(
                array_filter(
                    $services,
                    function( $service ) use ( $selected_service_id ) {
                        return absint( $service->ID ) === $selected_service_id;
                    }
                )
            );
        }

        if ( empty( $services ) ) {
            return '<p>' . __( 'No services available at the moment.', 'simple-booking' ) . '</p>';
        }

        $is_single_service_form = count( $services ) === 1;

        // Get timezone
        $timezone = wp_timezone_string();

        // Check for booking status in URL
        $booking_status = isset( $_GET['booking'] ) ? sanitize_text_field( $_GET['booking'] ) : '';

        ob_start();
        ?>
        <div id="simple-booking-form-wrapper">
            <?php if ( 'success' === $booking_status ) : ?>
                <div class="booking-message success">
                    <p><?php _e( 'Thank you! Your booking has been confirmed. Check your email for details.', 'simple-booking' ); ?></p>
                </div>
            <?php elseif ( 'cancelled' === $booking_status ) : ?>
                <div class="booking-message error">
                    <p><?php _e( 'Payment was cancelled. Please try again.', 'simple-booking' ); ?></p>
                </div>
            <?php endif; ?>

            <form id="simple-booking-form" class="simple-booking-form" method="post">
                <?php wp_nonce_field( 'simple_booking_submit', 'simple_booking_nonce' ); ?>

                <!-- Service Selection -->
                <div class="booking-field">
                    <label for="service_id"><?php _e( 'Select Service', 'simple-booking' ); ?> *</label>
                    <?php if ( $is_single_service_form ) : ?>
                        <?php
                        $single_service = $services[0];
                        $single_duration = get_post_meta( $single_service->ID, '_service_duration', true );
                        $single_duration = $single_duration ? $single_duration : 60;
                        $single_price_id = get_post_meta( $single_service->ID, '_stripe_price_id', true );
                        ?>
                        <p class="booking-selected-service"><strong><?php echo esc_html( $single_service->post_title . ' (' . $single_duration . ' min)' ); ?></strong></p>
                        <select id="service_id" name="service_id" required style="display:none;">
                            <option value="<?php echo esc_attr( $single_service->ID ); ?>"
                                    data-duration="<?php echo esc_attr( $single_duration ); ?>"
                                    data-has-price="<?php echo ! empty( $single_price_id ) ? '1' : '0'; ?>"
                                    selected>
                                <?php echo esc_html( $single_service->post_title . ' (' . $single_duration . ' min)' ); ?>
                            </option>
                        </select>
                    <?php else : ?>
                        <select id="service_id" name="service_id" required>
                            <option value=""><?php _e( 'Choose a service...', 'simple-booking' ); ?></option>
                            <?php foreach ( $services as $service ) : ?>
                                <?php
                                $duration = get_post_meta( $service->ID, '_service_duration', true );
                                $duration = $duration ? $duration : 60;
                                $price_id = get_post_meta( $service->ID, '_stripe_price_id', true );
                                ?>
                                <option value="<?php echo esc_attr( $service->ID ); ?>"
                                        data-duration="<?php echo esc_attr( $duration ); ?>"
                                        data-has-price="<?php echo ! empty( $price_id ) ? '1' : '0'; ?>">
                                    <?php echo esc_html( $service->post_title . ' (' . $duration . ' min)' ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>

                <!-- Date Selection -->
                <div class="booking-field">
                    <label for="booking_date"><?php _e( 'Select Date', 'simple-booking' ); ?> *</label>
                    <input type="text" readonly
                           id="booking_date"
                           name="booking_date"
                           required
                           min="<?php echo esc_attr( date( 'Y-m-d', strtotime( $this->get_min_datetime() ) ) ); ?>" />
                </div>

                <!-- Time Selection -->
                <div class="booking-field" id="time-container">
                    <?php // will be replaced by AJAX when a date is selected ?>
                    <label for="booking_time"><?php _e( 'Select Time', 'simple-booking' ); ?> *</label>
                    <select id="booking_time" name="booking_time" required>
                        <option value=""><?php _e( 'Choose a date first', 'simple-booking' ); ?></option>
                    </select>
                </div>

                <!-- Customer Name -->
                <div class="booking-field">
                    <label for="customer_name"><?php _e( 'Your Name', 'simple-booking' ); ?> *</label>
                    <input type="text"
                           id="customer_name"
                           name="customer_name"
                           required
                           maxlength="100" />
                </div>

                <!-- Customer Email -->
                <div class="booking-field">
                    <label for="customer_email"><?php _e( 'Email Address', 'simple-booking' ); ?> *</label>
                    <input type="email"
                           id="customer_email"
                           name="customer_email"
                           required
                           maxlength="150" />
                </div>

                <!-- Customer Phone -->
                <div class="booking-field">
                    <label for="customer_phone"><?php _e( 'Phone Number', 'simple-booking' ); ?> *</label>
                    <input type="tel"
                           id="customer_phone"
                           name="customer_phone"
                           required
                           maxlength="30" />
                </div>

                <!-- Hidden field for Stripe checkout -->
                <input type="hidden" id="stripe_session_id" name="stripe_session_id" value="" />

                <!-- Submit -->
                <div class="booking-field">
                    <button type="submit" id="booking-submit" class="booking-button">
                        <?php _e( 'Proceed to Payment', 'simple-booking' ); ?>
                    </button>
                </div>

                <!-- Messages -->
                <div id="booking-message" class="booking-message" style="display: none;"></div>
            </form>

            <!-- Timezone notice -->
            <p class="booking-timezone-notice">
                <?php printf( __( 'Times are shown in %s', 'simple-booking' ), '<strong>' . esc_html( $timezone ) . '</strong>' ); ?>
            </p>
            <script>
            console.log('simple booking form inline script executed');
            jQuery(function($){
                // quick ajax test log
                $('#booking_date, #service_id').on('change', function(){ console.log('inline change listener fired'); });
            });
            </script>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get minimum datetime (now + 1 hour)
     */
    private function get_min_datetime() {
        $timezone = wp_timezone();
        $now      = new DateTime( 'now', $timezone );
        $now->add( new DateInterval( 'PT1H' ) );
        return $now->format( 'Y-m-d\TH:i' );
    }

    /**
     * Handle form submission
     */
    public function handle_submission() {
        // Verify nonce
        if ( ! check_ajax_referer( 'simple_booking_form_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed', 'simple-booking' ) ) );
        }

        // Sanitize inputs
        $service_id       = isset( $_POST['service_id'] ) ? absint( $_POST['service_id'] ) : 0;
        // booking datetime can arrive as single field (new JS) or separate date/time (legacy)
        if ( isset( $_POST['booking_datetime'] ) && ! empty( $_POST['booking_datetime'] ) ) {
            $booking_datetime = sanitize_text_field( $_POST['booking_datetime'] );
        } elseif ( isset( $_POST['booking_date'], $_POST['booking_time'] ) ) {
            $booking_datetime = sanitize_text_field( $_POST['booking_date'] ) . 'T' . sanitize_text_field( $_POST['booking_time'] );
        } else {
            $booking_datetime = '';
        }
        $customer_name    = isset( $_POST['customer_name'] ) ? sanitize_text_field( $_POST['customer_name'] ) : '';
        $customer_email   = isset( $_POST['customer_email'] ) ? sanitize_email( $_POST['customer_email'] ) : '';
        $customer_phone  = isset( $_POST['customer_phone'] ) ? sanitize_text_field( $_POST['customer_phone'] ) : '';

        // Validate inputs
        $errors = array();

        if ( empty( $service_id ) ) {
            $errors[] = __( 'Please select a service', 'simple-booking' );
        }

        if ( empty( $booking_datetime ) ) {
            $errors[] = __( 'Please select a date and time', 'simple-booking' );
        } else {
            // log for debugging in case some formats slip through
            if ( simple_booking()->get_setting( 'debug_mode' ) ) {
                error_log( '[DEBUG] submit booking_datetime received: ' . $booking_datetime );
            }

            // server-side prevent past bookings as well
            try {
                $tz   = new DateTimeZone( wp_timezone_string() );
                $start_dt = new DateTime( $booking_datetime, $tz );
                $now      = new DateTime( 'now', $tz );
                if ( $start_dt < $now ) {
                    $errors[] = __( 'Selected time is in the past', 'simple-booking' );
                }
            } catch ( Exception $e ) {
                // ignore parse errors; they will be caught below
            }
        }

        if ( empty( $customer_name ) ) {
            $errors[] = __( 'Please enter your name', 'simple-booking' );
        }

        if ( empty( $customer_email ) || ! is_email( $customer_email ) ) {
            $errors[] = __( 'Please enter a valid email address', 'simple-booking' );
        }

        if ( empty( $customer_phone ) ) {
            $errors[] = __( 'Please enter your phone number', 'simple-booking' );
        }

        if ( ! empty( $errors ) ) {
            wp_send_json_error( array( 'message' => implode( ' ', $errors ) ) );
        }

        // Get service
        $service = Simple_Booking_Service::get_service( $service_id );
        if ( ! $service || ! $service['is_active'] ) {
            wp_send_json_error( array( 'message' => __( 'Selected service is not available', 'simple-booking' ) ) );
        }

        // server-side slot availability re-check
        if ( $booking_datetime ) {
            try {
                $tz    = new DateTimeZone( wp_timezone_string() );
                $start = new DateTime( $booking_datetime, $tz );
                $duration = intval( get_post_meta( $service_id, '_service_duration', true ) );
                if ( $duration <= 0 ) {
                    $duration = 60;
                }
                $end = clone $start;
                $end->modify( "+{$duration} minutes" );

                if ( class_exists( 'Simple_Booking_Google_Calendar' ) ) {
                    $google = new Simple_Booking_Google_Calendar();
                    $staff_availability = $google->find_available_staff( $service_id, $start->format( DateTime::ATOM ), $duration );
                    if ( ! is_array( $staff_availability ) ) {
                        wp_send_json_error( array( 'message' => __( 'Selected time slot is no longer available', 'simple-booking' ) ) );
                    }
                } else {
                    $events = $this->get_existing_events( $start->format( 'Y-m-d' ) );
                    if ( is_wp_error( $events ) ) {
                        wp_send_json_error( array( 'message' => $events->get_error_message() ) );
                    }

                    if ( ! $this->check_slot_availability( $start->format( DateTime::ATOM ), $end->format( DateTime::ATOM ), $events ) ) {
                        wp_send_json_error( array( 'message' => __( 'Selected time slot is no longer available', 'simple-booking' ) ) );
                    }
                }
            } catch ( Exception $e ) {
                // ignore parse problem, will be caught earlier
            }
        }

        // Prepare booking data
        $booking_data = array(
            'service_id'     => $service_id,
            'customer_name'  => $customer_name,
            'customer_email' => $customer_email,
            'customer_phone' => $customer_phone,
            'start_datetime' => $booking_datetime,
        );

        // Free booking flow (no Stripe Price ID)
        if ( empty( $service['stripe_price_id'] ) ) {
            $duration = isset( $service['duration'] ) ? absint( $service['duration'] ) : 60;
            if ( $duration <= 0 ) {
                $duration = 60;
            }

            try {
                $tz = new DateTimeZone( wp_timezone_string() );
                $start = new DateTime( $booking_datetime, $tz );
                $end = clone $start;
                $end->modify( "+{$duration} minutes" );
                $end_datetime = $end->format( 'Y-m-d\TH:i' );
            } catch ( Exception $e ) {
                wp_send_json_error( array( 'message' => __( 'Invalid booking time selected', 'simple-booking' ) ) );
            }

            $booking_payload = array(
                'service_id'        => $service_id,
                'service_name'      => $service['name'],
                'customer_name'     => $customer_name,
                'customer_email'    => $customer_email,
                'customer_phone'    => $customer_phone,
                'start_datetime'    => $booking_datetime,
                'end_datetime'      => $end_datetime,
                'meeting_link'      => isset( $service['meeting_link'] ) ? $service['meeting_link'] : '',
                'auto_google_meet'  => isset( $service['auto_google_meet'] ) ? $service['auto_google_meet'] : '0',
                'stripe_payment_id' => '',
            );

            $booking_id = Simple_Booking_Booking_Creator::create_booking( $booking_payload );

            if ( is_wp_error( $booking_id ) ) {
                wp_send_json_error( array( 'message' => $booking_id->get_error_message() ) );
            }

            Simple_Booking_Booking_Creator::send_confirmation_email( $booking_id );

            wp_send_json_success( array(
                'booking_id'    => $booking_id,
                'redirect_url'  => $this->get_success_redirect_url(),
                'message'       => __( 'Booking confirmed successfully.', 'simple-booking' ),
            ) );
        }

        // Paid booking flow (Stripe)
        $stripe = new Simple_Booking_Stripe();
        $session = $stripe->create_checkout_session( $service, $booking_data );

        if ( is_wp_error( $session ) ) {
            wp_send_json_error( array( 'message' => $session->get_error_message() ) );
        }

        if ( ! $session || ! isset( $session->id ) ) {
            wp_send_json_error( array( 'message' => __( 'Failed to create payment session', 'simple-booking' ) ) );
        }

        // Return session ID for redirect
        wp_send_json_success( array(
            'session_id' => $session->id,
            'url'        => $session->url,
        ) );
    }

    /**
     * Get success redirect URL for free bookings.
     *
     * @return string
     */
    private function get_success_redirect_url() {
        $page_id = get_option( 'simple_booking_success_page' );
        if ( $page_id ) {
            $page = get_post( $page_id );
            if ( $page && 'publish' === $page->post_status ) {
                return get_permalink( $page_id );
            }
            delete_option( 'simple_booking_success_page' );
        }

        return add_query_arg( 'booking', 'success', home_url( '/' ) );
    }

    /**
     * Find an active service ID by slug or title.
     *
     * @param string $service_ref Service slug or title.
     * @param array  $services Active service post objects.
     * @return int
     */
    private function find_service_id_by_slug_or_title( $service_ref, $services ) {
        $service_ref = sanitize_text_field( $service_ref );
        $service_slug = sanitize_title( $service_ref );

        foreach ( $services as $service ) {
            if ( $service->post_name === $service_slug ) {
                return absint( $service->ID );
            }
        }

        foreach ( $services as $service ) {
            if ( 0 === strcasecmp( $service->post_title, $service_ref ) ) {
                return absint( $service->ID );
            }
        }

        return 0;
    }

    /**
     * AJAX handler: return time dropdown HTML for a given date/service.
     */
    public function ajax_get_slots() {
        // security check
        if ( ! check_ajax_referer( 'simple_booking_form_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Security check failed' ) );
        }

        $debug = array();
        $date       = isset( $_POST['date'] ) ? sanitize_text_field( $_POST['date'] ) : '';
        $service_id = isset( $_POST['service_id'] ) ? absint( $_POST['service_id'] ) : 0;
        // Normalize date to YYYY-MM-DD to protect against locale variations
        if ( $date ) {
            $dt = DateTime::createFromFormat( 'Y-m-d', $date );
            if ( ! $dt ) {
                // try common alternate formats
                $dt = date_create( $date );
            }
            if ( $dt ) {
                $date = $dt->format( 'Y-m-d' );
            }
        }
        $debug[] = '[DEBUG]: ajax_get_slots called with date=' . $date . ' service_id=' . $service_id;
        if ( ! $date || ! $service_id ) {
            wp_send_json_error( array( 'message' => 'Missing parameters', 'debug' => $debug ) );
        }

        // respect configured per-day schedule
        $schedule = simple_booking()->get_setting( 'schedule', array() );
        // fallback to legacy settings if schedule not yet set
        if ( empty( $schedule ) ) {
            $old_days = simple_booking()->get_setting( 'working_days', array() );
            $old_start = simple_booking()->get_setting( 'work_start', '' );
            $old_end   = simple_booking()->get_setting( 'work_end', '' );
            if ( $old_days || $old_start || $old_end ) {
                $weekdayNames = array( 'monday','tuesday','wednesday','thursday','friday','saturday','sunday' );
                $schedule = array();
                foreach ( $weekdayNames as $idx => $name ) {
                    $schedule[ $name ] = array(
                        'enabled' => in_array( (string) ( $idx + 1 ), (array) $old_days, true ) ? 1 : 0,
                        'start'   => $old_start,
                        'end'     => $old_end,
                    );
                }
            }
        }
        $work_start = '';
        $work_end   = '';
        if ( ! empty( $schedule ) ) {
            try {
                $checkDt = new DateTime( $date, new DateTimeZone( wp_timezone_string() ) );
                $weekdayName = strtolower( $checkDt->format( 'l' ) );
                if ( isset( $schedule[ $weekdayName ] ) ) {
                    $day = $schedule[ $weekdayName ];
                    if ( empty( $day['enabled'] ) ) {
                        wp_send_json_error( array( 'message' => 'No availability on selected day', 'debug' => $debug ) );
                    }
                    // use start/end from schedule if provided
                    if ( ! empty( $day['start'] ) ) {
                        $work_start = $day['start'];
                    }
                    if ( ! empty( $day['end'] ) ) {
                        $work_end = $day['end'];
                    }
                } else {
                    // day not configured at all
                    wp_send_json_error( array( 'message' => 'No availability on selected day', 'debug' => $debug ) );
                }
            } catch ( Exception $e ) {
                // parsing failure, continue with defaults
            }
        }

        $duration = intval( get_post_meta( $service_id, '_service_duration', true ) );
        if ( $duration <= 0 ) {
            $duration = 60;
        }

        // Get service with availability settings
        $service = Simple_Booking_Service::get_service( $service_id );
        if ( ! $service ) {
            wp_send_json_error( array( 'message' => 'Service not found', 'debug' => $debug ) );
        }

        $google = class_exists( 'Simple_Booking_Google_Calendar' ) ? new Simple_Booking_Google_Calendar() : null;

        $events = array();
        if ( ! $google ) {
            $events = $this->get_existing_events( $date );
            if ( is_wp_error( $events ) ) {
                $debug[] = '[DEBUG]: get_existing_events error: ' . $events->get_error_message();
                wp_send_json_error( array( 'message' => $events->get_error_message(), 'debug' => $debug ) );
            }
        }

        // build slots starting on each hour, respecting work hours if configured
        $tz = new DateTimeZone( wp_timezone_string() );

        // compute bounds
        if ( $work_start ) {
            $pointer = new DateTime( $date . ' ' . $work_start, $tz );
        } else {
            $pointer = new DateTime( $date . ' 00:00:00', $tz );
        }
        // align to hour boundary just in case
        $pointer->setTime( (int) $pointer->format( 'H' ), 0, 0 );

        $endBoundary = null;
        if ( $work_end ) {
            $endBoundary = new DateTime( $date . ' ' . $work_end, $tz );
        }
        $endOfDay = new DateTime( $date . ' 23:59:59', $tz );
        $now      = new DateTime( 'now', $tz );
        $slots    = array();

        // if working hours are configured but start is after end, bail out
        if ( $endBoundary && $pointer >= $endBoundary ) {
            wp_send_json_error( array( 'message' => 'No slots available (outside working hours)', 'debug' => $debug ) );
        }

        // iterate hourly; ensure the full duration fits within working hours and does
        // not overlap any events.
        while ( true ) {
            $slotStart = clone $pointer;
            $slotEnd   = clone $pointer;
            $slotEnd->modify( "+{$duration} minutes" );

            // stop when the intended end exceeds whichever boundary is earlier
            $limitTs = $endOfDay->getTimestamp();
            if ( $endBoundary ) {
                $limitTs = min( $limitTs, $endBoundary->getTimestamp() );
            }
            if ( $slotEnd->getTimestamp() > $limitTs ) {
                break;
            }

            $reason = '';
            // check past boundary first
            if ( $slotStart < $now ) {
                $available_flag = false;
                $reason = 'past';
                $debug[] = '[DEBUG]: slot ' . $slotStart->format( DateTime::ATOM ) . ' is in the past';
            } else {
                if ( $google ) {
                    $staff_availability = $google->find_available_staff( $service_id, $slotStart->format( DateTime::ATOM ), $duration );
                    $available_flag = is_array( $staff_availability );
                    if ( $available_flag ) {
                        $assigned_staff_id = isset( $staff_availability['staff_id'] ) ? $staff_availability['staff_id'] : 'none';
                        $debug[] = '[DEBUG]: slot ' . $slotStart->format( DateTime::ATOM ) . ' is available (staff_id=' . $assigned_staff_id . ')';
                    } else {
                        $reason = 'booked';
                        $debug[] = '[DEBUG]: slot ' . $slotStart->format( DateTime::ATOM ) . ' unavailable (no staff available)';
                    }
                } else {
                    // fallback behavior when Google class is unavailable
                    $available_flag = $this->check_slot_availability(
                        $slotStart->format( DateTime::ATOM ),
                        $slotEnd->format( DateTime::ATOM ),
                        $events
                    );
                    if ( $available_flag ) {
                        $debug[] = '[DEBUG]: slot from ' . $slotStart->format( DateTime::ATOM ) . ' to ' . $slotEnd->format( DateTime::ATOM ) . ' fits';
                    } else {
                        $reason = 'booked';
                        $debug[] = '[DEBUG]: slot from ' . $slotStart->format( DateTime::ATOM ) . ' to ' . $slotEnd->format( DateTime::ATOM ) . ' unavailable due to overlap';
                    }
                }
            }

            $slots[] = array(
                'start'     => $slotStart->format( DateTime::ATOM ),
                'available' => $available_flag === true,
                'reason'    => $reason,
            );

            // advance by one hour always
            $pointer->modify( '+1 hour' );
        }

        $debug[] = '[DEBUG]: Generated ' . count( $slots ) . ' hourly slots for ' . $date;
        if ( simple_booking()->get_setting( 'debug_mode' ) ) {
            error_log( end( $debug ) );
        }

        // return options html instead of full container
        $options = '<option value="">' . esc_html__( 'Choose a time…', 'simple-booking' ) . '</option>';
        foreach ( $slots as $slot ) {
            $timeLabel = date_i18n( 'H:i', strtotime( $slot['start'] ) );
            $disabled = $slot['available'] ? '' : ' disabled';
            $title = '';
            if ( ! $slot['available'] ) {
                if ( isset( $slot['reason'] ) ) {
                    switch ( $slot['reason'] ) {
                        case 'past':
                            $title = __( 'Unavailable – past time', 'simple-booking' );
                            break;
                        case 'booked':
                            $title = __( 'Unavailable – already booked', 'simple-booking' );
                            break;
                        default:
                            $title = __( 'Unavailable', 'simple-booking' );
                    }
                } else {
                    $title = __( 'Unavailable', 'simple-booking' );
                }
            }
            // use only the local time as value; date is provided separately
            $options .= '<option value="' . esc_attr( $timeLabel ) . '"' . $disabled . ( $title ? ' title="' . esc_attr( $title ) . '"' : '' ) . '>' . esc_html( $timeLabel ) . '</option>';
        }

        wp_send_json_success( array( 'options' => $options, 'debug' => $debug, 'work_end' => $work_end, 'work_start' => $work_start, 'timezone' => wp_timezone_string() ) );
    }

    /**
     * Fetch raw events from Google Calendar for a given date.
     *
     * @param string $date YYYY-MM-DD
     * @return array|WP_Error
     */
    private function get_existing_events( $date ) {
        // Check if Google Calendar is available
        if ( ! class_exists( 'Simple_Booking_Google_Calendar' ) ) {
            if ( simple_booking()->get_setting( 'debug_mode' ) ) {
                error_log( '[DEBUG]: Google Calendar not available - returning empty events for ' . $date );
            }
            return array(); // Return empty array when Google Calendar is not available
        }

        $google = new Simple_Booking_Google_Calendar();
        $events = $google->fetch_events_on_date( $date );
        if ( is_wp_error( $events ) ) {
            if ( simple_booking()->get_setting( 'debug_mode' ) ) {
                error_log( '[DEBUG]: get_existing_events error for ' . $date . ' - ' . $events->get_error_message() );
            }
        } else {
            if ( simple_booking()->get_setting( 'debug_mode' ) ) {
                error_log( '[DEBUG]: get_existing_events returned ' . count( $events ) . ' events for ' . $date );
            }
        }
        return $events;
    }

    /**
     * Determine if a slot overlaps any existing event.
     *
     * @param string $start ISO datetime
     * @param string $end   ISO datetime
     * @param array  $events
     * @return bool True if slot free, false if overlap detected
     */
    private function check_slot_availability( $start, $end, $events ) {
        $tz      = new DateTimeZone( wp_timezone_string() );
        $startDt = new DateTime( $start, $tz );
        $endDt   = new DateTime( $end, $tz );

        foreach ( $events as $e ) {
            $evStart = new DateTime( $e['start'], $tz );
            $evEnd   = new DateTime( $e['end'], $tz );
            if ( simple_booking()->get_setting( 'debug_mode' ) ) {
                error_log( '[DEBUG]: checking slot ' . $start . ' - ' . $end );
                error_log( '[DEBUG]: existing event ' . $e['start'] . ' - ' . $e['end'] );
            }
            if ( $startDt < $evEnd && $endDt > $evStart ) {
                if ( simple_booking()->get_setting( 'debug_mode' ) ) {
                    error_log( '[DEBUG]: slot overlaps, marking unavailable' );
                }
                return false;
            }
        }
        if ( simple_booking()->get_setting( 'debug_mode' ) ) {
            error_log( '[DEBUG]: slot available' );
        }
        return true;
    }

    /**
     * Render HTML dropdown for a set of slots.
     *
     * @param array $slots Array of ['start'=>ISO,'available'=>bool]
     * @return string HTML markup
     */
    private function render_hourly_dropdown( $slots ) {
        $html  = '<label for="booking_time">' . esc_html__( 'Select Time', 'simple-booking' ) . ' *</label>';
        $html .= '<select id="booking_time" name="booking_time" required>';
        $html .= '<option value="">' . esc_html__( 'Choose a time…', 'simple-booking' ) . '</option>';
        foreach ( $slots as $slot ) {
            $timeLabel = date_i18n( 'H:i', strtotime( $slot['start'] ) );
            $disabled  = $slot['available'] ? '' : ' disabled';
            $html .= '<option value="' . esc_attr( $slot['start'] ) . '"' . $disabled . '>' . esc_html( $timeLabel ) . '</option>';
        }
        $html .= '</select>';
        return $html;
    }
}

// Initialize form
new Simple_Booking_Form();
