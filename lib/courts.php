<?php
// Court queries and admin management. Kept here (not in endpoints) so they are
// unit-testable.

// List a venue's courts, ordered by label. venue_id/id are cast to int.
function listCourts(PDO $pdo, int $venue_id): array
{
    $stmt = $pdo->prepare('SELECT id, venue_id, label FROM courts WHERE venue_id = ? ORDER BY label');
    $stmt->execute([$venue_id]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['id'] = (int) $r['id'];
        $r['venue_id'] = (int) $r['venue_id'];
    }
    return $rows;
}

// Add a court to a venue. Returns the new court id. Throws
// InvalidArgumentException on an empty label.
function createCourt(PDO $pdo, int $venue_id, string $label): int
{
    $label = trim($label);
    if ($label === '') {
        throw new InvalidArgumentException('Court label is required');
    }

    $stmt = $pdo->prepare('INSERT INTO courts (venue_id, label) VALUES (?, ?)');
    $stmt->execute([$venue_id, $label]);
    return (int) $pdo->lastInsertId();
}

// Delete a court (cascades to its bookings). Returns whether a row was removed.
function deleteCourt(PDO $pdo, int $court_id): bool
{
    $stmt = $pdo->prepare('DELETE FROM courts WHERE id = ?');
    $stmt->execute([$court_id]);
    return $stmt->rowCount() > 0;
}
