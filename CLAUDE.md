# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Stack

Native PHP + MySQL backend with a vanilla HTML/CSS/JS frontend. No fullstack framework, no Node/TypeScript, no build tools (course constraint). Backend is a JSON API consumed by the frontend via `fetch()` — see ADR-0002. Run locally with XAMPP/Laragon (Apache + PHP + MySQL).

## Architecture

Web root is `public/` — only files under it are served. Backend code (`lib/`, `config/`) lives outside the web root so credentials are never exposed.

```
public/            web root (Apache docroot)
  index.html, detail.html, main.js, css/, assets/   frontend (owned by FE engineer)
  api/             JSON endpoints (thin wrappers over lib/)
lib/               domain logic — auth, venues, availability, bookings, http/session helpers
config/db.php      PDO connection (env-overridable)
tests/             PHPUnit, run against a throwaway padelkuy_test DB
schema.sql, seed.sql
```

Decision-rich logic lives in `lib/` so it is unit-testable; `api/*.php` files stay thin. Availability is derived from bookings (no slots table) — see ADR-0001. Domain language is defined in `CONTEXT.md` (Venue / Court / Slot / Booking).

The shared stylesheet `public/css/style.css` is not split — all styles for both pages live there. Responsive rules are consolidated in a single `@media (max-width: 900px)` block at the bottom of the file.

### Running

```
mysql -u root < schema.sql && mysql -u root < seed.sql
php -S localhost:8000 -t public
php phpunit.phar          # download once: https://phar.phpunit.de/phpunit-10.phar
```

## Coding conventions

- CSS variables (`:root`) for brand colors only. Raw values for spacing/padding.
- Loose BEM for class names: `.court-card`, `.btn-primary`, `.time-pill`. Avoid long compound names.
- No comments unless the reason is non-obvious.
- Interactive state simulation (active dates, selected slots, taken slots) uses hardcoded modifier classes: `.active`, `.selected`, `.taken`, `.disabled`, `.muted`.
- CSS-only interactivity uses the `:target` pseudo-selector (e.g., the time-picker reveal on homepage).

## Brand

- Neon green `#c6ff3d` / dark charcoal `#1c1f24` theme.
- Assets go in `assets/images/` and `assets/icons/`. Currently placeholder divs with grey backgrounds stand in for real images.

## Agent skills

### Issue tracker

Issues and PRDs live as GitHub issues in `eycoo/padelkuy-web` (uses the `gh` CLI). See `docs/agents/issue-tracker.md`.

### Triage labels

Five canonical triage roles map to identically-named labels (`needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`). See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: one `CONTEXT.md` + `docs/adr/` at the repo root. See `docs/agents/domain.md`.
