/**
 * Admin Service Settings
 * 
 * Handles UI interactions for service availability settings editor
 */

( function() {
	'use strict';

	/**
	 * Toggle custom availability settings visibility based on schedule mode
	 */
	function initScheduleModeToggle() {
		const scheduleModeSelect = document.getElementById( 'schedule_mode' );
		const customAvailabilitySection = document.getElementById( 'custom-availability-section' );

		if ( ! scheduleModeSelect || ! customAvailabilitySection ) {
			return;
		}

		/**
		 * Update visibility state
		 */
		function updateVisibility() {
			const mode = scheduleModeSelect.value;
			const isCustom = 'custom' === mode;

			if ( isCustom ) {
				customAvailabilitySection.style.display = 'table-row-group';
				customAvailabilitySection.classList.add( 'visible' );
				customAvailabilitySection.classList.remove( 'hidden' );
			} else {
				customAvailabilitySection.style.display = 'none';
				customAvailabilitySection.classList.add( 'hidden' );
				customAvailabilitySection.classList.remove( 'visible' );
			}
		}

		// Initial state
		updateVisibility();

		// Listen for changes
		scheduleModeSelect.addEventListener( 'change', function() {
			updateVisibility();
			updateSchedulePreview();
		} );
	}

	/**
	 * Handle per-day schedule UI interactions
	 */
	function initPerDayScheduleUI() {
		const enabledCheckboxes = document.querySelectorAll( '.day-enabled-checkbox' );

		enabledCheckboxes.forEach( ( checkbox ) => {
			/**
			 * Update row styling and input disabled state based on enabled checkbox
			 */
			function updateDayRow() {
				const day = checkbox.getAttribute( 'data-day' );
				const row = checkbox.closest( 'tr' );
				const startInput = document.querySelector( `.day-start-time[data-day="${day}"]` );
				const endInput = document.querySelector( `.day-end-time[data-day="${day}"]` );
				const bufferInput = document.querySelector( `.day-buffer[data-day="${day}"]` );

				if ( checkbox.checked ) {

					// Day is enabled
					row.style.opacity = '1';
					row.style.backgroundColor = '';
					if ( startInput ) startInput.disabled = false;
					if ( endInput ) endInput.disabled = false;
					if ( bufferInput ) bufferInput.disabled = false;
				} else {
					// Day is disabled
					row.style.opacity = '0.6';
					row.style.backgroundColor = '#fafafa';
					if ( startInput ) startInput.disabled = true;
					if ( endInput ) endInput.disabled = true;
					if ( bufferInput ) bufferInput.disabled = true;
				}

				updateSchedulePreview();
			}

			// Initial state
			updateDayRow();

			// Listen for changes
			checkbox.addEventListener( 'change', updateDayRow );
		} );

		const customInputs = document.querySelectorAll( '.day-start-time, .day-end-time, .day-buffer' );
		customInputs.forEach( ( input ) => {
			input.addEventListener( 'change', updateSchedulePreview );
			input.addEventListener( 'input', updateSchedulePreview );
		} );
	}

	/**
	 * Keep meeting link editable and only adjust Google toggle dependency state.
	 */
	function initAutoMeetToggle() {
		const autoMeetInput = document.getElementById( 'auto_google_meet' );
		const createGoogleEventInput = document.getElementById( 'create_google_event' );
		const meetingLinkInput = document.getElementById( 'meeting_link' );

		if ( ! autoMeetInput || ! meetingLinkInput ) {
			return;
		}

		const autoMeetLabels = document.querySelectorAll( 'label[for="auto_google_meet"]' );

		function updateToggleState() {
			const eventCreationEnabled = createGoogleEventInput ? createGoogleEventInput.checked : true;
			const autoMeetEnabled = autoMeetInput.checked;

			// Update Auto-Meet checkbox state (disable if Event creation is OFF)
			autoMeetInput.disabled = ! eventCreationEnabled;
			autoMeetInput.setAttribute( 'aria-disabled', ! eventCreationEnabled ? 'true' : 'false' );

			// Dim only the labels and input, not the entire row
			autoMeetLabels.forEach( ( label ) => {
				label.style.opacity = ! eventCreationEnabled ? '0.65' : '1';
			} );
			autoMeetInput.style.opacity = ! eventCreationEnabled ? '0.65' : '1';

			autoMeetInput.title = ! eventCreationEnabled
				? 'Enable "Create Google Calendar Event" first to use auto-generated Google Meet links.'
				: '';

			// Keep manual meeting link editable; it serves as fallback/default URL.
			meetingLinkInput.disabled = false;
			meetingLinkInput.setAttribute( 'aria-disabled', 'false' );
			meetingLinkInput.title = autoMeetEnabled
				? 'Auto Google Meet is enabled. New bookings may use generated Meet links; this manual link remains available as fallback.'
				: '';
		}

		updateToggleState();
		autoMeetInput.addEventListener( 'change', updateToggleState );
		if ( createGoogleEventInput ) {
			createGoogleEventInput.addEventListener( 'change', updateToggleState );
		}
	}

	/**
	 * Parse JSON from hidden input safely
	 */
	function parseHiddenJson( inputId ) {
		const el = document.getElementById( inputId );
		if ( ! el || ! el.value ) {
			return null;
		}

		try {
			return JSON.parse( el.value );
		} catch ( e ) {
			return null;
		}
	}

	/**
	 * Build schedule object from current custom per-day controls
	 */
	function getCustomScheduleFromForm() {
		const schedule = {};

		for ( let day = 1; day <= 7; day++ ) {
			const enabledInput = document.querySelector( `.day-enabled-checkbox[data-day="${day}"]` );
			const startInput = document.querySelector( `.day-start-time[data-day="${day}"]` );
			const endInput = document.querySelector( `.day-end-time[data-day="${day}"]` );
			const bufferInput = document.querySelector( `.day-buffer[data-day="${day}"]` );

			schedule[ String( day ) ] = {
				enabled: !! ( enabledInput && enabledInput.checked ),
				start: startInput && startInput.value ? startInput.value : '09:00',
				end: endInput && endInput.value ? endInput.value : '17:00',
				buffer: bufferInput && bufferInput.value ? parseInt( bufferInput.value, 10 ) : 0,
			};
		}

		return schedule;
	}

	/**
	 * Render effective schedule preview table HTML
	 */
	function renderSchedulePreviewTable( schedule ) {
		const dayNames = {
			'1': 'Monday',
			'2': 'Tuesday',
			'3': 'Wednesday',
			'4': 'Thursday',
			'5': 'Friday',
			'6': 'Saturday',
			'7': 'Sunday',
		};

		let html = '<table style="width: 100%; border-collapse: collapse; margin-top: 10px;">';
		html += '<thead>';
		html += '<tr style="background-color: #f5f5f5; border-bottom: 2px solid #ddd;">';
		html += '<th style="padding: 10px; text-align: left; width: 15%; border: 1px solid #ddd;">Day</th>';
		html += '<th style="padding: 10px; text-align: center; width: 10%; border: 1px solid #ddd;">Status</th>';
		html += '<th style="padding: 10px; text-align: center; width: 20%; border: 1px solid #ddd;">Hours</th>';
		html += '<th style="padding: 10px; text-align: center; width: 20%; border: 1px solid #ddd;">Buffer</th>';
		html += '</tr>';
		html += '</thead><tbody>';

		Object.keys( dayNames ).forEach( ( day ) => {
			const dayData = schedule[ day ] || { enabled: false, start: '09:00', end: '17:00', buffer: 0 };
			const isEnabled = !! dayData.enabled;
			const status = isEnabled
				? '<span style="color: #28a745; font-weight: bold;">✓ Open</span>'
				: '<span style="color: #dc3545; font-weight: bold;">✗ Closed</span>';
			const hours = isEnabled ? `${dayData.start} – ${dayData.end}` : '—';
			const buffer = isEnabled
				? ( ( Number( dayData.buffer ) > 0 ) ? `${Number( dayData.buffer )} min` : '—' )
				: '—';
			const rowStyle = isEnabled
				? 'background-color: #f9fff9;'
				: 'background-color: #fff5f5; opacity: 0.7;';

			html += `<tr style="border-bottom: 1px solid #eee; ${rowStyle}">`;
			html += `<td style="padding: 10px; border: 1px solid #ddd;"><strong>${dayNames[ day ]}</strong></td>`;
			html += `<td style="padding: 10px; text-align: center; border: 1px solid #ddd;">${status}</td>`;
			html += `<td style="padding: 10px; text-align: center; border: 1px solid #ddd;">${hours}</td>`;
			html += `<td style="padding: 10px; text-align: center; border: 1px solid #ddd;">${buffer}</td>`;
			html += '</tr>';
		} );

		html += '</tbody></table>';
		return html;
	}

	/**
	 * Update preview based on schedule mode + current values
	 */
	function updateSchedulePreview() {
		const modeInput = document.getElementById( 'schedule_mode' );
		const container = document.getElementById( 'schedule-preview-container' );
		const note = document.getElementById( 'schedule-preview-note' );

		if ( ! modeInput || ! container ) {
			return;
		}

		const isCustom = 'custom' === modeInput.value;
		const globalSchedule = parseHiddenJson( 'preview-global-schedule' ) || {};
		const customSchedule = getCustomScheduleFromForm();
		const effectiveSchedule = isCustom ? customSchedule : globalSchedule;

		container.innerHTML = renderSchedulePreviewTable( effectiveSchedule );

		if ( note ) {
			note.textContent = isCustom
				? 'This service uses custom availability. Shows your configured per-day schedule above.'
				: 'This service uses the global Working Schedule. Effective availability is determined by plugin-level settings.';
		}
	}

	// Initialize when DOM is ready
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', function() {
			initAutoMeetToggle();
			initScheduleModeToggle();
			initPerDayScheduleUI();
			updateSchedulePreview();
		} );
	} else {
		initAutoMeetToggle();
		initScheduleModeToggle();
		initPerDayScheduleUI();
		updateSchedulePreview();
	}

} )();
