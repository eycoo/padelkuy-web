# PadelKuy — Handoff

Pick-up doc for whoever continues this project (frontend: Royan; or a fresh agent session).

## Where things stand

PadelKuy is being turned from a static demo into a working full-stack booking app.
**Customer backend is done and merged to `master`.** Frontend is filed as issues #14–#18.
**Admin backend (venues/courts CRUD + all-bookings oversight) is done and merged**
(PRD #19, BE issues #20–#23); its frontend is #24–#26.
**The booking payment lifecycle backend is done and merged** (ADR-0003, BE issues
#28–#32): bookings now have a status (`pending`/`paid`/`expired`/`cancelled`) and a
human-readable code, payment is simulated, and refunds are timed. Its frontend is
#33–#35.
**PDF payment receipt (kuitansi) is done and merged** (ADR-0004, issues #41–#42):
a customer downloads a generated-on-demand PDF receipt for a paid booking from
their order history (`GET /api/receipt.php`); also satisfies the "reporting PDF"
deliverable. **The customer frontend (Royan's `frontend` branch) has been merged
to `master`** — homepage, detail, login/register, and order history now call the API.
**The admin-editor backend gaps the FE needed are now filled** (ADR-0005, issues
#43–#51): venue About/`description`, facility chips, main image + detail gallery,
court type, per-court schedules (day-band hours + pricing, driving availability
with a flat-grid fallback), admin booking status filter + pagination, a
transactional bulk venue save, and an image-upload endpoint.

- **Stack:** native PHP + MySQL, no fullstack framework, no Node/TypeScript (course constraint). Backend is a JSON API; frontend is plain HTML/CSS/JS calling it with `fetch()`. See `docs/adr/0002-...`.
- **Default branch is `master`** (the real history). `main` is an unrelated 2-commit stub — ignore it.

## Repo layout

```
public/            web root (serve this dir)
  index.html detail.html main.js css/ assets/   frontend (Royan)
  api/             JSON endpoints (done); api/admin/ = admin-only endpoints
lib/               domain logic — http, session, auth, venues, venue_save, courts,
                   schedules, availability, bookings, payments, receipt, uploads
config/db.php      PDO connection (env-overridable)
tests/             PHPUnit (113 tests, all green)
schema.sql seed.sql              (+ schema.railway.sql / seed.railway.sql for managed hosts)
CONTEXT.md         domain glossary (Customer / Admin / Venue / Court / Slot / Booking / Booking code / Payment / Refund)
docs/adr/          0001 derive-availability, 0002 json-api, 0003 booking-payment-lifecycle,
                   0004 pdf-payment-receipt, 0005 court-schedules-and-venue-detail
docs/erd.png       rendered ERD (source docs/erd.mmd); regenerate after schema changes
```

`lib/`, `config/`, `tests/`, `*.sql` live **outside** `public/` on purpose — DB creds must not be web-servable.

## Run it locally

Requires XAMPP (already installed at `C:\xampp`, PHP 8.2 + MariaDB).

```
# 1. start MySQL (XAMPP Control Panel, or the mysqld in C:\xampp\mysql\bin)
# 2. load DB
C:\xampp\mysql\bin\mysql.exe -u root  < schema.sql
C:\xampp\mysql\bin\mysql.exe -u root  < seed.sql
# 3. serve (web root = public/)
C:\xampp\php\php.exe -S localhost:8000 -t public
# 4. tests
C:\xampp\php\php.exe phpunit.phar      # phar is gitignored; re-download from phar.phpunit.de/phpunit-10.phar
```

DB connection defaults: host `127.0.0.1`, db `padelkuy`, user `root`, no password. Override with env vars `DB_HOST/DB_NAME/DB_USER/DB_PASS`.

## API contracts (all live)

| Method + path | Body | Success | Errors |
|---|---|---|---|
| `GET /api/venues.php[?city=]` | — | `[{id,name,city,price_per_hour,tag,image_path}]` | — |
| `GET /api/availability.php?venue_id=&date=YYYY-MM-DD` | — | `{venue_id,date,courts:[{id,label,slots:[{hour,taken}]}]}` | `422` bad params |
| `POST /api/register.php` | `{name,email,password}` | `201 {id}` | `409` dup email · `422` invalid |
| `POST /api/login.php` | `{email,password}` | `200 {id,name,email,role}` + session cookie | `401` |
| `POST /api/logout.php` | — | `200 {ok:true}` | — |
| `POST /api/bookings.php` | `{court_id,date,start_hour,end_hour}` | `201 {id,code,status,venue_name,court_label,date,start_hour,end_hour,hours,price}` | `401` · `409` overlap · `422` (incl. past date) |
| `GET /api/bookings.php` | — (session) | `[{...booking, code, status, hours, price}]` | `401` |
| `POST /api/payments.php` | `{booking_id}` | `201 {id,booking_id,amount,status,paid_at,refunded_at}` (settles a pending booking) | `401` · `403` not owner · `404` · `422` not payable/expired |
| `POST /api/cancel.php` | `{booking_id}` | `200 {…payment, status:"refunded"}` (self-cancel paid booking inside the 5-min window) | `401` · `403` not owner / window closed · `404` · `422` not a paid booking |
| `GET /api/receipt.php?booking_id=N` | — (session) | `200` PDF stream (kuitansi for a paid booking; generated on demand, ADR-0004) | `401` · `403` not owner · `404` · `422` no payment yet |

Frontend must send `Content-Type: application/json` and include credentials so the PHP session cookie is stored/sent.

### Admin endpoints (all require an admin session)

`login` now returns `role` (`"user"` or `"admin"`). Every endpoint below returns
**401** if logged out and **403** if logged in as a non-admin. Seed admin:
`admin@padelkuy.test` / `admin123` (from `seed.sql`).

| Method + path | Body | Success | Errors |
|---|---|---|---|
| `GET /api/admin/venues.php[?id=N]` | — | venue list, or one venue (incl. `description,main_image_path,facilities[],images[]`) | `404` · `403/401` |
| `POST /api/admin/venues.php` | `{name,city,price_per_hour,tag?,description?,image_path?,main_image_path?,facilities?[],images?[]}` | `201 {venue}` | `422` · `403/401` |
| `PUT /api/admin/venues.php?id=N` | same fields | `200 {venue}` | `422` · `404` |
| `DELETE /api/admin/venues.php?id=N` | — | `200 {ok:true}` (cascades courts/bookings) | `404` |
| `POST /api/admin/venue_save.php` · `PUT ?id=N` | full bundle (venue + `facilities[]` + `images[]` + `courts[]` w/ nested `schedules[]`) | `201`/`200 {venue, courts:[{…,schedules}]}` (one transaction) | `422` · `404` |
| `GET /api/admin/courts.php?venue_id=N` | — | `[{id,venue_id,label,type}]` | `422` |
| `POST /api/admin/courts.php` | `{venue_id,label,type?}` | `201 {id}` | `422` |
| `PUT /api/admin/courts.php?id=N` | `{label,type?}` | `200 {ok:true}` | `404`·`422` |
| `DELETE /api/admin/courts.php?id=N` | — | `200 {ok:true}` (cascades bookings/schedules) | `404` |
| `GET /api/admin/schedules.php?court_id=N` | — | `[{id,court_id,day_band,start_hour,end_hour,price}]` | `422` |
| `PUT /api/admin/schedules.php?court_id=N` | `{schedules:[{day,start,end,price}]}` (replace-all) | `200 [schedules]` | `422` |
| `POST /api/admin/upload.php` | multipart field `image` (jpeg/png/webp, ≤5 MB) | `201 {path}` | `422` · `403/401` |
| `GET /api/admin/bookings.php[?venue_id=][&date=][&status=][&page=N&limit=N]` | — | `[{...booking, code, status, user_name, payment_status}]`; total in `X-Total-Count` header | `403/401` |
| `DELETE /api/admin/bookings.php?id=N` | — | `200 {ok:true}` (soft-cancel; refunds the payment if the booking was paid, no time limit) | `404` |

## Key decisions (don't re-litigate without reason)

- **ADR-0001:** availability is *derived* from bookings, no `slots` table. Operating hours fixed 07:00–20:00 (bookable start hours 7..20). A booking is a contiguous range `[start_hour, end_hour)` (end exclusive); conflicts caught by an overlap check in a transaction.
- **ADR-0002:** JSON API + `public/` web root (not server-rendered) so backend/frontend split cleanly by role.
- **ADR-0003:** booking payment lifecycle. A booking starts `pending` and **holds its slots**; availability and the overlap check count only `pending`/`paid` bookings, so `expired`/`cancelled` free their slots. Unpaid `pending` bookings are swept to `expired` 15 minutes after creation (lazy, on read — no cron). Payment is **simulated** (a `payments` row, `paid`/`refunded`). A customer may self-cancel a `paid` booking within **5 minutes** of paying (refund); after that it is locked and only an admin can cancel (admin cancel always refunds a paid booking). Cancellation is a **soft** status change — rows persist. `createBooking` also rejects past dates.
- **ADR-0005:** court **schedules** drive bookable hours + per-hour price (day bands `everyday`/`mon_fri`/`sat_sun`, specific band beats everyday), with a **fallback**: a court with no schedules keeps the fixed 07:00–20:00 grid + flat venue price (so legacy/seed courts and the whole pre-existing suite are unaffected). Booking price stays derived (sum of per-hour rates). Venue detail extras (About, facilities, gallery, court type) are additive columns/child tables; `saveVenueBundle` writes the whole editor in one transaction.
- **Glossary (`CONTEXT.md`):** Venue = the place; Court = A/B/C inside it; Slot = one bookable hour; Booking = a user's reservation over a range. Use these words in code/UI.

## Frontend work (Royan) — issues #14–#18

| Issue | What | Blocked by |
|---|---|---|
| #14 | Homepage: render venues from API + city filter | — |
| #15 | Detail: render availability grid from API (delete 177KB hardcoded markup) | — |
| #16 | Auth UI: register/login/logout forms + nav state | — |
| #17 | Booking UI: range selection + submit + confirmation | #15, #16 |
| #18 | My Bookings page | #16, #17 |

All labelled `ready-for-human`, parent #1. Start with #14/#15/#16 (independent), then #17, then #18.

### Admin frontend (Royan) — issues #24–#26

| Issue | What | Blocked by |
|---|---|---|
| #24 | Admin panel shell: login gate + nav (non-admins redirected) | #20 (BE, done) |
| #25 | Venue & court management UI | #24, #21, #22 |
| #26 | All-bookings table + cancel UI | #24, #23 |

Parent PRD #19. The admin BE (#20–#23) is merged to `master`.

### Payment lifecycle frontend (Royan) — issues #33–#35

| Issue | What | Blocked by |
|---|---|---|
| #33 | Payment UI: "Bayar" button + confirmation (extends #17) | #30 (BE, done) |
| #34 | My Bookings: status + code + cancel/refund within window (extends #18) | #28/#30/#31 (BE, done) |
| #35 | Admin booking-management page: status + payment status + cancel (extends #26) | #32 (BE, done) |

All BE blockers are merged; these can start whenever Royan picks them up.

## Deploy & CI

- **CI:** `.github/workflows/ci.yml` runs the full PHPUnit suite against a MySQL 8
  service on every push to `master` and every PR. `tests/bootstrap.php` builds the
  throwaway `padelkuy_test` DB from `schema.sql`, so nothing extra is needed — keep
  it green before merging.
- **Deploy (Railway, Docker):** `Dockerfile` is `php:8.2-apache` with the docroot
  set to `public/` (so `lib/`+`config/` are never web-served) and Apache listening
  on `$PORT`. `railway.json` builds from it. Connect the repo + add a MySQL plugin,
  then map its vars to the env the app reads: `DB_HOST/DB_NAME/DB_USER/DB_PASS`
  (see `config/db.php` — all env-overridable).
- **Schema on a managed host:** import `schema.railway.sql` then `seed.railway.sql`
  (the plain `schema.sql`/`seed.sql` carry `CREATE DATABASE`/`USE padelkuy`, which
  managed providers reject — the `.railway.sql` variants drop those lines).
- **Auto-update from GitHub:** Railway redeploys on every push to `master` — but
  only the **code**. The database is NOT migrated automatically. A code-only
  feature goes live on push; a feature that changes the schema (new column/table)
  needs its SQL applied to the Railway MySQL by hand, or the deployed code will hit
  a stale DB (the "Unknown column 'status'" class of error). Keep schema changes
  in `schema.sql` AND mirror them into `schema.railway.sql`.

## Open / loose ends

- All backend is merged to `master`; the only open work is frontend (Royan): customer #14–#18, admin #24–#26, payment lifecycle #33–#35.
- The `payments` table is new — reload `schema.sql` (+`seed.sql`) into the dev `padelkuy` DB before a demo so it exists. `tests/bootstrap.php` rebuilds the test DB from `schema.sql` automatically.
- Regenerate the ERD after any schema change: `npx -y -p @mermaid-js/mermaid-cli mmdc -i docs/erd.mmd -o docs/erd.png -b white --scale 3` (run via `cmd //c` — the bash `npx` is intercepted in this environment).
- Parent PRDs #1 (customer) and #19 (admin) stay open until their frontends land.
- Tests target a throwaway `padelkuy_test` DB (auto-created by `tests/bootstrap.php`).
