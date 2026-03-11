# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Planned
- (No planned items currently)

## [3.0.17] - 2026-03-11

### Added
- Module registry and availability checks (`Simple_Booking_Module_Manager`) to support plug-and-play feature modules.
- Admin "Modules Status" diagnostics panel in settings with install/license availability breakdown.

### Changed
- Calendar provider settings now derive availability from both module installation state and license state.
- Admin provider controls now gray out unavailable modules and show reason text (missing module vs Pro required).
- Optional module files (Google/Outlook provider + OAuth handlers) are now loaded safely only when present to avoid bootstrap fatals.
- Service editor now keeps Google Event Options visible but disabled/grayed out when Google is unavailable or inactive provider, and staff assignment controls are disabled with reason text when Staff module is unavailable.
- Staff submenu is now always visible under Services, while non-Pro mode blocks Staff CRUD actions and shows a Pro-only notice.
- Pro detection for module gating now supports filter override (`simple_booking_is_pro_active`) and local license option fallback, fixing cases where Staff submenu stayed hidden in Pro mode.
- Pro force constants from `wp-config.php` now accept common truthy forms (`true`, `1`, `'true'`, `'1'`, `'yes'`, `'on'`) and support `SIMPLE_BOOKING_PRO_MODE` alias.
- Added compatibility aliases for forced Pro constants (`SIMPLE_BOOKING_PRO`, `SIMPLE_BOOKING_IS_PRO`) and bootstrap-level forced-Pro bypass for staff CPT registration.
- Staff editor fields remain editable in Pro mode; non-Pro mode can access list view only (no add/edit/delete).

## [3.0.16] - 2026-03-11

### Added
- WP-Cron dependency notice added below the Webhook Queue Status panel with WP-CLI and server cron instructions.

### Fixed
- Outlook calendar events now fall back to default calendar when a stored staff calendar ID becomes stale ("Id is malformed" Graph error), preventing booking failures after token refresh or re-auth.
- Webhook `booking.created` payload now includes the generated Google Meet link instead of an empty string.

## [3.0.15] - 2026-03-10

### Added
- Staff calendar selector now auto-loads available provider calendars in admin with one-click refresh.
- Webhook Queue Status diagnostics panel in admin now shows pending retry jobs with booking context.
- Manual admin action to run due webhook retry jobs immediately.
- Manual admin action to clear far-future webhook retries for test cleanup.

### Changed
- Outlook and Google staff calendar routing now supports end-to-end CRUD parity in booking lifecycle flows.
- Google calendar dropdown now lists writable calendars only to avoid selecting read-only calendars.

### Fixed
- Google calendar list endpoint corrected for staff dropdown population.
- Google event creation now retries without conference data when Meet insertion is unsupported on target calendar.
- Webhook retries moved to deferred background scheduling to avoid slowing booking requests during rate limiting.
- Webhook retry logs now include booking context and avoid duplicate scheduling for identical retry args.

## [3.0.14.4] - 2026-03-10

### Fixed
- Stripe refund processing now works correctly (uses `latest_charge` from PaymentIntent)
- Refunds now properly issued on booking cancellation
- Tested and validated with live Stripe test charges

## [3.0.14.3] - 2026-03-10

### Fixed
- Fixed Stripe refund charge lookup (improved error handling for edge cases)
- Better support for Stripe PaymentIntent charge retrieval

## [3.0.14.2] - 2026-03-10

### Fixed
- Critical: Fixed missing closing brace in Booking Creator class (parse error)
- Site now loads correctly

## [3.0.14.1] - 2026-03-10

### Fixed
- Critical: Fixed missing closing brace in Stripe handler class (parse error)
- Site now loads correctly after v3.0.14 deployment

## [3.0.14] - 2026-03-10

### Added
- Admin setting: Refund Percentage (0-100%, configurable per-instance)
- Admin setting: Refund Policy text (optional, for future frontend display)
- Automatic refund processing on booking cancellation (using configured percentage)
- Free reschedule flow for paid bookings (no re-payment required)
- Refund tracking: stores refund ID, status, and error messages in booking meta

### Changed
- Paid bookings can now be rescheduled without requiring new payment
- Rescheduling a paid booking applies the same service to a new time slot
- Stripe refund API integration: refunds issued to original payment method
- Cancel action now processes refund before trashing booking

