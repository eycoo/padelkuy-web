<?php

use PHPUnit\Framework\TestCase;

final class CancelRefundTest extends TestCase
{
    private PDO $pdo;
    private string $date;

    protected function setUp(): void
    {
        $this->pdo = db();
        $this->date = date('Y-m-d', strtotime('+7 days'));
        $this->pdo->exec('DELETE FROM payments');
        $this->pdo->exec('DELETE FROM bookings');
        $this->pdo->exec('DELETE FROM courts');
        $this->pdo->exec('DELETE FROM venues');
        $this->pdo->exec('DELETE FROM users');

        $this->pdo->exec("INSERT INTO venues (id, name, city, price_per_hour) VALUES (1, 'Test Venue', 'Jakarta', 100000)");
        $this->pdo->exec("INSERT INTO courts (id, venue_id, label) VALUES (1, 1, 'A')");
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash) VALUES (1, 'Alice', 'a@e.com', 'x'), (2, 'Bob', 'b@e.com', 'x')");
    }

    private function paidBooking(int $user_id = 1): int
    {
        $bid = createBooking($this->pdo, $user_id, 1, $this->date, 8, 10);
        payForBooking($this->pdo, $user_id, $bid);
        return $bid;
    }

    private function agePayment(int $booking_id, int $minutes): void
    {
        $this->pdo->exec("UPDATE payments SET paid_at = NOW() - INTERVAL $minutes MINUTE WHERE booking_id = $booking_id");
    }

    public function test_owner_cancels_within_window_and_is_refunded(): void
    {
        $bid = $this->paidBooking();

        $payment = cancelOwnBookingWithRefund($this->pdo, 1, $bid);

        $this->assertSame('refunded', $payment['status']);
        $this->assertSame('cancelled', getBooking($this->pdo, $bid)['status']);

        $grid = getAvailability($this->pdo, 1, $this->date);
        $this->assertCount(0, array_filter($grid, fn($s) => $s['taken']), 'refund frees the slot');
    }

    public function test_after_the_window_the_booking_is_locked(): void
    {
        $bid = $this->paidBooking();
        $this->agePayment($bid, 6); // past the 5-minute window

        try {
            cancelOwnBookingWithRefund($this->pdo, 1, $bid);
            $this->fail('expected RefundWindowClosedException');
        } catch (RefundWindowClosedException $e) {
            // expected
        }

        $this->assertSame('paid', getBooking($this->pdo, $bid)['status'], 'booking is unchanged');
    }

    public function test_only_the_owner_can_self_cancel(): void
    {
        $bid = $this->paidBooking(1); // Alice's

        $this->expectException(NotBookingOwnerException::class);
        cancelOwnBookingWithRefund($this->pdo, 2, $bid); // Bob
    }

    public function test_an_unpaid_booking_cannot_be_refunded(): void
    {
        $bid = createBooking($this->pdo, 1, 1, $this->date, 8, 10); // pending, never paid

        $this->expectException(NotRefundableException::class);
        cancelOwnBookingWithRefund($this->pdo, 1, $bid);
    }
}
