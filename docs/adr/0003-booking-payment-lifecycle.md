# Booking payment lifecycle: pending holds the slot, simulated payment, timed refund

A booking is no longer created as an instantly-confirmed reservation. It starts as `pending` and moves through an explicit lifecycle: `pending → paid` (after payment), `pending → expired` (if unpaid past a window), or `→ cancelled` (voided, with a refund when it was paid). Payment is **simulated** — a `payments` row records that money changed hands (`paid` / `refunded`), no real gateway. This amends ADR-0001.

- **A pending booking holds its slots.** Availability (ADR-0001) and the overlap check now count a slot as taken when a booking on it is `paid` **or** `pending and not yet expired`; `expired` and `cancelled` bookings no longer hold their slots. This closes the race where two customers could each create an unpaid booking on the same slot and then both pay.
- **Lazy expiry, 15 minutes.** An unpaid `pending` booking expires 15 minutes after creation. There is no cron (course constraint): stale `pending` rows are swept to `expired` on read before availability/booking-list queries run, so the stored status stays truthful for the admin booking view.
- **Refund window, 5 minutes.** A customer may self-cancel a `paid` booking within 5 minutes of paying, which refunds it and frees the slots. After 5 minutes the booking is locked to the customer. An admin may cancel any booking at any time, and cancelling a paid booking always refunds it.
- **Cancellation is soft.** `cancelBooking` no longer hard-deletes the row — cancelled/expired/refunded bookings must persist so the admin booking-management view can show their status and so refunds have a record.

## Considered Options

*Pay-first* (no booking row until payment succeeds) was rejected: it leaves nothing to manage between intent and payment, defeating the admin booking-management view and a customer "pending payment" state. *Only paid bookings hold the slot* was rejected for the double-pay race it allows. A *status column on bookings* (instead of a separate `payments` table) was considered; a `payments` table was chosen to keep the money record (amount, paid/refunded, timestamps) as its own entity.

## Consequences

Schema grows: `bookings` gains a `status` and a human-readable `code` (e.g. `PDL-0001`); a new `payments` table holds the money record 1:1 with a booking. Every availability and overlap query must filter on booking status, and the sweep-to-expired step adds a write before those reads. The two timers (15-minute expiry, 5-minute refund) are independent and easy to confuse — they are recorded here so the values are not mistaken for typos.
