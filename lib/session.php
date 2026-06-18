<?php
// Session helpers for reading the logged-in user.

// Return the logged-in user's id, or null if no active session.
function current_user_id(): ?int
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

// Send a 401 and stop if no user is logged in; otherwise return the user id.
function require_login(): int
{
    $id = current_user_id();
    if ($id === null) {
        send_error('Authentication required', 401);
        exit;
    }
    return $id;
}

// Require an admin: 401 if not logged in, 403 if logged in but not an admin.
// Returns the admin user's id.
function require_admin(PDO $pdo): int
{
    $id = require_login();
    if (!is_admin($pdo, $id)) {
        send_error('Admin access required', 403);
        exit;
    }
    return $id;
}

// Require an admin who owns venue $venue_id (ADR-0006): 401/403 by role, then
// 403 if the venue isn't theirs. Returns the admin id. Needs lib/ownership.php.
function require_venue_owner(PDO $pdo, int $venue_id): int
{
    $id = require_admin($pdo);
    if (!ownsVenue($pdo, $id, $venue_id)) {
        send_error('Not your venue', 403);
        exit;
    }
    return $id;
}

// Like require_venue_owner but for a court (checks court -> venue ownership).
function require_court_owner(PDO $pdo, int $court_id): int
{
    $id = require_admin($pdo);
    if (!ownsCourt($pdo, $id, $court_id)) {
        send_error('Not your court', 403);
        exit;
    }
    return $id;
}

// Like require_venue_owner but for a booking (checks booking -> venue ownership).
function require_booking_owner(PDO $pdo, int $booking_id): int
{
    $id = require_admin($pdo);
    if (!ownsBooking($pdo, $id, $booking_id)) {
        send_error('Not your booking', 403);
        exit;
    }
    return $id;
}
