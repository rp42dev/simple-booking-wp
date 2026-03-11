# Simple Booking: Free/Pro Split Roadmap

**Strategy:** Soft Launch (Option B)  
**Start Date:** March 7, 2026  
**Target Completion:** Mid April 2026  
**Total Duration:** 21-30 days

---

## Overview

Transform Simple Booking into a freemium product with Free (WordPress.org) and Pro (licensed) versions. Implement licensing in existing codebase first, then distribute as two separate builds.

**Current shipped baseline (v3.0.15):** Google + Outlook calendar support, provider-manager architecture, staff calendar routing, webhook retry queue diagnostics, and admin calendar dropdowns are already live in the unified codebase. Roadmap items below should be read as licensing/free-pro packaging work on top of that baseline.

---

## Feature Split

### FREE Version
- ✅ Booking forms (`[simple_booking_form]` shortcode)
- ✅ Service management (custom post type)
- ✅ Booking dashboard (admin)
- ✅ Basic email notifications
- ✅ Custom availability schedules
- ✅ Static meeting links
- ✅ Free bookings (no payment required)
- ✅ ICS feed fallback (no OAuth setup)

### PRO Version (Licensed)
- 💎 **Stripe Payments** - Checkout sessions, webhooks, refunds
- 💎 **Google Calendar** - OAuth sync, automatic event creation
- 💎 **Microsoft Outlook Calendar** - Graph API sync, automatic event lifecycle
- 💎 **Auto Google Meet** - Generated meeting links
- 💎 **Multi-Staff** - Staff CPT, availability routing
- 💎 **Tokenized Links** - Secure reschedule/cancel
- 💎 **Automatic Refunds** - Configurable refund percentage
- 💎 **Customer Timezone** - Browser timezone detection
- 💎 **Advanced Scheduling** - Per-service schedules, buffer times
- 💎 **Webhooks** - booking.created external notifications
- 💎 **Priority Support** - <24hr response time

---

## Pricing Strategy

| Tier | Annual Price | Sites | Target Customer |
|------|--------------|-------|-----------------|
| **Free** | $0 | Unlimited | Hobbyists, testing |
| **Pro Personal** | $79 | 1 | Freelancers, coaches |
| **Pro Business** | $149 | 5 | Small agencies |
| **Pro Agency** | $299 | Unlimited | Large agencies |

**Discounts:**
- Early adopter: 30% off first year (launch month)
- Existing users: 90-day grace period, then 20% loyalty discount

---

## Phase 1: License Foundation (v3.1.0)

**Duration:** 3-5 days  
**Focus:** Core licensing infrastructure

### Deliverables

#### 1.1 License Manager Class ✏️
**File:** `includes/license/class-license-manager.php` (NEW)

**Methods:**
```php
- get_license_key(): string
- set_license_key($key): bool
- activate_license($key): true|WP_Error
- deactivate_license(): bool
- check_license_status(): array
- is_pro_active(): bool
- is_feature_available($feature): bool
- get_grace_period_remaining(): int
```

**Database:**
- Option: `simple_booking_license`
- Option: `simple_booking_license_cache` (24hr transient)

**Features:**
- Remote API validation (your server)
- 24-hour cache for performance
- 30-day grace period from first install
- Automatic expiry handling

---

#### 1.2 Main Plugin Modifications ✏️
**File:** `simple-booking.php`

**Changes:**
1. Add `$license_manager` property
2. Modify `load_dependencies()` - conditional file loading
3. Modify `init()` - conditional Staff CPT registration
4. Add `is_pro_active()` helper method
5. Add `get_license_manager()` accessor

**Conditional Loading Logic:**
```php
// FREE CORE - Always load
require 'post-types/class-booking-service.php';
require 'post-types/class-booking.php';
require 'admin/class-admin-settings.php';
require 'frontend/class-booking-form.php';
require 'booking/class-booking-creator.php';
require 'license/class-license-manager.php';
require 'calendar/interface-calendar-provider.php';
require 'calendar/class-calendar-provider-manager.php';
require 'calendar/providers/class-ics-provider.php';

// PRO FEATURES - Only if licensed
if ( $this->is_pro_active() ) {
    require 'stripe/class-stripe-handler.php';
    require 'webhook/class-stripe-webhook.php';
    require 'google/class-google-calendar.php';
   require 'outlook/class-outlook-calendar.php';
   require 'calendar/providers/class-google-provider.php';
   require 'calendar/providers/class-outlook-provider.php';
    require 'post-types/class-staff.php';
    require 'webhook/class-booking-webhook.php';
}
```

