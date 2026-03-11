# Free/Pro Split - Implementation Checklist

Quick reference for tracking implementation progress.

Testing runbook: [PHASE6-TEST-PLAN.md](docs/PHASE6-TEST-PLAN.md)
Test execution log: [PHASE6-TEST-RESULTS-LOG.md](docs/PHASE6-TEST-RESULTS-LOG.md)

---

## Phase 1: License Foundation (v3.1.0) - Days 1-5

### Day 1-2: License Manager
- [ ] Create `includes/license/class-license-manager.php`
- [ ] Implement `get_license_key()`
- [ ] Implement `set_license_key()`
- [ ] Implement `activate_license()` with API call
- [ ] Implement `deactivate_license()` with API call
- [ ] Implement `check_license_status()` with caching
- [ ] Implement `is_pro_active()` with grace period logic
- [ ] Implement `is_feature_available($feature)`
- [ ] Implement `get_grace_period_remaining()`
- [ ] Add database option handlers
- [ ] Test all methods in isolation

### Day 3: Main Plugin Integration
- [ ] Modify `simple-booking.php` - add license manager property
- [ ] Modify `load_dependencies()` - add conditional loading
- [ ] Modify `init()` - conditionally register Staff CPT
- [ ] Add `is_pro_active()` helper
- [ ] Add `get_license_manager()` accessor
- [ ] Test free mode (no Pro files loaded)
- [ ] Test pro mode (all files loaded)
- [ ] Verify no fatal errors

### Day 4: Admin License Settings
- [ ] Add License section to `class-admin-settings.php`
- [ ] Add license key field (password type)
- [ ] Add activate button with AJAX handler
- [ ] Add deactivate button with AJAX handler
- [ ] Add status display area
- [ ] Add plan type display
- [ ] Add expiry date display
- [ ] Add grace period countdown
- [ ] Add "Get Pro" link
- [ ] Style license section
- [ ] Test activation/deactivation flow

### Day 5: License API Server
- [ ] Choose platform (Lemon Squeezy vs Custom)
- [ ] If custom: Set up database schema
- [ ] If custom: Create licenses table
- [ ] If custom: Create activations table
- [ ] Implement POST /api/v1/licenses/activate
- [ ] Implement POST /api/v1/licenses/deactivate
- [ ] Implement GET /api/v1/licenses/check
- [ ] Add rate limiting (10 requests/minute per IP)
- [ ] Add domain validation
- [ ] Add activation limit enforcement
- [ ] Test with 20+ activations
- [ ] Deploy to production server

### Testing Phase 1
- [ ] Fresh WordPress install - free mode works
- [ ] Activate valid license - Pro features appear
- [ ] Deactivate license - Pro features disappear
- [ ] Invalid license - error message shown
- [ ] Expired license - grace period starts
- [ ] Grace period expires - Pro disabled
- [ ] Cache works (no API call for 24hrs)
- [ ] Multiple sites with same license (up to limit)
- [ ] Site migration works (deactivate/reactivate)

---

## Phase 2: Admin UI & Gates (v3.2.0) - Days 6-11

### Day 6: Settings Pro Badges
- [ ] Add CSS for `.simple-booking-pro-badge`
- [ ] Add CSS for `.simple-booking-upgrade-prompt`
- [ ] Add badge to Stripe Settings section
- [ ] Add badge to Google Calendar Settings section
- [ ] Add badge to Refund Settings section
- [ ] Add badge to Webhook Settings section
- [ ] Add badge to Schedule buffer fields
- [ ] Disable Pro fields if free
- [ ] Add upgrade prompts above disabled fields
- [ ] Link prompts to pricing page
- [ ] Test visual appearance
- [ ] Test field disabling

### Day 7: Service Editor Restrictions
- [ ] Add PRO badge to Stripe Price ID field
- [ ] Disable Stripe Price ID if free
- [ ] Add tooltip to Stripe Price ID
- [ ] Hide Staff Assignment section if free
- [ ] Show upgrade card for Staff Assignment
- [ ] Add PRO badge to Custom Schedule option
- [ ] Disable Custom Schedule if free
- [ ] Add PRO badge to Google Calendar toggles
- [ ] Disable Google toggles if free
- [ ] Test service editor (free vs pro)
- [ ] Verify save meta works correctly

