<?php
// Availability is derived from bookings — there is no slots table (ADR-0001).
// Operating hours default to a fixed 07:00..20:00 grid, but a court may override
// both its bookable hours and pricing via court_schedules (#48).
require_once __DIR__ . '/schedules.php';

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

// All bookable start hours of a day (the fixed-grid fallback).
function operating_hours(): array
{
    return range(OPEN_HOUR, CLOSE_HOUR);
}

// True if a day band applies to a date. everyday always applies; mon_fri on
// Mon-Fri; sat_sun on Sat/Sun.
function bandMatchesDate(string $band, string $date): bool
{
    $w = (int) date('N', strtotime($date)); // 1=Mon .. 7=Sun
    return match ($band) {
        'mon_fri' => $w >= 1 && $w <= 5,
        'sat_sun' => $w >= 6,
        default   => true, // everyday
    };
}

// The bookable start hours for a court on a date. Driven by court_schedules
// when the court has any (union of the hours whose band matches the date);
// otherwise the fixed operating_hours() grid.
function bookableHoursForDate(PDO $pdo, int $court_id, string $date): array
{
    $schedules = listSchedules($pdo, $court_id);
    if ($schedules === []) {
        return operating_hours();
    }

    $hours = [];
    foreach ($schedules as $s) {
        if (!bandMatchesDate($s['day_band'], $date)) {
            continue;
        }
        for ($h = $s['start_hour']; $h < $s['end_hour']; $h++) {
            $hours[$h] = true;
        }
    }
    $hours = array_keys($hours);
    sort($hours);
    return $hours;
}

// The price of one hour on a court for a date: the matching schedule's price
// (a specific band beats everyday), falling back to the venue's flat
// price_per_hour when no schedule covers it.
function priceForHour(PDO $pdo, int $court_id, string $date, int $hour): int
{
    $best = null;
    $bestSpecificity = -1;
    foreach (listSchedules($pdo, $court_id) as $s) {
        if (!bandMatchesDate($s['day_band'], $date)) {
            continue;
        }
        if ($hour < $s['start_hour'] || $hour >= $s['end_hour']) {
            continue;
        }
        $specificity = $s['day_band'] === 'everyday' ? 0 : 1;
        if ($specificity > $bestSpecificity) {
            $best = (int) $s['price'];
            $bestSpecificity = $specificity;
        }
    }
    if ($best !== null) {
        return $best;
    }

    $stmt = $pdo->prepare(
        'SELECT v.price_per_hour FROM courts c JOIN venues v ON v.id = c.venue_id WHERE c.id = ?'
    );
    $stmt->execute([$court_id]);
    return (int) $stmt->fetchColumn();
}

// The price of a booked range [start, end) on a court for a date: the sum of
// its per-hour rates (#48). The single home for booking-price logic — the
// booking quote (applySchedulePrice) and the payment amount (payForBooking)
// both go through here, so a quote and the amount charged can't drift apart.
function priceForRange(PDO $pdo, int $court_id, string $date, int $start, int $end): int
{
    $sum = 0;
    for ($h = $start; $h < $end; $h++) {
        $sum += priceForHour($pdo, $court_id, $date, $h);
    }
    return $sum;
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
    foreach (bookableHoursForDate($pdo, $court_id, $date) as $hour) {
        $taken = false;
        foreach ($bookings as $b) {
            if ((int) $b['start_hour'] <= $hour && $hour < (int) $b['end_hour']) {
                $taken = true;
                break;
            }
        }
        $grid[] = [
            'hour'  => $hour,
            'taken' => $taken,
            'price' => priceForHour($pdo, $court_id, $date, $hour),
        ];
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
