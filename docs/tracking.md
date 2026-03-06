# Development Tracking – Simple Booking Plugin

This document keeps a running log of steps taken during recent work on the
plugin. It is intended for internal tracking and handover; it should be removed
or trimmed when the project stabilizes.

## 2026-03-03 – Debugging & Double Booking Fixes

1. **Goal**: Improve visibility into Google Calendar integration while
diagnosing why events sometimes failed to appear. Subsequent requirement to
prevent overlapping bookings.

2. **Added file-based debug logging**
   - Introduced `DEBUG_FILE` constant (`debug-google.txt` in plugin root).
   - Implemented `debug_log()` and `debug_clear()` helpers in
     `includes/google/class-google-calendar.php`.
   - Mirrored simple logger in
     `includes/booking/class-booking-creator.php` for early-stage logs.
   - Converted all existing `error_log()` calls in Google flow to this new
     mechanism; added explanatory comments and usage instructions.
   - Created `docs/google-debugging.md` summarizing usage and reminding to
     remove after troubleshooting.

3. **Slot availability & double-booking prevention**
   - Added `fetch_events_on_date()`, `get_available_slots()` and a new
     `is_slot_available()` helper to the Google calendar class.
     `fetch_events_on_date()` retrieves raw events; `get_available_slots()` can
     compute a list of potential start times; `is_slot_available()` performs a
     quick overlap test for a single requested interval.
     All methods use the site timezone when interpreting times.
   - Updated `create_booking()` in the booking creator to call
     `is_slot_available()` before persisting the booking, returning a
     `WP_Error` if the interval overlaps an existing calendar event.
   - Enhanced frontend booking form:
     * replaced a freeform datetime picker with separate date and time
       controls.
     * time dropdown is populated via AJAX and shows slots stepping by the
       service duration; overlapping slots are disabled/greyed out.
     * new server helpers (`get_existing_events()`, `check_slot_availability()`,
       `render_hourly_dropdown()`) support this behaviour, and JS listens for
       date/service changes to refresh the dropdown.
   - Included debug log entries at each step of the availability check.
   - Updated `TODO.md` and added a new TODO item describing this requirement.

4. **Documentation updates**
   - Added `docs/tracking.md` (this file).
   - Expanded `TODO.md` with new tasks and marked the recent work as complete.
   - `docs/google-debugging.md` created earlier handles debug-specific details.

5. **Next steps**
   - Integrate `get_available_slots()` into the frontend form (AJAX call).
   - Remove temporary debug logging once Google issue is resolved (see
     priority 1.2 in TODO).
   - Consider moving slot logic into its own service class if reuse grows.
   - Add unit tests covering slot calculations and booking validation.

6. **2026-03-03 (continued)**

   - Fixed form submission validation by accepting combined `booking_datetime` field from updated JS; previously the server expected separate `booking_date` and `booking_time` causing a "Please select a date and time" error even when both were chosen.   - Implemented past-slot filtering in `ajax_get_slots()` by comparing each generated start time to “now” (site timezone); past times are marked unavailable and disabled in the UI. Added redundant server‑side check during submission to guard against manipulated requests.
   * Enhanced logic to explicitly compute slot end based on service duration and stop generating options once the interval would overflow the day.
   * Debug output now records whether a long slot “fits” or overlaps an existing event, ensuring multi-hour services properly respect availability.

   - New admin settings added for working schedule: each day can be enabled/disabled with its own start and end times. Slot generation now looks up the configured hours for the requested weekday and errors if the day is closed or the time would fall outside that day’s bounds.

   - Added server-side re‑check in `handle_submission()` before creating a Stripe session. The selected datetime is revalidated against existing events to catch any race conditions or bypass attempts.

   - Frontend enhancements:
     * End‑time estimate displayed whenever a slot is chosen.
     * Warning message appears when a long service would finish less than an hour before closing.
     * Disabled slot options now include hover tooltips explaining why they’re unavailable (past or booked).
     * Month‑view calendar (jQuery UI datepicker) now greys out and prevents selection of days that are disabled in the weekly schedule; CSS used to show a grey background and not-allowed cursor. Input switched to readonly text field and `minDate` set to one hour from now to prevent manual typing of earlier dates.
     * Dropdown styling tweaked for clarity.
---

**Usage notes:** keep this file updated as further features or fixes are
implemented. It provides quick insight into recent changes for new developers
and reviewers.