### Day 8: Booking List Restrictions
- [ ] Hide Refund column if free
- [ ] Add "Upgrade to Pro" in action links
- [ ] Test booking list view (free)
- [ ] Test booking list view (pro)
- [ ] Verify export works

### Day 9: Frontend Validation
- [ ] Add service validation in AJAX submission
- [ ] Block paid services in free version
- [ ] Add reschedule action gating
- [ ] Redirect reschedule to license page if free
- [ ] Add cancel action gating
- [ ] Redirect cancel to license page if free
- [ ] Silently ignore staff assignments if free
- [ ] Test booking submission (free)
- [ ] Test booking submission (pro)
- [ ] Test reschedule links (free = blocked)
- [ ] Test cancel links (free = blocked)

### Day 10: Email Modifications
- [ ] Add conditional reschedule link rendering
- [ ] Add conditional cancel link rendering
- [ ] Add upgrade message for free version
- [ ] Modify email footer (free vs pro)
- [ ] Test free booking email
- [ ] Test pro booking email
- [ ] Verify links work/don't work correctly

### Day 11: Admin Notices
- [ ] Add welcome notice (free users)
- [ ] Make welcome notice dismissible
- [ ] Add grace period warning notice
- [ ] Make grace warning NOT dismissible
- [ ] Add grace expired notice
- [ ] Make expired notice NOT dismissible
- [ ] Add activation success notice
- [ ] Auto-dismiss success after 5 seconds
- [ ] Test notice display logic
- [ ] Test dismissal works
- [ ] Verify notices reappear correctly

### Testing Phase 2
- [ ] Free user sees all Pro badges
- [ ] Pro settings disabled for free users
- [ ] Upgrade prompts visible and linked
- [ ] Service editor restricts Pro fields
- [ ] Frontend blocks paid services (free)
- [ ] Reschedule URLs redirect (free)
- [ ] Cancel URLs redirect (free)
- [ ] Emails render correctly (both versions)
- [ ] Admin notices show at right times
- [ ] Notice dismissal persists

---

## Phase 3: Free Distribution (v3.3.0) - Days 12-14

### Day 12: Build Script & Free Package
- [ ] Create `build-free.sh`
- [ ] Add Pro file removal logic
- [ ] Add main file modification logic
- [ ] Test script on copy of repo
- [ ] Verify free package has no Pro code
- [ ] Test fresh install of free package
- [ ] Verify all free features work
- [ ] Check for PHP errors/warnings

### Day 13: WordPress.org Assets
- [ ] Create `readme.txt` (WordPress.org format)
- [ ] Write plugin description
- [ ] Write installation instructions
- [ ] Write FAQ section
- [ ] Add "Upgrade to Pro" section
- [ ] Design banner image (1544x500)
- [ ] Design icon image (256x256)
- [ ] Create screenshot 1 (booking form)
- [ ] Create screenshot 2 (service editor)
- [ ] Create screenshot 3 (admin dashboard)
- [ ] Validate readme.txt (WordPress.org validator)
- [ ] Verify assets meet requirements

### Day 14: Documentation & Pro Landing Page
- [ ] Write `docs/installation.md`
- [ ] Write `docs/migration.md`
- [ ] Write `docs/features.md`
- [ ] Write `docs/troubleshooting.md`
- [ ] Design Pro upgrade page
- [ ] Add feature comparison table
- [ ] Add testimonials section
- [ ] Add pricing cards
- [ ] Add FAQ
- [ ] Add purchase CTAs
- [ ] Test all documentation links

### Testing Phase 3
- [ ] Free build installs cleanly
- [ ] Zero Pro code in free build
- [ ] All free features work
- [ ] Upgrade prompts visible
- [ ] Documentation complete
- [ ] Assets pass WordPress validator
- [ ] Screenshots accurate
- [ ] Pro landing page loads fast

---

## Phase 4: Pro Launch (v3.4.0) - Days 15-19

