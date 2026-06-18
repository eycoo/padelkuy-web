# Panduan Demo Backend — PadelKuy

Catatan presentasi untuk **Backend Engineer**. Tiap bagian ditulis dengan pola
**Context dulu → baru penjelasan apa yang dilakukan**, supaya saat demo kamu bisa
menjelaskan *kenapa* sebelum *apa*.

> Akun demo (dari `seed.sql`):
> - Customer: `user@padelkuy.test` / `user123`
> - Admin: `admin@padelkuy.test` / `admin123`

---

## 1. Stack & Arsitektur

**Context.** Mata kuliah melarang fullstack framework, Node/TypeScript, dan build
tools. Jadi backend harus native dan sederhana, tapi tetap rapi dan teruji.

**Yang dilakukan.**
- **Native PHP + MySQL**. Backend = **JSON API**, frontend HTML/CSS/JS murni yang
  memanggil API lewat `fetch()`.
- **Web root = `public/`**. Hanya isi `public/` yang bisa diakses browser.
  `lib/`, `config/`, `tests/`, dan file `*.sql` ada **di luar** web root → kredensial
  DB tidak mungkin ke-serve ke publik. (Ini sekaligus poin keamanan.)
- **Logika di `lib/`, endpoint tipis di `api/`.** Semua keputusan (validasi, overlap,
  harga, lifecycle) ada di `lib/` supaya bisa di-unit-test; file `api/*.php` cuma
  membaca request → panggil fungsi `lib/` → kirim JSON.

```
public/            web root (yang di-serve Apache/PHP)
  *.html *.js css/ assets/   frontend
  api/             endpoint JSON (tipis); api/admin/ = khusus admin
lib/               logika domain: auth, venues, courts, schedules,
                   availability, bookings, payments, receipt, uploads
config/db.php      koneksi PDO (bisa di-override env var)
tests/             PHPUnit (114 test) terhadap DB padelkuy_test
schema.sql seed.sql
```

---

## 2. ERD & Relasi Antar Tabel

**Context.** Domain PadelKuy: sebuah **Venue** (tempat) berisi beberapa **Court**
(lapangan A/B/C); customer membuat **Booking** (reservasi rentang jam) atas sebuah
court, lalu **Payment** mencatat pembayarannya. Availability **tidak disimpan** —
dihitung dari booking (lihat ADR-0001), jadi tidak ada tabel `slots`.

**Yang dilakukan.** 8 tabel dengan relasi seperti ini:

```mermaid
erDiagram
    users ||--o{ bookings : "membuat"
    venues ||--o{ courts : "punya"
    venues ||--o{ venue_facilities : "punya"
    venues ||--o{ venue_images : "punya"
    courts ||--o{ court_schedules : "punya"
    courts ||--o{ bookings : "dipesan via"
    bookings ||--|| payments : "dibayar oleh"

    users {
        int id PK
        string name
        string email UK
        string password_hash
        enum role "user | admin"
    }
    venues {
        int id PK
        string name
        string city
        int price_per_hour
        string tag
        text description
        string image_path
        string main_image_path
    }
    venue_facilities {
        int id PK
        int venue_id FK
        string name
    }
    venue_images {
        int id PK
        int venue_id FK
        string image_path
        int sort_order
    }
    courts {
        int id PK
        int venue_id FK
        string label
        enum type "indoor | outdoor"
    }
    court_schedules {
        int id PK
        int court_id FK
        enum day_band "everyday | mon_fri | sat_sun"
        int start_hour
        int end_hour
        int price
    }
    bookings {
        int id PK
        int court_id FK
        int user_id FK
        date date
        int start_hour
        int end_hour
        enum status "pending | paid | expired | cancelled"
        string code "PDL-0001"
        timestamp created_at
    }
    payments {
        int id PK
        int booking_id FK_UK
        int amount
        enum status "paid | refunded"
        timestamp paid_at
        timestamp refunded_at
    }
```

**Cara membaca relasinya (jelaskan satu-satu):**