### Technical
- Added `Simple_Booking_Booking_Creator::is_paid_booking()` to detect paid bookings
- Added `Simple_Booking_Booking_Creator::refund_booking()` to issue refunds
- Added `Simple_Booking_Stripe::issue_refund()` to process Stripe refunds
- Modified `cancel_booking()` to process refunds before deletion
- Modified booking form submission to skip Stripe checkout for paid booking reschedules
- Refund percentage setting stored in `simple_booking_settings[refund_percentage]` (default 100)
- Booking meta fields: `_refund_status`, `_refund_id`, `_refund_error`

### Admin UI
- New "Refund Settings" section in Simple Booking Settings
- Refund Percentage field (0-100, default 100%)
- Refund Policy text field for custom policy text

## [3.0.13] - 2026-03-10

### Fixed
- Automatic OAuth token refresh on authentication failures: when Google API returns 401 errors, system now automatically refreshes the access token and retries the request
- Google Calendar events now create successfully even when access tokens have expired
- Slot availability checks recover gracefully from expired tokens
- Event deletion works reliably with automatic token refresh

### Changed
- Enhanced all Google Calendar API methods (`create_event`, `delete_event`, `fetch_events_on_date`) with automatic token refresh retry logic
- When 401 authentication error detected, system calls `refresh_token()` and retries the API call once before failing
- Detailed debug logging for token refresh attempts and results

### Technical
- Modified `create_event()` to detect 401 errors, refresh token, and retry event creation
- Modified `delete_event()` to detect 401 errors, refresh token, and retry event deletion
- Modified `fetch_events_on_date()` to detect 401 errors, refresh token, and retry event fetch
- Prevents "invalid authentication credentials" errors from blocking calendar operations when tokens expire

## [3.0.12] - 2026-03-10

### Fixed
- Improved error handling for Google Calendar API timeouts: when ALL assigned staff have API errors, booking proceeds with graceful fallback instead of graying out all slots
- Added tracking of staff members with availability check errors to detect cascading failures
- Slots now display and allow booking even when Google Calendar integration is experiencing issues

### Changed
- Enhanced `find_available_staff()` to count error cases per staff member
- When all active staff encounter API failures, system now allows booking to proceed instead of hard-blocking

### Technical
- Added `$error_staff_count` variable to track how many active staff had API failures
- If `error_staff_count === active_staff_count` and both > 0, use graceful fallback with first staff member
- Prevents cascading failures from completely blocking bookings

## [3.0.11] - 2026-03-07

### Fixed
- Fixed slot availability checking to gracefully handle Google Calendar API errors
- Slots no longer gray out when Google API is temporarily unavailable
- Staff members with API errors are skipped instead of blocking all availability checks
- Bookings proceed with graceful fallback when verification fails instead of hard failure

### Changed
- Enhanced `find_available_staff()` error handling to catch and skip WP_Error responses from `is_slot_available()`
- Debug logging now tracks Google API failures at the staff level for troubleshooting

### Technical
- Modified `includes/google/class-google-calendar.php::find_available_staff()` to handle WP_Error responses from Google API calls
- When Google API fails for a specific staff member, that staff is skipped and next available staff is checked
- Fallback behavior allows booking creation when verification cannot be completed

## [3.0.10] - 2026-03-07

### Fixed
- Emergency recovery release to restore site stability after v3.0.9 caused a critical runtime failure

### Changed
- Reverted v3.0.9 slot-availability error-handling changes
- Restored stable behavior baseline equivalent to v3.0.8

### Notes
- Critical outage mitigation release; targeted v3.0.9 fix will be reworked and reintroduced safely in a later patch

## [3.0.8] - 2026-03-07

### Fixed
- Cancel action now removes the related Google Calendar event and moves the old booking out of active admin list
- Reschedule completion now removes the old booking's Google Calendar event and moves the old booking out of active admin list
- Free reschedule completion now redirects to confirmation flow

### Changed
- Added booking status indication column in WP admin booking list
- Email customization UI now lists all supported template variables including `{reschedule_link}` and `{cancel_link}`

### Technical
- Added Google event deletion API wrapper in `includes/google/class-google-calendar.php`
- Added booking cancel/delete helpers in `includes/booking/class-booking-creator.php`
- Updated booking management action handler in `includes/frontend/class-booking-form.php` to use centralized cancel logic

## [3.0.7] - 2026-03-07

### Added
- Dedicated "Manage Booking" page (`booking-manage`) created automatically with embedded booking form shortcode
- Upgrade-safe page initialization so required pages are created without requiring plugin reactivation

