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

### Fork Plan (from stable `v3.0.16`)
- [x] F0. Baseline branch created from stable tag (`refocus/stable-v3.0.16`)
- [ ] F1. Branch A: `feat/license-core` (license manager only, no calendar/provider load changes)
- [ ] F2. Branch B: `feat/license-ui` (admin panel + activate/deactivate UX)
- [ ] F3. Branch C: `feat/provider-compat` (provider loading compatibility layer, OAuth callback safety)
- [ ] F4. Branch D: `feat/free-build` (script to produce Free ZIP and Pro ZIP deterministically)
- [ ] F5. Run mandatory matrix tests (Google/Outlook/ICS + create/reschedule/cancel + staff calendar load)
- [ ] F6. Merge order: A -> B -> C -> D only if previous branch passes matrix
- [ ] F7. Release control: CHANGELOG + tag `v3.1.0` + push

### Guardrails (must not regress)
- [ ] G1. OAuth callback routes always available (`/wp-json/simple-booking/v1/google/oauth`, `/wp-json/simple-booking/v1/outlook/oauth`)
- [ ] G2. Selecting Google/Outlook in settings must never produce provider hard-failure in slot AJAX
- [ ] G3. Staff menu must remain visible when Pro is active
- [ ] G4. Staff "Load Calendars" must return user-friendly errors, never HTTP 500
- [ ] G5. `SIMPLE_BOOKING_FORCE_PRO` is test-only and never required in production

### Progress Snapshot (in current code)
- [x] Added module registry + availability checks (`Simple_Booking_Module_Manager`)
- [x] Calendar provider select now disables unavailable options and shows reasons
- [x] Added admin Modules Status panel (installed / requires Pro / available / reason)
- [x] Staff menu remains visible; non-Pro mode blocks Staff CRUD with Pro-only notice

---

## Backlog (Unscheduled -- Do Not Touch Mid-Phase)

- WP-CLI command to manually trigger due webhook retries
- Retry outcome persistence (store success/failure count per booking)
- Recurring bookings
- Package / membership bookings
- SMS / WhatsApp notifications