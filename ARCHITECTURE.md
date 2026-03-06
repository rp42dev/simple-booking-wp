# Simple Booking Plugin - Architecture Documentation

## 1. Project Overview

A lightweight WordPress booking engine with Stripe payment processing and Google Calendar integration. The plugin handles:

- Service management via custom post types
- Frontend booking form with Stripe Checkout
- Automatic booking creation via Stripe webhooks
- Google Calendar event creation for bookings
- Optional auto-generated Google Meet links per service
- Booking-level meeting link source auditing and admin override support

## 2. Folder Structure

```
simple-booking/
├── simple-booking.php           # Main plugin entry point
├── composer.json                # Composer dependencies
├── .gitignore                   # Git ignore rules
├── README.md
├── includes/
│   ├── admin/
│   │   └── class-admin-settings.php      # Admin settings page
│   ├── booking/
│   │   └── class-booking-creator.php      # Booking creation logic
│   ├── frontend/
│   │   └── class-booking-form.php         # Frontend booking form
│   ├── google/
│   │   └── class-google-calendar.php      # Google Calendar OAuth & API
│   ├── post-types/
│   │   ├── class-booking.php              # Booking post type
│   │   └── class-booking-service.php      # Service post type
│   ├── stripe/
│   │   └── class-stripe-handler.php       # Stripe API wrapper
│   └── webhook/
│       └── class-stripe-webhook.php        # Stripe webhook handler
├── assets/
│   ├── css/booking-form.css
│   └── js/booking-form.js
└── vendor/stripe-php/           # Stripe SDK (installed via composer)
```

## 3. Booking Flow (Step-by-Step)

### 3.1 Customer Submits Booking Form

1. **Frontend Form** (`class-booking-form.php`)
   - Shortcode: `[simple_booking_form]`
   - Shortcode supports optional scoping attributes:
     - `[simple_booking_form service_id="123"]`
     - `[simple_booking_form service="consultation"]`
   - User selects service, date/time, enters contact info
   - AJAX submission to `wp_ajax_simple_booking_submit`
   - Nonce verification: `check_ajax_referer('simple_booking_form_nonce', 'nonce')`
   - Optional Google Calendar availability checking before payment

2. **Paid vs Free Branching** (`class-booking-form.php`)
    - If service has Stripe Price ID:
       - Creates Stripe Checkout Session with metadata:
          - `customer_name`, `customer_email`, `customer_phone`
          - `service_id`, `start_datetime`
       - Success URL: `booking-confirmed` page URL with `session_id={CHECKOUT_SESSION_ID}` appended
       - Cancel URL: `booking-cancelled` page URL
    - If service has no Stripe Price ID:
       - Skips Stripe checkout
       - Creates booking immediately via `Simple_Booking_Booking_Creator::create_booking()`
       - Sends confirmation email immediately
       - Redirects to configured success page (with homepage fallback)

3. **Customer Redirect**
   - Paid: customer redirected to Stripe Checkout, then webhook finalizes booking
   - Free: customer redirected to success page immediately after direct booking

### 3.2 Webhook Processing

1. **Webhook Endpoint** (`class-stripe-webhook.php:50`)
   - Route: `POST /wp-json/simple-booking/v1/webhook`
   - Signature verification via `verify_signature()`
   - Handles: `checkout.session.completed`

2. **Booking Creation** (`handle_checkout_completed()`)
   - Check for existing booking by payment ID
   - Get service metadata from session
   - Calculate end datetime from duration
   - Call `Simple_Booking_Booking_Creator::create_booking()`

### 3.3 Booking Post Creation

1. **Create Booking Post** (`class-booking-creator.php:18`)
   - Calls `Simple_Booking_Post::create($data)`
   - Creates post with title: "Service Name - Customer Name"
   - Saves meta fields

2. **Google Calendar Event** (`class-booking-creator.php:27`)
   - Calls `create_google_event($data)`
   - If successful, saves `_google_event_id` meta

3. **Confirmation Email** (`class-booking-creator.php:70`)
   - Sends email to customer via `wp_mail()`

## 4. Stripe Flow

### 4.1 Configuration
- **Option Key**: `simple_booking_settings`
- **Stored Keys**:
  - `stripe_publishable_key` - Frontend use
  - `stripe_secret_key` - API calls
  - `stripe_webhook_secret` - Signature verification

### 4.2 Checkout Session Creation
- **File**: `includes/stripe/class-stripe-handler.php`
- **Function**: `create_checkout_session($service, $booking_data)`
- Uses Stripe SDK: `\Stripe\Checkout\Session::create()`