### Day 15-16: License Server Setup
**If using Lemon Squeezy:**
- [ ] Create Lemon Squeezy account
- [ ] Create products (Personal, Business, Agency)
- [ ] Set up webhook endpoint
- [ ] Configure license key generation
- [ ] Test purchase flow
- [ ] Test license API
- [ ] Test webhook delivery

**If using Custom Server:**
- [ ] Set up PHP server
- [ ] Deploy database schema
- [ ] Implement activate endpoint
- [ ] Implement deactivate endpoint
- [ ] Implement check endpoint
- [ ] Add authentication
- [ ] Add rate limiting
- [ ] Deploy to production
- [ ] Test all endpoints
- [ ] Load test (100+ requests)

### Day 17: Automated Updates
- [ ] Create `includes/license/class-updater.php`
- [ ] Integrate WordPress Update Checker
- [ ] Set up update server endpoint
- [ ] Test update check (daily cron)
- [ ] Test update download (with license)
- [ ] Test installation
- [ ] Verify background updates work

### Day 18: Customer Portal
- [ ] Set up member area plugin/custom
- [ ] Create dashboard page
- [ ] Create licenses page
- [ ] Create downloads page
- [ ] Create billing page
- [ ] Create support page
- [ ] Test login/registration
- [ ] Test license management
- [ ] Test plugin downloads
- [ ] Verify invoices display

### Day 19: Payment & Launch
- [ ] Create Stripe products
- [ ] Set up checkout flow
- [ ] Configure webhooks
- [ ] Test purchase (Pro Personal)
- [ ] Test purchase (Pro Business)
- [ ] Test purchase (Pro Agency)
- [ ] Verify license email sent
- [ ] Verify customer account created
- [ ] Test renewal flow
- [ ] Submit free version to WordPress.org
- [ ] Activate Pro purchase page
- [ ] Send launch announcement
- [ ] Monitor for issues

### Testing Phase 4
- [ ] Complete purchase flow works
- [ ] License generated correctly
- [ ] License activates on site
- [ ] Pro features unlock
- [ ] Updates check works
- [ ] Updates install works
- [ ] Customer portal functions
- [ ] Payment processing stable
- [ ] Email notifications send
- [ ] Zero critical bugs

---

## 4️⃣ Improve UX (Wizard, Onboarding, Setup Guides) - Phase 5 (v3.5.0) - Days 20-24

### Day 20: Setup Wizard Foundation
- [ ] Create `includes/admin/class-onboarding-wizard.php`
- [ ] Create `assets/js/admin-onboarding.js`
- [ ] Create `assets/css/admin-onboarding.css`
- [ ] Add wizard route/menu entry under plugin admin
- [ ] Add wizard state storage (option or user meta)
- [ ] Add step progress UI with resume support

### Day 21: Wizard Steps & Validation
- [ ] Build Welcome + plan selection step
- [ ] Build business setup step (timezone, schedule defaults)
- [ ] Build service setup step (create first service)
- [ ] Build Pro payment step (Stripe key validation)
- [ ] Build Pro calendar step (Google connect + test)
- [ ] Build go-live step (test booking + preview)
- [ ] Add per-step validation and clear error messages

### Day 22: Onboarding Checklist Panel
- [ ] Create `includes/admin/class-onboarding-checklist.php`
- [ ] Add checklist panel to settings/dashboard
- [ ] Auto-detect completion from real config data
- [ ] Add manual refresh/resync action
- [ ] Add dismiss/complete UX for checklist

### Day 23: Setup Guides & Contextual Help
- [ ] Add setup help blocks in settings sections
- [ ] Add quick links to Stripe and Google troubleshooting
- [ ] Add short guide for Free users (first booking in 5 minutes)
- [ ] Add short guide for Pro users (Stripe + Google in 15 minutes)
- [ ] Ensure gated sections explain unlock + next steps clearly

### Day 24: UX Polish & Validation
- [ ] Improve empty-state copy in admin and service editor
- [ ] Improve CTA labels for action clarity
- [ ] Test free onboarding end-to-end
- [ ] Test pro onboarding end-to-end
- [ ] Verify wizard resume after refresh/logout
- [ ] Verify no regressions in booking/payment/calendar flows