### Changed
- Tokenized cancel links now redirect customers to the cancel page for a cleaner post-action UX
- Tokenized reschedule links now route to the dedicated manage page instead of generic home URL
- Free reschedules now return a clear "rescheduled successfully" management message after completion
- Paid reschedules now return a "rescheduled pending" success-page state after Stripe checkout completion

### Technical
- Added `simple_booking_manage_page` and `simple_booking_pages_initialized` options in page provisioning flow
- Added management/cancel URL helpers in `includes/frontend/class-booking-form.php`
- Updated Stripe success URL behavior for reschedule context in `includes/stripe/class-stripe-handler.php`

## [3.0.6] - 2026-03-07

### Added
- Tokenized booking management links (`reschedule` and `cancel`) generated per booking and included in confirmation emails
- Public management action handler for secure cancel/reschedule entry points
- Reschedule context support in booking form with hidden linkage fields and customer data prefill

### Changed
- Booking confirmation email templates now support `{reschedule_link}` and `{cancel_link}` variables
- Stripe metadata now carries reschedule context so paid bookings preserve reschedule linkage through webhook booking creation
- Free and paid reschedules both mark original booking as rescheduled after successful new booking creation

### Technical
- Added management token utilities in `includes/booking/class-booking-creator.php`
- Added booking status and reschedule relation meta fields in `includes/post-types/class-booking.php`
- Added token verification and management redirects in `includes/frontend/class-booking-form.php`
- Added reschedule context pass-through in `assets/js/booking-form.js`, `includes/stripe/class-stripe-handler.php`, and `includes/webhook/class-stripe-webhook.php`

## [3.0.5] - 2026-03-07

### Added
- Customer timezone detection in booking form using browser timezone
- Customer-local slot label rendering in time dropdown
- Canonical ISO slot value submission to keep booking and availability checks timezone-safe

### Changed
- Slot AJAX now accepts `customer_timezone` and returns labels for that timezone
- Booking submit flow now sends selected slot datetime directly instead of rebuilding from date + local time
- Timezone notice in the form now reflects detected/customer timezone

### Technical
- Updated `assets/js/booking-form.js` to send `customer_timezone` for slots and submission
- Updated `includes/frontend/class-booking-form.php` to render slot option labels in customer timezone while preserving canonical slot values

## [3.0.4] - 2026-03-07

### Fixed
- Frontend slot list now uses staff-aware availability instead of global-calendar-only availability
- Submission-time revalidation now checks `find_available_staff()` to match slot rendering behavior
- Services with assigned staff where all assigned staff are inactive now fall back to global calendar availability

### Technical
- Updated `ajax_get_slots()` in `includes/frontend/class-booking-form.php` to evaluate per-slot staff availability
- Updated submission path in `includes/frontend/class-booking-form.php` to align server-side slot checks with staff routing logic
- Updated `find_available_staff()` in `includes/google/class-google-calendar.php` with inactive-staff fallback logic

## [3.0.3] - 2026-03-07

### Added
- Staff selection UI in Service editor with active staff checkboxes
- Service-level persistence for assigned staff using `_assigned_staff` meta during post save
- Service payload now includes assigned staff IDs for downstream booking logic

### Changed
- Service editor now loads and displays previously assigned staff selections
- Booking staff assignment workflow is now fully operable end-to-end (data model + availability routing + admin assignment UI)

### Technical
- `includes/post-types/class-booking-service.php` now reads active staff from `Simple_Booking_Staff::get_active_staff()`
- `_assigned_staff` values are saved using `sanitize_staff_assignment()` in the service save handler

### Notes
- Backward compatible: services can leave staff unassigned and continue using global calendar behavior

## [3.0.2] - 2026-03-07

### Added
- Staff-aware availability checking: bookings now route to available staff members
- `find_available_staff()` method: queries each assigned staff's calendar to find availability
- `_assigned_staff_id` booking meta: records which staff member was assigned to each booking
- Calendar ID parameter support in `fetch_events_on_date()`, `is_slot_available()`, and `create_event()`

### Changed
- Availability logic now checks staff-specific calendars (via `_staff_calendar_id` override or global fallback)
- Booking creation flow now stores assigned staff ID with each booking
- Google Calendar events now created on assigned staff member's calendar
- Services without assigned staff continue using global calendar (backward compatible)

### Technical
- Extended Google Calendar API methods to accept optional `$calendar_id` parameter
- Booking creator now calls `find_available_staff()` instead of simple `is_slot_available()`
- Staff calendar ID override falls back to global calendar ID from plugin settings
- Active staff filter ensures only available staff are checked for availability

