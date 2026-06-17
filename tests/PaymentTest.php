<?php

use PHPUnit\Framework\TestCase;

final class PaymentTest extends TestCase
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

    public function test_paying_settles_the_booking_and_records_the_amount(): void
    {
        $bid = createBooking($this->pdo, 1, 1, $this->date, 18, 20); // 2h * 100000

        $payment = payForBooking($this->pdo, 1, $bid);

        $this->assertSame('paid', $payment['status']);
        $this->assertSame(200000, $payment['amount']);
        $this->assertSame('paid', getBooking($this->pdo, $bid)['status']);
    }

    public function test_amount_charged_matches_the_schedule_aware_quote(): void
    {
        // Court with a schedule priced above the flat venue rate (100000).
        $this->pdo->exec(
            "INSERT INTO court_schedules (court_id, day_band, start_hour, end_hour, price)
             VALUES (1, 'everyday', 7, 22, 250000)"
        );

        $bid = createBooking($this->pdo, 1, 1, $this->date, 18, 20); // 2h * 250000

        $payment = payForBooking($this->pdo, 1, $bid);

        // Charged the schedule price, not the flat venue rate (2 * 100000).
        $this->assertSame(500000, $payment['amount']);
        // ... and it agrees with the price the customer was quoted.
        $this->assertSame(getBooking($this->pdo, $bid)['price'], $payment['amount']);
    }

    public function test_only_the_owner_can_pay(): void
    {
        $bid = createBooking($this->pdo, 1, 1, $this->date, 18, 20); // Alice's

        $this->expectException(NotBookingOwnerException::class);
        payForBooking($this->pdo, 2, $bid); // Bob tries to pay
    }

    public function test_an_already_paid_booking_is_not_payable(): void
    {
        $bid = createBooking($this->pdo, 1, 1, $this->date, 18, 20);
        payForBooking($this->pdo, 1, $bid);

        $this->expectException(BookingNotPayableException::class);
        payForBooking($this->pdo, 1, $bid);
    }

    public function test_an_expired_booking_is_not_payable(): void
    {
        $bid = createBooking($this->pdo, 1, 1, $this->date, 18, 20);
        $this->pdo->exec("UPDATE bookings SET created_at = NOW() - INTERVAL 16 MINUTE WHERE id = $bid");

        $this->expectException(BookingNotPayableException::class);
        payForBooking($this->pdo, 1, $bid);
    }

    public function test_paying_a_missing_booking_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        payForBooking($this->pdo, 1, 999999);
    }
}