### 4.3 Webhook Handling
- **File**: `includes/webhook/class-stripe-webhook.php`
- **Route**: `simple-booking/v1/webhook`
- **Permission**: `__return_true` (relies on signature verification)
- **Event Type**: `checkout.session.completed`

## 5. Google OAuth Flow

### 5.1 Admin Initiates OAuth
1. Admin visits Settings > Simple Booking
2. Clicks "Connect / Authorize Google Calendar"
3. Button calls `Simple_Booking_Google_Calendar::get_oauth_url(true)`

### 5.2 State Generation
- **Function**: `get_oauth_url($save_state = true)`
- Generates UUID4: `wp_generate_uuid4()`
- Saves to option: `simple_booking_google_oauth_state`
- OAuth params:
  - `client_id` from settings
  - `redirect_uri`: `/wp-json/simple-booking/v1/google/oauth`
  - `scope`: `https://www.googleapis.com/auth/calendar.events`
  - `access_type`: `offline`
  - `prompt`: `consent`

### 5.3 OAuth Callback
- **Route**: `GET /wp-json/simple-booking/v1/google/oauth`
- **Function**: `handle_oauth_callback()`
- State verification:
  - Get `state` param from request
  - Compare with `simple_booking_google_oauth_state` option
  - Strict comparison: `$returned_state !== $saved_state`
  - Delete option after verification

### 5.4 Token Exchange
- **Function**: `exchange_code_for_tokens($code)`
- **Token URL**: `https://oauth2.googleapis.com/token`
- **Stored in**: `simple_booking_google_tokens` option
- **Token fields**:
  - `access_token`
  - `refresh_token`
  - `expires_in`
  - `created` (Unix timestamp - added manually)

## 6. Google Event Creation Flow

### 6.1 Booking Creator Calls Google
1. `Simple_Booking_Booking_Creator::create_google_event($booking_data)`
2. Checks `class_exists('Simple_Booking_Google_Calendar')` for graceful degradation
3. Creates `Simple_Booking_Google_Calendar` instance if available
4. Checks `is_connected()` - verifies `access_token` exists
5. Calls `create_event($booking_data)` if connected
6. Falls back gracefully if Google Calendar unavailable (booking still created)

### 6.2 Token Retrieval
- **Function**: `get_access_token()`
- Checks option: `simple_booking_google_tokens`
- **Token Expiry Check**:
  - If `time() > $tokens['created'] + $tokens['expires_in'] - 60`
  - Calls `refresh_token()`

### 6.3 Token Refresh
- **Function**: `refresh_token()`
- Uses `refresh_token` from stored tokens
- Updates `access_token` and `created` timestamp
- Keeps same `refresh_token`

### 6.4 Event Creation API Call
- **URL**: `https://www.googleapis.com/calendar/v3/calendars/{calendar_id}/events`
- **Method**: `wp_remote_post()`
- **Headers**:
  ```
  Authorization: Bearer {access_token}
  Content-Type: application/json
  ```
- **Payload**:
  ```json
  {
    "summary": "Service Name - Customer Name",
    "description": "Customer: Name\nEmail: email\n...",
    "start": { "dateTime": "2024-01-01T10:00:00+00:00", "timeZone": "America/New_York" },
    "end": { "dateTime": "2024-01-01T11:00:00+00:00", "timeZone": "America/New_York" }
  }
  ```

### 6.5 Response Handling
- Checks for `WP_Error`
- Checks HTTP status code
- Checks for `body['error']`
- Returns `body['id']` (Google Event ID) on success

## 7. Data Storage Structure

### 7.1 Custom Post Types

#### Booking Service (`booking_service`)
- **Post Type Key**: `booking_service`
- **Menu**: Appears under Services
- **Meta Fields**:
  | Meta Key | Type | Description |
  |----------|------|-------------|
  | `_service_duration` | integer | Duration in minutes |
  | `_stripe_price_id` | string | Stripe Price ID (e.g., `price_xxx`) |
  | `_service_active` | boolean | Whether service is available |

#### Booking (`booking`)
- **Post Type Key**: `booking`
- **Menu**: Under Services > Bookings
- **Meta Fields**:
  | Meta Key | Type | Description |
  |----------|------|-------------|
  | `_customer_name` | string | Customer's name |
  | `_customer_email` | string | Customer's email |
  | `_customer_phone` | string | Customer's phone |
  | `_service_id` | integer | Related service post ID |
  | `_start_datetime` | string | Booking start (Y-m-d H:i:s) |
  | `_end_datetime` | string | Booking end (Y-m-d H:i:s) |
  | `_stripe_payment_id` | string | Stripe Session ID |
  | `_google_event_id` | string | Google Calendar Event ID |

### 7.2 Options

