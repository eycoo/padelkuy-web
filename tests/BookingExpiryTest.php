<?php

use PHPUnit\Framework\TestCase;

final class BookingExpiryTest extends TestCase
{
    private PDO $pdo;
    private string $date;

    protected function setUp(): void
    {
        $this->pdo = db();
        $this->date = date('Y-m-d', strtotime('+7 days'));
        $this->pdo->exec('DELETE FROM bookings');
        $this->pdo->exec('DELETE FROM courts');
        $this->pdo->exec('DELETE FROM venues');
        $this->pdo->exec('DELETE FROM users');

        $this->pdo->exec("INSERT INTO venues (id, name, city, price_per_hour) VALUES (1, 'Test Venue', 'Jakarta', 100000)");
        $this->pdo->exec("INSERT INTO courts (id, venue_id, label) VALUES (1, 1, 'A')");
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash) VALUES (1, 'U', 'u@e.com', 'x')");
    }

    private function ageBooking(int $id, int $minutes): void
    {
        $this->pdo->exec("UPDATE bookings SET created_at = NOW() - INTERVAL $minutes MINUTE WHERE id = $id");
    }

    public function test_stale_unpaid_pending_expires_and_frees_its_slot(): void
    {
        $id = createBooking($this->pdo, 1, 1, $this->date, 8, 10);
        $this->ageBooking($id, 16); // older than the 15-minute window

        $grid = getAvailability($this->pdo, 1, $this->date);
        $taken = array_filter($grid, fn($s) => $s['taken']);
        $this->assertCount(0, $taken, 'an expired pending booking must not hold its slots');

        $b = getBooking($this->pdo, $id);
        $this->assertSame('expired', $b['status'], 'the sweep materialises the status');
    }

    public function test_paid_booking_never_expires_however_old(): void
    {
        $id = createBooking($this->pdo, 1, 1, $this->date, 8, 10);
        $this->pdo->exec("UPDATE bookings SET status = 'paid' WHERE id = $id");
        $this->ageBooking($id, 120); // two hours old, but paid

        $grid = getAvailability($this->pdo, 1, $this->date);
        $taken = array_filter($grid, fn($s) => $s['taken']);
        $this->assertCount(2, $taken, 'a paid booking keeps holding its slots');
    }

    public function test_recent_unpaid_pending_still_holds_its_slot(): void
    {
        $id = createBooking($this->pdo, 1, 1, $this->date, 8, 10);
        $this->ageBooking($id, 5); // inside the 15-minute window

        $grid = getAvailability($this->pdo, 1, $this->date);
        $taken = array_filter($grid, fn($s) => $s['taken']);
        $this->assertCount(2, $taken);

        $b = getBooking($this->pdo, $id);
        $this->assertSame('pending', $b['status']);
    }

    public function test_expired_slot_can_be_rebooked(): void
    {
        $id = createBooking($this->pdo, 1, 1, $this->date, 8, 10);
        $this->ageBooking($id, 16);

        $new = createBooking($this->pdo, 1, 1, $this->date, 8, 10);
        $this->assertGreaterThan(0, $new, 'the freed range can be re-booked once expired');
    }
}
