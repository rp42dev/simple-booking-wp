# Free/Pro Split - Implementation Notes

Technical considerations and best practices for the Free/Pro implementation.

---

## Architecture Decisions

### Why Conditional File Loading?

**Chosen Approach:** Load Pro files only when license is active

**Alternatives Considered:**
1. ❌ **Feature flags only** - Pro code always loaded, just disabled
   - Cons: Bloated free version, easier to crack
2. ❌ **Separate plugins** - Two completely different plugins
   - Cons: Code duplication, harder to maintain
3. ✅ **Conditional loading** - Single codebase, Pro files only load if licensed
   - Pros: Clean free build, secure, maintainable

**Benefits:**
- Free version truly lightweight (no Pro code at all)
- Security: Can't bypass license by hacking PHP
- WordPress.org compliant (no "crippled" features)
- Easy maintenance (one codebase)

---

## Security Considerations

### License Validation

**Threat Model:**
1. **License key sharing** - Multiple sites using one key
2. **API bypass** - Hardcoding is_pro_active() to return true
3. **File inclusion** - Manually requiring Pro files
4. **Cache manipulation** - Modifying cached license data

**Mitigations:**
1. **Activation limits** - Max sites per license (enforced server-side)
2. **Domain validation** - License tied to specific domain
3. **Remote checks** - Regular API validation (24hr cache max)
4. **Code obfuscation** (optional) - Make harder to modify
5. **Update gating** - Updates require valid license

**Not Worth Fighting:**
- Determined pirates will always find a way
- Focus on honest customers, not thieves
- Better to make Pro valuable than uncrackable

---

## Performance Optimization

### Caching Strategy

**License Status Cache:**
- Duration: 24 hours (DAY_IN_SECONDS)
- Storage: WordPress transients
- Invalidation: On activate/deactivate, manual refresh
- Fallback: Use local data if API unavailable

**Why 24 hours?**
- Balance between performance and license validation
- Reduces API server load
- Most licenses check once per day max
- Grace period detection still works

**Cache Keys:**
```php
simple_booking_license_cache - Main status cache
simple_booking_license_check_{site_hash} - Server-side rate limit
```

### API Request Optimization

**Rate Limiting:**
- Client: Max 1 check per hour (unless manual refresh)
- Server: Max 10 requests per minute per IP
- Exponential backoff on failures

**Timeout Handling:**
```php
wp_remote_post( $url, array(
    'timeout' => 15, // 15 seconds max
    'blocking' => true,
) );
```

**Graceful Degradation:**
- API down? Use last known status
- No internet? Use local license data
- Never block site functionality due to API issues

---

## Database Schema

### WordPress Options

**simple_booking_license** (auto-load: no)
```php
array(
    'key'          => 'XXXX-XXXX-XXXX-XXXX',
    'status'       => 'active|expired|revoked',
    'plan'         => 'pro_personal|pro_business|pro_agency',
    'expires'      => '2027-03-07', // Y-m-d format
    'activated_at' => '2026-03-07 12:34:56', // MySQL datetime
    'last_check'   => 1709821496, // Unix timestamp
)
```

**simple_booking_license_cache** (transient, 24hr)
```php
array(
    'valid'   => true,
    'status'  => 'active',
    'plan'    => 'pro_personal',
    'expires' => '2027-03-07',
)
```

### License Server Database

**licenses table:**
```sql
CREATE TABLE licenses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    license_key VARCHAR(64) UNIQUE NOT NULL,
    plan ENUM('pro_personal', 'pro_business', 'pro_agency') NOT NULL,
    status ENUM('active', 'expired', 'revoked') NOT NULL,
    max_activations INT NOT NULL,
    customer_email VARCHAR(255) NOT NULL,
    purchase_id VARCHAR(255), -- Stripe payment ID
    created_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    INDEX idx_license_key (license_key),
    INDEX idx_status (status),
    INDEX idx_expires (expires_at)
);
```

**activations table:**
```sql
CREATE TABLE activations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    license_id INT NOT NULL,
    site_url VARCHAR(255) NOT NULL,
    site_hash VARCHAR(64) NOT NULL, -- SHA-256 of site_url
    activated_at DATETIME NOT NULL,
    last_check DATETIME NOT NULL,
    checks_count INT DEFAULT 0,
    FOREIGN KEY (license_id) REFERENCES licenses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_activation (license_id, site_hash),
    INDEX idx_last_check (last_check)
);
```

---

## API Endpoints Specification

### POST /api/v1/licenses/activate

**Request:**
```json
{
    "license_key": "XXXX-XXXX-XXXX-XXXX",
    "site_url": "https://example.com",
    "product": "simple-booking"
}
```

**Success Response (200):**
```json
{
    "success": true,
    "license": {
        "status": "active",
        "plan": "pro_personal",
        "expires": "2027-03-07",
        "activations_remaining": 0,
        "max_activations": 1
    }
}
```

**Error Responses:**
- 400: Invalid license key format
- 404: License key not found
- 409: Activation limit reached
- 410: License expired or revoked

