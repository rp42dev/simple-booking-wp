# Simple Booking

A lightweight, modular WordPress booking engine with Stripe and Google Calendar integration.

Current release: v3.0.6 (stable)

## Features

- **Custom Post Types**: Services and Bookings managed in WordPress admin
- **Stripe Checkout**: Secure payment processing with Stripe Checkout
- **Google Calendar**: Automatic event creation for bookings
- **Auto Google Meet**: Optional per-service Google Meet link generation on event creation
- **Staff Assignment UI**: Assign active staff to services directly in Service editor
- **Frontend Form**: Simple shortcode `[simple_booking_form]`
- **Webhook Processing**: Real-time booking creation after payment
- **Free Booking Support**: Services without Stripe Price ID book instantly
- **Service-Specific Forms**: Scope form to one service via shortcode attributes
- **Email Notifications**: Confirmation emails sent automatically
- **Tokenized Booking Management**: Secure reschedule/cancel links included in confirmation emails
- **Success/Cancel Pages**: Automatic creation of dedicated redirect pages for better UX
- **Meeting Link Audit**: Booking-level source tracking (`generated`, `static`, `none`)
- **Admin Override**: Editable booking meeting link with validation and admin error notice

## File Structure

```
simple-booking/
├── assets/
│   ├── css/
│   │   └── booking-form.css
│   └── js/
│       └── booking-form.js
├── includes/
│   ├── admin/
│   │   └── class-admin-settings.php
│   ├── booking/
│   │   └── class-booking-creator.php
│   ├── frontend/
│   │   └── class-booking-form.php
│   ├── google/
│   │   └── class-google-calendar.php
│   ├── post-types/
│   │   ├── class-booking-service.php
│   │   └── class-booking.php
│   ├── stripe/
│   │   └── class-stripe-handler.php
│   └── webhook/
│       └── class-stripe-webhook.php
├── composer.json
└── simple-booking.php
```

## Installation

### 1. Install Dependencies

The plugin requires the Stripe PHP SDK. You have two options:

**Option A: Using Composer (Recommended)**
```bash
cd wp-content/plugins/simple-booking
composer install
```

**Option B: Manual Download**
1. Download Stripe PHP SDK from https://github.com/stripe/stripe-php
2. Extract to: `wp-content/plugins/simple-booking/vendor/stripe-php/`

### 2. Install Plugin

1. Upload the `simple-booking` folder to `/wp-content/plugins/`
2. Or install via WordPress admin: Plugins > Add New > Upload Plugin
3. Activate the plugin

## Configuration

### 1. Configure Stripe

1. Go to **Settings > Simple Booking** in WordPress admin
2. Enter your Stripe API keys:

**Stripe Keys** (found in Stripe Dashboard > Developers > API keys):
- **Publishable Key**: `pk_test_xxxxxxxxxxxxxx`
- **Secret Key**: `sk_test_xxxxxxxxxxxxxx`
- **Webhook Secret**: `whsec_xxxxxxxxxxxxxx`

3. Create a Stripe Price ID:
   - Go to Stripe Dashboard > Products
   - Create a product or price
   - Copy the Price ID (starts with `price_`)

### 2. Configure Google Calendar (Optional)

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project
3. Enable **Google Calendar API**
4. Create OAuth 2.0 credentials:
   - Application type: Web application
   - Authorized redirect URIs: `https://your-site.com/wp-json/simple-booking/v1/google/oauth`
5. Copy credentials to plugin settings:
   - **Client ID**: `xxxxxxxxxxxxxx.apps.googleusercontent.com`
   - **Client Secret**: `xxxxxxxxxxxxxx`
6. Enter your Calendar ID (found in Google Calendar settings):
   - **Calendar ID**: `xxxxxxxxxxxxxx@group.calendar.google.com`
7. Click **Save Settings**
8. Click **Connect / Authorize Google Calendar** button to complete OAuth authorization

**Note:** The OAuth connection is optional. Without connecting, bookings will be created but won't sync to Google Calendar.

### 3. Add Stripe Webhook

1. Go to Stripe Dashboard > Developers > Webhooks
2. Add endpoint:
   - **Endpoint URL**: `https://your-site.com/wp-json/simple-booking/v1/webhook`
3. Select event: `checkout.session.completed`
4. Copy the Webhook Secret to plugin settings

## Creating Services

1. Go to **Services** in WordPress admin menu
2. Click **Add New**
3. Enter service name (e.g., "Consultation")
4. Fill in Service Details:
   - **Duration**: Service duration in minutes
   - **Stripe Price ID**: The price ID from Stripe
   - **Active**: Check to make available for booking
5. Publish

## Using the Booking Form

Add the booking form to any page using the shortcode:

```
[simple_booking_form]
```

Single-service form examples:

```
[simple_booking_form service_id="123"]
[simple_booking_form service="consultation"]
```

## How It Works

1. Customer selects a service and date/time
2. Fills in contact information
3. Clicks submit (`Proceed to Payment` for paid, `Book Now` for free)
4. Paid services: redirected to Stripe Checkout
5. Paid services: Stripe sends webhook after payment
6. Free services: booking is created immediately (no Stripe)
7. Customer is redirected to:
   - Booking Confirmed page (if configured)
   - Homepage fallback with `session_id` if success page is missing
8. Plugin creates:
   - Booking post in WordPress
   - Google Calendar event (if configured)
   - Confirmation email to customer

## Security

- All inputs are sanitized
- Nonce verification on forms
- Stripe signature verification on webhooks
- WordPress coding standards followed
