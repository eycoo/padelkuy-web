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

**The frontend is now wired end-to-end (2026-06-18 session).** The customer detail
page (`public/detail.js`) loads real venues + availability and creates a booking via
`POST /api/bookings.php`, then redirects to `pembayaran.html?booking_id=<id>` (it was
previously mock and navigated with no id → "ID pesanan tidak ditemukan"). The admin
page (`public/admin.html` + `admin.js`) was entirely mock (`alert()` placeholders) and
is now a working editor: venue selector, real image upload (`admin/upload.php`),
add/remove facility chips, court + schedule editing, save via `admin/venue_save.php`,
and a live bookings table with status tabs + cancel. A demo customer account was
seeded: `user@padelkuy.test` / `user123`.

- **Stack:** native PHP + MySQL, no fullstack framework, no Node/TypeScript (course constraint). Backend is a JSON API; frontend is plain HTML/CSS/JS calling it with `fetch()`. See `docs/adr/0002-...`.
- **Default branch is `master`** (the real history). `main` is an unrelated 2-commit stub — ignore it.

## ✅ DONE: Point C — per-venue admin / owner model (ADR-0006)

Implemented 2026-06-18. The admin model is now **one admin per venue** (1 admin owns 1
venue, `venues.owner_id`); every `api/admin/*` endpoint is scoped to the caller's venue.

- **Domain:** `CONTEXT.md` Admin entry rewritten; new **ADR-0006** supersedes the
  global-admin stance.
- **Schema:** `venues.owner_id INT NULL → users(id) ON DELETE SET NULL` in `schema.sql`
  + `schema.railway.sql`. Live/managed DBs take the **additive, idempotent** migration
  `migrations/0006_add_venue_owner.php` (do NOT re-run `schema.railway.sql`). `getVenue`
  now returns `owner_id`; `createVenue($pdo,$in,$owner_id=null)`; `saveVenueBundle(...,$owner_id=null)`.
- **Ownership:** `lib/ownership.php` (`ownedVenueId`, `ownsVenue`, `ownsCourt`,
  `ownsBooking`) + HTTP guards in `lib/session.php` (`require_venue_owner` /
  `require_court_owner` / `require_booking_owner`, 403 on non-owner).
- **Endpoints scoped:** `admin/venues.php` (GET lists only own venue; POST blocks a 2nd
  venue with 409 + sets owner; PUT/DELETE owner-only), `admin/venue_save.php` (POST 409
  if already owns, PUT owner-only), `admin/courts.php` + `admin/schedules.php`
  (court→venue ownership), `admin/bookings.php` (GET forced to own venue — no more
  all-venues list; DELETE booking→venue ownership). `admin/upload.php` stays plain
  admin-gated (no venue param to scope).
- **Registration is real:** new `POST /api/register_admin.php` creates the admin user +
  their venue (owner set) in one transaction and logs them in; `lib/auth.php` gained
  `registerAdmin()`. `registerai-admin.html` now collects City + Harga; `auth.js`
  `register-admin-form` calls the endpoint (was a fake `setTimeout`).
- **Frontend:** `admin.html`/`admin.js` dropped the venue selector — the panel loads
  "my venue" (`loadMyVenue`), or a blank create-form if the admin has none.
- **Seed:** four venue admins, one per seed venue (see Demo accounts below).
- **Tests:** `tests/OwnershipTest.php` (7 tests) — predicate + registerAdmin coverage.
  Full suite **121 green**.

Follow-ups: regenerate `docs/erd.png` (source updated); run the live migration +
re-seed/own venues on Railway before the next deploy; reload the local dev `padelkuy`
DB (`schema.sql` + `seed.sql`) so `owner_id` + the 4 admins exist.

<details><summary>Original Point C brief (for reference)</summary>

**⚠️ This CONTRADICTS the current domain.** `CONTEXT.md` defines Admin as "a single
global role… there is no per-venue owner" (and explicitly _Avoids_ "owner/venue
owner/partner"). ADR-0002+ assume a global admin. So step 0 is to **revise the domain,
not just the code**:

1. **Domain first:** rewrite the `Admin` entry in `CONTEXT.md` (or add an `Owner`
   actor) and write a **new ADR** (`docs/adr/0006-per-venue-admin.md`) that supersedes
   the global-admin stance and records why.
2. **Schema (additive — see migration rules below):** add ownership. Recommended:
   `venues.owner_id INT NULL REFERENCES users(id)`. Mirror into `schema.sql` +
   `schema.railway.sql`; migrate the live DB additively.
3. **Backend scoping** (the core work): a helper like `ownedVenueId(pdo, user_id)` and
   an authorize-this-is-mine guard (403 otherwise). Then scope every admin endpoint to
   the caller's venue:
   - `admin/venues.php` — list/GET only own venue; POST sets `owner_id = me` and blocks
     a second venue; PUT/DELETE only if owner.
   - `admin/venue_save.php` — POST sets owner; PUT only own venue.
   - `admin/courts.php` / `schedules.php` / `upload.php` — verify the court/venue is the
     caller's.
   - `admin/bookings.php` — filter to bookings whose court→venue is owned by the caller
     (today it lists **all** venues — this is the multi-venue list the user noticed).
4. **Admin registration:** `public/registerai-admin.html` + the `register-admin-form`
   handler in `auth.js` is currently **fake** (a `setTimeout`, no API call). Wire it to
   really create an admin user + their venue and set `owner_id`.
5. **Frontend:** drop the venue selector in `admin.js` (an admin has exactly one venue);
   load "my venue"; bookings table no longer needs the venue dropdown.
