# Phase 6 Test Plan (Calendar Providers)

Purpose: keep the calendar-provider migration safe by testing after every meaningful change, not only at the milestone end.

---

## 1) Testing cadence (mandatory)

Use this cadence for all Phase 6 work:

- After each code batch (1-3 files): run **Smoke Suite** (5-10 min)
- After every 3-5 commits: run **Mini Regression** (15-25 min)
- At Phase 6 completion: run **Full Milestone Regression** (45-90 min)

Rule: do not continue to next batch if Smoke Suite fails.

---

## 2) Test environment prerequisites

Before any run:

1. Confirm plugin branch/version deployed on test site
2. Enable WordPress debug logging
3. Confirm Stripe test keys configured (for paid-flow checks)
4. Confirm Google provider is connected (for Google path checks)
5. Keep one test service free and one test service paid
6. Keep one reusable test customer identity (email/phone)

Suggested test matrix baseline:

- Provider: google
- Provider: ics
- Provider: outlook (stub behavior for now)
- Flow: free booking
- Flow: paid booking
- Actions: create, cancel, reschedule

---

## 3) Smoke Suite (run after every code batch)

Execute in this order:

1. Open booking form
2. Create a **free booking**
   - Expected: booking created, no fatal, no white screen
3. Create a **paid booking**
   - Expected: redirects to Stripe Checkout successfully
4. Complete Stripe test checkout
   - Expected: webhook creates booking in dashboard
5. Cancel the paid booking from management link
   - Expected: booking cancelled, no crash
6. Check logs
   - Expected: no new fatal errors in wp-content/debug.log

Pass criteria: all 6 steps succeed.

---

## 4) Mini Regression (every 3-5 commits)

### A) Provider selection checks

1. Set provider to **google**
2. Create booking
   - Expected: external calendar event created
3. Cancel booking
   - Expected: external event deleted

4. Set provider to **ics**
5. Create booking
   - Expected: booking succeeds without OAuth requirements
6. Cancel booking
   - Expected: booking cancel succeeds and no provider fatal errors

7. Set provider to **outlook** (stub phase)
8. Create booking
   - Expected: booking still succeeds (graceful provider behavior)

### B) Availability behavior checks

1. With google provider, book occupied slot attempt
   - Expected: slot protection still works as before
2. With ics/outlook provider, book slot
   - Expected: flow is non-blocking and no hard error

### C) Critical business flow checks

1. Paid booking -> cancel -> refund path
   - Expected: no regression in refund behavior
2. Paid booking -> reschedule path
   - Expected: no forced re-payment for already-paid reschedule

Pass criteria: no fatal errors and no critical flow regressions.

---

## 5) Full Milestone Regression (end of Phase 6)

Run complete matrix and mark each as Pass/Fail:

### Matrix

- Provider google + free create/cancel/reschedule
- Provider google + paid create/cancel/reschedule
- Provider ics + free create/cancel/reschedule
- Provider ics + paid create/cancel/reschedule
- Provider outlook + free create/cancel/reschedule
- Provider outlook + paid create/cancel/reschedule

### Additional required checks

1. Provider switching does not break old bookings
2. Booking list/admin pages load without warnings
3. Webhook booking creation still works
4. Email links still work (cancel/reschedule)
5. No PHP syntax/runtime errors in changed files
6. No new fatal entries in debug log

Pass criteria: all critical paths pass and no blocker defects remain.

---

## 6) Defect severity rules

Classify failures immediately:

- P0: Fatal error / checkout blocked / data loss
- P1: Paid flow broken / cancel-reschedule broken
- P2: Provider-specific non-critical issue
- P3: UX copy or minor inconsistency

Release gate:

- Any P0/P1 = block release
- P2 allowed only with documented workaround
- P3 can be deferred to next patch

---

## 7) Test execution template (copy each run)

Use this template in your notes or issue tracker:

- Date/Time:
- Commit SHA:
- Provider under test:
- Flow under test (free/paid):
- Steps executed:
- Result: Pass/Fail
- Logs checked: Yes/No
- Defects found (ID + severity):
- Next action:

---

## 8) Immediate test steps for current codebase

Run these now on your staging/live test environment:

1. Provider=google: free booking create -> confirm calendar event exists
2. Provider=google: paid booking create -> Stripe complete -> booking appears
3. Provider=google: cancel paid booking -> confirm cancel + refund behavior unchanged
4. Provider=ics: create free booking -> confirm flow does not block
5. Provider=outlook: create free booking -> confirm graceful non-fatal behavior
6. Check wp-content/debug.log for new fatal errors

If all pass, continue next Phase 6 implementation batch.
