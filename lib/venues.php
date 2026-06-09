<?php
// Venue queries. Kept here (not in the endpoint) so they are unit-testable.

// List venues, optionally filtered by city. price_per_hour is cast to int.
function listVenues(PDO $pdo, ?string $city = null): array
{
    if ($city !== null && $city !== '') {
        $stmt = $pdo->prepare(
            'SELECT id, name, city, price_per_hour, tag, image_path
             FROM venues WHERE city = ? ORDER BY name'
        );
        $stmt->execute([$city]);
    } else {
        $stmt = $pdo->query(
            'SELECT id, name, city, price_per_hour, tag, image_path
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

// Fetch one venue, or null if it does not exist.
function getVenue(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, name, city, price_per_hour, tag, image_path FROM venues WHERE id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $row['id'] = (int) $row['id'];
    $row['price_per_hour'] = (int) $row['price_per_hour'];
    return $row;
}

// Validate and normalise venue input. Throws InvalidArgumentException on bad
// input. Returns [name, city, price_per_hour, tag, image_path].
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

    $tag = isset($in['tag']) && $in['tag'] !== '' ? trim((string) $in['tag']) : null;
    $image = isset($in['image_path']) && $in['image_path'] !== '' ? trim((string) $in['image_path']) : null;

    return [$name, $city, $price, $tag, $image];
}

// Create a venue. Returns the new id.
function createVenue(PDO $pdo, array $in): int
{
    [$name, $city, $price, $tag, $image] = validateVenueInput($in);

    $stmt = $pdo->prepare(
        'INSERT INTO venues (name, city, price_per_hour, tag, image_path)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$name, $city, $price, $tag, $image]);
    return (int) $pdo->lastInsertId();
}

// Update a venue's fields. Returns true if a row was changed/matched.
function updateVenue(PDO $pdo, int $id, array $in): bool
{
    [$name, $city, $price, $tag, $image] = validateVenueInput($in);

    $stmt = $pdo->prepare(
        'UPDATE venues SET name = ?, city = ?, price_per_hour = ?, tag = ?, image_path = ?
         WHERE id = ?'
    );
    $stmt->execute([$name, $city, $price, $tag, $image, $id]);
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