| Relasi | Tipe | Arti | FK |
|---|---|---|---|
| `users` → `bookings` | 1‑ke‑banyak | satu customer punya banyak booking | `bookings.user_id` |
| `venues` → `courts` | 1‑ke‑banyak | satu venue punya banyak court | `courts.venue_id` |
| `venues` → `venue_facilities` | 1‑ke‑banyak | chip fasilitas (Shower, Parking, …) | `venue_facilities.venue_id` |
| `venues` → `venue_images` | 1‑ke‑banyak | galeri foto detail, terurut | `venue_images.venue_id` |
| `courts` → `court_schedules` | 1‑ke‑banyak | jam + harga per day-band | `court_schedules.court_id` |
| `courts` → `bookings` | 1‑ke‑banyak | satu court dipesan banyak kali | `bookings.court_id` |
| `bookings` → `payments` | **1‑ke‑1** | satu booking → satu payment (`booking_id` UNIQUE) | `payments.booking_id` |

**Dua hal penting untuk ditekankan:**
1. **`ON DELETE CASCADE`** di semua FK. Hapus venue → court, schedule, booking,
   payment, facility, image ikut terhapus otomatis (integritas terjaga di DB,
   bukan di kode).
2. **Tidak ada tabel `slots`.** Slot (satu jam bookable) adalah konsep yang
   *dihitung*, bukan disimpan — ini keputusan ADR-0001 (bagian 5).

---

## 3. Alur End‑to‑End (Booking Lifecycle)

**Context.** Booking bukan sekadar "insert satu baris". Ada siklus hidup: dari niat
(pending) → bayar (paid) atau kedaluwarsa (expired) → bisa dibatalkan/refund
(cancelled). Ini inti ADR-0003 dan paling sering ditanya penguji.

**Yang dilakukan (alur lengkap):**

```mermaid
flowchart TD
    A[Customer register] --> B[Login -> session cookie + role]
    B --> C[Lihat venues /api/venues.php]
    C --> D[Lihat availability court /api/availability.php]
    D --> E[Buat booking /api/bookings.php]
    E -->|status=pending, kode PDL-0001| F{Bayar dalam 15 menit?}
    F -->|Ya| G[/api/payments.php -> status=paid + payment row]
    F -->|Tidak| H[expired - slot dilepas otomatis saat dibaca]
    G --> I[Download kuitansi PDF /api/receipt.php]
    G --> J{Batal dalam 5 menit?}
    J -->|Ya| K[/api/cancel.php -> refund, status=cancelled]
    J -->|Tidak| L[Terkunci - hanya admin bisa batalkan]
```

**Detail tiap transisi:**

- **Pending menahan slot.** Begitu booking dibuat, ia langsung "memesan" slotnya.
  Availability dan cek overlap hanya menghitung booking ber-status `pending`/`paid`.
  Ini menutup *race condition*: dua orang tidak bisa sama-sama membuat booking di
  slot yang sama lalu dua-duanya bayar.
- **Expired itu lazy (tanpa cron).** Karena tidak boleh pakai cron, booking `pending`
  yang lewat 15 menit di-sweep jadi `expired` **saat ada query availability/list
  dibaca** (`expireStalePendingBookings`). Jadi status di DB selalu jujur.
- **Refund window 5 menit.** Customer boleh batal sendiri (refund) dalam 5 menit
  setelah bayar. Lewat itu, terkunci — hanya admin yang bisa batalkan (admin selalu
  refund kalau booking-nya sudah paid).
- **Cancel itu soft.** Baris booking/payment **tidak dihapus**, hanya ganti status,
  supaya jejak (riwayat & catatan refund) tetap ada untuk panel admin.

---

## 4. Cara Mencegah Double‑Booking (overlap check)

**Context.** Karena tidak ada tabel `slots` dengan `UNIQUE(court, date, hour)`,
tidak ada constraint DB yang otomatis mencegah dua booking bertabrakan. Harus
dicek manual — dan harus aman dari akses bersamaan.

**Yang dilakukan.** `createBooking` (`lib/bookings.php`) membungkus cek + insert
dalam **satu transaksi** dan mengunci baris dengan `FOR UPDATE`:

```sql
SELECT id FROM bookings
WHERE court_id = ? AND date = ?
  AND start_hour < ? AND end_hour > ?        -- rumus overlap
  AND status IN ('pending','paid')
FOR UPDATE;
```

Rumus overlap: dua rentang `[s1,e1)` dan `[s2,e2)` bertabrakan jika
`s1 < e2 AND e1 > s2`. Kalau ada baris yang cocok → `BookingConflictException` (409).
Kalau kosong → insert booking + generate kode `PDL-0001`.

