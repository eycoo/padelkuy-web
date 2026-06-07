# Derive slot availability from bookings instead of a slots table

Operating hours are fixed (07:00–20:00), so we don't store a row per court/date/hour. Availability is computed: PHP generates the hour grid and marks a slot taken if any booking on that court and date overlaps it. A booking covers a contiguous hour range (`start_hour`, `end_hour`), so conflicts are caught with an overlap check (`start_hour < new_end AND end_hour > new_start`) rather than a `UNIQUE(court_id, date, hour)` index.

## Considered Options

A pre-generated `slots` table (one row per court/date/hour with a status) was rejected: it reproduces the same data explosion that made the static `detail.html` 177KB, requires generating rows ahead of time, and buys flexibility (per-slot price/blocking) we don't need.

## Consequences

No simple unique constraint guards against double-booking — the overlap check must run inside the insert (ideally a transaction) or two concurrent range bookings could both succeed. If per-slot pricing or manual blocking is ever needed, this decision must be revisited.
