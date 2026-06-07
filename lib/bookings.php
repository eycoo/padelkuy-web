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
    if ($start >= $end) {
        throw new InvalidArgumentException('end_hour must be after start_hour');
    }
    if ($start < OPEN_HOUR || $end > CLOSE_HOUR + 1) {
        throw new InvalidArgumentException('booking is outside operating hours');
    }

    $pdo->beginTransaction();
    try {
        // Lock matching rows so a concurrent booking can't slip past the check.
        $stmt = $pdo->prepare(
            'SELECT id FROM bookings
             WHERE court_id = ? AND date = ? AND start_hour < ? AND end_hour > ?
             FOR UPDATE'
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
        'SELECT b.id, b.user_id, b.date, b.start_hour, b.end_hour,
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
    return formatBooking($row);
}

// All bookings owned by a user, newest date first, with labels and price.
function listUserBookings(PDO $pdo, int $user_id): array
{
    $stmt = $pdo->prepare(
        'SELECT b.id, b.user_id, b.date, b.start_hour, b.end_hour,
                c.label AS court_label, v.name AS venue_name, v.price_per_hour
         FROM bookings b
         JOIN courts c ON c.id = b.court_id
         JOIN venues v ON v.id = c.venue_id
         WHERE b.user_id = ?
         ORDER BY b.date DESC, b.start_hour ASC'
    );
    $stmt->execute([$user_id]);
    return array_map('formatBooking', $stmt->fetchAll());
}

// Shape a raw booking row into the API representation with a computed price.
function formatBooking(array $row): array
{
    $hours = (int) $row['end_hour'] - (int) $row['start_hour'];
    return [
        'id'         => (int) $row['id'],
        'venue_name' => $row['venue_name'],
        'court_label' => $row['court_label'],
        'date'       => $row['date'],
        'start_hour' => (int) $row['start_hour'],
        'end_hour'   => (int) $row['end_hour'],
        'hours'      => $hours,
        'price'      => $hours * (int) $row['price_per_hour'],
    ];
}
