# Simple Booking – Development Roadmap

A lightweight modular WordPress booking engine with Stripe payments and Google Calendar integration.

This roadmap defines future development phases to expand the plugin while keeping the architecture simple, maintainable, and modular.

Each version milestone should be implemented incrementally and tested before progressing.

## Current Version
v3.0.10 (RELEASED) → v3.0 (IN PROGRESS)

Core booking flow is operational.

**v3.0 Progress:** Emergency recovery release applied after reverting v3.0.9; platform restored to stable v3.0.8 behavior baseline.

## Release Control (Mandatory Before New Feature Work)

Before starting any new implementation milestone, complete this checklist:

1. **Version Sync**
    - `simple-booking.php` header version matches `SIMPLE_BOOKING_VERSION`
    - `README.md` current release matches shipped tag

2. **Changelog Sync**
    - Add release entry in `CHANGELOG.md` using Keep a Changelog sections
    - Include Added/Changed/Fixed with short business-facing notes

3. **Roadmap Sync**
    - Update `TODO.md` Current Version line
    - Mark completed stage/micro-stage status
    - Add/adjust next immediate stage so there is always one clear next target

4. **Git Release Sync**
    - Commit on `main`
    - Merge `main` → `master`
    - Push `master`
    - Create and push release tag
    - Return to `main`

## Roadmap Exit & Next-Roadmap Planning

When a roadmap milestone is completed (example: v3.x phase finish), immediately do:

1. **Milestone Closure**
    - Add a short “What shipped / What validated / What deferred” note in `TODO.md`

2. **Next Roadmap Draft**
    - Define next major milestone with 3-6 micro stages
    - Add one-line acceptance criteria per stage
    - Order stages by dependency (data model → business logic → UI → polish)

3. **Kickoff Gate**
    - Start next roadmap only after Release Control checklist above is fully complete

## Implemented Features

✔ Services Custom Post Type
✔ Bookings Custom Post Type
✔ Stripe Checkout integration
✔ Stripe Webhook booking creation
✔ Stripe price per service
✔ Booking form shortcode [simple_booking_form]
✔ Email confirmation system
✔ Google Calendar OAuth connection
✔ Google Calendar event creation
✔ Google Calendar uses service duration
✔ Debug logging system for calendar testing

Stripe price is stored in service settings and validated before checkout.

Google Calendar integration creates an event after a successful booking.

## Testing Phase (Before Next Version)

Before continuing development:

Perform Multiple Tests

Test full flow several times:

Open booking form

Select service

Select time

Enter contact details

Complete Stripe payment

Verify webhook triggers booking creation

Verify booking post created

Verify Google Calendar event created

Verify confirmation email sent

Verify:

Stripe payment success

webhook response

booking stored

Google event created

correct event duration

email received

## v1.11 – Redirect & Success Page System

Improve user experience after payment.

Status: ✅ Completed and tested

### Auto Create Pages

When plugin activates, automatically create pages if they do not exist.

Pages:

**Booking Confirmed**
Slug: booking-confirmed

**Booking Cancelled**
Slug: booking-cancelled

Store page IDs in options:

simple_booking_success_page
simple_booking_cancel_page

### Stripe Redirect Configuration

Stripe checkout success redirect:

/booking-confirmed?session_id={CHECKOUT_SESSION_ID}

Cancel redirect:

/booking-cancelled

Success page should later support dynamic booking display.

### v1.11 Validation Notes

- Success redirect to Booking Confirmed page works
- Success page can be manually customized
- Fallback redirect works when success page is deleted
- Fallback now uses a valid URL format with session_id query parameter

## v1.12 – Meeting Link Support

Allow services to define external meeting links.

Status: ✅ Implementation Complete and Tested

Add service field:

**Meeting Link** (optional)

Examples:

Zoom
Google Meet
Microsoft Teams
Custom meeting URL

Example values:

https://zoom.us/j/xxxxx
https://meet.google.com/xxxx

If meeting link exists:

Include it in:

confirmation email

Google Calendar event description

Example event description:

Service: Consultation

Client: John Smith
Email: john@email.com

Meeting Link:
https://zoom.us/j/xxxxx

### Implementation Notes

- Meeting link field added to service editor
- URL validation with `esc_url_raw`
- Meeting link included in confirmation emails
- Meeting link included in Google Calendar event descriptions
- Optional field with graceful fallback if empty
- Tested and confirmed working in production

## v1.13 – Email Customization

Allow administrators to edit booking emails.

Status: ✅ Implementation Complete and Tested

Add settings fields:

**Email Subject**
**Email Body Template**

Support template variables:

{customer_name}
{service_name}
{booking_date}
{booking_time}
{meeting_link}
{timezone}
{site_name}