### Notes
- Backward compatible: services without staff assignments continue working with global calendar
- Staff assignment UI coming in v3.0.3

## [3.0.1] - 2026-03-07

### Added
- Staff custom post type (`booking_staff`) with email, calendar ID, and active status fields
- Staff assignment capability on services (stored as JSON array in `_assigned_staff` meta)
- Staff meta box with email, Google Calendar ID override, and active toggle
- `get_active_staff()` helper for retrieving all active staff members

### Technical
- New file: `includes/post-types/class-staff.php`
- Staff post type registered in plugin init
- Staff save hook registered for meta persistence
- Service meta sanitization for staff ID array

### Notes
- No UI changes yet—pure data model foundation
- Existing booking flow unchanged (staff not yet used in availability logic)

## [2.4.0] - 2026-03-06

### Added
- Booking admin now shows a success notice when a valid Meeting Link is saved

### Changed
- Booking edit notices now provide both positive (saved) and negative (invalid URL) feedback

## [2.3.9] - 2026-03-06

### Added
- Visible admin error notice when booking Meeting Link fails URL validation

### Changed
- Meeting Link save path normalizes and sanitizes values using `wp_unslash()`, `trim()`, and `esc_url_raw()`

## [2.3.8] - 2026-03-06

### Added
- Booking details now support manual Meeting Link override (editable URL field)

### Security
- Nonce-validated save path for booking Meeting Link edits

## [2.3.7] - 2026-03-06

### Fixed
- Auto-Meet dependency dimming now targets only relevant label/input controls (no visual bleed)

## [2.3.6] - 2026-03-06

### Changed
- Auto-Meet checkbox disabled when Google Event creation is off to enforce prerequisite dependency

## [2.3.5] - 2026-03-06

### Fixed
- Meeting Link disable state now depends only on Auto-Meet (business-rule correction)

## [2.3.4] - 2026-03-06

### Fixed
- Corrected service-editor lock behavior for Meeting Link input under toggle state combinations

## [2.3.3] - 2026-03-06

### Added
- Booking-level meeting link source audit metadata: `generated`, `static`, `none`
- Admin booking list column and booking details display for source tracking

## [2.3.2] - 2026-03-06

### Changed
- Service editor visually disables/dims static Meeting Link when auto-generated Meet mode is active

## [2.3.1] - 2026-03-06

### Added
- Service editor guidance text clarifying generated Meet precedence over static links

## [2.3.0] - 2026-03-06

### Added
- Optional per-service auto-generated Google Meet links during Google Calendar event creation
- Booking-level storage and email usage of generated meeting links

## [2.2.7] - 2026-03-06

### Added
- Live effective schedule preview updates while editing service schedule mode and per-day values

## [2.2.6] - 2026-03-06

### Fixed
- Inherited schedule preview now correctly reflects global day buffers and configured hours

## [2.2.5] - 2026-03-06

### Added
- Effective schedule preview UI for service editor (inherit/custom visibility)

---

## Legacy Release Notes

## [1.15] - In Progress

### Added
- **Service-Specific Shortcodes**: Booking form now supports service scoping via shortcode attributes
- **Shortcode by ID**: `[simple_booking_form service_id="123"]`
- **Shortcode by Service Name/Slug**: `[simple_booking_form service="consultation"]`
- **Service Editor Shortcode Helper**: Service edit screen now shows copy-ready shortcode for that service

### Technical
- **Shortcode Attribute Parsing**: `render_shortcode()` now accepts and resolves `service` and `service_id`
- **Service Resolution Helper**: Added internal slug/title resolver for active services
- **Single-Service Rendering**: Service dropdown preloads selected scoped service

---

## [1.14] - 2026-03-06

### Added
- **Free Booking Support**: Services without Stripe Price ID now skip checkout and book immediately
- **Direct Booking Flow**: Creates booking, sends confirmation email, and attempts Google event without Stripe
- **Mixed Service Support**: Booking form supports both paid and free services simultaneously
- **Dynamic Submit Label**: Button text changes based on selected service (`Proceed to Payment` / `Book Now`)

### Technical
- **Frontend Flow Branching**: `handle_submission()` now branches into free or paid booking flow
- **Success Redirect for Free Bookings**: Uses configured success page option with homepage fallback
- **Service Metadata in UI**: Service dropdown includes `data-has-price` for UX behavior

