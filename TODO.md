# Simple Booking — Active Tasks

Current version: **v3.2.0 (RELEASED)**

Workflow rules and release control: see [`CONTRIBUTING.md`](CONTRIBUTING.md).
Full phase breakdown: see [`docs/ROADMAP.md`](docs/ROADMAP.md).

---

## ~~v3.0.16 (Stabilization Close-out)~~ ✅ RELEASED 2026-03-11

Shipped: WP-Cron notice, Outlook stale calendar ID fallback, webhook meeting_link fix. All smoke passes green.

## ~~v3.0.17 (Module-Aware Gating + Staff Lock)~~ ✅ RELEASED 2026-03-11

Shipped: Module manager registry, module-aware settings gating, service editor availability messaging, and non-Pro Staff CRUD lock while keeping Staff menu visible.

---

## Active Roadmap -- v3.2.0: Admin UI & Feature Gates

See [`docs/ROADMAP.md`](docs/ROADMAP.md) for full phase breakdown.

### Phase 2 -- Admin UI & Gates
- [x] 2.1 Pro Badges on Settings — visual indicators on Pro-only sections
- [x] 2.2 Service Editor Restrictions — field-level gating (Stripe, Google, Staff, Schedule Mode)
- [x] 2.3 Frontend Validation — prevent Pro features in free mode
- [x] 2.4 Email Template Modifications — conditional reschedule/cancel links
- [x] 2.5 Admin Notices System — license status alerts + upgrade CTAs
- [ ] 2.6 Release control: v3.2.0, CHANGELOG, tag, push

Next: Phase 3 - Free vs Pro packaging scripts and distribution builds.

---

## Backlog (Unscheduled -- Do Not Touch Mid-Phase)

- WP-CLI command to manually trigger due webhook retries
- Retry outcome persistence (store success/failure count per booking)
- Recurring bookings
- Package / membership bookings
- SMS / WhatsApp notifications