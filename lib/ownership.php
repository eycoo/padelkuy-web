<?php
// Venue ownership predicates (ADR-0006). An admin owns exactly one venue
// (venues.owner_id). These are pure booleans — the HTTP 403 guards that use
// them live in lib/session.php. Kept here (not in endpoints) so they are
// unit-testable.

// The id of the venue owned by $user_id, or null if they own none.
function ownedVenueId(PDO $pdo, int $user_id): ?int
{
    $stmt = $pdo->prepare('SELECT id FROM venues WHERE owner_id = ? LIMIT 1');
    $stmt->execute([$user_id]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int) $id;
}

// True if $user_id owns venue $venue_id.
function ownsVenue(PDO $pdo, int $user_id, int $venue_id): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM venues WHERE id = ? AND owner_id = ?');
    $stmt->execute([$venue_id, $user_id]);
    return (bool) $stmt->fetchColumn();
}

// True if $user_id owns the venue that court $court_id belongs to.
function ownsCourt(PDO $pdo, int $user_id, int $court_id): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM courts c
         JOIN venues v ON v.id = c.venue_id
         WHERE c.id = ? AND v.owner_id = ?'
    );
    $stmt->execute([$court_id, $user_id]);
    return (bool) $stmt->fetchColumn();
}

// True if $user_id owns the venue that booking $booking_id falls under.
function ownsBooking(PDO $pdo, int $user_id, int $booking_id): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM bookings b
         JOIN courts c ON c.id = b.court_id
         JOIN venues v ON v.id = c.venue_id
         WHERE b.id = ? AND v.owner_id = ?'
    );
    $stmt->execute([$booking_id, $user_id]);
    return (bool) $stmt->fetchColumn();
}
