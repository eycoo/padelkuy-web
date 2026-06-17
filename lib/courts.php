<?php
// Court queries and admin management. Kept here (not in endpoints) so they are
// unit-testable.

const COURT_TYPES = ['indoor', 'outdoor'];

// List a venue's courts, ordered by label. venue_id/id are cast to int.
function listCourts(PDO $pdo, int $venue_id): array
{
    $stmt = $pdo->prepare('SELECT id, venue_id, label, type FROM courts WHERE venue_id = ? ORDER BY label');
    $stmt->execute([$venue_id]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['id'] = (int) $r['id'];
        $r['venue_id'] = (int) $r['venue_id'];
    }
    return $rows;
}

// Validate a court label + type. Returns [label, type]. Throws
// InvalidArgumentException on an empty label or unknown type.
function validateCourtInput(string $label, string $type): array
{
    $label = trim($label);
    if ($label === '') {
        throw new InvalidArgumentException('Court label is required');
    }
    if (!in_array($type, COURT_TYPES, true)) {
        throw new InvalidArgumentException('Court type must be indoor or outdoor');
    }
    return [$label, $type];
}

// Add a court to a venue. Returns the new court id. Throws
// InvalidArgumentException on an empty label or unknown type.
function createCourt(PDO $pdo, int $venue_id, string $label, string $type = 'indoor'): int
{
    [$label, $type] = validateCourtInput($label, $type);

    $stmt = $pdo->prepare('INSERT INTO courts (venue_id, label, type) VALUES (?, ?, ?)');
    $stmt->execute([$venue_id, $label, $type]);
    return (int) $pdo->lastInsertId();
}

// Update a court's label + type. Returns whether the court exists.
function updateCourt(PDO $pdo, int $id, string $label, string $type = 'indoor'): bool
{
    [$label, $type] = validateCourtInput($label, $type);

    $stmt = $pdo->prepare('UPDATE courts SET label = ?, type = ? WHERE id = ?');
    $stmt->execute([$label, $type, $id]);

    $exists = $pdo->prepare('SELECT 1 FROM courts WHERE id = ?');
    $exists->execute([$id]);
    return (bool) $exists->fetchColumn();
}

// Delete a court (cascades to its bookings). Returns whether a row was removed.
function deleteCourt(PDO $pdo, int $court_id): bool
{
    $stmt = $pdo->prepare('DELETE FROM courts WHERE id = ?');
    $stmt->execute([$court_id]);
    return $stmt->rowCount() > 0;
}
