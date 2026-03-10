# Simple Booking - Working Roadmap

A lightweight modular WordPress booking engine with Stripe payments, Google Calendar, and Microsoft Outlook integration.

---

## Current Version

**v3.0.15 (RELEASED) -> v3.0 (STABILIZING)**

Core booking flow operational. Stripe payments, Google Calendar, Microsoft Outlook, multi-staff routing, webhook retry queue, and admin diagnostics all shipped.

---

## Working Discipline (Three Lanes)

| Lane | What goes here | Rule |
|---|---|---|
| **Hotfix** | Broken things blocking testing | Fix -> CHANGELOG `Fixed` -> patch version bump -> release control |
| **Roadmap** | Planned phase work (see below) | Only start after previous hotfix is fully closed |
| **Backlog** | Ideas mid-session, nice-to-haves | Note in Backlog section -- do not touch until current roadmap phase closes |

**The gate:** A hotfix must be committed, versioned, and release-control-complete before returning to roadmap work. Never mix hotfix and feature in the same commit.

---

## Release Control (Mandatory Before New Feature Work)

Before starting any new milestone:

1. **Version Sync** -- `simple-booking.php` header + `SIMPLE_BOOKING_VERSION` constant match; `README.md` current release matches shipped tag
2. **Changelog Sync** -- Add entry in `CHANGELOG.md` (Added / Changed / Fixed)
3. **Roadmap Sync** -- Update Current Version line; mark completed stage; set next immediate stage
4. **Git Release Sync** -- Commit on `main` -> merge to `master` -> push `master` -> create + push release tag -> return to `main`

---

## Roadmap Exit & Next-Roadmap Planning

When a roadmap phase closes:

1. Add a one-line "What shipped / What deferred" note under the phase
2. Draft next phase with 3-6 micro stages, one acceptance criterion each, ordered by dependency
3. Do not start next phase until Release Control checklist above is fully complete

---

## Next Immediate Stage -- v3.0.16 (Stabilization Close-out)

Goal: clean gate before Free/Pro roadmap begins.

- [ ] Add compact admin notice under Webhook Queue panel explaining WP-Cron dependency (sites without real cron should use WP-CLI or server cron)
- [ ] Final smoke pass: Google booking -> calendar event -> email -> reschedule -> cancel
- [ ] Final smoke pass: Outlook booking -> calendar event -> email -> reschedule -> cancel
- [ ] Confirm webhook background retry fires correctly on failure
- [ ] Release control: bump to v3.0.16, CHANGELOG, tag, push

---

## Active Roadmap -- v3.1.0: License Foundation (Free/Pro Split)

See [ROADMAP-FREE-PRO.md](ROADMAP-FREE-PRO.md) for full phase breakdown.

### Phase 1 -- License Foundation
- [ ] 1.1 License key schema and activation endpoint
- [ ] 1.2 Feature gate helper (`simple_booking_is_pro()`)
- [ ] 1.3 Pro feature classes wrapped behind gate
- [ ] 1.4 Free-only build script (strips Pro files)
- [ ] 1.5 Admin license settings panel (activate / deactivate / status)

Start Phase 1 only after v3.0.16 is tagged.

---

## Backlog (Unscheduled -- Do Not Touch Mid-Phase)

- WP-CLI command to manually trigger due webhook retries
- Retry outcome persistence (store success/failure count per booking)
- Recurring bookings
- Package / membership bookings
- SMS / WhatsApp notifications