---

## 5. Harga Berbasis Schedule (derived, tidak disimpan)

**Context.** Awalnya tiap court pakai satu harga flat dari venue. ADR-0005
menambah **court_schedules**: tiap court bisa punya jam & harga berbeda per
day-band (`everyday`, `mon_fri`, `sat_sun`), dengan **fallback** — court tanpa
schedule tetap pakai grid 07:00–20:00 + harga flat venue.

**Yang dilakukan.**
- `bookableHoursForDate()` → jam mana saja yang bisa dipesan (union schedule yang
  cocok dengan tanggal, atau grid tetap kalau tak ada schedule).
- `priceForHour()` → harga satu jam (band spesifik mengalahkan `everyday`, fallback
  ke harga venue).
- `priceForRange()` → **satu rumah** untuk total harga rentang (jumlah `priceForHour`).
  Baik **quote** booking (`applySchedulePrice`) maupun **jumlah tagihan**
  (`payForBooking`) lewat fungsi ini, jadi harga yang ditampilkan dan yang ditagih
  tidak mungkin beda.

> **Cerita bagus untuk demo (menunjukkan paham invariant, bukan cuma CRUD):**
> sempat ada bug — `payForBooking` menagih harga flat venue padahal quote pakai
> jumlah schedule, sehingga court ber-schedule bisa salah tagih. Fix: tarik
> rumus ke `priceForRange` (satu interface), tambah regression test. (commit
> `e576a1d` + `362c21b`.)

---

## 6. CRUD — Apa yang Bisa Dibuat/Dibaca/Diubah/Dihapus

**Context.** Ada dua jenis pemakai: **Customer** (publik) dan **Admin** (di-seed,
global). CRUD dipisah: endpoint customer di `api/`, endpoint admin di `api/admin/`
dan dijaga `require_admin`.

### CRUD Customer

| Entity | Operasi | Endpoint | Catatan |
|---|---|---|---|
| User | **C**reate | `POST /api/register.php` | password di-hash `password_hash()` |
| Session | Create/Delete | `POST /api/login.php` · `logout.php` | session cookie + `role` |
| Venue | **R**ead | `GET /api/venues.php[?city=]` | filter kota |
| Availability | **R**ead | `GET /api/availability.php?venue_id=&date=` | dihitung dari bookings |
| Booking | **C**reate / **R**ead | `POST` / `GET /api/bookings.php` | hanya milik sendiri |
| Payment | **C**reate | `POST /api/payments.php` | settle booking pending |
| Refund | Update | `POST /api/cancel.php` | dalam window 5 menit |
| Receipt | **R**ead | `GET /api/receipt.php?booking_id=` | stream PDF |

### CRUD Admin (semua butuh session admin → 401/403)

| Entity | Operasi | Endpoint |
|---|---|---|
| Venue | C/R/U/D | `GET/POST/PUT/DELETE /api/admin/venues.php` |
| Venue (bundle) | C/U transaksional | `POST/PUT /api/admin/venue_save.php` |
| Court | C/R/U/D | `GET/POST/PUT/DELETE /api/admin/courts.php` |
| Schedule | R / **replace-all** | `GET/PUT /api/admin/schedules.php?court_id=` |
| Image | C (upload) | `POST /api/admin/upload.php` (multipart) |
| Booking (semua) | R (filter+paging) / D (soft-cancel) | `GET/DELETE /api/admin/bookings.php` |

**Dua pola CRUD yang perlu dijelaskan:**
- **Replace-all** untuk child rows (facilities, images, schedules): saat disimpan,
  baris lama di-`DELETE` lalu di-insert ulang, dalam satu transaksi. Lebih sederhana
  daripada diff per baris.
- **Bulk save transaksional** (`saveVenueBundle`): seluruh editor venue (data venue +
  facilities + galeri + courts + schedules) disimpan dalam **satu transaksi** — kalau
  satu baris gagal validasi, semua di-rollback.

---

## 7. Keamanan

**Context.** Penguji hampir pasti tanya soal keamanan. Siapkan 4 poin ini.

