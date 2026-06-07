# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Stack

Pure static site — vanilla HTML and CSS only. No JavaScript, no build tools, no package manager. Open any `.html` file directly in a browser.

## Architecture

Single shared stylesheet at `css/style.css` — do not split it. All styles for both pages live there, organized in three blocks:

1. **Shared** — `:root` vars, reset, `.navbar`, `.btn`, `.footer`
2. **Homepage** (`index.html`) — `.hero`, `.search-bar`, `.time-picker`, `.courts`, `.court-grid`, `.court-card`
3. **Detail page** (`detail.html`) — `.breadcrumb`, `.detail-wrap`, `.booking-widget`, `.calendar`, `.slots`, `.booking-form`

Responsive rules are consolidated in a single `@media (max-width: 900px)` block at the bottom of the file.

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
