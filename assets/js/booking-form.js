/**
 * Simple Booking Form JavaScript
 */

(function($) {
    'use strict';
    console.log('simple-booking-form.js loaded');

    $(document).ready(function() {
        $('.simple-booking-form').each(function(formIndex) {
        const form = $(this);
        const submitBtn = form.find('#booking-submit');
        const messageEl = form.find('#booking-message');
        const stripeSessionInput = form.find('#stripe_session_id');
        const serviceField = form.find('[name="service_id"]');
        const dateField = form.find('[name="booking_date"]');
        const timeField = form.find('[name="booking_time"]');
        const nameField = form.find('[name="customer_name"]');
        const emailField = form.find('[name="customer_email"]');
        const phoneField = form.find('[name="customer_phone"]');
        const timeContainer = form.find('#time-container');

        // Ensure unique element IDs per form instance (datepicker is sensitive to duplicate IDs)
        const idSuffix = '_' + formIndex;
        const fieldsToUniq = [
            { el: serviceField, old: 'service_id' },
            { el: dateField, old: 'booking_date' },
            { el: timeField, old: 'booking_time' },
            { el: nameField, old: 'customer_name' },
            { el: emailField, old: 'customer_email' },
            { el: phoneField, old: 'customer_phone' }
        ];
        fieldsToUniq.forEach(function(item) {
            if (item.el && item.el.length) {
                const newId = item.old + idSuffix;
                item.el.attr('id', newId);
                form.find('label[for="' + item.old + '"]').attr('for', newId);
            }
        });

        function getServiceMeta() {
            const serviceFieldRef = serviceField;
            if (!serviceFieldRef.length) {
                return { hasPrice: false, duration: 0 };
            }

            if (serviceFieldRef.is('select')) {
                const selected = serviceFieldRef.find('option:selected');
                return {
                    hasPrice: selected.data('has-price') === 1 || selected.data('has-price') === '1',
                    duration: parseInt(selected.data('duration'), 10) || 0,
                };
            }

            return {
                hasPrice: serviceFieldRef.data('has-price') === 1 || serviceFieldRef.data('has-price') === '1',
                duration: parseInt(serviceFieldRef.data('duration'), 10) || 0,
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
            const date = dateField.val();
            const serviceId = serviceField.val();
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
                    timeField.html(response.data.options);
                    timeField.prop('disabled', false);
                } else {
                    timeField.html('<option value="">' + (response.data && response.data.message ? response.data.message : 'No slots') + '</option>');
                    timeField.prop('disabled', true);
                    alert('No slots available or error: ' + (response.data && response.data.message ? response.data.message : 'unknown'));
                }
                // re-evaluate end estimate/warning in case selected value remains
                updateEndEstimate();
            }).fail(function(xhr, status, err) {
                console.log('Failed to load slots', status, err);
                timeField.html('<option value="">Failed to load</option>');
                timeField.prop('disabled', true);
                alert('Slot request failed: ' + status);
            });
        }

        // initialize date input (jQuery UI datepicker with native fallback)
        (function() {
            var dateInput = dateField;
            if (!dateInput.length) {
                return;
            }

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

                // Safety guard: never block all dates in the picker UI
                if (disabled.length === 7) {
                    disabled = [];
                }
            }

            var initialized = false;
            if ($.fn && typeof $.fn.datepicker === 'function') {
                try {
                    dateInput.datepicker({
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
                    initialized = true;
                } catch (e) {
                    console.warn('Datepicker init failed, falling back to native date input', e);
                }
            }

            if (!initialized) {
                dateInput.prop('readonly', false);
                dateInput.attr('type', 'date');
                if (simpleBooking.minDate) {
                    dateInput.attr('min', simpleBooking.minDate);
                }
                dateInput.on('change', function() {
                    loadSlots();
                    clearEndEstimate();
                });
            }
        })();

        // pick up changes
        serviceField.on('change', function(){ loadSlots(); clearEndEstimate(); updateSubmitLabel(); });
        timeField.on('change', updateEndEstimate);
        updateSubmitLabel();
        // optionally load slots on page load if date present
        if (dateField.val() && serviceField.val()) {
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
                service_id: serviceField.val(),
                // booking date/time fields replaced by separate inputs
            booking_datetime: (function(){
                const d = dateField.val();
                const t = timeField.val();
                return d && t ? d + 'T' + t : '';
            })(),
                customer_name: nameField.val(),
                customer_email: emailField.val(),
                customer_phone: phoneField.val()
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
            form.find('#end-estimate, #closing-warning').remove();
        }

        // update estimated end time + warning
        function updateEndEstimate() {
            // remove previous notes
            $('#end-estimate, #closing-warning').remove();
            const date = dateField.val();
            const time = timeField.val();
            const dur = getServiceMeta().duration;
            if (date && time && dur) {
                const startDt = new Date(date + 'T' + time);
                const endDt = new Date(startDt.getTime() + dur * 60000);
                const opts = {hour:'2-digit', minute:'2-digit'};
                const text = 'Ends at ' + endDt.toLocaleTimeString([], opts);
                timeContainer.append('<p id="end-estimate">'+text+'</p>');
                // determine closing time for that weekday from schedule if available
                if (simpleBooking.schedule) {
                    const dt = new Date(date);
                    const weekday = dt.toLocaleDateString(undefined, { weekday: 'long' }).toLowerCase();
                    const dayCfg = simpleBooking.schedule[weekday];
                    if (dayCfg && dayCfg.enabled && dayCfg.end) {
                        const workEndDt = new Date(date + 'T' + dayCfg.end);
                        if (endDt.getTime() > workEndDt.getTime() - 60*60000) {
                            timeContainer.append('<p id="closing-warning" class="error">Service ends less than 1 hour before closing!</p>');
                        }
                    }
                }
            }
        }

        // Validate form
        function validateForm() {
            let isValid = true;
            const errors = [];

            const serviceId = serviceField.val();
            const date = dateField.val();
            const time = timeField.val();
            const datetime = date && time ? date + 'T' + time : '';
            const name = nameField.val();
            const email = emailField.val();
            const phone = phoneField.val();

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
    });

})(jQuery);