---

## [1.13] - 2026-03-06

### Added
- **Email Customization**: Administrators can customize email subject and body templates
- **Template Variables**: Support for dynamic variables in emails: `{customer_name}`, `{service_name}`, `{booking_date}`, `{booking_time}`, `{meeting_link}`, `{timezone}`, `{site_name}`
- **Admin UI**: New settings section for email customization with subject and body fields
- **Default Templates**: Falls back to original email format if no custom template is set

### Technical
- **Settings Fields**: New `email_subject` and `email_body` settings
- **Variable Replacement**: Template engine replaces variables at email send time
- **Graceful Fallback**: Empty meeting links handled cleanly in templates
- **Date Formatting**: `{booking_date}` formatted as "March 6, 2026", `{booking_time}` as "2:30 PM"

### Fixed
- **Template Email Reliability**: Added fail-safe datetime parsing and fallback subject/body to prevent send failures with custom templates

---

## [1.12] - 2026-03-06

### Added
- **Meeting Link Support**: Services can now include external meeting links (Zoom, Google Meet, etc.)
- **Email Enhancement**: Meeting links automatically included in confirmation emails
- **Calendar Integration**: Meeting links appear in Google Calendar event descriptions

### Technical
- **Service Meta Field**: New `_meeting_link` meta field for service post type
- **URL Validation**: Meeting links sanitized with `esc_url_raw`
- **Optional Field**: Meeting link is optional and falls back gracefully if not provided

---

## [1.11] - 2026-03-06

### Added
- **Redirect & Success Page System**: Automatic creation of success and cancel pages on plugin activation
- **Improved User Experience**: Custom pages for post-payment redirects instead of generic URLs
- **Stripe Redirect URLs**: Updated checkout sessions to use dedicated success/cancel pages

### Fixed
- **Fallback URL Handling**: Fixed double-? query parameter issue when pages are deleted
- **Page Verification**: Added logic to verify pages exist before using them
- **Auto-Cleanup**: Automatically clears options when pages are deleted

### Technical
- **Page Auto-Creation**: Plugin activation hook creates default pages if they don't exist
- **Fallback URLs**: Graceful fallback to homepage with query parameters if pages are missing
- **Option Storage**: Page IDs stored in WordPress options for persistent configuration
- **Query Parameter Helper**: New `append_query_param()` method for proper URL construction

---

## [1.10] - 2026-03-06

### Added
- **Stripe Integration**: Complete Stripe Checkout payment processing
- **Google Calendar Integration**: OAuth authentication and automatic event creation
- **Webhook Processing**: Real-time booking creation after Stripe payment
- **Email Notifications**: Automated confirmation emails to customers
- **Service Management**: Custom post type for booking services with pricing
- **Booking Management**: Custom post type for tracking all bookings
- **Frontend Booking Form**: Shortcode `[simple_booking_form]` for easy integration
- **Admin Settings**: Comprehensive settings panel for Stripe and Google Calendar
- **Debug System**: Toggleable debug logging for troubleshooting
- **Security Features**: Input sanitization, nonce verification, signature validation

### Fixed
- **Critical Compilation Error**: Resolved duplicate method declaration
- **Google Calendar Dependencies**: Added graceful degradation for missing classes
- **Plugin Stability**: Ensured WordPress loads without fatal errors

### Technical
- **Modular Architecture**: Clean separation of concerns across multiple classes
- **WordPress Standards**: Follows WordPress coding standards and security practices
- **Composer Support**: Proper dependency management with composer.json
- **Git Version Control**: Complete repository with proper .gitignore

### Security
- Stripe webhook signature verification
- WordPress nonce protection on forms
- Input sanitization and output escaping
- Admin permission checks

---

## Development Roadmap

### Upcoming Releases
- **v1.11**: Success/Cancel page system
- **v1.12**: Meeting link support
- **v1.13**: Email customization
- **v1.14**: Free booking support
- **v1.15**: Service-specific forms
- **v1.16**: Google Calendar improvements
- **v2.0**: Automation integrations
- **v3.0**: Advanced booking platform

---

## Installation
1. Download the plugin ZIP from releases
2. Upload to WordPress `/wp-content/plugins/`
3. Activate the plugin
4. Configure Stripe and Google Calendar settings
5. Add the booking form shortcode to any page

## Requirements
- WordPress 5.0+
- PHP 7.4+
- Stripe account
- Google Cloud Console project (optional)