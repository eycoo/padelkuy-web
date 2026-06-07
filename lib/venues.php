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
