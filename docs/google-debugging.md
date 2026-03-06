# Google Calendar Debugging Notes (Temporary)

This document explains recent changes made to support file-based debugging during
investigation of calendar event creation issues. A new `debug_mode` checkbox has
been added to the General settings page; toggling it on enables the file logging
and other verbose output, allowing developers to switch debugging on/off without
code changes.  This document is aimed at developers and AI assistants working
on the plugin.  **Cleanup is required once the issue is resolved.**

## Files Modified

- `includes/google/class-google-calendar.php`
  - Added `DEBUG_FILE` & `DEBUG_ENABLED` constants and the private `debug_log`
    helper.
  - Added `debug_clear()` helper.
  - Converted all `error_log()` calls in the Google flow (token exchange,
    access token, event creation) to `debug_log()`.
  - Added extensive comments at the top explaining how to use and disable the
    file-based logging.

- `includes/booking/class-booking-creator.php`
  - Added matching debug constants and static `debug_log()` helper so the
    booking creator can emit the initial messages.
  - Switched logging in `create_google_event()` from `error_log()` to the new
    debug helper and added detailed entries (booking data JSON, connection
    status, result codes).
  - Included a TODO comment reminding to remove the debug code later.

## Added Logging

The debug file (`debug-google.txt` in the plugin root) now receives entries
for:

1. Start of Google event handling (in both creator and calendar classes).
2. Full booking data (keys & values) passed into `create_google_event()`.
3. Connection status (`is_connected()`) and stored token keys.
4. Details of token exchanges and refreshes (responses and errors).
5. API request payloads (`event` array) and response bodies / HTTP status.
6. Final results: created event ID or any WP_Error messages.
7. Any warnings or errors during the process.

Each message is timestamped and categorized (`BOOKING`, `EVENT`, `TOKEN`)
for easier filtering.

## Testing Instructions

1. Ensure `debug-google.txt` exists in the plugin directory and is writable by
the web server.
2. (Optional) delete or clear its contents before starting a fresh test run. You
   can call `Simple_Booking_Google_Calendar::debug_clear()` from a temporary
   hook or simply empty the file manually.
3. Perform a booking using the frontend form or trigger the booking creator via
the webhook flow.
4. Open `debug-google.txt` to inspect sequential log entries showing the
   lifecycle of the Google Calendar interaction.

> **Note:** the log is deliberately scoped to the Google-specific flow. No
> other plugin operations write here.

## Why This Is Temporary

- File-based logging is only intended for diagnosing a specific issue with the
  Calendar integration. Real deployments should not rely on manual log files.
- The extra I/O and sensitive information (even truncated tokens) should be
  removed once debugging is complete.
- After verification, remove all `debug_log()` calls, the constants, and the
  `google-debugging.md` documentation.

---

Once the underlying problem has been identified, revert the changes and restore
standard logging or error handling as appropriate.