---

### POST /api/v1/licenses/deactivate

**Request:**
```json
{
    "license_key": "XXXX-XXXX-XXXX-XXXX",
    "site_url": "https://example.com"
}
```

**Success Response (200):**
```json
{
    "success": true,
    "message": "License deactivated successfully"
}
```

---

### GET /api/v1/licenses/check

**Request:**
```
GET /api/v1/licenses/check?license_key=XXXX&site_url=https://example.com
```

**Success Response (200):**
```json
{
    "valid": true,
    "status": "active",
    "plan": "pro_personal",
    "expires": "2027-03-07"
}
```

**Rate Limit:** 10 requests per minute per IP

---

## WordPress.org Submission Guidelines

### Plugin Review Requirements

**Must Have:**
- GPL-compatible license
- No encrypted/obfuscated code
- No "phone home" without explicit permission
- Proper sanitization/escaping
- Internationalization ready
- Accessibility compliant
- Security best practices

**Forbidden:**
- Tracking without opt-in
- Affiliate links (except in readme)
- Upsells in admin (subtle OK, aggressive NO)
- Requiring external accounts
- Collecting email addresses

**Our Compliance:**
- ✅ GPL v2+ license
- ✅ All code readable
- ✅ License check is optional (free works without it)
- ✅ Sanitization everywhere
- ✅ Translatable strings
- ✅ WCAG 2.0 AA compliant
- ✅ No tracking (license check only)
- ✅ Upgrade prompts subtle
- ✅ No external accounts required

---

## Upgrade Prompt Best Practices

### Where to Show Prompts

**High Conversion Locations:**
1. ✅ Service Editor (Stripe Price ID field)
2. ✅ Settings page (Pro sections)
3. ✅ Booking form (when paid service selected)
4. ✅ Booking list (upgrade to manage)

**Low Conversion (Avoid):**
1. ❌ Dashboard widgets (too aggressive)
2. ❌ Every admin page (annoying)
3. ❌ During booking creation (interrupts flow)
4. ❌ Modal popups (intrusive)

### Prompt Design Principles

**DO:**
- Explain the benefit ("Accept payments with Stripe")
- Show value ("Sync with Google Calendar automatically")
- Single, clear CTA ("Upgrade to Pro →")
- Soft colors (blue gradient, not red)
- Contextual (show on relevant pages)

**DON'T:**
- Guilt trip ("You're missing out!")
- Fake urgency ("Limited time!")
- Disable free features
- Block workflows
- Use modal overlays

### A/B Testing Ideas

**Version A: Feature-Focused**
```
🚀 Accept Payments with Stripe
Upgrade to Pro to process credit card payments securely.
[View Pricing →]
```

**Version B: Benefit-Focused**
```
💰 Start Earning from Your Bookings
Get paid instantly with Stripe integration in Pro.
[Upgrade Now →]
```

**Version C: Social Proof**
```
⭐ Join 500+ Pro Users
Unlock payments, calendar sync, and automated refunds.
[See Pro Features →]
```

---

## Testing Strategy

### Unit Tests (Phase 1)

**License Manager:**
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

### Integration Tests (Phase 2)

**Feature Gating:**
```php
test_stripe_files_not_loaded_when_free()
test_stripe_files_loaded_when_pro()
test_service_editor_hides_pro_fields_when_free()
test_service_editor_shows_pro_fields_when_pro()
test_booking_form_blocks_paid_services_when_free()
test_reschedule_redirects_to_license_page_when_free()
```

### Manual Testing Checklist

**Free Version:**
- [ ] Install on clean WordPress
- [ ] Create free service
- [ ] Submit booking (success)
- [ ] Receive confirmation email
- [ ] View booking in admin
- [ ] No PHP errors in debug.log
- [ ] No Pro files in build

**Pro Version:**
- [ ] Activate valid license
- [ ] Pro settings visible
- [ ] Create paid service
- [ ] Complete Stripe checkout
- [ ] Google Calendar event created
- [ ] Reschedule link works
- [ ] Cancel processes refund
- [ ] No PHP errors in debug.log

**License Scenarios:**
- [ ] Invalid key shows error
- [ ] Expired key enters grace period
- [ ] Grace period expiry disables Pro
- [ ] Deactivate removes Pro access
- [ ] Reactivate restores Pro access
- [ ] Multiple sites (reach limit)
- [ ] Site migration (deactivate/activate)

---

## Backwards Compatibility

### Existing Users Migration

**Scenario 1: Free User (Pre-v3.1)**
- No license system existed
- Upgrade to v3.1.0
- **Impact:** None (still free)
- **Action:** Optional license activation

**Scenario 2: Current User with Stripe Setup**
- Has Stripe keys configured
- Upgrade to v3.1.0
- **Impact:** Pro features stop working (no license)
- **Action:** 90-day grace period automatic
- **Communication:** Email about licensing + discount

