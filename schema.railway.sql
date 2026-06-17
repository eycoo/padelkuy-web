-- PadelKuy schema for managed hosts (Railway/PlanetScale/etc) where the
-- database already exists and its name is fixed by the provider. Same tables as
-- schema.sql but WITHOUT `CREATE DATABASE` / `USE` — import into the provider's
-- DB and point DB_NAME at it. See ADR-0001 (no slots table) and ADR-0003.

DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS courts;
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
  id             INT AUTO_INCREMENT PRIMARY KEY,
  name           VARCHAR(150) NOT NULL,
  city           VARCHAR(100) NOT NULL,
  price_per_hour INT          NOT NULL,           -- rupiah per hour, e.g. 180000
  tag            VARCHAR(255),
  image_path     VARCHAR(255)
);

CREATE TABLE courts (
  id       INT AUTO_INCREMENT PRIMARY KEY,
  venue_id INT         NOT NULL,
  label    VARCHAR(10) NOT NULL,                  -- A, B, C
  FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE CASCADE
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
