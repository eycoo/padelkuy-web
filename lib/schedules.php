<?php
// Court schedules (#47): per-court, per-day-band bookable hour ranges with a
// price. Each row covers [start_hour, end_hour) for a day band. These drive
// availability and pricing in availability.php (a court with no schedules falls
// back to the fixed grid + venue price — ADR-0001).

// FE day labels -> stored enum values.
const SCHEDULE_BANDS = ['everyday', 'mon_fri', 'sat_sun'];

// Normalise a day band from FE ("EVERYDAY", "MON-FRI", "SAT-SUN") or a stored
// value. Throws on anything else.
function normaliseBand(string $band): string
{
    $b = strtolower(str_replace('-', '_', trim($band)));
    if (!in_array($b, SCHEDULE_BANDS, true)) {
        throw new InvalidArgumentException("Unknown day band: $band");
    }
    return $b;
}

// Parse an hour from an int (7) or a whole-hour "HH:MM" string ("07:00").
// Rejects half hours and out-of-range values.
function parseHour($value): int
{
    if (is_int($value) || (is_string($value) && ctype_digit($value))) {
        $hour = (int) $value;
    } elseif (is_string($value) && preg_match('/^(\d{1,2}):(\d{2})$/', trim($value), $m)) {
        if ((int) $m[2] !== 0) {
            throw new InvalidArgumentException("Schedule hours must be on the hour: $value");
        }
        $hour = (int) $m[1];
    } else {
        throw new InvalidArgumentException("Invalid hour: " . var_export($value, true));
    }

    if ($hour < 0 || $hour > 24) {
        throw new InvalidArgumentException("Hour out of range: $hour");
    }
    return $hour;
}

// Validate and normalise one schedule row from the FE shape
// {day|day_band, start|start_hour, end|end_hour, price}.
function validateScheduleRow(array $row): array
{
    $band = normaliseBand((string) ($row['day_band'] ?? $row['day'] ?? ''));
    $start = parseHour($row['start_hour'] ?? $row['start'] ?? null);
    $end   = parseHour($row['end_hour'] ?? $row['end'] ?? null);
    $price = (int) ($row['price'] ?? 0);

    if ($start >= $end) {
        throw new InvalidArgumentException('Schedule end_hour must be after start_hour');
    }
    if ($price <= 0) {
        throw new InvalidArgumentException('Schedule price must be a positive integer');
    }

    return ['day_band' => $band, 'start_hour' => $start, 'end_hour' => $end, 'price' => $price];
}

// A court's schedule rows, ordered, with ints cast.
function listSchedules(PDO $pdo, int $court_id): array
{
    $stmt = $pdo->prepare(
        'SELECT id, court_id, day_band, start_hour, end_hour, price
         FROM court_schedules WHERE court_id = ? ORDER BY day_band, start_hour'
    );
    $stmt->execute([$court_id]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['id'] = (int) $r['id'];
        $r['court_id'] = (int) $r['court_id'];
        $r['start_hour'] = (int) $r['start_hour'];
        $r['end_hour'] = (int) $r['end_hour'];
        $r['price'] = (int) $r['price'];
    }
    return $rows;
}

// Replace a court's schedules with $rows (validated up front, so a bad row
// leaves the existing schedules untouched). All-or-nothing in a transaction.
function setSchedules(PDO $pdo, int $court_id, array $rows): void
{
    $clean = array_map('validateScheduleRow', $rows);

    $own = !$pdo->inTransaction();
    if ($own) {
        $pdo->beginTransaction();
    }
    try {
        $pdo->prepare('DELETE FROM court_schedules WHERE court_id = ?')->execute([$court_id]);
        $ins = $pdo->prepare(
            'INSERT INTO court_schedules (court_id, day_band, start_hour, end_hour, price)
             VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($clean as $r) {
            $ins->execute([$court_id, $r['day_band'], $r['start_hour'], $r['end_hour'], $r['price']]);
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
