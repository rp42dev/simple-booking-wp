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

	// Initialize when DOM is ready
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', initScheduleModeToggle );
	} else {
		initScheduleModeToggle();
	}

} )();
