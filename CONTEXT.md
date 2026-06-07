# PadelKuy

A web app for finding and booking padel courts in Indonesian cities. Users browse venues, pick a court and time, and reserve a slot.

## Language

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
A reservation by a user covering a contiguous range of hours on one court and date (e.g. 18:00-20:00). Spans one or more slots.
_Avoid_: reservation, order, pesanan
