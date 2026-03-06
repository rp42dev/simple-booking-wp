# Simple Booking – Development Roadmap

A lightweight modular WordPress booking engine with Stripe payments and Google Calendar integration.

This roadmap defines future development phases to expand the plugin while keeping the architecture simple, maintainable, and modular.

Each version milestone should be implemented incrementally and tested before progressing.

## Current Version
v2.2.1 (IN PROGRESS) → v2.3 (NEXT)

Core booking flow is operational.

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

Status: 🟡 In Progress (Micro Stage 1 Complete)

### Stage Plan

- **Micro Stage 1 (Completed):** Add `Schedule Mode` to each service (`Inherit Global` / `Custom Service`)
- **Micro Stage 2 (Next):** Hide/disable custom day/hour controls when mode is `Inherit`
- **Micro Stage 3 (Next):** Per-day time ranges in structured format (separate intervals per weekday)
- **Micro Stage 4 (Next):** "Effective Schedule" preview and conflict messaging in admin UI

### Micro Stage 1 Notes

- Added service-level `schedule_mode` meta with default `inherit`
- Availability checks now apply service day/hour restrictions only in `custom` mode
- Buffer enforcement remains active for both modes
- Keeps backward compatibility with existing global Working Schedule

## v2.3 – Google Meet Auto Generation

Allow automatic meeting link creation.

When Google event is created:

generate Google Meet link automatically

Requires Google Calendar API conferenceData.

Generated link should appear in:

confirmation email

calendar event

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