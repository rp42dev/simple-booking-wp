# Simple Booking — Active Tasks

Current version: **v3.0.16 (RELEASED)**

Workflow rules and release control: see [`CONTRIBUTING.md`](CONTRIBUTING.md).
Full phase breakdown: see [`docs/ROADMAP.md`](docs/ROADMAP.md).

---

## ~~v3.0.16 (Stabilization Close-out)~~ ✅ RELEASED 2026-03-11

Shipped: WP-Cron notice, Outlook stale calendar ID fallback, webhook meeting_link fix. All smoke passes green.

---

## Active Roadmap -- v3.1.0: License Foundation (Free/Pro Split)

See [`docs/ROADMAP.md`](docs/ROADMAP.md) for full phase breakdown.

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