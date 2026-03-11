# Testing Guide

Test plan and results log for Simple Booking.

---

## Test Cadence (Mandatory)

| When | Suite | Time |
|------|-------|------|
| After each code batch (1–3 files) | Smoke Suite | 5–10 min |
| Every 3–5 commits | Mini Regression | 15–25 min |
| At milestone completion | Full Milestone Regression | 45–90 min |

**Rule:** Do not continue to the next batch if Smoke Suite fails.

---

## Environment Prerequisites

Before any run:

1. Confirm plugin branch/version deployed on test site
2. Enable WordPress debug logging (`WP_DEBUG_LOG = true`)
3. Confirm Stripe test keys configured
4. Confirm Google provider connected (for Google path tests)
5. Keep one free test service and one paid test service
6. Keep one reusable test customer identity (email/phone)

**Test matrix baseline:**

- Provider: `google`
- Provider: `ics`
- Provider: `outlook`
- Flow: free booking
- Flow: paid booking
- Actions: create, cancel, reschedule

---

## Smoke Suite (run after every code batch)

Execute in order:

1. Open booking form
2. Create a **free booking** — expected: booking created, no fatal, no white screen
3. Create a **paid booking** — expected: redirects to Stripe Checkout
4. Complete Stripe test checkout — expected: webhook creates booking in dashboard
5. Cancel the paid booking from management link — expected: booking cancelled, no crash
6. Check `wp-content/debug.log` — expected: no new fatal errors

Pass criteria: all 6 steps succeed.

---

## Mini Regression (every 3–5 commits)

### A) Provider selection

1. Set provider to `google` → create booking → expected: calendar event created → cancel → expected: event deleted
2. Set provider to `ics` → create booking → expected: no OAuth requirement, succeeds → cancel → expected: no provider fatals
3. Set provider to `outlook` → create booking → expected: graceful non-fatal behavior

### B) Availability checks

1. With `google` provider, attempt booking an occupied slot → expected: slot protection works
2. With `ics`/`outlook` provider, book slot → expected: non-blocking, no hard error

### C) Critical business flows

1. Paid booking → cancel → refund → expected: refund path unchanged
2. Paid booking → reschedule → expected: no forced re-payment for already-paid booking

Pass criteria: no fatal errors, no critical flow regressions.

---

## Full Milestone Regression (end of phase)

### Matrix

| Provider | Flow | Create | Cancel | Reschedule |
|----------|------|--------|--------|------------|
| google | free | ☐ | ☐ | ☐ |
| google | paid | ☐ | ☐ | ☐ |
| ics | free | ☐ | ☐ | ☐ |
| ics | paid | ☐ | ☐ | ☐ |
| outlook | free | ☐ | ☐ | ☐ |
| outlook | paid | ☐ | ☐ | ☐ |

### Additional required checks

- [ ] Provider switching does not break existing bookings
- [ ] Booking list / admin pages load without warnings
- [ ] Webhook booking creation still works
- [ ] Email cancel/reschedule links still work
- [ ] No PHP syntax/runtime errors in changed files
- [ ] No new fatal entries in debug log

Pass criteria: all critical paths pass, no P0/P1 defects.

---

## Defect Severity

| Severity | Definition |
|----------|-----------|
| P0 | Fatal error / checkout blocked / data loss |
| P1 | Core business flow broken (paid/cancel/reschedule) |
| P2 | Provider-specific functional issue, non-blocking |
| P3 | Minor UX/content inconsistency |

**Release gate:** Any P0/P1 blocks release. P2 allowed with documented workaround. P3 can defer to next patch.

---

## Run Templates

### Smoke Suite Entry

```
- Date/Time:
- Commit SHA:
- Environment (staging/live test):
- Provider (google/ics/outlook):
- Steps run:
  - [ ] Open booking form
  - [ ] Create free booking
  - [ ] Create paid booking
  - [ ] Complete Stripe checkout
  - [ ] Cancel paid booking
  - [ ] Check debug log for fatals
- Result: Pass / Fail
- Fail details:
- Defects raised:
- Next action:
```

