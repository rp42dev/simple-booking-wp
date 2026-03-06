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
		scheduleModeSelect.addEventListener( 'change', updateVisibility );
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
			}

			// Initial state
			updateDayRow();

			// Listen for changes
			checkbox.addEventListener( 'change', updateDayRow );
		} );
	}

	// Initialize when DOM is ready
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', function() {
			initScheduleModeToggle();
			initPerDayScheduleUI();
		} );
	} else {
		initScheduleModeToggle();
		initPerDayScheduleUI();
	}

} )();
