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
        const rescheduleBookingIdField = form.find('[name="reschedule_booking_id"]');
        const rescheduleTokenField = form.find('[name="reschedule_token"]');
        const timeContainer = form.find('#time-container');
        const timezoneNotice = form.closest('#simple-booking-form-wrapper').find('.booking-timezone-notice');
        const customerTimezone = (function() {
            try {
                return Intl.DateTimeFormat().resolvedOptions().timeZone || simpleBooking.timezone || '';
            } catch (e) {
                return simpleBooking.timezone || '';
            }
        })();

        if (timezoneNotice.length && customerTimezone) {
            timezoneNotice.html('Times are shown in <strong>' + customerTimezone + '</strong>');
        }
        form.find('#customer_timezone').val(customerTimezone);

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
                return { hasPrice: false, duration: 0, type: 'one_off', schedule: '', soldout: false };
            }

            if (serviceFieldRef.is('select')) {
                const selected = serviceFieldRef.find('option:selected');
                return {
                    hasPrice: selected.data('has-price') === 1 || selected.data('has-price') === '1',
                    duration: parseInt(selected.data('duration'), 10) || 0,
                    type: selected.data('type') || 'one_off',
                    schedule: selected.data('schedule') || '',
                    soldout: selected.data('soldout') === 1 || selected.data('soldout') === '1'
                };
            }

            return {
                hasPrice: serviceFieldRef.data('has-price') === 1 || serviceFieldRef.data('has-price') === '1',
                duration: parseInt(serviceFieldRef.data('duration'), 10) || 0,
                type: serviceFieldRef.data('type') || 'one_off',
                schedule: serviceFieldRef.data('schedule') || '',
                soldout: serviceFieldRef.data('soldout') === 1 || serviceFieldRef.data('soldout') === '1'
            };
        }

        function getSubmitLabel() {
            const meta = getServiceMeta();
            if (meta.type === 'recurring_group') {
                return meta.soldout ? 'Join Waitlist' : 'Subscribe Now';
            }
            if (meta.hasPrice) {
                return simpleBooking.i18n.submitText || 'Proceed to Payment';
            }
            return simpleBooking.i18n.submitFreeText || 'Book Now';
        }

        function updateSubmitLabel() {
            const meta = getServiceMeta();
            submitBtn.text(getSubmitLabel());
            submitBtn.prop('disabled', false); // Waitlist allows submission
        }

        // Initialize Membership Schedule Display
        let scheduleDisplay = form.find('.membership-schedule-display');
        if (!scheduleDisplay.length) {
            scheduleDisplay = $('<div class="booking-field membership-schedule-display" style="display:none;"></div>');
            serviceField.closest('.booking-field').after(scheduleDisplay);
        }

        function updateFieldVisibility() {
            const meta = getServiceMeta();
            if (meta.type === 'recurring_group') {
                dateField.closest('.booking-field').hide();
                timeContainer.hide();
                dateField.removeAttr('required');
                timeField.removeAttr('required');
                
                scheduleDisplay.html('<p><strong>Schedule:</strong> ' + meta.schedule + '</p>').show();
            } else {
                dateField.closest('.booking-field').show();
                timeContainer.show();
                dateField.attr('required', 'required');
                timeField.attr('required', 'required');
                scheduleDisplay.hide();
            }
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

            // Show loading state
            timeField.html('<option value="">' + (simpleBooking.i18n.loadingSlots || 'Loading slots...') + '</option>');
            timeField.prop('disabled', true);
            timeField.addClass('loading-slots');

            // Clear any previous messages
            messageEl.hide().removeClass('error success').text('');

            $.ajax({
                url: simpleBooking.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'simple_booking_get_slots',
                    nonce: simpleBooking.nonce,
                    date: date,
                    service_id: serviceId,
                    customer_timezone: customerTimezone,
                },
                dataType: 'json'
            }).done(function(response) {
                timeField.removeClass('loading-slots');
                console.log('slot AJAX response', response);
                if (response.data && response.data.debug) {
                    console.log('slot AJAX debug:', response.data.debug.join('\n'));
                }
                if (response.success && response.data && response.data.options) {
                    timeField.html(response.data.options);
                    timeField.prop('disabled', false);
                    if (timezoneNotice.length && response.data.timezone) {
                        timezoneNotice.html('Times are shown in <strong>' + response.data.timezone + '</strong>');
                    }
                } else {
                    const errorMsg = (response.data && response.data.message ? response.data.message : 'No slots available for this day.');
                    timeField.html('<option value="">' + errorMsg + '</option>');
                    timeField.prop('disabled', true);
                    
                    // Show inline message instead of alert
                    messageEl.addClass('error').text(errorMsg).fadeIn();
                }
                // re-evaluate end estimate/warning in case selected value remains
                updateEndEstimate();
            }).fail(function(xhr, status, err) {
                timeField.removeClass('loading-slots');
                console.log('Failed to load slots', status, err);
                timeField.html('<option value="">Failed to load</option>');
                timeField.prop('disabled', true);
                messageEl.addClass('error').text('Slot request failed: ' + status).fadeIn();
            });
        }

        // initialize date input (jQuery UI datepicker with native fallback)
        let disabledDays = [];
        let bookedDates = [];

        function loadBookedDates() {
            const serviceId = serviceField.val();
            if (!serviceId) return;

            $.ajax({
                url: simpleBooking.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'simple_booking_get_booked_dates',
                    nonce: simpleBooking.nonce,
                    service_id: serviceId
                }
            }).done(function(response) {
                if (response.success && response.data.booked_dates) {
                    bookedDates = response.data.booked_dates;
                    if (dateField.data('datepicker')) {
                        dateField.datepicker('refresh');
                    }
                }
            });
        }

        function updateDisabledDays() {
            disabledDays = [];
            const serviceId = serviceField.val();
            let schedule = simpleBooking.schedule; // global default

            // check if we have a service-specific schedule
            if (serviceId && simpleBooking.serviceSchedules && simpleBooking.serviceSchedules[serviceId]) {
                const sData = simpleBooking.serviceSchedules[serviceId];
                if (sData.mode === 'custom' && sData.schedule) {
                    schedule = sData.schedule;
                    // Custom schedule uses 1-7 keys (1=Mon, 7=Sun)
                    const dayKeys = ['1','2','3','4','5','6','7'];
                    dayKeys.forEach(function(key) {
                        const cfg = schedule[key];
                        if (!cfg || !cfg.enabled) {
                            // Map 1-7 (Mon-Sun) to JS 0-6 (Sun-Sat)
                            // 7 (Sun) becomes 0
                            // 1 (Mon) becomes 1, etc.
                            const jsIdx = (parseInt(key, 10) % 7);
                            disabledDays.push(jsIdx);
                        }
                    });
                    return; // Early return for custom
                }
            }

            // Global schedule fallback
            if (schedule) {
                const names = ['sunday','monday','tuesday','wednesday','thursday','friday','saturday'];
                names.forEach(function(name, idx) {
                    const cfg = schedule[name];
                    // If no config or not enabled, mark as disabled
                    if (!cfg || (cfg.enabled !== 1 && cfg.enabled !== '1' && cfg.enabled !== true)) {
                        disabledDays.push(idx);
                    }
                });
            }

            // No safety guard needed here; if it's closed, it's closed.
        }

        (function() {
            var dateInput = dateField;
            if (!dateInput.length) {
                return;
            }

            updateDisabledDays();

            // Client-side today's date calculation (100% cache-safe)
            var today = new Date();
            var yyyy = today.getFullYear();
            var mm = String(today.getMonth() + 1).padStart(2, '0');
            var dd = String(today.getDate()).padStart(2, '0');
            var todayStr = yyyy + '-' + mm + '-' + dd;

            var initialized = false;
            if ($.fn && typeof $.fn.datepicker === 'function') {
                try {
                    dateInput.datepicker({
                        dateFormat: 'yy-mm-dd',
                        minDate: 0, // 0 represents today in jQuery UI (dynamic client-side calculated)
                        beforeShowDay: function(date) {
                            var dateStr = $.datepicker.formatDate('yy-mm-dd', date);
                            if (bookedDates.indexOf(dateStr) !== -1) {
                                return [false, 'booked-out', 'Fully Booked'];
                            }
                            var wd = date.getDay();
                            if (disabledDays.indexOf(wd) !== -1) {
                                return [false, 'unavailable', 'Not available'];
                            }
                            return [true, '', 'Available'];
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
                dateInput.attr('min', todayStr);
                dateInput.on('change', function() {
                    loadSlots();
                    clearEndEstimate();
                });
            }
        })();

        function refreshDatepicker() {
            updateDisabledDays();
            if (dateField.data('datepicker')) {
                dateField.datepicker('refresh');
                // if currently selected date is now disabled, clear it
                const current = dateField.val();
                if (current) {
                    const d = new Date(current + 'T00:00:00');
                    const dStr = $.datepicker.formatDate('yy-mm-dd', d);
                    if (disabledDays.indexOf(d.getDay()) !== -1 || bookedDates.indexOf(dStr) !== -1) {
                        dateField.val('');
                        timeField.html('<option value="">' + simpleBooking.i18n.selectDateTime + '</option>');
                        timeField.prop('disabled', true);
                    }
                }
            }
        }

        // pick up changes
        serviceField.on('change', function(){ 
            updateFieldVisibility(); 
            refreshDatepicker(); 
            loadBookedDates();
            const meta = getServiceMeta();
            if (meta.type !== 'recurring_group') {
                loadSlots(); 
            }
            clearEndEstimate(); 
            updateSubmitLabel(); 
        });
        timeField.on('change', updateEndEstimate);
        updateFieldVisibility();
        updateSubmitLabel();
        loadBookedDates();
        // optionally load slots on page load if date present
        if (dateField.val() && serviceField.val() && getServiceMeta().type !== 'recurring_group') {
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
                const t = timeField.val();
                if (!t) {
                    return '';
                }
                // New flow: slot value is ISO datetime from server
                if (String(t).indexOf('T') !== -1) {
                    return t;
                }
                // Legacy fallback: HH:mm value + selected date
                const d = dateField.val();
                return d ? d + 'T' + t : '';
            })(),
                customer_timezone: customerTimezone,
                customer_name: nameField.val(),
                customer_email: emailField.val(),
                customer_phone: phoneField.val(),
                reschedule_booking_id: rescheduleBookingIdField.length ? rescheduleBookingIdField.val() : '',
                reschedule_token: rescheduleTokenField.length ? rescheduleTokenField.val() : ''
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
            form.find('#end-estimate, #closing-warning').remove();
            const date = dateField.val();
            const time = timeField.val();
            const dur = getServiceMeta().duration;
            if (date && time && dur) {
                const startDt = time.indexOf('T') !== -1 ? new Date(time) : new Date(date + 'T' + time);
                const endDt = new Date(startDt.getTime() + dur * 60000);
                const opts = {hour:'2-digit', minute:'2-digit'};
                const text = 'Ends at ' + endDt.toLocaleTimeString([], opts);
                timeContainer.find('#end-estimate').remove();
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
            const datetime = (function() {
                if (!time) {
                    return '';
                }
                if (String(time).indexOf('T') !== -1) {
                    return time;
                }
                return date ? date + 'T' + time : '';
            })();
            const name = nameField.val();
            const email = emailField.val();
            const phone = phoneField.val();

            const meta = getServiceMeta();

            if (!serviceId) {
                errors.push(simpleBooking.i18n.selectService);
                isValid = false;
            }

            if (meta.type === 'recurring_group') {
                if (meta.soldout) {
                    errors.push('This group is currently sold out.');
                    isValid = false;
                }
            } else {
                if (!datetime) {
                    errors.push(simpleBooking.i18n.selectDateTime);
                    isValid = false;
                }
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