---

#### 1.3 Admin License Settings Tab ✏️
**File:** `includes/admin/class-admin-settings.php`

**New Section:** "License" (priority 1, displayed first)

**Fields:**
- License Key (password field with show/hide toggle)
- Activate/Deactivate button
- Status display (Active/Expired/Invalid/Grace Period)
- Plan type display
- Expiry date display
- Grace period countdown (if applicable)
- "Get Pro" link (if free)

**AJAX Handlers:**
- `simple_booking_activate_license`
- `simple_booking_deactivate_license`

---

#### 1.4 License API Server ✏️
**Platform:** PHP REST API (your domain)

**Endpoints:**
```
POST /api/v1/licenses/activate
POST /api/v1/licenses/deactivate
GET  /api/v1/licenses/check
```

**Database Schema:**
```sql
CREATE TABLE licenses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    license_key VARCHAR(64) UNIQUE,
    plan ENUM('pro_personal', 'pro_business', 'pro_agency'),
    status ENUM('active', 'expired', 'revoked'),
    max_activations INT,
    activations INT,
    created_at DATETIME,
    expires_at DATETIME
);

CREATE TABLE activations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    license_id INT,
    site_url VARCHAR(255),
    activated_at DATETIME,
    last_check DATETIME,
    FOREIGN KEY (license_id) REFERENCES licenses(id)
);
```

---

#### 1.5 Testing Checklist ✅
- [ ] Fresh install (free) - no Pro files loaded
- [ ] Activate valid license - Pro files load
- [ ] Deactivate license - Pro features disabled
- [ ] License expiry triggers grace period
- [ ] Grace period expiry disables Pro features
- [ ] Invalid license shows error message
- [ ] API server handles rate limiting
- [ ] Cache invalidation works correctly
- [ ] Site migration (deactivate/activate) works

---

## Phase 2: Admin UI & Gates (v3.2.0)

**Duration:** 4-6 days  
**Focus:** User experience and feature restrictions

### Deliverables

#### 2.1 Pro Badges on Settings ✏️
**File:** `includes/admin/class-admin-settings.php`

**Sections to Badge:**
- Stripe Settings → 💎 PRO
- Google Calendar Settings → 💎 PRO
- Refund Settings → 💎 PRO
- Webhook Settings → 💎 PRO
- Working Schedule (buffer fields) → 💎 PRO

**Behavior:**
- Show badge on section title
- Disable all fields if free
- Show upgrade prompt above disabled fields
- Link to pricing page

**CSS:**
```css
.simple-booking-pro-badge {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    margin-left: 8px;
    text-transform: uppercase;
}

.simple-booking-upgrade-prompt {
    background: #f0f7ff;
    border-left: 4px solid #667eea;
    padding: 12px;
    margin: 10px 0;
}
```

---

#### 2.2 Service Editor Restrictions ✏️
**File:** `includes/post-types/class-booking-service.php`

**Field-Level Gating:**

1. **Stripe Price ID**
   - Add PRO badge
   - Disable if free: `<input disabled>`
   - Tooltip: "Upgrade to Pro to accept payments"

2. **Staff Assignment Section**
   - Hide entirely if free: `if ( simple_booking()->is_pro_active() )`
   - Show upgrade card instead

3. **Custom Schedule Mode**
   - Show PRO badge on "Custom" option
   - Disable radio if free
   - Tooltip: "Pro required for per-service schedules"

4. **Google Calendar Toggles**
   - Create Event checkbox → PRO badge + disable
   - Auto Google Meet checkbox → PRO badge + disable

**Upgrade Card Template:**
```html
<div class="simple-booking-upgrade-card">
    <h3>🚀 Unlock Premium Features</h3>
    <p>Staff management requires Simple Booking Pro</p>
    <a href="[pricing_url]" class="button button-primary">
        Upgrade to Pro
    </a>
</div>
```

