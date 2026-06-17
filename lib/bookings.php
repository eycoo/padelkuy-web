<?php
// Booking creation with overlap protection (ADR-0001). A booking covers a
// contiguous hour range [start_hour, end_hour); two bookings on the same court
// and date conflict when start_hour < other_end AND end_hour > other_start.
require_once __DIR__ . '/availability.php';

class BookingConflictException extends RuntimeException {}

// Create a booking. Returns the new booking id (with computed details via
// getBooking). Throws InvalidArgumentException on a bad range,
// BookingConflictException if it overlaps an existing booking.
function createBooking(PDO $pdo, int $user_id, int $court_id, string $date, int $start, int $end): int
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new InvalidArgumentException('date must be YYYY-MM-DD');
    }
    if ($date < date('Y-m-d')) {
        throw new InvalidArgumentException('cannot book a date in the past');
    }
    if ($start >= $end) {
        throw new InvalidArgumentException('end_hour must be after start_hour');
    }
    if ($start < OPEN_HOUR || $end > CLOSE_HOUR + 1) {
        throw new InvalidArgumentException('booking is outside operating hours');
    }

    // Every hour must be bookable for this court/date (schedule-aware; falls
    // back to the fixed grid when the court has no schedules — #48).
    $bookable = bookableHoursForDate($pdo, $court_id, $date);
    for ($h = $start; $h < $end; $h++) {
        if (!in_array($h, $bookable, true)) {
            throw new InvalidArgumentException('booking includes an hour the court is not open');
        }
    }

    // Free any expired holds so a stale pending booking can't block a new one.
    expireStalePendingBookings($pdo);

    $pdo->beginTransaction();
    try {
        // Lock matching rows so a concurrent booking can't slip past the check.
        $stmt = $pdo->prepare(
            "SELECT id FROM bookings
             WHERE court_id = ? AND date = ? AND start_hour < ? AND end_hour > ?
               AND status IN ('pending','paid')
             FOR UPDATE"
        );
        $stmt->execute([$court_id, $date, $end, $start]);

        if ($stmt->fetch()) {
            $pdo->rollBack();
            throw new BookingConflictException('That time range is already booked');
        }

        $ins = $pdo->prepare(
            'INSERT INTO bookings (court_id, user_id, date, start_hour, end_hour)
             VALUES (?, ?, ?, ?, ?)'
        );
        $ins->execute([$court_id, $user_id, $date, $start, $end]);
        $id = (int) $pdo->lastInsertId();

        // Booking code is derived from the id (unique by construction).
        $pdo->prepare('UPDATE bookings SET code = ? WHERE id = ?')
            ->execute([sprintf('PDL-%04d', $id), $id]);

        $pdo->commit();
        return $id;
    } catch (BookingConflictException $e) {
        throw $e;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

// Fetch a booking with venue/court labels and computed price.
function getBooking(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT b.id, b.court_id, b.user_id, b.date, b.start_hour, b.end_hour, b.status, b.code,
                c.label AS court_label, v.name AS venue_name, v.price_per_hour
         FROM bookings b
         JOIN courts c ON c.id = b.court_id
         JOIN venues v ON v.id = c.venue_id
         WHERE b.id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    return applySchedulePrice($pdo, formatBooking($row));
}

// All bookings owned by a user, newest date first, with labels and price.
function listUserBookings(PDO $pdo, int $user_id): array
{
    expireStalePendingBookings($pdo);

    $stmt = $pdo->prepare(
        'SELECT b.id, b.court_id, b.user_id, b.date, b.start_hour, b.end_hour, b.status, b.code,
                c.label AS court_label, v.name AS venue_name, v.price_per_hour
         FROM bookings b
         JOIN courts c ON c.id = b.court_id
         JOIN venues v ON v.id = c.venue_id
         WHERE b.user_id = ?
         ORDER BY b.date DESC, b.start_hour ASC'
    );
    $stmt->execute([$user_id]);
    return array_map(fn ($row) => applySchedulePrice($pdo, formatBooking($row)), $stmt->fetchAll());
}

// Build the shared WHERE clause + args for the admin booking views from
// 'venue_id' (int), 'date' (YYYY-MM-DD) and 'status' filters.
function bookingFilterClause(array $filters): array
{
    $where = [];
    $args  = [];

    if (!empty($filters['venue_id'])) {
        $where[] = 'v.id = ?';
        $args[]  = (int) $filters['venue_id'];
    }
    if (!empty($filters['date'])) {
        $where[] = 'b.date = ?';
        $args[]  = (string) $filters['date'];
    }
    if (!empty($filters['status'])) {
        $where[] = 'b.status = ?';
        $args[]  = (string) $filters['status'];
    }

    return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $args];
}

