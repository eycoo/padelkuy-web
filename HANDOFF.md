# PadelKuy — Handoff

Pick-up doc for whoever continues this project (frontend: Royan; or a fresh agent session).

## Where things stand

PadelKuy is being turned from a static demo into a working full-stack booking app.
**Backend is done and merged to `master`.** Frontend is next and is filed as issues #14–#18.

- **Stack:** native PHP + MySQL, no fullstack framework, no Node/TypeScript (course constraint). Backend is a JSON API; frontend is plain HTML/CSS/JS calling it with `fetch()`. See `docs/adr/0002-...`.
- **Default branch is `master`** (the real history). `main` is an unrelated 2-commit stub — ignore it.

## Repo layout

```
public/            web root (serve this dir)
  index.html detail.html main.js css/ assets/   frontend (Royan)
  api/             JSON endpoints (done)
lib/               domain logic — http, session, auth, venues, availability, bookings
config/db.php      PDO connection (env-overridable)
tests/             PHPUnit (20 tests, all green)
schema.sql seed.sql
CONTEXT.md         domain glossary (Venue / Court / Slot / Booking)
docs/adr/          0001 derive-availability, 0002 json-api
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
| `POST /api/login.php` | `{email,password}` | `200 {id,name,email}` + session cookie | `401` |
| `POST /api/logout.php` | — | `200 {ok:true}` | — |
| `POST /api/bookings.php` | `{court_id,date,start_hour,end_hour}` | `201 {id,venue_name,court_label,date,start_hour,end_hour,hours,price}` | `401` · `409` overlap · `422` |
| `GET /api/bookings.php` | — (session) | `[{...booking, hours, price}]` | `401` |

Frontend must send `Content-Type: application/json` and include credentials so the PHP session cookie is stored/sent.

## Key decisions (don't re-litigate without reason)

- **ADR-0001:** availability is *derived* from bookings, no `slots` table. Operating hours fixed 07:00–20:00 (bookable start hours 7..20). A booking is a contiguous range `[start_hour, end_hour)` (end exclusive); conflicts caught by an overlap check in a transaction.
- **ADR-0002:** JSON API + `public/` web root (not server-rendered) so backend/frontend split cleanly by role.
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

## Open / loose ends

- Merged remote feature branches not yet deleted: `feat/api-auth`, `feat/api-availability`, `feat/api-bookings`, `feat/api-my-bookings`, `chore/restructure-public`. Safe to delete (all merged).
- `main` stub branch still exists. Delete or leave.
- Dev `padelkuy` DB has leftover smoke-test rows (a `smoke@example.com` user + a few bookings). Reload `schema.sql` + `seed.sql` for clean demo data.
- Parent PRD #1 stays open until the frontend lands.
- Tests currently target a throwaway `padelkuy_test` DB (auto-created by `tests/bootstrap.php`).
