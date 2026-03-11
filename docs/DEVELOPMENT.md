# Development Guide

Technical reference for setting up and extending Simple Booking.

See [`CONTRIBUTING.md`](../CONTRIBUTING.md) for workflow rules and release control.

---

## Architecture Decisions

### Why Module-Aware File Loading?

**Chosen approach:** Keep one admin and one codebase, but gate features by module availability (installed files + license eligibility).

| Option | Verdict | Reason |
|--------|---------|--------|
| Feature flags only (Pro code always loaded) | ❌ | Hard to package true module variants |
| Separate plugins (two codebases) | ❌ | Code duplication, harder to maintain |
| **Module registry + optional loading** | ✅ | Single admin UX, plug-and-play modules, safer bootstrap |

```php
// Core always loaded
require 'post-types/class-booking-service.php';
require 'post-types/class-booking.php';
require 'admin/class-admin-settings.php';
require 'frontend/class-booking-form.php';
require 'booking/class-booking-creator.php';
require 'license/class-license-manager.php';
require 'modules/class-module-manager.php';
require 'calendar/interface-calendar-provider.php';
require 'calendar/class-calendar-provider-manager.php';
require 'calendar/providers/class-ics-provider.php';

// Optional modules — loaded if module file exists
$this->require_optional_dependency( 'calendar/providers/class-google-provider.php' );
$this->require_optional_dependency( 'calendar/providers/class-outlook-provider.php' );
$this->require_optional_dependency( 'google/class-google-calendar.php' );
$this->require_optional_dependency( 'outlook/class-outlook-calendar.php' );

// Runtime gates still use license checks where required
$module_manager->is_module_available( 'calendar_google' );
```

This avoids maintaining two separate admin screens. Settings stay visible in one place and unavailable modules are disabled with explicit reason text.

---

## Security Considerations

### License Validation Threat Model

| Threat | Mitigation |
|--------|-----------|
| License key sharing (multiple sites, one key) | Activation limits enforced server-side |
| API bypass (hardcoding `is_pro_active()`) | Obfuscation optional; focus on honest users |
| File inclusion (manually requiring Pro files) | Pro files are not shipped in free build |
| Cache manipulation | Transients use site-specific keys |

**Not worth fighting:** Determined pirates will always find a way. Make Pro valuable, not uncrackable.

### WordPress.org Compliance Checklist

- ✅ GPL v2+ license
- ✅ All code readable (no obfuscation in free build)
- ✅ Free version works without license check
- ✅ Sanitization and escaping throughout
- ✅ Translatable strings (`__()`, `_e()`)
- ✅ No tracking without opt-in
- ✅ Upgrade prompts are contextual, not intrusive

---

## Performance: Caching Strategy

### License Status Cache

- **Duration:** 24 hours (`DAY_IN_SECONDS`)
- **Storage:** WordPress transients
- **Invalidation:** On activate/deactivate, or manual refresh
- **Fallback:** Use last known status if API is unreachable — never block site functionality

**Cache keys:**
```
simple_booking_license_cache        — main status cache (plugin)
simple_booking_license_check_{hash} — server-side rate limit key
```

### API Rate Limiting

- Client: max 1 check per hour (unless manual refresh)
- Server: max 10 requests per minute per IP
- Timeout: 15 seconds per request (`wp_remote_post`)
- On failure: exponential backoff, fall back to cached value

---

## Database Schema

### WordPress Options

**`simple_booking_license`** (auto-load: no)
```php
[
    'key'          => 'XXXX-XXXX-XXXX-XXXX',
    'status'       => 'active|expired|revoked',
    'plan'         => 'pro_personal|pro_business|pro_agency',
    'expires'      => '2027-03-07',       // Y-m-d
    'activated_at' => '2026-03-07 12:34:56',
    'last_check'   => 1709821496,         // Unix timestamp
]
```

**`simple_booking_license_cache`** (transient, 24 hr)
```php
[
    'valid'   => true,
    'status'  => 'active',
    'plan'    => 'pro_personal',
    'expires' => '2027-03-07',
]
```

### License Server Schema

```sql
CREATE TABLE licenses (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    license_key     VARCHAR(64) UNIQUE NOT NULL,
    plan            ENUM('pro_personal','pro_business','pro_agency') NOT NULL,
    status          ENUM('active','expired','revoked') NOT NULL,
    max_activations INT NOT NULL,
    customer_email  VARCHAR(255) NOT NULL,
    purchase_id     VARCHAR(255),
    created_at      DATETIME NOT NULL,
    expires_at      DATETIME NOT NULL,
    INDEX idx_license_key (license_key),
    INDEX idx_status (status),
    INDEX idx_expires (expires_at)
);

CREATE TABLE activations (
    id           INT PRIMARY KEY AUTO_INCREMENT,
    license_id   INT NOT NULL,
    site_url     VARCHAR(255) NOT NULL,
    site_hash    VARCHAR(64) NOT NULL,
    activated_at DATETIME NOT NULL,
    last_check   DATETIME NOT NULL,
    checks_count INT DEFAULT 0,
    FOREIGN KEY (license_id) REFERENCES licenses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_activation (license_id, site_hash),
    INDEX idx_last_check (last_check)
);
```

---

## API Endpoints Specification

### POST `/api/v1/licenses/activate`

**Request:**
```json
{ "license_key": "XXXX-XXXX-XXXX-XXXX", "site_url": "https://example.com", "product": "simple-booking" }
```

**Success (200):**
```json
{ "success": true, "license": { "status": "active", "plan": "pro_personal", "expires": "2027-03-07", "activations_remaining": 0, "max_activations": 1 } }
```

**Errors:** 400 invalid format · 404 not found · 409 activation limit · 410 expired/revoked

---

### POST `/api/v1/licenses/deactivate`

**Request:**
```json
{ "license_key": "XXXX-XXXX-XXXX-XXXX", "site_url": "https://example.com" }
```

**Success (200):**
```json
{ "success": true, "message": "License deactivated successfully" }
```

---

### GET `/api/v1/licenses/check`

```
GET /api/v1/licenses/check?license_key=XXXX&site_url=https://example.com
```

**Success (200):**
```json
{ "valid": true, "status": "active", "plan": "pro_personal", "expires": "2027-03-07" }
```

Rate limit: 10 requests/minute per IP.

---

## Upgrade Prompt Guidelines

### Where to show prompts (high-conversion locations)

1. Service editor — Stripe Price ID field
2. Settings page — Pro sections
3. Booking list — admin management view

### Where NOT to show prompts

- Dashboard widgets (too aggressive)
- Every admin page (annoying)
- During booking creation (interrupts flow)
- Modal popups (intrusive)

### Prompt design

- Lead with the benefit ("Accept payments with Stripe")
- Single clear CTA ("Upgrade to Pro →")
- Soft colors — never guilt-trip or fake urgency
- Never disable free features

---

## Testing Strategy Overview

See [`docs/TESTING.md`](TESTING.md) for the full test plan and results log.

### Unit Tests — License Manager

```php
test_get_license_key_empty()
test_set_license_key_stores_correctly()
test_activate_license_with_valid_key()
test_activate_license_with_invalid_key()
test_deactivate_license_clears_data()
test_check_license_status_uses_cache()
test_is_pro_active_returns_false_when_free()
test_is_pro_active_returns_true_when_active()
test_grace_period_calculation()
```

### Integration Tests — Feature Gating

```php
test_stripe_files_not_loaded_when_free()
test_stripe_files_loaded_when_pro()
test_service_editor_hides_pro_fields_when_free()
```
