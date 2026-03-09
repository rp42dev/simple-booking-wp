# Phase 6 Test Results Log

Use this file to record every Smoke Suite, Mini Regression, and Full Milestone Regression run.

---

## How to use

1. Copy one of the templates below.
2. Fill in all fields right after running tests.
3. If any test fails, add a defect line item with severity.
4. Keep entries newest first.

Severity scale:
- P0 = Fatal error / checkout blocked / data loss
- P1 = Core business flow broken (paid/cancel/reschedule)
- P2 = Provider-specific functional issue (non-blocking)
- P3 = Minor UX/content inconsistency

Release gate:
- Any P0/P1 blocks release.

---

## Current status snapshot

- Latest commit tested:
- Latest provider tested:
- Smoke Suite status: Not run
- Mini Regression status: Not run
- Full Regression status: Not run
- Open blockers (P0/P1): 0

---

## Smoke Suite Entry (5-10 min)

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

---

## Mini Regression Entry (15-25 min)

- Date/Time:
- Commit SHA range:
- Environment:
- Providers tested:
  - [ ] google
  - [ ] ics
  - [ ] outlook
- Flows tested:
  - [ ] free create/cancel/reschedule
  - [ ] paid create/cancel/reschedule
  - [ ] refund path sanity check
- Result: Pass / Fail
- Fail details:
- Defects raised:
- Next action:

---

## Full Milestone Regression Entry (45-90 min)

- Date/Time:
- Milestone:
- Commit SHA:
- Environment:

### Matrix
- google + free create/cancel/reschedule: Pass / Fail
- google + paid create/cancel/reschedule: Pass / Fail
- ics + free create/cancel/reschedule: Pass / Fail
- ics + paid create/cancel/reschedule: Pass / Fail
- outlook + free create/cancel/reschedule: Pass / Fail
- outlook + paid create/cancel/reschedule: Pass / Fail

### Required checks
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

---

## Defect register (Phase 6)

| ID | Date | Severity | Provider | Flow | Summary | Status | Owner |
|----|------|----------|----------|------|---------|--------|-------|
|    |      |          |          |      |         |        |       |

---

## Change log

- 2026-03-09: Created initial Phase 6 test results logging template.