---

#### 2.3 Frontend Form Validation ✏️
**File:** `includes/frontend/class-booking-form.php`

**Validation Rules:**

1. **Service Selection (AJAX)**
   ```php
   if ( has_stripe_price && !is_pro_active() ) {
       return WP_Error('pro_required', 'This paid service requires Pro');
   }
   ```

2. **Reschedule/Cancel Actions**
   ```php
   if ( in_array($action, ['reschedule', 'cancel']) && !is_pro_active() ) {
       wp_redirect( admin_url('admin.php?page=simple_booking_settings&tab=license') );
       exit;
   }
   ```

3. **Staff-Specific Bookings**
   ```php
   // Silently ignore staff assignments in free version
   if ( !is_pro_active() ) {
       unset($data['staff_id']);
   }
   ```

---

#### 2.4 Email Template Modifications ✏️
**File:** `includes/booking/class-booking-creator.php`

**Changes:**

1. **Conditional Tokens**
   ```php
   if ( simple_booking()->is_pro_active() ) {
       $reschedule_link = self::get_management_url(...);
       $cancel_link = self::get_management_url(...);
   } else {
       $reschedule_link = '[Reschedule feature available in Pro]';
       $cancel_link = '[Cancel feature available in Pro]';
   }
   ```

2. **Email Footer**
   ```php
   $footer = simple_booking()->is_pro_active() 
       ? 'Powered by Simple Booking Pro'
       : 'Powered by Simple Booking | <a href="[pricing]">Upgrade to Pro</a>';
   ```

---

#### 2.5 Admin Notices System ✏️
**File:** `includes/license/class-license-manager.php`

**Notice Types:**

1. **Welcome (Free)**
   - Show on first activation
   - Dismissible (reappears after 7 days)
   - Message: "🎉 Welcome! Upgrade to Pro for payments & calendar sync"
   - CTA: "View Pro Features"

2. **Grace Period Warning**
   - Show when license expires
   - NOT dismissible
   - Message: "⚠️ License expired. Grace period: X days remaining"
   - CTA: "Renew License"

3. **Grace Period Expired**
   - Show after grace period
   - NOT dismissible
   - Message: "❌ Pro features disabled. Renew to restore functionality"
   - CTA: "Renew Now"

4. **Activation Success**
   - Show after license activation
   - Auto-dismiss after 5 seconds
   - Message: "✅ Simple Booking Pro activated!"

---

#### 2.6 Testing Checklist ✅
- [ ] Free user sees all Pro badges
- [ ] Pro settings fields disabled for free
- [ ] Service editor shows upgrade prompts
- [ ] Frontend blocks paid services in free
- [ ] Reschedule/cancel URLs redirect to license page
- [ ] Emails render correctly (free vs pro)
- [ ] Admin notices display at correct times
- [ ] Notice dismissal works correctly

---

## Phase 3: Free Distribution (v3.3.0)

**Duration:** 2-3 days  
**Focus:** WordPress.org submission

### Deliverables

#### 3.1 Build Script ✏️
**File:** `build-free.sh` (NEW)

**Script Actions:**
```bash
#!/bin/bash
# Remove Pro files
rm -rf includes/stripe/
rm -rf includes/google/
rm -rf includes/webhook/class-booking-webhook.php
rm includes/post-types/class-staff.php

# Modify main file (remove Pro loading)
sed -i '/PRO FEATURES/,/}/d' simple-booking.php

# Generate readme.txt for WordPress.org
cp readme-wporg.txt readme.txt

# Create zip
zip -r simple-booking-free.zip . -x "*.git*" "node_modules/*" "build-free.sh"
```

---

#### 3.2 WordPress.org Submission ✏️

**Required Files:**

1. **readme.txt** (WordPress.org format)
   ```
   === Simple Booking ===
   Contributors: yourusername
   Tags: booking, appointments, services
   Requires at least: 5.8
   Tested up to: 6.4
   Stable tag: 3.3.0
   License: GPLv2 or later
   
   Lightweight booking engine for services and appointments.
   
   == Description ==
   Free booking forms for your services...
   [Feature list]
   
   == Upgrade to Pro ==
   - Stripe payments
   - Google Calendar sync
   - Multi-staff management
   [Full list]
   
   Learn more: [your-site.com/pricing]
   ```