| Option Key | Type | Description |
|------------|------|-------------|
| `simple_booking_settings` | array | Plugin settings (Stripe keys, Google credentials) |
| `simple_booking_google_tokens` | array | OAuth tokens (access_token, refresh_token, expires_in, created) |
| `simple_booking_google_oauth_state` | string | Temporary OAuth state (UUID4) |
| `simple_booking_success_page` | integer | WordPress page ID used for Stripe success redirect |
| `simple_booking_cancel_page` | integer | WordPress page ID used for Stripe cancel redirect |

### 7.3 Settings Array Structure

```php
// simple_booking_settings
[
    'stripe_publishable_key' => 'pk_test_xxx',
    'stripe_secret_key' => 'sk_test_xxx',
    'stripe_webhook_secret' => 'whsec_xxx',
    'google_client_id' => 'xxx.apps.googleusercontent.com',
    'google_client_secret' => 'xxx',
    'google_calendar_id' => 'xxx@group.calendar.google.com',
]
```

## 8. REST Endpoints

| Route | Methods | Handler | Permission |
|-------|---------|---------|------------|
| `/simple-booking/v1/webhook` | POST | `handle_webhook()` | Signature verification |
| `/simple-booking/v1/google/oauth` | GET | `handle_oauth_callback()` | Public (state verified) |
| `/simple-booking/v1/google/status` | GET | `get_auth_status()` | `manage_options` |
| `/simple-booking/v1/google/disconnect` | POST | `disconnect()` | `manage_options` |

## 9. Security Handling

### 9.1 Form Submissions
- **Nonce**: `wp_create_nonce('simple_booking_form_nonce')`
- **Verification**: `check_ajax_referer()`

### 9.2 Stripe Webhook
- **Signature Verification**: `verify_signature($payload, $signature)`
- Uses Stripe SDK: `\Stripe\Webhook::constructEvent()`

### 9.3 Google OAuth State
- **Generation**: `wp_generate_uuid4()` (not wp_create_nonce)
- **Storage**: WordPress option `simple_booking_google_oauth_state`
- **Verification**: Strict comparison (`!==`)
- **Deletion**: Immediately after verification

### 9.4 Admin Actions
- **Disconnect**: Uses `wp_create_nonce()` + `wp_verify_nonce()`
- **Nonce action**: `google_disconnect_{user_id}`

### 9.5 Permissions
- Admin settings: `manage_options`
- Google status/disconnect: `manage_options`
- Webhook: Open (relies on Stripe signature)
- OAuth callback: Open (relies on state verification)

## 10. Known Issues / Design Notes

### 10.1 Token Storage
- OAuth tokens stored in WordPress options table
- No encryption applied
- `refresh_token` persists across sessions

### 10.2 Token Expiry Logic
- Token expiry calculated as: `created + expires_in - 60 seconds` (60s buffer)
- If token missing `created` or `expires_in`, treated as non-expiring

### 10.3 Debug Logging
- Uses `error_log()` statements when debug mode is enabled
- Controlled by `debug_mode` setting in plugin options
- Logs: token keys, API requests/responses, HTTP status codes
- Can be toggled on/off via admin settings

### 10.4 Google Event Scope
- Uses: `https://www.googleapis.com/auth/calendar.events`
- Creates events only (no update/delete)

### 10.5 Booking Flow Assumptions
- End datetime calculated in webhook from service duration
- Google Calendar availability checking implemented
- Prevents double-bookings when Google Calendar is connected
- Falls back gracefully when Google Calendar unavailable

## 11. System Rules (Do Not Refactor Without Instruction)

1. **Do not change post type keys** - `booking_service`, `booking`
2. **Do not change meta key prefixes** - `_customer_`, `_service_`, `_stripe_`, `_google_`, `_start_`, `_end_`
3. **Do not change option keys** - `simple_booking_settings`, `simple_booking_google_tokens`
4. **Do not change REST namespaces** - `simple-booking/v1`
5. **Do not change route names** - `webhook`, `google/oauth`, `google/status`, `google/disconnect`
6. **Do not change shortcode** - `simple_booking_form`
7. **Do not modify Google OAuth scopes** without testing
8. **Do not remove token refresh logic** - required for long-lived connections

---

# LIVING DOCUMENT NOTICE

This architecture document must be updated whenever:

1. **Data structure changes**
   - New post types added
   - New meta fields added
   - New option keys added
   - Changes to existing field names

2. **OAuth flow changes**
   - New OAuth endpoints
   - Scope changes
   - Token handling changes

3. **Booking logic changes**
   - New steps in booking creation
   - Changes to webhook handling
   - New external API integrations

4. **External API changes**
   - Stripe API version changes
   - Google Calendar API changes
   - New payment providers
   - New calendar providers

**Last Updated**: 2026-03-06
