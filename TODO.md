# Simple Booking — Active Tasks

Current version: **v3.0.17 (RELEASED)**

Workflow rules and release control: see [`CONTRIBUTING.md`](CONTRIBUTING.md).
Full phase breakdown: see [`docs/ROADMAP.md`](docs/ROADMAP.md).

---

## ~~v3.0.16 (Stabilization Close-out)~~ ✅ RELEASED 2026-03-11

Shipped: WP-Cron notice, Outlook stale calendar ID fallback, webhook meeting_link fix. All smoke passes green.

## ~~v3.0.17 (Module-Aware Gating + Staff Lock)~~ ✅ RELEASED 2026-03-11

Shipped: Module manager registry, module-aware settings gating, service editor availability messaging, and non-Pro Staff CRUD lock while keeping Staff menu visible.

---

## Active Roadmap -- v3.1.0: License Foundation (Free/Pro Split)

See [`docs/ROADMAP.md`](docs/ROADMAP.md) for full phase breakdown.

### Phase 1 -- License Foundation
- [x] 1.1 License manager — full implementation (activate, deactivate, status check, cache, grace period)
- [x] 1.2 Feature gate helper (`is_pro_active()`, `is_feature_available()`)
- [x] 1.3 Pro file conditional loading in main plugin
- [x] 1.4 Singleton fix for main plugin class
- [x] 1.5 Admin license settings panel (activate / deactivate / status / AJAX)
- [ ] 1.6 License API server — set up endpoint (Lemon Squeezy or custom)
- [ ] 1.7 Smoke test: Free mode loads correctly, no Pro files included
- [ ] 1.8 Smoke test: SIMPLE_BOOKING_FORCE_PRO=true loads all Pro files
- [ ] 1.9 Release control: v3.1.0, CHANGELOG, tag, push

Next: choose license server platform and point `SIMPLE_BOOKING_LICENSE_API_URL` at it.

---

## Backlog (Unscheduled -- Do Not Touch Mid-Phase)

- WP-CLI command to manually trigger due webhook retries
- Retry outcome persistence (store success/failure count per booking)
- Recurring bookings
- Package / membership bookings
- SMS / WhatsApp notifications