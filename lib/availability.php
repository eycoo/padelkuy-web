<?php
// Availability is derived from bookings — there is no slots table (ADR-0001).
// Operating hours are fixed: bookable start hours run 07:00..20:00.

const OPEN_HOUR  = 7;   // first bookable start hour
const CLOSE_HOUR = 20;  // last bookable start hour (the 20:00 slot covers 20-21)

// An unpaid booking is held for this long before it expires (ADR-0003).
const PENDING_EXPIRY_MINUTES = 15;

// Lazy expiry (ADR-0003): there is no cron, so unpaid `pending` bookings older
// than the window are swept to `expired` before any availability or booking
// read, keeping the stored status truthful. Paid bookings never expire.
function expireStalePendingBookings(PDO $pdo): void
{
    $pdo->exec(
        "UPDATE bookings SET status = 'expired'
         WHERE status = 'pending'
           AND created_at < (NOW() - INTERVAL " . PENDING_EXPIRY_MINUTES . " MINUTE)"
    );
}

// All bookable start hours of a day.
function operating_hours(): array
{
    return range(OPEN_HOUR, CLOSE_HOUR);
}

// The hour grid for one court on one date: each hour flagged taken if any
// booking on that court and date covers it (start_hour <= hour < end_hour).
function getAvailability(PDO $pdo, int $court_id, string $date): array
{
    expireStalePendingBookings($pdo);

    // Only active bookings hold a slot (ADR-0003); cancelled/expired do not.
    $stmt = $pdo->prepare(
        "SELECT start_hour, end_hour FROM bookings
         WHERE court_id = ? AND date = ? AND status IN ('pending','paid')"
    );
    $stmt->execute([$court_id, $date]);
    $bookings = $stmt->fetchAll();

    $grid = [];
    foreach (operating_hours() as $hour) {
        $taken = false;
        foreach ($bookings as $b) {
            if ((int) $b['start_hour'] <= $hour && $hour < (int) $b['end_hour']) {
                $taken = true;
                break;
            }
        }
        $grid[] = ['hour' => $hour, 'taken' => $taken];
    }
    return $grid;
}

// Availability for every court of a venue on a date.
function getVenueAvailability(PDO $pdo, int $venue_id, string $date): array
{
    $stmt = $pdo->prepare('SELECT id, label FROM courts WHERE venue_id = ? ORDER BY label');
    $stmt->execute([$venue_id]);
    $courts = $stmt->fetchAll();

    $result = [];
    foreach ($courts as $court) {
        $result[] = [
            'id'    => (int) $court['id'],
            'label' => $court['label'],
            'slots' => getAvailability($pdo, (int) $court['id'], $date),
        ];
    }
    return $result;
}