**Yang dilakukan.**
- **Password** disimpan hanya sebagai hash bcrypt (`password_hash`/`password_verify`),
  tidak pernah plain-text.
- **SQL injection** dicegah dengan **prepared statements** di semua query (PDO,
  `ATTR_EMULATE_PREPARES => false`).
- **Otorisasi**: `require_login()` (401 kalau belum login) dan `require_admin()`
  (403 kalau bukan admin) menjaga tiap endpoint. Login mengembalikan `role`.
- **Kredensial DB** di luar web root + bisa di-override env var (`config/db.php`),
  jadi tidak ada secret di dalam folder yang di-serve.

---

## 8. Testing & CI

**Context.** Karena logika ada di `lib/`, interface fungsi = permukaan test. Tidak
perlu menembak HTTP untuk menguji aturan domain.

**Yang dilakukan.**
- **114 PHPUnit test, semua hijau.** Jalankan live: `php phpunit.phar`.
- `tests/bootstrap.php` membangun DB **throwaway** `padelkuy_test` dari `schema.sql`
  sebelum test → tidak mengganggu DB dev.
- **CI GitHub Actions** (`.github/workflows/ci.yml`) menjalankan seluruh suite
  terhadap MySQL service di tiap push & PR.
- Test mencakup: auth, availability, booking, overlap, expiry, payment, refund,
  receipt, schedule, bulk save, upload, admin venue/court/booking.

---

## 9. Deploy

**Context.** Dideploy ke Railway pakai Docker. Apache sempat dipakai tapi
crash-loop ("More than one MPM loaded"), jadi pindah ke PHP built-in server.

**Yang dilakukan.**
- **Live:** https://web-production-97880.up.railway.app
- `Dockerfile` = `php:8.2-cli` menjalankan `php -S 0.0.0.0:$PORT -t public` →
  docroot tetap `public/`, `lib/`+`config/` aman.
- `config/db.php` membaca env `DB_HOST/DB_NAME/DB_USER/DB_PASS` yang dipetakan ke
  MySQL service Railway.
- **Redeploy manual**: `railway up --service web` (push git belum di-set auto-deploy).
- Perubahan schema di DB produksi yang sudah ada data → **migrasi additive** saja
  (`ALTER ... ADD`, `CREATE TABLE IF NOT EXISTS`), jangan re-run `schema.railway.sql`
  (itu DROP semua tabel).

---

## 10. Skrip Demo (urutan yang disarankan)

**Context.** Alur ini menunjukkan seluruh backend bekerja end-to-end dalam ±5 menit.

1. Tunjukkan **struktur folder** → tekankan `lib/`+`config/` di luar `public/`.
2. Buka **ERD** (`docs/erd.png`) → jelaskan relasi (bagian 2).
3. Jalankan **`php phpunit.phar`** → 114 hijau.
4. Demo alur customer: **register → login → pilih venue → availability → booking
   (pending, dapat kode PDL-xxxx) → bayar → riwayat → download kuitansi PDF →
   cancel/refund**.
5. Demo admin: **login admin → kelola venue/court/schedule → lihat semua booking →
   cancel (refund otomatis kalau paid)**.
6. Tunjukkan **cek overlap** (bagian 4): coba booking jam yang sama → ditolak 409.
7. (Bonus) Cerita **bug harga & fix `priceForRange`** (bagian 5) untuk menunjukkan
   pemahaman invariant domain.

---

## Lampiran — Daftar Singkat Modul `lib/`

| File | Tanggung jawab |
|---|---|
| `auth.php` | register, login, `is_admin` |
| `session.php` | `current_user_id`, `require_login`, `require_admin` |
| `http.php` | `send_json`, `send_error`, `read_body` |
| `venues.php` | query venue + facilities + images, validasi, CRUD |
| `courts.php` | query & CRUD court |
| `schedules.php` | parsing/validasi/replace schedule |
| `availability.php` | derive availability, `bookableHoursForDate`, `priceForHour`, `priceForRange`, lazy expiry |
| `bookings.php` | `createBooking` (overlap), list, format, harga |
| `payments.php` | `payForBooking`, refund customer & admin |
| `receipt.php` | generate PDF kuitansi (tanpa dependency) |
| `uploads.php` | validasi & simpan upload gambar |
| `venue_save.php` | `saveVenueBundle` (bulk transaksional) |