**Grace Period Logic:**
```php
// Check if user has Pro features configured
$has_stripe = ! empty( simple_booking()->get_setting('stripe_secret_key') );
$has_google = ! empty( get_option('simple_booking_google_tokens') );

if ( $has_stripe || $has_google ) {
    // Grant 90-day grace period for existing users
    $license = array(
        'status'       => 'grace',
        'activated_at' => current_time('mysql'),
        'grace_until'  => date('Y-m-d', strtotime('+90 days')),
    );
    update_option( 'simple_booking_license', $license );
}
```

---

## Deployment Checklist

### Pre-Deployment
- [ ] All tests passing
- [ ] No debug code (var_dump, error_log)
- [ ] Version numbers updated
- [ ] CHANGELOG.md updated
- [ ] Documentation complete
- [ ] License server tested
- [ ] Backup plan ready

### Deployment
- [ ] Tag release in Git
- [ ] Build free version
- [ ] Test free build locally
- [ ] Upload to WordPress.org SVN
- [ ] Deploy Pro to update server
- [ ] Activate license server
- [ ] Monitor error logs
- [ ] Check API response times

### Post-Deployment
- [ ] Test fresh installs (free)
- [ ] Test license activation (pro)
- [ ] Monitor support requests
- [ ] Track metrics (conversions)
- [ ] Fix critical bugs within 24hrs
- [ ] Gather user feedback
- [ ] Plan v3.2.0 improvements

---

## Common Pitfalls to Avoid

### 1. Over-Aggressive Upselling
**Problem:** Too many upgrade prompts annoy users
**Solution:** Max 1 prompt per admin page, context-aware only

### 2. License Check Failures Breaking Site
**Problem:** API down = site broken
**Solution:** Always graceful fallback, use cached data

### 3. Grace Period Too Short
**Problem:** Users don't see email, license expires immediately
**Solution:** 30-day grace period + multiple reminder emails

### 4. Unclear Feature Boundaries
**Problem:** Users confused what's free vs pro
**Solution:** Clear badges, documented comparison table

### 5. Data Loss on Downgrade
**Problem:** Pro user downgrades, loses all booking data
**Solution:** Preserve all data, just disable Pro features

### 6. Update System Breaks
**Problem:** Pro users can't update
**Solution:** Thoroughly test, provide manual download fallback

### 7. WordPress.org Rejection
**Problem:** Free version rejected for policy violations
**Solution:** Review guidelines before submission, ask for pre-review

---

## Support Preparation

### Common Support Requests (Expected)

**License Issues:**
- "Can't activate license (activation limit reached)"
  - **Solution:** Deactivate old sites in customer portal
- "License expired but site still works"
  - **Solution:** Grace period active, expires in X days
- "Upgraded but Pro features not showing"
  - **Solution:** Clear cache, check license status

**Migration Issues:**
- "Moved site, license won't activate"
  - **Solution:** Deactivate old domain, activate new
- "Lost license key"
  - **Solution:** Check purchase email, customer portal

**Feature Confusion:**
- "Why can't I add Stripe prices (free user)"
  - **Solution:** Explain Pro required, provide upgrade link
- "Calendar sync stopped working"
  - **Solution:** Check license status, reauthorize Google

### Response Templates

**License Activation Limit:**
```
Hi {name},

Your license has reached its activation limit ({max} sites).

To activate on this new site:
1. Visit your account portal: {portal_url}
2. Deactivate any old/unused sites
3. Return to your WordPress admin
4. Re-enter your license key

Need more sites? Upgrade to Business or Agency plan:
{upgrade_url}

Best,
Support Team
```

---

## Metrics to Track

### Business Metrics
- Free installs (WordPress.org stats)
- Pro license sales (Stripe dashboard)
- Free → Pro conversion rate (Google Analytics)
- MRR (Monthly Recurring Revenue)
- Churn rate (canceled subscriptions)
- LTV (Lifetime Value per customer)
- CAC (Customer Acquisition Cost)

### Technical Metrics
- License activation success rate (target: >95%)
- API response time (target: <500ms)
- API uptime (target: 99.9%)
- Update success rate (target: >99%)
- PHP errors per 1000 requests (target: <5)
- Support ticket volume (target: <20/week)

### User Experience Metrics
- WordPress.org rating (target: 4.5+)
- Time to first booking (target: <5 min)
- Checkout completion rate (target: >75%)
- Average support resolution time (target: <24hrs)

---

## Future Enhancements (v4.0+)

**Architecture Refactor:**
- Separate free/pro codebases completely
- Shared core library
- Pro as add-on plugin

**Additional Tiers:**
- White-label tier (remove branding)
- Enterprise tier (dedicated support)

**New Pro Features:**
- SMS notifications (Twilio)
- Multi-currency support
- Advanced analytics dashboard
- Custom email builder
- Booking widget (iframe embed)

---

**Status:** Documentation Complete  
**Next Step:** Begin Phase 1 Implementation  
**File to Create:** `includes/license/class-license-manager.php` (template ready)