Example template:

Hello {customer_name},

Your booking for {service_name} has been confirmed.

Date: {booking_date}
Time: {booking_time}

Join your meeting:
{meeting_link}

Thank you.

Templates should be stored in WordPress options.

### Implementation Notes

- New "Email Customization" section in admin settings
- Email subject field with template variable support
- Email body textarea with template variable support
- Template variables replaced at email send time
- Graceful fallback to default email format if templates not set
- Date formatting: `{booking_date}` as "March 6, 2026", `{booking_time}` as "2:30 PM"
- Empty meeting links handled cleanly

## v1.14 – Free Booking Support

Allow services without payment.

Status: ✅ Implementation Complete and Tested

If service has no Stripe Price ID:

skip Stripe checkout
create booking immediately
create Google event
send confirmation email

Logic:

if stripe_price_id empty
→ direct booking flow

Useful for:

free consultations

discovery calls

intro sessions

### Implementation Notes

- If service has no Stripe Price ID, booking is created immediately
- Direct flow creates booking post, Google event (if connected), and confirmation email
- Redirects to configured success page after direct booking
- Booking form now supports mixed paid/free services in one dropdown
- Button label updates dynamically (`Proceed to Payment` vs `Book Now`)

## v1.15 – Service Specific Forms

Allow multiple booking forms.

Status: ✅ Implementation Complete and Tested

Current shortcode:

[simple_booking_form]

Add support:

[simple_booking_form service="consultation"]

or

[simple_booking_form service_id="123"]

Admin UI should display shortcode for each service:

Example:

Consultation Service

[simple_booking_form service_id="123"]

Admin can copy shortcode and place it on pages.

### Implementation Notes

- Shortcode now supports `service_id` attribute: `[simple_booking_form service_id="123"]`
- Shortcode now supports `service` attribute by slug/title: `[simple_booking_form service="consultation"]`
- When scoped to one service, form preloads that service as selected
- Service editor now displays copy-ready shortcode for the current service

## v1.16 – Google Calendar Improvements

Improve calendar integration.

Status: ✅ Implementation Complete

### Google Event Toggle

Add service setting:

**Create Google Calendar Event**

If disabled:

skip Google event creation

### Enhanced Event Description

Google Calendar event should include:

Customer name
Customer email
Service name
Meeting link

Example:

Booking: Consultation

Client: John Smith
Email: john@email.com

Meeting:
https://zoom.us/j/xxxxx

### Implementation Notes

- New "Create Google Calendar Event" checkbox in service editor (default: enabled)
- Service toggle defaults to true for backward compatibility
- Event creation respects per-service toggle setting
- Enhanced event description format with cleaner layout
- Event description now shows "Booking:" instead of mixing fields
- Meeting link section simplified to "Meeting:" with link below
- Removed phone and time fields from description (time is in calendar event itself)

## v2.0 – Automation Integrations

Introduce automation compatibility.

Status: ✅ Implementation Complete

### Webhook System

Trigger event when booking is created.

Webhook name:

booking.created

Payload example:

{
 service_name
 customer_name
 email
 date
 time
 meeting_link
}

Admin setting:

**Webhook URL**

Send POST request when booking is created.

Allows integration with:

Zapier

Make

CRM systems

marketing automation

### Implementation Notes

- New "Webhook Settings" section in admin settings
- Webhook URL field added with URL validation
- Webhook sender class created (class-booking-webhook.php)
- Webhook triggered after successful booking creation
- Non-blocking implementation - failures don't break booking flow
- Debug logging for webhook failures when debug mode enabled
- Empty webhook URL safely skips webhook sending

## v2.1 – Booking Management Improvements

Improve WordPress admin experience.

Status: ✅ Implementation Complete

Add booking filters:

Service filter
Date filter
Payment status

Booking details page should show:

Customer name
Customer email
Service
Booking date
Stripe session ID
Google event ID

### Implementation Notes

- Custom admin columns: Service, Customer (name + email), Booking Date (date + time), Payment Status
- Service filter dropdown (populated from available services)
- Date filter dropdown (month/year grouping from actual bookings)
- Payment status filter (Paid / Free)
- Sortable booking date column
- All filters work together with AND logic
- Color-coded payment status (green = paid, blue = free)
- Booking details meta box displays all key information

## v2.2 – Availability Control

Add scheduling logic and per-service availability rules.

Status: ✅ Implementation Complete

Service settings now support:

**Available Days** – Select which days of week the service is available

**Available Hours** – Set opening/closing time for that service (9 AM - 5 PM)

**Buffer Time** – Enforce minimum gap between bookings (in minutes)

Booking form now shows only available slots respecting all constraints.

