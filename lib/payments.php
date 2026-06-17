<?php
// Simulated payment for a booking (ADR-0003). Paying settles a `pending`
// booking — it records a Payment and moves the booking to `paid`. There is no
// real gateway. Refunds are handled by the cancel paths.
require_once __DIR__ . '/availability.php';
require_once __DIR__ . '/bookings.php';

class PaymentException extends RuntimeException {}
class NotBookingOwnerException extends PaymentException {}    // 403
class BookingNotPayableException extends PaymentException {}  // 422
class NotRefundableException extends PaymentException {}      // 422 (not a paid booking)
class RefundWindowClosedException extends PaymentException {} // 403 (past the window)

// A customer may self-cancel a paid booking only within this many minutes of
// paying (ADR-0003). After that it is locked to the customer; admins override.
const REFUND_WINDOW_MINUTES = 5;

// Pay for a pending booking owned by $user_id. Returns the created Payment.
// Throws InvalidArgumentException if the booking is missing,
// NotBookingOwnerException if it belongs to someone else, and
// BookingNotPayableException if it is not in a payable (`pending`) state.
function payForBooking(PDO $pdo, int $user_id, int $booking_id): array
{
    // A stale hold is not payable — settle expiries first so status is current.
    expireStalePendingBookings($pdo);

    $stmt = $pdo->prepare(
        'SELECT b.id, b.user_id, b.status, b.court_id, b.date, b.start_hour, b.end_hour
         FROM bookings b
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

    // Charge the same schedule-aware price the customer was quoted (#48): sum of
    // per-hour rates, not the flat venue rate (a court with schedules can price
    // an hour differently from venue.price_per_hour).
    $amount = 0;
    for ($h = (int) $b['start_hour']; $h < (int) $b['end_hour']; $h++) {
        $amount += priceForHour($pdo, (int) $b['court_id'], (string) $b['date'], $h);
    }

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

// Customer self-cancel of their own paid booking, refunding it, but only
// within the refund window (ADR-0003). Cancels the booking, refunds the
// payment, and frees the slots. Throws NotBookingOwnerException for someone
// else's booking, NotRefundableException if it isn't a paid booking, and
// RefundWindowClosedException once the window has passed.
function cancelOwnBookingWithRefund(PDO $pdo, int $user_id, int $booking_id): array
{
    $stmt = $pdo->prepare(
        "SELECT b.user_id, b.status AS booking_status,
                p.id AS payment_id,
                (p.paid_at < (NOW() - INTERVAL " . REFUND_WINDOW_MINUTES . " MINUTE)) AS window_closed
         FROM bookings b
         LEFT JOIN payments p ON p.booking_id = b.id
         WHERE b.id = ?"
    );
    $stmt->execute([$booking_id]);
    $row = $stmt->fetch();

    if (!$row) {
        throw new InvalidArgumentException('booking not found');
    }
    if ((int) $row['user_id'] !== $user_id) {
        throw new NotBookingOwnerException('not your booking');
    }
    if ($row['booking_status'] !== 'paid' || $row['payment_id'] === null) {
        throw new NotRefundableException('booking is not a paid booking');
    }
    if ((int) $row['window_closed'] === 1) {
        throw new RefundWindowClosedException('refund window has closed');
    }

    return refundBookingPayment($pdo, $booking_id, (int) $row['payment_id']);
}

// Admin override: cancel any booking at any time (no refund window). If the
// booking was paid, its payment is refunded; otherwise it is a plain
// soft-cancel. Returns false if the booking is missing or already cancelled.
function adminCancelBooking(PDO $pdo, int $booking_id): bool
{
    $stmt = $pdo->prepare(
        "SELECT b.status AS booking_status, p.id AS payment_id, p.status AS payment_status
         FROM bookings b
         LEFT JOIN payments p ON p.booking_id = b.id
         WHERE b.id = ?"
    );
    $stmt->execute([$booking_id]);
    $row = $stmt->fetch();

    if (!$row) {
        return false;
    }
    if ($row['booking_status'] === 'paid' && $row['payment_id'] !== null && $row['payment_status'] === 'paid') {
        refundBookingPayment($pdo, $booking_id, (int) $row['payment_id']);
        return true;
    }
    return cancelBooking($pdo, $booking_id);
}

// Cancel a booking and refund its payment in one transaction. Shared by the
// customer refund path and the admin override.
function refundBookingPayment(PDO $pdo, int $booking_id, int $payment_id): array
{
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?")
            ->execute([$booking_id]);
        $pdo->prepare("UPDATE payments SET status = 'refunded', refunded_at = NOW() WHERE id = ?")
            ->execute([$payment_id]);
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