2. **Assets:**
   - `assets/banner-1544x500.png` - WordPress.org banner
   - `assets/icon-256x256.png` - Plugin icon
   - `assets/screenshot-1.png` - Booking form in action
   - `assets/screenshot-2.png` - Service editor
   - `assets/screenshot-3.png` - Admin dashboard

---

#### 3.3 Pro Upgrade Page ✏️
**Platform:** Your website

**Page Structure:**
```html
Hero Section
├── Headline: "Transform Your Booking System"
├── Subheadline: "Accept payments, sync calendars, manage staff"
└── CTA: "View Pricing"

Feature Comparison Table
├── Column: Free
├── Column: Pro Personal
├── Column: Pro Business
└── Column: Pro Agency

Testimonials Section
├── Customer quote #1
├── Customer quote #2
└── Customer quote #3

Pricing Cards
├── Pro Personal - $79/year
├── Pro Business - $149/year (Popular)
└── Pro Agency - $299/year

FAQ Section
└── Common questions

Purchase CTA
└── Stripe Checkout integration
```

---

#### 3.4 Documentation ✏️

**Create Docs:**

1. **Installation Guide** (`docs/installation.md`)
   - WordPress.org installation
   - Pro version installation
   - License activation steps

2. **Migration Guide** (`docs/migration.md`)
   - Free → Pro upgrade path
   - Data preservation
   - Feature activation checklist

3. **Feature Comparison** (`docs/features.md`)
   - Side-by-side table
   - Use case examples
   - Screenshots

4. **Troubleshooting** (`docs/troubleshooting.md`)
   - License activation issues
   - Common error messages
   - Support contact info

---

#### 3.5 Testing Checklist ✅
- [ ] Free build contains zero Pro code
- [ ] Free version installs on clean WordPress
- [ ] All free features work error-free
- [ ] Upgrade prompts visible and linked
- [ ] readme.txt passes WordPress.org validator
- [ ] Assets meet WordPress.org requirements (dimensions, file size)
- [ ] Screenshots showcase features accurately
- [ ] Documentation links work

---

## Phase 4: Pro Launch & Updates (v3.4.0)

**Duration:** 4-5 days  
**Focus:** Monetization and distribution

### Deliverables

#### 4.1 License Server Implementation ✏️

**Option A: Lemon Squeezy (Recommended - Fastest)**
- Hosted solution (no server management)
- Built-in license key generation
- API for validation
- Automatic tax handling
- Customer portal included
- Setup time: 1 day

**Option B: Custom Server**
- Full control
- PHP + MySQL
- API endpoints (activate, deactivate, check)
- Setup time: 3-4 days

**Recommendation:** Start with Lemon Squeezy for speed, migrate to custom later if needed.

---

#### 4.2 Automated Updates ✏️
**File:** `includes/license/class-updater.php` (NEW)

**Implementation:**
- Use WordPress Plugin Update Checker library
- Check for updates daily
- Validate license before downloading
- Background updates (WordPress standard)

**Update Server Endpoint:**
```
GET https://yoursite.com/api/v1/updates/simple-booking-pro
Response: {
  "version": "3.4.0",
  "download_url": "https://[signed-url]",
  "requires": "5.8",
  "tested": "6.4",
  "changelog": "# What's New..."
}
```

---

#### 4.3 Customer Portal ✏️
**Platform:** Your website (members area)

**Features:**
- Login/register
- View license keys
- Manage activations (see sites, deactivate)
- Download Pro plugin (latest version)
- View invoices
- Renew/upgrade subscription
- Submit support tickets

**Pages:**
- `/account/` - Dashboard
- `/account/licenses/` - License management
- `/account/downloads/` - Plugin downloads
- `/account/billing/` - Invoices & payment methods
- `/account/support/` - Ticket system

---

#### 4.4 Payment Setup ✏️

**Stripe Products:**
```
Product: Simple Booking Pro Personal
Price: $79/year
Metadata: plan=pro_personal, sites=1

Product: Simple Booking Pro Business
Price: $149/year
Metadata: plan=pro_business, sites=5

Product: Simple Booking Pro Agency
Price: $299/year
Metadata: plan=pro_agency, sites=unlimited
```

