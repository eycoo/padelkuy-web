# PadelKuy

A web app for finding and booking padel courts in Indonesian cities. Users browse venues, pick a court and time, and reserve a slot.

## Language

### Actors

**Customer**:
A person with an account who browses venues, books slots, and pays for them. The role every public registration gets.
_Avoid_: user (when meaning specifically a booker), member, pelanggan

**Admin**:
A platform operator who manages venues and courts and oversees every booking. A single global role, seeded into the database — there is no per-venue owner and no self-registration.
_Avoid_: owner, venue owner, partner, superuser

### Booking and payment

**Venue**:
A padel club or location that hosts one or more courts (e.g. "the G club Padel"). Has a city and a price.
_Avoid_: court (when meaning the place), club, tempat

**Court**:
A single playable padel court inside a venue (e.g. A, B, C). What a slot is booked against.
_Avoid_: lapangan, sub-court

**Slot**:
One bookable hour for one court on one date (e.g. 08:00 on court A). The unit of availability; either free or taken.
_Avoid_: time-pill, jadwal

**Booking**:
A reservation by a customer covering a contiguous range of hours on one court and date (e.g. 18:00-20:00). Spans one or more slots. Holds its slots from the moment it is made until it is paid; an unpaid booking that is never paid stops holding them.
_Avoid_: reservation, order, pesanan

**Booking code**:
A short human-readable reference for a booking (e.g. PDL-0001), shown to the customer and used by admins to look a booking up.
_Avoid_: order code, kode pesanan, kode lapangan

**Payment**:
The record that a customer has paid for a booking. Either settled or refunded.
_Avoid_: transaction, invoice, bill, tagihan

**Refund**:
Returning a booking's payment when the booking is cancelled within the allowed window. Frees the slots again.
_Avoid_: chargeback, return, pengembalian
