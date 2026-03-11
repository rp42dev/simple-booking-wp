)# Contributing & Dev Rules

This is the central reference for working on Simple Booking. Read this before starting any task.

---

## Documentation Map

| File | Purpose |
|------|---------|
| [`README.md`](README.md) | Public overview, features, install instructions |
| [`CHANGELOG.md`](CHANGELOG.md) | Version history (Keep a Changelog format) |
| [`ARCHITECTURE.md`](ARCHITECTURE.md) | System design, folder structure, booking flow |
| [`TODO.md`](TODO.md) | Active roadmap stage + backlog |
| [`docs/ROADMAP.md`](docs/ROADMAP.md) | Full Free/Pro roadmap with phase deliverables |
| [`docs/FEATURES.md`](docs/FEATURES.md) | Free vs Pro feature comparison (marketing) |
| [`docs/CHECKLIST.md`](docs/CHECKLIST.md) | Implementation checklist per phase |
| [`docs/DEVELOPMENT.md`](docs/DEVELOPMENT.md) | Dev setup, architecture decisions, API specs |
| [`docs/TESTING.md`](docs/TESTING.md) | Test plans, cadence rules, results log |

---

## Working Discipline (Three Lanes)

| Lane | What goes here | Rule |
|------|----------------|------|
| **Hotfix** | Broken things blocking testing | Fix → CHANGELOG `Fixed` → patch version bump → release control |
| **Roadmap** | Planned phase work | Only start after previous hotfix is fully closed |
| **Backlog** | Ideas mid-session, nice-to-haves | Note in `TODO.md` Backlog section — do not touch until current phase closes |

**The gate:** A hotfix must be committed, versioned, and release-control-complete before returning to roadmap work. Never mix hotfix and feature in the same commit.

---

## Release Control (Mandatory Before New Feature Work)

Before starting any new milestone, complete all four steps:

1. **Version Sync** — `simple-booking.php` header + `SIMPLE_BOOKING_VERSION` constant match; `README.md` current release matches shipped tag
2. **Changelog Sync** — Add entry in `CHANGELOG.md` (Added / Changed / Fixed)
3. **Roadmap Sync** — Update Current Version line in `TODO.md`; mark completed stage; set next immediate stage
4. **Git Release Sync** — Commit on `main` → merge to `master` → push `master` → create + push release tag → return to `main`

---

## Roadmap Exit Rules

When a roadmap phase closes:

1. Add a one-line "What shipped / What deferred" note under the phase in `docs/ROADMAP.md`
2. Draft next phase with 3–6 micro stages, one acceptance criterion each, ordered by dependency
3. Do not start next phase until Release Control above is fully complete

---

## Branch Strategy

```
master - Production releases (tagged)
└── feature/free-pro-split  - Active development branch
    ├── feat/license-manager     - Phase 1
    ├── feat/admin-ui-gates      - Phase 2
    ├── feat/free-build          - Phase 3
    ├── feat/pro-launch          - Phase 4
    ├── feat/ux-onboarding       - Phase 5
    └── feat/calendar-providers  - Phase 6
```

---

## Dev Environment

### Requirements

- PHP 7.4+
- WordPress 5.8+
- Composer (for Stripe SDK — run `composer install`)
- Git

### Recommended Tools

- **IDE:** VS Code, PhpStorm
- **Local dev:** Local by Flywheel, Laravel Valet, XAMPP
- **API testing:** Postman, Insomnia
- **License server:** Lemon Squeezy (recommended) or custom PHP

---

## Documentation Rules

- Each file has one concern — do not duplicate content across files.
- Temporary notes (debugging, session tracking) must be deleted once resolved; never commit them long-term.
- Update `CHANGELOG.md` for every release, following [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
- Update `TODO.md` when phases start or close.
- Use relative links between docs files.
- Do not add `docs/` files for one-off debugging sessions — use git commit messages or issue comments instead.