**Checkout Flow:**
1. Customer selects plan → Stripe Checkout
2. Webhook receives `checkout.session.completed`
3. Generate license key
4. Store in database (licenses table)
5. Send email with license key
6. Create customer account
7. Grant access to customer portal

---

#### 4.5 Launch Checklist ✅

**Pre-Launch (1 week before):**
- [ ] License server tested (100+ test activations)
- [ ] Payment processing validated (test purchases)
- [ ] Customer portal functional (all pages)
- [ ] Update system working (manual test)
- [ ] Email templates designed (license, renewal, support)
- [ ] Documentation complete (installation, FAQ)
- [ ] Support system ready (helpdesk or email)
- [ ] Early adopter discount code created (30% off)
- [ ] Affiliate program setup (optional, 20% commission)

**Launch Day:**
- [ ] Submit free version to WordPress.org
- [ ] Activate Pro purchase page
- [ ] Send launch email to mailing list
- [ ] Post on social media (Twitter, LinkedIn, Facebook)
- [ ] Post on WordPress forums/groups
- [ ] Update GitHub repo (public free, private pro)
- [ ] Monitor error logs

**Post-Launch (First Week):**
- [ ] Respond to support within 24hrs
- [ ] Monitor license activation success rate (target: >95%)
- [ ] Track conversion rate (target: 5-10%)
- [ ] Fix critical bugs within 48hrs
- [ ] Gather user feedback
- [ ] Iterate on upgrade prompts based on data

---

## 4️⃣ Improve UX (Wizard, Onboarding, Setup Guides) - Phase 5 (v3.5.0)

**Duration:** 3-5 days  
**Focus:** Remove setup friction and improve first-time activation success

### Deliverables

#### 5.1 Setup Wizard ✏️
**Files:**
- `includes/admin/class-onboarding-wizard.php` (NEW)
- `assets/js/admin-onboarding.js` (NEW)
- `assets/css/admin-onboarding.css` (NEW)

**Wizard Steps:**
1. Welcome + plan selection
2. Business setup (timezone, work hours)
3. Services setup (create first service)
4. Payments setup (Pro: Stripe keys + test)
5. Calendar setup (Pro: connect selected provider + test)
6. Go-live check (preview + test booking)

---

#### 5.2 Onboarding Checklist in Admin ✏️
**File:** `includes/admin/class-onboarding-checklist.php` (NEW)

**Checklist Tasks:**
- Create first service
- Add booking form shortcode to a page
- Configure email template
- (Pro) Connect Stripe
- (Pro) Connect calendar provider (Google or Outlook)
- Run test booking

---

#### 5.3 Setup Guides & Contextual Help ✏️
**Files:**
- `includes/admin/class-admin-settings.php` (help blocks)
- `docs/setup/` (guide sources)

**Guide Topics:**
- 5-minute Free setup
- 15-minute Pro setup
- Stripe troubleshooting quick fixes
- Google Calendar troubleshooting quick fixes
- Outlook Calendar troubleshooting quick fixes

---

#### 5.4 UX Copy & Empty-State Improvements ✏️
**Files:**
- `includes/admin/class-admin-settings.php`
- `includes/post-types/class-booking-service.php`
- `includes/frontend/class-booking-form.php`

**Improvements:**
- Clear empty states with next-step actions
- Inline validation in plain language
- Better CTA labels: "Create your first service", "Connect Stripe", "Run test booking"

---

#### 5.5 Testing Checklist ✅
- [ ] New free user can publish booking form in one session
- [ ] New pro user can complete Stripe + calendar-provider setup in one session
- [ ] Wizard state resumes after refresh/logout
- [ ] Checklist progress updates automatically from saved config
- [ ] No regressions in booking, Stripe, Google, or Outlook flows

---

## 6️⃣ Calendar Provider Architecture (Google + Outlook + ICS Fallback) - Phase 6 (Delivered in v3.0.15 baseline)

**Status:** Core provider architecture shipped early during v3.0 stabilization  
**Focus:** Maintain and extend the existing multi-provider calendar sync foundation

### Deliverables

