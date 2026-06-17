-- PadelKuy schema. Native MySQL. See ADR-0001: no slots table; availability
-- is derived from bookings (contiguous hour ranges).

CREATE DATABASE IF NOT EXISTS padelkuy
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE padelkuy;

DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS court_schedules;
DROP TABLE IF EXISTS courts;
DROP TABLE IF EXISTS venue_images;
DROP TABLE IF EXISTS venue_facilities;
DROP TABLE IF EXISTS venues;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100)  NOT NULL,
  email         VARCHAR(255)  NOT NULL UNIQUE,
  password_hash VARCHAR(255)  NOT NULL,
  role          ENUM('user','admin') NOT NULL DEFAULT 'user',
  created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE venues (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  name            VARCHAR(150) NOT NULL,
  city            VARCHAR(100) NOT NULL,
  price_per_hour  INT          NOT NULL,          -- rupiah per hour, e.g. 180000
  tag             VARCHAR(255),
  description     TEXT,                           -- the "About" blurb (admin editor)
  image_path      VARCHAR(255),                   -- thumbnail (customer venue cards)
  main_image_path VARCHAR(255)                    -- hero image on the detail page
);

-- A venue's facility chips (Shower, Parking, ...). Many per venue.
CREATE TABLE venue_facilities (
  id       INT AUTO_INCREMENT PRIMARY KEY,
  venue_id INT          NOT NULL,
  name     VARCHAR(100) NOT NULL,
  FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE CASCADE
);

-- A venue's detail-gallery images, ordered. Thumbnail/main live on venues.
CREATE TABLE venue_images (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  venue_id   INT          NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  sort_order INT          NOT NULL DEFAULT 0,
  FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE CASCADE
);

CREATE TABLE courts (
  id       INT AUTO_INCREMENT PRIMARY KEY,
  venue_id INT          NOT NULL,
  label    VARCHAR(100) NOT NULL,                 -- court name, e.g. "Lapangan 1" / A
  type     ENUM('indoor','outdoor') NOT NULL DEFAULT 'indoor',
  FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE CASCADE
);

-- Per-court bookable hours + price, grouped by day band (ADR-0001 fallback: a
-- court with no schedules uses the fixed 07:00-20:00 grid and the venue price).
-- A row covers the hour range [start_hour, end_hour) for the given band.
CREATE TABLE court_schedules (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  court_id   INT NOT NULL,
  day_band   ENUM('everyday','mon_fri','sat_sun') NOT NULL DEFAULT 'everyday',
  start_hour INT NOT NULL,
  end_hour   INT NOT NULL,                        -- exclusive end, > start_hour
  price      INT NOT NULL,                        -- rupiah per hour for this band
  FOREIGN KEY (court_id) REFERENCES courts(id) ON DELETE CASCADE
);

-- A booking covers a contiguous hour range [start_hour, end_hour) on one court
-- and date. No UNIQUE(court_id,date,hour) — conflicts are caught by an overlap
-- check at insert time (see ADR-0001).
CREATE TABLE bookings (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  court_id   INT       NOT NULL,
  user_id    INT       NOT NULL,
  date       DATE      NOT NULL,
  start_hour INT       NOT NULL,                  -- 7..20, inclusive start
  end_hour   INT       NOT NULL,                  -- exclusive end, > start_hour
  status     ENUM('pending','paid','expired','cancelled') NOT NULL DEFAULT 'pending',
  code       VARCHAR(20) UNIQUE,                  -- human-readable ref, e.g. PDL-0001
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (court_id) REFERENCES courts(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
  INDEX idx_court_date (court_id, date)
);

-- A simulated money record, one per booking (ADR-0003). Created when a booking
-- is paid; moves to 'refunded' if the booking is cancelled within the window.
CREATE TABLE payments (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  booking_id  INT NOT NULL UNIQUE,
  amount      INT NOT NULL,                    -- rupiah, = booking price
  status      ENUM('paid','refunded') NOT NULL DEFAULT 'paid',
  paid_at     TIMESTAMP NULL,
  refunded_at TIMESTAMP NULL,
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
);
