<?php
// Bulk venue save (#50): persist the whole admin editor — venue scalar fields,
// facility chips, the detail gallery, courts and each court's schedules — in a
// single transaction. Any validation error rolls the lot back.
require_once __DIR__ . '/venues.php';
require_once __DIR__ . '/courts.php';
require_once __DIR__ . '/schedules.php';

// Create ($id === null) or update a venue and replace all of its nested data
// from $bundle. Returns the saved venue (getVenue shape) with a nested
// `courts` array, each court carrying its `schedules`.
function saveVenueBundle(PDO $pdo, ?int $id, array $bundle): array
{
    $pdo->beginTransaction();
    try {
        if ($id === null) {
            $id = createVenue($pdo, $bundle);
        } elseif (!updateVenue($pdo, $id, $bundle)) {
            throw new InvalidArgumentException('Venue not found');
        }

        setFacilities($pdo, $id, $bundle['facilities'] ?? []);
        setImages($pdo, $id, $bundle['images'] ?? []);

        $existing = array_map('intval', array_column(listCourts($pdo, $id), 'id'));
        $keep = [];

        foreach ($bundle['courts'] ?? [] as $c) {
            $label = (string) ($c['label'] ?? '');
            $type  = (string) ($c['type'] ?? 'indoor');
            $cid   = (int) ($c['id'] ?? 0);

            if ($cid > 0 && in_array($cid, $existing, true)) {
                updateCourt($pdo, $cid, $label, $type);
            } else {
                $cid = createCourt($pdo, $id, $label, $type);
            }
            setSchedules($pdo, $cid, $c['schedules'] ?? []);
            $keep[] = $cid;
        }

        foreach ($existing as $old) {
            if (!in_array($old, $keep, true)) {
                deleteCourt($pdo, $old);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    $venue = getVenue($pdo, $id);
    $venue['courts'] = array_map(
        fn (array $c) => $c + ['schedules' => listSchedules($pdo, (int) $c['id'])],
        listCourts($pdo, $id)
    );
    return $venue;
}