#### 6.1 Provider Interface Layer ✏️
**Files:**
- `includes/calendar/interface-calendar-provider.php` (NEW)
- `includes/calendar/class-calendar-provider-manager.php` (NEW)

**Standard Methods:**
- `create_event()`
- `update_event()`
- `delete_event()`
- `fetch_busy_windows()`
- `is_connected()`

**Acceptance Criteria:**
- Booking flow calls provider manager only (not provider-specific classes directly)
- Provider can be swapped without changing booking core logic

**Current State:** ✅ Shipped

---

#### 6.2 Google Provider Adapter ✏️
**Files:**
- `includes/calendar/providers/class-google-provider.php` (NEW)

**Scope:**
- Wrap existing Google logic behind provider interface
- Preserve token refresh + retry behavior
- Preserve current staff routing support

**Current State:** ✅ Shipped, including staff calendar selection and Meet fallback handling

---

#### 6.3 Outlook Provider Adapter (Microsoft Graph) ✏️
**Files:**
- `includes/calendar/providers/class-outlook-provider.php` (NEW)

**Scope:**
- OAuth flow for Microsoft account
- Event create/update/delete via Graph API
- Busy-window lookup for availability checks

**Admin Requirements:**
- Microsoft App Client ID
- Microsoft App Client Secret
- Tenant/common settings

**Current State:** ✅ Shipped, including staff calendar CRUD parity and admin calendar dropdown support

---

#### 6.4 ICS Feed Fallback Provider ✏️
**Files:**
- `includes/calendar/providers/class-ics-provider.php` (NEW)
- `includes/calendar/class-ics-feed-controller.php` (NEW)

**Scope:**
- Generate per-site or per-staff ICS subscription URL
- Include booking events as VEVENTs
- Reflect updates/cancellations through regenerated feed output
- No OAuth required

**UX Note:**
- Document that refresh timing depends on calendar client polling interval

**Current State:** ⚠️ Provider selection/gating exists; feed-controller follow-up may still be needed depending on packaging approach

---

#### 6.5 Calendar Provider Selection UI ✏️
**Files:**
- `includes/admin/class-admin-settings.php`

**Options:**
1. Google Calendar API
2. Microsoft Outlook Calendar (Graph)
3. ICS Feed (fallback)

**Behavior:**
- One active provider at a time (MVP)
- ICS always available as fallback option
- Pro-gate Google/Outlook, allow ICS in Free

**Current State:** ✅ Shipped in settings UI

---

#### 6.6 Testing Checklist ✅
- [x] Google provider handles create/update/delete/reschedule correctly
- [x] Outlook provider handles create/update/delete/reschedule correctly
- [ ] ICS feed includes new bookings and reflects cancellations
- [x] Switching provider does not break existing booking flow
- [x] Provider failures gracefully degrade without blocking checkout

---

#### 6.7 Booking Management Link Hardening (Edge Cases) ✏️
**Files:**
- `includes/booking/class-booking-creator.php`
- `includes/frontend/class-booking-form.php`

**Scope:**
- Only latest booking in a reschedule chain is actionable
- Old reschedule/cancel links show a stale-link message with next step
- One-time-use management tokens for cancel/reschedule actions
- Idempotent handling for repeated clicks ("already processed" response)

**Acceptance Criteria:**
- [ ] Cancel from original (old) email does not leave latest rescheduled booking active
- [ ] Reusing a consumed cancel/reschedule link is blocked gracefully
- [ ] User receives clear stale-link guidance when booking has moved
- [ ] No duplicate refunds or duplicate cancellation state transitions

---

## Success Metrics

### Week 1-4 (Launch)
| Metric | Target | Tracking |
|--------|--------|----------|
| Free Installs | 100+ | WordPress.org stats |
| Pro Licenses | 10+ | License server |
| Conversion Rate | 5-10% | Google Analytics |
| Critical Bugs | 0 | Error logs |
| Support Response | <24hrs | Helpdesk |

### Month 2-3 (Growth)
| Metric | Target | Tracking |
|--------|--------|----------|
| Free Installs | 500+ | WordPress.org |
| Pro Licenses | 25+ | License server |
| MRR | $1,500+ | Stripe dashboard |
| WP.org Rating | 4.5+ | Reviews |
| Churn Rate | <5% | Subscription data |

