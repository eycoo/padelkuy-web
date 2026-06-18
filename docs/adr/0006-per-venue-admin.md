# An admin owns one venue; admin scope is per-venue, not global

ADR-0002 and the original `CONTEXT.md` defined **Admin** as a single global role,
seeded into the database, that manages every venue and oversees every booking —
explicitly *not* a per-venue owner, with no self-registration. This ADR
**supersedes that stance**: an admin now owns exactly one venue and can only see
and touch that venue's data.

## Decision

- **Ownership lives on the venue.** A nullable `venues.owner_id` references
  `users(id)` (`ON DELETE SET NULL`). One admin → one venue; an admin who already
  owns a venue cannot create or register a second (the create paths return `409`).
  `owner_id` is nullable so a venue can briefly exist unowned (e.g. an orphaned
  legacy row), but the registration flow always sets it.
- **Admins self-register with their venue.** `POST /api/register_admin.php`
  creates the admin user (role `admin`) **and** their venue in one transaction,
  setting `owner_id` to the new user, then logs them in. This replaces the
  earlier "seeded only, no self-registration" rule. The customer
  `register.php` is unchanged and still only mints `user` accounts.
- **Every admin endpoint is scoped to the caller's owned venue.** Pure predicate
  helpers (`ownedVenueId`, `ownsVenue`, `ownsCourt`, `ownsBooking` in
  `lib/ownership.php`) answer "is this mine?"; HTTP guards
  (`require_venue_owner` / `require_court_owner` / `require_booking_owner` in
  `lib/session.php`) turn a non-owner into a `403`. `admin/bookings.php` GET no
  longer lists all venues — it is forced to the caller's venue.
- **`require_admin` still gates the door.** Ownership is layered *on top of* the
  existing role check: a request must be an admin (`401`/`403` by role) **and**
  own the target (`403` by ownership).

## Considered Options

*A join table (`venue_admins`)* for many-admins-per-venue or
many-venues-per-admin was rejected — the model is a strict 1:1, so a single
`owner_id` column is enough and keeps the scoping queries trivial. *A global
`admin` plus a separate `owner` actor* was rejected to avoid two overlapping
admin concepts; instead the existing `Admin` actor is redefined. *Enforcing
one-venue-per-owner with a `UNIQUE(owner_id)` constraint* was left out so a NULL
owner stays representable and the cap is enforced in the create paths (clearer
`409` than a constraint violation).

## Consequences

- Schema gains `venues.owner_id` in `schema.sql` and `schema.railway.sql`. The
  live managed DB takes an **additive** migration
  (`migrations/0006_add_venue_owner.php`, idempotent via `information_schema`) —
  do **not** re-run `schema.railway.sql` (it drops every table).
- The seed now creates four venue admins (`admin@`…`admin4@padelkuy.test`, all
  `admin123`), one per seed venue, instead of one global admin.
- The admin frontend drops its venue selector: an admin always loads "my venue".
- ADR-0001/0003/0005 are unaffected (availability, payments, schedules are
  unchanged); this ADR only narrows *who* an admin endpoint serves.
