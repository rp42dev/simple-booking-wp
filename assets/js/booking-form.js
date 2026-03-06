/**
 * Simple Booking Form JavaScript
 */

(function($) {
    'use strict';
    console.log('simple-booking-form.js loaded');

    $(document).ready(function() {
        const form = $('#simple-booking-form');
        const submitBtn = $('#booking-submit');
        const messageEl = $('#booking-message');
        const stripeSessionInput = $('#stripe_session_id');

        function getServiceMeta() {
            const serviceField = $('#service_id');
            if (!serviceField.length) {
                return { hasPrice: false, duration: 0 };
            }

            if (serviceField.is('select')) {
                const selected = serviceField.find('option:selected');
                return {
                    hasPrice: selected.data('has-price') === 1 || selected.data('has-price') === '1',
                    duration: parseInt(selected.data('duration'), 10) || 0,
                };
            }

            return {
                hasPrice: serviceField.data('has-price') === 1 || serviceField.data('has-price') === '1',
                duration: parseInt(serviceField.data('duration'), 10) || 0,
            };
        }

        function getSubmitLabel() {
            const hasPrice = getServiceMeta().hasPrice;
            if (hasPrice) {
                return simpleBooking.i18n.submitText || 'Proceed to Payment';
            }
            return simpleBooking.i18n.submitFreeText || 'Book Now';
        }

        function updateSubmitLabel() {
            submitBtn.text(getSubmitLabel());
        }

        // Initialize Stripe
        let stripe = null;
        const publishableKey = simpleBooking && simpleBooking.publishableKey ? simpleBooking.publishableKey : null;

        if (publishableKey && typeof Stripe !== 'undefined') {
            stripe = Stripe(publishableKey);
        }

        // hook up slot loader
        function loadSlots() {
            const date = $('#booking_date').val();
            const serviceId = $('#service_id').val();
            console.log('loadSlots triggered', date, serviceId);
            if (!date || !serviceId) {
                return;
            }
            $.ajax({
                url: simpleBooking.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'simple_booking_get_slots',
                    nonce: simpleBooking.nonce,
                    date: date,
                    service_id: serviceId,
                },
                dataType: 'json'
            }).done(function(response) {
                console.log('slot AJAX response', response);
                if (response.data && response.data.debug) {
                    console.log('slot AJAX debug:', response.data.debug.join('\n'));
                }
                if (response.success && response.data && response.data.options) {
                    $('#booking_time').html(response.data.options);
                    $('#booking_time').prop('disabled', false);
                } else {
                    $('#booking_time').html('<option value="">' + (response.data && response.data.message ? response.data.message : 'No slots') + '</option>');
                    $('#booking_time').prop('disabled', true);
                    alert('No slots available or error: ' + (response.data && response.data.message ? response.data.message : 'unknown'));
                }
                // re-evaluate end estimate/warning in case selected value remains
                updateEndEstimate();
            }).fail(function(xhr, status, err) {
                console.log('Failed to load slots', status, err);
                $('#booking_time').html('<option value="">Failed to load</option>');
                $('#booking_time').prop('disabled', true);
                alert('Slot request failed: ' + status);
            });
        }

        // initialize datepicker with disabled weekdays
        (function() {
            // compute disabled weekday indexes (0=Sunday..6)
            var disabled = [];
            if (simpleBooking.schedule) {
                var names = ['sunday','monday','tuesday','wednesday','thursday','friday','saturday'];
                var hasScheduleConfig = names.some(function(name) {
                    var cfg = simpleBooking.schedule[name];
                    return cfg && (cfg.enabled === 1 || cfg.enabled === '1' || cfg.enabled === true);
                });
                names.forEach(function(name, idx) {
                    var cfg = simpleBooking.schedule[name];
                    if (hasScheduleConfig && (!cfg || !cfg.enabled)) {
                        disabled.push(idx);
                    }
                });
            }
            $('#booking_date').datepicker({
                dateFormat: 'yy-mm-dd',
                minDate: simpleBooking.minDate || null,
                beforeShowDay: function(date) {
                    var wd = date.getDay();
                    if (disabled.indexOf(wd) !== -1) {
                        return [false, 'unavailable'];
                    }
                    return [true, ''];
                },
                onSelect: function() {
                    loadSlots();
                    clearEndEstimate();
                }
            });
        })();

        // pick up changes
        $('#service_id').on('change', function(){ loadSlots(); clearEndEstimate(); updateSubmitLabel(); });
        $('#booking_time').on('change', updateEndEstimate);
        updateSubmitLabel();
        // optionally load slots on page load if date present
        if ($('#booking_date').val() && $('#service_id').val()) {
            loadSlots();
        }

        // Form submission
        form.on('submit', function(e) {
            e.preventDefault();

            // Clear previous message
            hideMessage();

            // Validate form
            if (!validateForm()) {
                return;
            }

            // Show loading
            setLoading(true);

            // Get form data
            const formData = {
                action: 'simple_booking_submit',
                nonce: simpleBooking.nonce,
                service_id: $('#service_id').val(),
                // booking date/time fields replaced by separate inputs
            booking_datetime: (function(){
                const d = $('#booking_date').val();
                const t = $('#booking_time').val();
                return d && t ? d + 'T' + t : '';
            })(),
                customer_name: $('#customer_name').val(),
                customer_email: $('#customer_email').val(),
                customer_phone: $('#customer_phone').val()
            };

            // Submit via AJAX
            $.ajax({
                url: simpleBooking.ajaxUrl,
                method: 'POST',
                data: formData,
                dataType: 'json'
            })
            .done(function(response) {
                if (response.success) {
                    // Store session ID
                    if (response.data.session_id) {
                        stripeSessionInput.val(response.data.session_id);
                    }

                    // Redirect for free bookings
                    if (response.data.redirect_url) {
                        window.location.href = response.data.redirect_url;
                        return;
                    }

                    // Redirect to Stripe
                    if (response.data.url) {
                        window.location.href = response.data.url;
                    } else {
                        showMessage(simpleBooking.i18n.error, 'error');
                        setLoading(false);
                    }
                } else {
                    showMessage(response.data.message || simpleBooking.i18n.error, 'error');
                    setLoading(false);
                }
            })
            .fail(function() {
                showMessage(simpleBooking.i18n.error, 'error');
                setLoading(false);
            });
        });

        // clear estimate
        function clearEndEstimate() {
            $('#end-estimate, #closing-warning').remove();
        }

        // update estimated end time + warning
        function updateEndEstimate() {
            // remove previous notes
            $('#end-estimate, #closing-warning').remove();
            const date = $('#booking_date').val();
            const time = $('#booking_time').val();
            const dur = getServiceMeta().duration;
            if (date && time && dur) {
                const startDt = new Date(date + 'T' + time);
                const endDt = new Date(startDt.getTime() + dur * 60000);
                const opts = {hour:'2-digit', minute:'2-digit'};
                const text = 'Ends at ' + endDt.toLocaleTimeString([], opts);
                $('#time-container').append('<p id="end-estimate">'+text+'</p>');
                // determine closing time for that weekday from schedule if available
                if (simpleBooking.schedule) {
                    const dt = new Date(date);
                    const weekday = dt.toLocaleDateString(undefined, { weekday: 'long' }).toLowerCase();
                    const dayCfg = simpleBooking.schedule[weekday];
                    if (dayCfg && dayCfg.enabled && dayCfg.end) {
                        const workEndDt = new Date(date + 'T' + dayCfg.end);
                        if (endDt.getTime() > workEndDt.getTime() - 60*60000) {
                            $('#time-container').append('<p id="closing-warning" class="error">Service ends less than 1 hour before closing!</p>');
                        }
                    }
                }
            }
        }

        // Validate form
        function validateForm() {
            let isValid = true;
            const errors = [];

            const serviceId = $('#service_id').val();
            const date = $('#booking_date').val();
            const time = $('#booking_time').val();
            const datetime = date && time ? date + 'T' + time : '';
            const name = $('#customer_name').val();
            const email = $('#customer_email').val();
            const phone = $('#customer_phone').val();

            if (!serviceId) {
                errors.push(simpleBooking.i18n.selectService);
                isValid = false;
            }

            if (!datetime) {
                errors.push(simpleBooking.i18n.selectDateTime);
                isValid = false;
            }

            if (!name.trim()) {
                errors.push(simpleBooking.i18n.enterName);
                isValid = false;
            }

            if (!email.trim() || !isValidEmail(email)) {
                errors.push(simpleBooking.i18n.enterEmail);
                isValid = false;
            }

            if (!phone.trim()) {
                errors.push(simpleBooking.i18n.enterPhone);
                isValid = false;
            }

            if (!isValid) {
                showMessage(errors.join('<br>'), 'error');
            }

            return isValid;
        }

        // Validate email
        function isValidEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }

        // Show message
        function showMessage(message, type) {
            messageEl
                .removeClass('success error')
                .addClass(type)
                .html(message)
                .show();
        }

        // Hide message
        function hideMessage() {
            messageEl.hide();
        }

        // Set loading state
        function setLoading(loading) {
            if (loading) {
                submitBtn
                    .prop('disabled', true)
                    .addClass('loading')
                    .text('Processing...');
            } else {
                submitBtn
                    .prop('disabled', false)
                    .removeClass('loading')
                    .text(getSubmitLabel());
            }
        }
    });

})(jQuery);
