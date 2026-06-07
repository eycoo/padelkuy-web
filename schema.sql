-- PadelKuy schema. Native MySQL. See ADR-0001: no slots table; availability
-- is derived from bookings (contiguous hour ranges).

CREATE DATABASE IF NOT EXISTS padelkuy
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE padelkuy;

DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS courts;
DROP TABLE IF EXISTS venues;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100)  NOT NULL,
  email         VARCHAR(255)  NOT NULL UNIQUE,
  password_hash VARCHAR(255)  NOT NULL,
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
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (court_id) REFERENCES courts(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
  INDEX idx_court_date (court_id, date)
);