### Implementation Notes

- Per-service availability settings in service editor
- Available days via checkboxes (Monday-Sunday)
- Time windows with time picker inputs
- Buffer time in minutes (0, 5, 10, 15, etc.)
- Slot generation in booking form respects all availability rules
- Compatibility with global plugin scheduling settings
- Existing bookings checked against buffer time to prevent overlap
- Non-blocking implementation - uses service-level settings only if configured

## v2.2.1 – Schedule Mode Hardening (Staged)

Refine scheduling UX so global schedule and service schedule are explicit and non-conflicting.

Status: ✅ Complete (Micro Stage 4 Complete - v2.2.5 Released)

### Stage Plan

- **Micro Stage 1 (Completed):** Add `Schedule Mode` to each service (`Inherit Global` / `Custom Service`)
- **Micro Stage 2 (Completed):** Hide/disable custom day/hour controls when mode is `Inherit`
- **Micro Stage 3 (Completed):** Per-day time ranges in structured format (separate intervals per weekday)
- **Micro Stage 4 (Completed):** "Effective Schedule" preview displaying effective availability in admin UI

### Micro Stage 1 Notes

- Added service-level `schedule_mode` meta with default `inherit`
- Availability checks now apply service day/hour restrictions only in `custom` mode
- Buffer enforcement remains active for both modes
- Keeps backward compatibility with existing global Working Schedule

### Micro Stage 2 Notes

- Added admin JavaScript to conditionally show/hide custom availability controls
- Custom section (days, hours, buffer) only visible when mode is set to `Custom`
- When mode is `Inherit`, these controls are hidden to reduce UI confusion
- User clearly sees: `inherit` mode = use global schedule, `custom` mode = override with service-specific rules

### Micro Stage 3 Notes

- Replaced comma-separated flat format with per-day schedule JSON structure
- Each day now has: enabled flag, start time, end time, buffer time (individual for each day)
- Updated UI to table showing Mon-Sun with individual controls per day
- JavaScript disables/dims inputs for disabled days
- Per-day buffers allow different gap requirements (e.g., Mon 30min buffer, Wed no buffer)
- Availability checker reads per-day schedule: checks if day enabled, validates time range, applies per-day buffer
- Backward compatible: old format still supported, defaults to standard 9-17 all weekdays if no per-day schedule exists

### Admin Global Schedule Additions (v2.2.4)


### Micro Stage 4 Notes

- Added "Effective Schedule Preview" section to service editor
- Read-only preview table showing what the final effective availability will be
- For "Inherit Global" mode: displays global Working Schedule (Days, Open/Closed, Hours, Buffer)
- For "Custom Service Schedule" mode: displays service-level per-day schedule
- Color-coded status: Green "✓ Open" vs Red "✗ Closed" for visual clarity
- Open days show hours and buffer time; closed days show "—" (dashes)
- Preview updates in real-time as admin changes schedule settings
- JavaScript: updateSchedulePreview() triggers on mode toggle and any day/time/buffer change
- Admin PHP method: build_schedule_preview() generates preview HTML table
- Helps admin understand final availability before saving service
- New method: Simple_Booking_Service::build_schedule_preview( $mode, $schedule )

### v2.2.6 Patch Notes

- Fixed Inherit mode preview to read actual global Working Schedule values (including per-day buffer)
- Effective Schedule Preview now shows inherited buffer minutes correctly instead of defaults/dashes
- Cleaned preview table markup around Service Shortcode row

### v2.2.7 Patch Notes

- Effective Schedule Preview now updates live when switching Schedule Mode between Inherit and Custom
- In Inherit mode, preview uses global admin Working Schedule (including per-day buffers)
- In Custom mode, preview reflects current unsaved per-day custom edits immediately
- Preview note text now switches live with mode selection

## v2.3 – Google Meet Auto Generation

Status: ✅ Released (v2.3.0)

Allow automatic meeting link creation with a per-service toggle.

### Implemented

- Added service-level toggle: **Auto-Create Google Meet Link**
- Meet generation runs only when:
    - service has **Create Google Calendar Event** enabled, and
    - service has **Auto-Create Google Meet Link** enabled
- Google event creation now requests `conferenceData` (`hangoutsMeet`)
- Generated Meet link is extracted from Google API response and stored on booking
- Confirmation email now prefers booking-level meeting link (generated Meet), then falls back to static service meeting link
- Works for both paid (Stripe webhook) and free-booking flows

### Notes

- Existing services remain backward compatible (`auto_google_meet` defaults to off)
- Google Meet links are now booking-specific when auto-generated
- Event creation still works without Meet when toggle is disabled

### v2.3.1 Patch Notes

