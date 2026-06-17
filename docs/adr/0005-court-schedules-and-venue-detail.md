# Court schedules drive availability + pricing; venue detail fields fill the admin editor

The admin venue editor (the merged frontend) edits more than the backend stored: an **About** blurb, **facility** chips, a **thumbnail / main / detail-gallery** image set, a court **type** (indoor/outdoor), and — per court — a list of **schedules** `{day band, start, end, price}`. This ADR records how those were modelled. It amends ADR-0001 (fixed 07:00–20:00 grid, single flat venue price).

- **Schedules are the source of bookable hours and price, with a fallback.** A court with one or more `court_schedules` rows is bookable only in the union of the hours whose `day_band` matches the date (`everyday`, `mon_fri`, `sat_sun`); each hour costs the matching schedule's price, a specific band beating `everyday`. A court with **no** schedules keeps the ADR-0001 behaviour exactly: the fixed `operating_hours()` grid and the flat `venues.price_per_hour`. This fallback is deliberate — it let the existing 69-test suite stay green and means seed/legacy venues need no migration.
- **Pricing is still derived, never stored.** Booking price = sum of `priceForHour` over the booked range (computed in `applySchedulePrice`), mirroring ADR-0001's "derive, don't store" stance. For a fallback court the sum equals `hours × venue price`, so nothing changes for them.
- **Hours stay whole and integer.** Schedules accept `"07:00"` strings from the FE but store integer hours; half hours are rejected, keeping the slot grid one-hour like ADR-0001.
- **Venue detail is normalised where it is a list.** Facilities and the detail gallery are their own child tables (`venue_facilities`, `venue_images`), replaced wholesale per save. The single thumbnail stays on `venues.image_path` (customer cards already read it); `main_image_path` is added alongside. About text is a `description` column.
- **One transactional save.** `saveVenueBundle` upserts the venue and replaces facilities, images, courts, and each court's schedules atomically (the `set*` helpers are transaction-aware so they compose under one outer transaction); a bad nested row rolls the whole save back.

## Considered Options

*Per-booking price snapshot column* was rejected to stay consistent with ADR-0001's derived availability/price. *Replacing the fixed grid outright* (no fallback) was rejected: it would force a migration of every existing court and break the suite for no functional gain. *JSON columns for facilities/images/schedules* were rejected in favour of child tables, matching the relational style of the rest of the schema and keeping them queryable.

## Consequences

Schema grows by `venues.description` / `venues.main_image_path`, `courts.type`, and three tables (`venue_facilities`, `venue_images`, `court_schedules`). Availability and booking-price code now consult schedules per hour, which adds queries (acceptable at this scale; a court-level cache is the obvious later optimisation). Admin booking lists gained `status` filtering and `limit`/`offset` paging (total returned via the `X-Total-Count` header) for the editor's tabs and pager. Because these are schema changes, `schema.railway.sql` must be re-applied to the managed DB on deploy (see HANDOFF "Auto-update").
