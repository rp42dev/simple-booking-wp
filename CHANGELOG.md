# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.11] - In Progress

### Added
- **Redirect & Success Page System**: Automatic creation of success and cancel pages on plugin activation
- **Improved User Experience**: Custom pages for post-payment redirects instead of generic URLs
- **Stripe Redirect URLs**: Updated checkout sessions to use dedicated success/cancel pages

### Technical
- **Page Auto-Creation**: Plugin activation hook creates default pages if they don't exist
- **Fallback URLs**: Graceful fallback to query parameter URLs if pages are missing
- **Option Storage**: Page IDs stored in WordPress options for persistent configuration

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