- Added service editor hint under Meeting Link clarifying Auto-Meet precedence
- Clarifies that generated per-booking Google Meet link overrides static Meeting Link in confirmations when enabled

### v2.3.2 Patch Notes

- Service editor now disables/dims static Meeting Link input when Auto-Create Google Meet Link is enabled
- Preserves existing static link value as fallback (value is not deleted)
- Reduces admin confusion about which link is active for new bookings

### v2.3.3 Patch Notes

- Added booking-level audit metadata: `_meeting_link_source` (`generated`, `static`, `none`)
- Booking list now shows new **Meeting Link Source** column
- Booking details screen now displays Meeting Link Source value
- Source logic:
    - `generated` when Google Meet is auto-created and saved on booking
    - `static` when service static meeting link is used
    - `none` when no meeting link exists

### v2.3.8 Patch Notes

- **Admin override capability:** Meeting Link field now editable in booking details metabox
- Allows admins to manually fix/update meeting links if auto-generation failed or link is wrong
- URL validation: invalid URLs are rejected, valid URLs and empty values accepted
- Nonce protection: form submission validated with WordPress security tokens
- Escape handling: URLs properly escaped and sanitized on input/output
- Useful for support scenarios: admin can correct a broken link without recreating booking

### v2.3.9 Patch Notes

- Added visible admin error notice when Meeting Link save fails URL validation
- Invalid URL now redirects back with explicit feedback instead of silent failure
- Notice text guides admin to include `http://` or `https://`
- Save path now normalizes input with `wp_unslash()` + `trim()` and stores via `esc_url_raw()`

### v2.4.0 Patch Notes

- Added visible admin success notice when Meeting Link saves with a valid value
- Booking edit redirect now carries explicit success state for meeting-link updates
- Success and error notices now complement each other for clear admin feedback

### v2.3.7 Patch Notes

- Fixed UI regression: Auto-Meet row dimming no longer affects other checkboxes
- Changed from row-level opacity to selective label + input dimming
- Active checkbox now remains fully visible and interactive when Auto-Meet is disabled
- More surgical CSS approach prevents visual interference with neighboring controls

### v2.3.6 Patch Notes

- **Dependency validation:** Auto-Meet checkbox now disabled when Google Calendar Event creation is OFF
- Prevents admins from enabling a feature that won't function without the prerequisite
- Auto-Meet toggled ON automatically re-enables when Event creation is turned ON
- Tooltip: "Enable 'Create Google Calendar Event' first to use auto-generated Google Meet links."
- Improved admin UX by enforcing business dependency at the UI level

### v2.3.5 Patch Notes

- **Corrected** auto-meet behavior: Meeting Link field disabled based **only** on Auto-Meet state
- Business rule clarification: Auto-Meet toggle controls the static link field (when ON, field is locked; when OFF, field is editable)
- Google Calendar Event setting is orthogonal—does not affect Meeting Link field disable state
- Simplified JavaScript toggle logic (removed unnecessary dependency on create_google_event checkbox)

### v2.3.4 Patch Notes

- Fixed Meeting Link input lock condition in service editor
- Meeting Link is now disabled only when BOTH are enabled:
    - `Create Google Calendar Event`
    - `Auto-Create Google Meet Link`
- If Google Calendar Event creation is OFF, Meeting Link remains editable (no gray lock)

## v3.0 – Advanced Booking Platform

Major expansion of system.

### Multi Staff Support

Services can assign staff members.

Each staff member has:

own calendar
own availability

Booking system chooses available staff.

### Timezone Detection

Detect customer timezone automatically.

Display booking time in:

customer local timezone

### Reschedule / Cancel Links

Email should include:

Reschedule booking link
Cancel booking link

Customer can manage booking without admin.

## Debug System (Temporary)

Current debug system exists for Google Calendar testing.

File:

debug-google.txt

Controlled by:

debug_mode checkbox

After debugging is complete:

Remove:

debug_log()
debug constants
debug documentation

Debug logging should not remain in production releases.

## Code Architecture Requirements

Future features must follow modular structure.

includes/
    admin/
    booking/
    stripe/
    google/
    email/
    integrations/

Code must follow WordPress standards.

## Security Requirements

All features must include:

input sanitization
output escaping
nonce verification
Stripe webhook signature verification

## GitHub Strategy (Recommended)

Recommended repository structure:

simple-booking-core

Public open-source repository.

Benefits:

transparency

credibility

developer contributions

portfolio value

Possible future product:

Simple Booking Pro

Premium features could include:

multi staff
advanced scheduling
SMS reminders
CRM integrations

## Future Ideas (Not Scheduled)

Possible future features:

Recurring bookings
Package bookings
Membership bookings
SMS reminders
WhatsApp notifications