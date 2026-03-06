# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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