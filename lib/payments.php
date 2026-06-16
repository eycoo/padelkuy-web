<?php
// Simulated payment for a booking (ADR-0003). Paying settles a `pending`
// booking — it records a Payment and moves the booking to `paid`. There is no
// real gateway. Refunds are handled by the cancel paths.
require_once __DIR__ . '/availability.php';
require_once __DIR__ . '/bookings.php';

class PaymentException extends RuntimeException {}
class NotBookingOwnerException extends PaymentException {}   // 403
class BookingNotPayableException extends PaymentException {} // 422

// Pay for a pending booking owned by $user_id. Returns the created Payment.
// Throws InvalidArgumentException if the booking is missing,
// NotBookingOwnerException if it belongs to someone else, and
// BookingNotPayableException if it is not in a payable (`pending`) state.
function payForBooking(PDO $pdo, int $user_id, int $booking_id): array
{
    // A stale hold is not payable — settle expiries first so status is current.
    expireStalePendingBookings($pdo);

    $stmt = $pdo->prepare(
        'SELECT b.id, b.user_id, b.status, b.start_hour, b.end_hour, v.price_per_hour
         FROM bookings b
         JOIN courts c ON c.id = b.court_id
         JOIN venues v ON v.id = c.venue_id
         WHERE b.id = ?'
    );
    $stmt->execute([$booking_id]);
    $b = $stmt->fetch();

    if (!$b) {
        throw new InvalidArgumentException('booking not found');
    }
    if ((int) $b['user_id'] !== $user_id) {
        throw new NotBookingOwnerException('not your booking');
    }
    if ($b['status'] !== 'pending') {
        throw new BookingNotPayableException('booking is not payable');
    }

    $amount = ((int) $b['end_hour'] - (int) $b['start_hour']) * (int) $b['price_per_hour'];

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE bookings SET status = 'paid' WHERE id = ?")
            ->execute([$booking_id]);
        $pdo->prepare(
            "INSERT INTO payments (booking_id, amount, status, paid_at)
             VALUES (?, ?, 'paid', NOW())"
        )->execute([$booking_id, $amount]);
        $payment_id = (int) $pdo->lastInsertId();
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return getPayment($pdo, $payment_id);
}

// Fetch a payment row in API shape.
function getPayment(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT id, booking_id, amount, status, paid_at, refunded_at FROM payments WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    return [
        'id'          => (int) $row['id'],
        'booking_id'  => (int) $row['booking_id'],
        'amount'      => (int) $row['amount'],
        'status'      => $row['status'],
        'paid_at'     => $row['paid_at'],
        'refunded_at' => $row['refunded_at'],
    ];
}