### Mini Regression Entry

```
- Date/Time:
- Commit SHA range:
- Environment:
- Providers tested: [ ] google  [ ] ics  [ ] outlook
- Flows tested:     [ ] free create/cancel/reschedule
                    [ ] paid create/cancel/reschedule
                    [ ] refund path sanity check
- Result: Pass / Fail
- Fail details:
- Defects raised:
- Next action:
```

### Full Milestone Regression Entry

```
- Date/Time:
- Milestone:
- Commit SHA:
- Environment:

Matrix results: (use table above)

Required checks:
- Provider switching keeps old bookings stable: Pass / Fail
- Booking admin pages load cleanly: Pass / Fail
- Webhook booking creation works: Pass / Fail
- Email cancel/reschedule links work: Pass / Fail
- No fatal/runtime blockers in logs: Pass / Fail

- Overall result: Pass / Fail
- Blockers (P0/P1):
- Defects raised (P2/P3):
- Release recommendation: Go / No-Go
- Follow-up actions:
```

---

## Results Log

Entries newest first.

---

### Mini Regression — 2026-03-09

**Commit:** `eba6874`
**Duration:** ~25 min
**Result:** ✅ PASSED

| Area | Result | Notes |
|------|--------|-------|
| ICS provider selection | ✅ | Selectable, saves correctly |
| Booking create (ICS) | ✅ | No Google event, no fatal |
| Meeting link conditional rendering | ✅ | Present when set, absent when removed |
| Reschedule flow | ✅ | |
| Cancel flow | ✅ | |
| Old email cancel link after reschedule | ✅ | Correctly cancels latest booking in chain |
| Pro-gating (Google/Outlook disabled for Free) | ✅ | Grayed out with "Pro" label |
| D002 retest (duplicate refund) | ✅ | Verified fixed |
| Webhook 429 during test | ℹ️ | Non-blocking observation |

---

### Smoke Suite — 2026-03-09

**Commit:** `8060db9`
**Provider:** Google Calendar
**Duration:** ~8 min
**Result:** ✅ PASSED

| Test | Status | Notes |
|------|--------|-------|
| Free booking create | ✅ | Booking created in post_type |
| Paid booking with Stripe | ✅ | Redirects to Stripe test checkout |
| Stripe test checkout completion | ✅ | Webhook creates booking, email sent |
| Cancel paid booking | ✅ | Cancellation + refund processed |
| Reschedule booking | ✅ | Date updated, notification sent |
| Debug log check | ✅ | No fatal errors |

---

## Defect Register

| ID | Date | Severity | Flow | Summary | Status |
|----|------|----------|------|---------|--------|
| D001 | 2026-03-09 | P2 | Cancel/Reschedule | Cancel/reschedule links were reusable (no consumed-token check) | ✅ Fixed |
| D002 | 2026-03-09 | P1 | Reusable Management Links | Cancel after cancel → Stripe 400 duplicate refund; reschedule after cancel not blocked. Root cause: no booking status validation before processing actions. Fix: added status checks. | ✅ Verified Fixed |

---

## Change Log

- 2026-03-09 17:45 — Phase 6.7 action-level idempotency verified. Cancelled booking + manual token deletion → retry cancel → still blocked by execution marker (commit 20e5f77).
- 2026-03-09 17:35 — UX copy unified: stale/used/already_cancelled all show "This booking has already been cancelled or rescheduled and cannot be modified." (commit 325cce4).
- 2026-03-09 17:15 — D002 retest confirmed fix. No Stripe 400 on repeated cancel.
- 2026-03-09 14:58 — Post-fix retest: parse fix + webhook retry (commit a1cb4e3). Retries on 429 correct; cancel/refund idempotent.