### Month 6 (Maturity)
| Metric | Target | Tracking |
|--------|--------|----------|
| Free Installs | 2,000+ | WordPress.org |
| Pro Licenses | 100+ | License server |
| MRR | $6,000+ | Stripe dashboard |
| Support Tickets | <20/week | Helpdesk |
| Feature Requests | Prioritized by Pro users | Feedback board |

---

## Risk Management

| Risk | Probability | Impact | Mitigation Strategy |
|------|-------------|--------|---------------------|
| WordPress.org approval delay | Medium | Medium | Submit 2 weeks early, perfect readme |
| License server downtime | Low | High | Use 99.9% uptime host, monitoring |
| Free users upset by gating | Medium | Low | 90-day grace for existing users |
| Update system breaks | Low | High | Thorough testing, manual fallback |
| Low conversion rate | Medium | Medium | A/B test prompts, improve onboarding |
| Piracy (license sharing) | Medium | Medium | Activation limits, domain validation |
| Support overwhelm | Medium | High | Knowledge base, FAQ, email templates |

---

## File Structure (After Implementation)

```
simple-booking/
├── includes/
│   ├── calendar/
│   │   ├── interface-calendar-provider.php   [SHIPPED]
│   │   ├── class-calendar-provider-manager.php [SHIPPED]
│   │   └── providers/
│   │       ├── class-google-provider.php     [PRO ONLY / SHIPPED]
│   │       ├── class-outlook-provider.php    [PRO ONLY / SHIPPED]
│   │       └── class-ics-provider.php        [FREE / SHIPPED FOUNDATION]
│   ├── license/
│   │   ├── class-license-manager.php      [NEW - v3.1.0]
│   │   └── class-updater.php              [NEW - v3.4.0]
│   ├── stripe/                            [PRO ONLY]
│   │   └── class-stripe-handler.php
│   ├── google/                            [PRO ONLY]
│   │   └── class-google-calendar.php
│   ├── outlook/                           [PRO ONLY]
│   │   └── class-outlook-calendar.php
│   ├── post-types/
│   │   ├── class-booking-service.php      [MODIFIED]
│   │   ├── class-booking.php              [FREE]
│   │   └── class-staff.php                [PRO ONLY]
│   ├── webhook/
│   │   ├── class-stripe-webhook.php       [PRO ONLY]
│   │   └── class-booking-webhook.php      [PRO ONLY]
│   ├── admin/
│   │   └── class-admin-settings.php       [MODIFIED]
│   ├── frontend/
│   │   └── class-booking-form.php         [MODIFIED]
│   └── booking/
│       └── class-booking-creator.php      [MODIFIED]
├── docs/                                  [NEW - v3.3.0]
│   ├── installation.md
│   ├── migration.md
│   ├── features.md
│   └── troubleshooting.md
├── assets/
│   ├── banner-1544x500.png               [NEW]
│   ├── icon-256x256.png                  [NEW]
│   └── screenshots/                       [NEW]
├── simple-booking.php                     [MODIFIED]
├── readme.txt                             [NEW - WordPress.org]
├── build-free.sh                          [NEW]
└── ROADMAP.md                             [THIS FILE]
```

---

## Next Steps

1. **Review this roadmap** - Adjust timelines/scope if needed
2. **Set up development environment** - Staging site for testing
3. **Begin Phase 1 (safe fork flow)** - Start from `refocus/stable-v3.0.16`
4. **Daily standups** - Track progress, blockers
5. **Version control** - Branches: `feat/license-core` -> `feat/license-ui` -> `feat/provider-compat` -> `feat/free-build`

---

## Quick Start Commands

```bash
# Start from stable refocus baseline
git checkout refocus/stable-v3.0.16

# Create first focused branch
git checkout -b feat/license-core

# Create license directory
mkdir -p includes/license

# Start Phase 1.1 - License Manager
touch includes/license/class-license-manager.php

# Track progress
git add .
git commit -m "feat: license core groundwork"
git push origin feat/license-core
```

---

**Status:** In Progress - Refocused from stable baseline  
**Next Action:** Complete `feat/license-core` and run regression matrix  
**Estimated Completion:** TBD after fork-by-fork validation
