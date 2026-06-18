<?php
// Venue queries. Kept here (not in the endpoint) so they are unit-testable.

// List venues, optionally filtered by city. price_per_hour is cast to int.
// Returns scalar venue columns only — facilities/images are loaded per-venue by
// getVenue (the detail view), not in the list.
function listVenues(PDO $pdo, ?string $city = null): array
{
    if ($city !== null && $city !== '') {
        $stmt = $pdo->prepare(
            'SELECT id, name, city, price_per_hour, tag, description, image_path, main_image_path
             FROM venues WHERE city = ? ORDER BY name'
        );
        $stmt->execute([$city]);
    } else {
        $stmt = $pdo->query(
            'SELECT id, name, city, price_per_hour, tag, description, image_path, main_image_path
             FROM venues ORDER BY name'
        );
    }

    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['id'] = (int) $r['id'];
        $r['price_per_hour'] = (int) $r['price_per_hour'];
    }
    return $rows;
}

// Fetch one venue with its facility chips and detail gallery, or null if it
// does not exist.
function getVenue(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, name, city, price_per_hour, tag, description, image_path, main_image_path, owner_id
         FROM venues WHERE id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $row['id'] = (int) $row['id'];
    $row['price_per_hour'] = (int) $row['price_per_hour'];
    $row['owner_id'] = $row['owner_id'] === null ? null : (int) $row['owner_id'];
    $row['facilities'] = listFacilities($pdo, $id);
    $row['images'] = listImages($pdo, $id);
    return $row;
}

// A venue's facility chips, in insertion order.
function listFacilities(PDO $pdo, int $venue_id): array
{
    $stmt = $pdo->prepare('SELECT name FROM venue_facilities WHERE venue_id = ? ORDER BY id');
    $stmt->execute([$venue_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Replace a venue's facilities with $names (trimmed, de-duplicated, blanks
// dropped) in one transaction.
function setFacilities(PDO $pdo, int $venue_id, array $names): void
{
    $clean = [];
    foreach ($names as $n) {
        $n = trim((string) $n);
        if ($n !== '' && !in_array($n, $clean, true)) {
            $clean[] = $n;
        }
    }

    $own = !$pdo->inTransaction();
    if ($own) {
        $pdo->beginTransaction();
    }
    try {
        $pdo->prepare('DELETE FROM venue_facilities WHERE venue_id = ?')->execute([$venue_id]);
        $ins = $pdo->prepare('INSERT INTO venue_facilities (venue_id, name) VALUES (?, ?)');
        foreach ($clean as $n) {
            $ins->execute([$venue_id, $n]);
        }
        if ($own) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

// A venue's detail-gallery image paths, ordered.
function listImages(PDO $pdo, int $venue_id): array
{
    $stmt = $pdo->prepare('SELECT image_path FROM venue_images WHERE venue_id = ? ORDER BY sort_order, id');
    $stmt->execute([$venue_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Replace a venue's detail gallery with $paths (trimmed, blanks dropped),
// numbering sort_order by position, in one transaction.
function setImages(PDO $pdo, int $venue_id, array $paths): void
{
    $clean = [];
    foreach ($paths as $p) {
        $p = trim((string) $p);
        if ($p !== '') {
            $clean[] = $p;
        }
    }

    $own = !$pdo->inTransaction();
    if ($own) {
        $pdo->beginTransaction();
    }
    try {
        $pdo->prepare('DELETE FROM venue_images WHERE venue_id = ?')->execute([$venue_id]);
        $ins = $pdo->prepare('INSERT INTO venue_images (venue_id, image_path, sort_order) VALUES (?, ?, ?)');
        foreach ($clean as $i => $p) {
            $ins->execute([$venue_id, $p, $i]);
        }
        if ($own) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

// Validate and normalise venue input. Throws InvalidArgumentException on bad
// input. Returns [name, city, price_per_hour, tag, description, image_path,
// main_image_path] — optional fields are null when absent/empty.
function validateVenueInput(array $in): array
{
    $name = trim((string) ($in['name'] ?? ''));
    $city = trim((string) ($in['city'] ?? ''));
    $price = (int) ($in['price_per_hour'] ?? 0);

    if ($name === '' || $city === '') {
        throw new InvalidArgumentException('Venue name and city are required');
    }
    if ($price <= 0) {
        throw new InvalidArgumentException('price_per_hour must be a positive integer');
    }

    $optional = fn (string $k) => isset($in[$k]) && trim((string) $in[$k]) !== '' ? trim((string) $in[$k]) : null;

    return [
        $name, $city, $price,
        $optional('tag'),
        $optional('description'),
        $optional('image_path'),
        $optional('main_image_path'),
    ];
}

// Create a venue, optionally owned by an admin (ADR-0006). Returns the new id.
function createVenue(PDO $pdo, array $in, ?int $owner_id = null): int
{
    [$name, $city, $price, $tag, $description, $image, $mainImage] = validateVenueInput($in);

    $stmt = $pdo->prepare(
        'INSERT INTO venues (name, city, price_per_hour, tag, description, image_path, main_image_path, owner_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$name, $city, $price, $tag, $description, $image, $mainImage, $owner_id]);
    return (int) $pdo->lastInsertId();
}

// Update a venue's fields. Returns true if a row was changed/matched.
function updateVenue(PDO $pdo, int $id, array $in): bool
{
    [$name, $city, $price, $tag, $description, $image, $mainImage] = validateVenueInput($in);

    $stmt = $pdo->prepare(
        'UPDATE venues SET name = ?, city = ?, price_per_hour = ?, tag = ?,
                           description = ?, image_path = ?, main_image_path = ?
         WHERE id = ?'
    );
    $stmt->execute([$name, $city, $price, $tag, $description, $image, $mainImage, $id]);
    return getVenue($pdo, $id) !== null;
}

// Delete a venue (cascades to its courts and bookings). Returns whether a row
// was removed.
function deleteVenue(PDO $pdo, int $id): bool
{
    $stmt = $pdo->prepare('DELETE FROM venues WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->rowCount() > 0;
}