6. **Tests:** existing admin tests assume a global admin and will need an owner set up;
   add ownership/authorization tests (an admin cannot touch another admin's venue).
7. **Seed:** give the seed admin an owned venue.

(If the user later only wants a *focused view* without changing the model, the cheaper
"Point B" is FE-only: filter the bookings table by `admin/bookings.php?venue_id=N` —
the endpoint already supports it.)

</details>

## Repo layout

```
public/            web root (serve this dir)
  index.html detail.html main.js css/ assets/   frontend (Royan)
  api/             JSON endpoints (done); api/admin/ = admin-only endpoints
lib/               domain logic — http, session, auth, venues, venue_save, courts,
                   schedules, availability, bookings, payments, receipt, uploads
config/db.php      PDO connection (env-overridable)
tests/             PHPUnit (114 tests, all green)
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
**401** if logged out, **403** if logged in as a non-admin, and (ADR-0006) **403** if
the admin doesn't own the target venue/court/booking. Seed admins (one per venue, all
`admin123`): `admin@padelkuy.test` (the G club), `admin2@` (Fote), `admin3@` (Padel
First), `admin4@` (Hobi Padl). Admins self-register via `POST /api/register_admin.php`
(`{name,email,password,venue_name,city,price_per_hour}` → `201` + session).

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
- **ADR-0005:** court **schedules** drive bookable hours + per-hour price (day bands `everyday`/`mon_fri`/`sat_sun`, specific band beats everyday), with a **fallback**: a court with no schedules keeps the fixed 07:00–20:00 grid + flat venue price (so legacy/seed courts and the whole pre-existing suite are unaffected). Booking price stays derived (sum of per-hour rates), and the booking quote and the payment amount now go through a single `priceForRange` (`lib/availability.php`) so they cannot drift — an earlier bug charged the flat venue rate while quoting the schedule sum (fixed 2026-06-18, commits `e576a1d`/`362c21b`). Venue detail extras (About, facilities, gallery, court type) are additive columns/child tables; `saveVenueBundle` writes the whole editor in one transaction.
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
- **Deploy (Railway, Docker):** live at https://web-production-97880.up.railway.app
  (project `padelkuy`: `web` service + a MySQL 9 service, DB name `railway`).
  `Dockerfile` is `php:8.2-cli` running the PHP built-in server
  (`php -S 0.0.0.0:$PORT -t public`) so the docroot is `public/` and `lib/`+`config/`
  are never web-served. (Apache was abandoned — it crash-looped with "More than one
  MPM loaded".) The `web` env maps `DB_HOST/DB_NAME/DB_USER/DB_PASS` to the MySQL
  service vars; read by `config/db.php`.
- **Redeploy is manual:** a `git push` does NOT trigger a deploy (the GitHub App
  isn't authorized on the repo). Deploy with **`railway up --service web`** from the
  repo root — it uploads the working tree and builds in the cloud.
- **First schema load on the managed DB:** import `schema.railway.sql` then
  `seed.railway.sql` (the `.railway.sql` variants drop the `CREATE DATABASE`/`USE`
  lines that managed providers reject).
- **Schema CHANGES on a DB that already has data:** do NOT re-run
  `schema.railway.sql` — it `DROP`s every table and wipes the data. Apply an
  **additive** migration instead (`ALTER TABLE ... ADD COLUMN`, `CREATE TABLE IF NOT
  EXISTS`), idempotent via `information_schema` checks. The local MariaDB client
  can't authenticate to MySQL 9 (`caching_sha2_password`), so run the migration with
  a one-off PHP PDO script over the MySQL service's public TCP proxy. Still keep
  schema changes in `schema.sql` AND mirror them into `schema.railway.sql`.

## Open / loose ends

- All backend is merged to `master` and **deployed live** — including the admin-editor BE (#43–#51: venue detail, court schedules, bookings paging, bulk save, upload). The Railway DB has been migrated additively for ADR-0005 (no data loss).
- **Frontend is wired** (2026-06-18): customer homepage/detail/booking/payment/riwayat and the admin editor all call the API (see "Where things stand"). Brought forward from the `frontend` branch file-by-file — that branch predates the ADR-0005 backend, so a full merge would have reverted `lib/`/`tests/`/`schema*`; only `public/` files were taken. `detail.js`/`admin.js`/`admin.html` were then rewritten to wire the real endpoints. The `frontend` branch is now stale vs `master`.
- This session's commits on `master`: `e576a1d`/`362c21b` (payment price fix + `priceForRange`), `d7b72f7` (FE refresh from `frontend`), `c95fbf4` (seed demo user), `771b49f` (detail page wired), `c64dde0` (admin editor wired). The live Railway DB also had the demo user inserted via a one-off PDO script (its seed was loaded before that account existed).
- **Demo accounts:** four venue admins `admin@`/`admin2@`/`admin3@`/`admin4@padelkuy.test` (all `admin123`, one venue each, ADR-0006), customer `user@padelkuy.test`/`user123` (all in `seed.sql`/`seed.railway.sql`).
- New docs: `docs/demo-backend.md` (backend demo guide) and `docs/presentation-brief.md` (brief for generating slides). Not yet committed at time of writing — commit if keeping.
- Remaining FE polish (not blockers): the customer detail page only books a single hour per slot (no range select); admin schedule editing is functional but minimal.
- The `payments` table is new — reload `schema.sql` (+`seed.sql`) into the dev `padelkuy` DB before a demo so it exists. `tests/bootstrap.php` rebuilds the test DB from `schema.sql` automatically.
- Regenerate the ERD after any schema change: `npx -y -p @mermaid-js/mermaid-cli mmdc -i docs/erd.mmd -o docs/erd.png -b white --scale 3` (run via `cmd //c` — the bash `npx` is intercepted in this environment).
- Parent PRDs #1 (customer) and #19 (admin) stay open until their frontends land.
- Tests target a throwaway `padelkuy_test` DB (auto-created by `tests/bootstrap.php`).