### Testing Phase 5
- [ ] New free user publishes first booking flow in one session
- [ ] New pro user completes setup in one session
- [ ] Setup checklist reflects real completion state
- [ ] Contextual guides reduce setup confusion
- [ ] UX flow works on desktop and mobile admin

---

## 6️⃣ Calendar Provider Architecture (Google + Outlook + ICS Fallback) - Phase 6 (v3.6.0) - Days 25-30

### Day 25: Provider Core Abstraction
- [ ] Create `includes/calendar/interface-calendar-provider.php`
- [ ] Create `includes/calendar/class-calendar-provider-manager.php`
- [ ] Define provider contract (`create/update/delete/fetch_busy/is_connected`)
- [ ] Add provider selection resolution logic
- [ ] Add provider-level error normalization

### Day 26: Google Provider Adapter
- [ ] Create `includes/calendar/providers/class-google-provider.php`
- [ ] Move Google event logic behind provider interface
- [ ] Preserve token refresh/retry logic
- [ ] Preserve availability + slot conflict behavior
- [ ] Add regression checks vs current Google flow

### Day 27: Outlook Provider Adapter
- [ ] Create `includes/calendar/providers/class-outlook-provider.php`
- [ ] Implement Microsoft OAuth flow wiring
- [ ] Implement create/update/delete event actions
- [ ] Implement busy-window fetch for availability
- [ ] Add Outlook settings validation in admin

### Day 28: ICS Feed Provider
- [ ] Create `includes/calendar/providers/class-ics-provider.php`
- [ ] Create `includes/calendar/class-ics-feed-controller.php`
- [ ] Generate dynamic ICS URL(s)
- [ ] Include booking changes (create/update/cancel) in feed output
- [ ] Add secure token/nonce for feed access

### Day 29: Provider Selection UI + Gating
- [ ] Add provider selector to settings (Google / Outlook / ICS)
- [ ] Pro-gate Google and Outlook options
- [ ] Keep ICS available in Free tier
- [ ] Add helper copy about ICS refresh delay behavior
- [ ] Add migration helper when switching active provider

### Day 30: Integration & Validation
- [ ] Test full booking lifecycle with Google provider
- [ ] Test full booking lifecycle with Outlook provider
- [ ] Test full booking lifecycle with ICS provider
- [ ] Verify fallback behavior when provider API fails
- [ ] Verify paid and free booking flows remain stable

### Testing Phase 6
- [ ] Google, Outlook, and ICS all pass create/update/cancel tests
- [ ] Provider switching does not break historical bookings
- [ ] ICS subscriptions update as expected after booking changes
- [ ] No fatal errors if provider credentials are missing
- [ ] Calendar integration remains optional and non-blocking

---

## Post-Launch Monitoring (First Week)

### Daily Checks
- [ ] Check error logs
- [ ] Review support tickets
- [ ] Monitor license activation rate
- [ ] Track conversion rate
- [ ] Check payment processing
- [ ] Review user feedback
- [ ] Monitor WordPress.org reviews
- [ ] Check update system status

### Weekly Reviews
- [ ] Analyze metrics vs targets
- [ ] Prioritize bug fixes
- [ ] Respond to all support tickets
- [ ] Update documentation
- [ ] Adjust upgrade prompts if needed
- [ ] A/B test pricing page
- [ ] Plan next features

---

## Success Criteria

### Must Have (Launch Blockers)
- ✅ License activation works 100%
- ✅ Free version has zero Pro code
- ✅ Pro features unlock reliably
- ✅ Payment processing works
- ✅ Updates system functional
- ✅ No critical bugs

### Should Have (Quality)
- ✅ Documentation complete
- ✅ Support system ready
- ✅ Conversion rate 5%+
- ✅ WordPress.org approved
- ✅ Pro landing page live
- ✅ Email templates designed

### Nice to Have (Polish)
- ✅ Video tutorials
- ✅ Affiliate program
- ✅ Onboarding wizard
- ✅ Live chat support
- ✅ Testimonials
- ✅ Case studies

---

**Status:** Ready to Begin  
**Next Task:** Phase 1, Day 1 - Create License Manager Class  
**File:** `includes/license/class-license-manager.php`