// Total bookings matching the filters, ignoring limit/offset — for the pager.
function countAllBookings(PDO $pdo, array $filters = []): int
{
    expireStalePendingBookings($pdo);
    [$clause, $args] = bookingFilterClause($filters);

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM bookings b
         JOIN courts c ON c.id = b.court_id
         JOIN venues v ON v.id = c.venue_id
         $clause"
    );
    $stmt->execute($args);
    return (int) $stmt->fetchColumn();
}

// Admin view: every booking across all users, newest date first, with the
// booker's name. Optional filters: 'venue_id' (int), 'date' (YYYY-MM-DD),
// 'status'. Optional paging: 'limit' (1..100) + 'offset'.
function listAllBookings(PDO $pdo, array $filters = []): array
{
    expireStalePendingBookings($pdo);

    [$clause, $args] = bookingFilterClause($filters);

    // Sanitised ints, inlined (LIMIT/OFFSET placeholders are awkward under PDO).
    $paging = '';
    if (isset($filters['limit'])) {
        $limit  = max(1, min(100, (int) $filters['limit']));
        $offset = max(0, (int) ($filters['offset'] ?? 0));
        $paging = " LIMIT $limit OFFSET $offset";
    }

    $stmt = $pdo->prepare(
        "SELECT b.id, b.court_id, b.user_id, b.date, b.start_hour, b.end_hour, b.status, b.code,
                c.label AS court_label, v.name AS venue_name, v.price_per_hour,
                u.name AS user_name, p.status AS payment_status
         FROM bookings b
         JOIN courts c ON c.id = b.court_id
         JOIN venues v ON v.id = c.venue_id
         JOIN users  u ON u.id = b.user_id
         LEFT JOIN payments p ON p.booking_id = b.id
         $clause
         ORDER BY b.date DESC, b.start_hour ASC$paging"
    );
    $stmt->execute($args);

    return array_map(function (array $row) use ($pdo) {
        $out = applySchedulePrice($pdo, formatBooking($row));
        $out['user_name'] = $row['user_name'];
        $out['payment_status'] = $row['payment_status'];
        return $out;
    }, $stmt->fetchAll());
}

// Cancel a booking with a soft status change (ADR-0003) — the row persists so
// the admin view and any refund record survive. A cancelled booking no longer
// holds its slots (see the active-status filter in availability/overlap).
// Returns whether a booking moved to cancelled (false if missing or already so).
function cancelBooking(PDO $pdo, int $id): bool
{
    $stmt = $pdo->prepare(
        "UPDATE bookings SET status = 'cancelled' WHERE id = ? AND status <> 'cancelled'"
    );
    $stmt->execute([$id]);
    return $stmt->rowCount() > 0;
}

// Shape a raw booking row into the API representation with a flat computed
// price (venue rate). applySchedulePrice refines this when schedules apply.
function formatBooking(array $row): array
{
    $hours = (int) $row['end_hour'] - (int) $row['start_hour'];
    return [
        'id'         => (int) $row['id'],
        'court_id'   => isset($row['court_id']) ? (int) $row['court_id'] : null,
        'code'       => $row['code'] ?? null,
        'status'     => $row['status'] ?? null,
        'venue_name' => $row['venue_name'],
        'court_label' => $row['court_label'],
        'date'       => $row['date'],
        'start_hour' => (int) $row['start_hour'],
        'end_hour'   => (int) $row['end_hour'],
        'hours'      => $hours,
        'price'      => $hours * (int) $row['price_per_hour'],
    ];
}

// Recompute a booking's price as the sum of its per-hour rates (#48). For a
// court with no schedules priceForHour returns the flat venue rate, so this
// leaves fallback bookings unchanged.
function applySchedulePrice(PDO $pdo, array $b): array
{
    if (empty($b['court_id'])) {
        return $b;
    }
    $b['price'] = priceForRange($pdo, (int) $b['court_id'], (string) $b['date'], (int) $b['start_hour'], (int) $b['end_hour']);
    return $b;
}
