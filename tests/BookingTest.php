<?php

use PHPUnit\Framework\TestCase;

final class BookingTest extends TestCase
{
    private PDO $pdo;
    private const DATE = '2026-06-10';

    protected function setUp(): void
    {
        $this->pdo = db();
        $this->pdo->exec('DELETE FROM bookings');
        $this->pdo->exec('DELETE FROM courts');
        $this->pdo->exec('DELETE FROM venues');
        $this->pdo->exec('DELETE FROM users');

        $this->pdo->exec("INSERT INTO venues (id, name, city, price_per_hour) VALUES (1, 'Test Venue', 'Jakarta', 100000)");
        $this->pdo->exec("INSERT INTO courts (id, venue_id, label) VALUES (1, 1, 'A')");
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash) VALUES (1, 'U', 'u@e.com', 'x')");
    }

    public function test_valid_booking_is_persisted_with_price(): void
    {
        $id = createBooking($this->pdo, 1, 1, self::DATE, 18, 20);
        $this->assertGreaterThan(0, $id);

        $b = getBooking($this->pdo, $id);
        $this->assertSame(2, $b['hours']);
        $this->assertSame(200000, $b['price']); // 2h * 100000
    }

    public function test_overlapping_range_is_rejected(): void
    {
        createBooking($this->pdo, 1, 1, self::DATE, 18, 20);

        $this->expectException(BookingConflictException::class);
        createBooking($this->pdo, 1, 1, self::DATE, 19, 21); // overlaps 19
    }

    public function test_adjacent_range_is_allowed(): void
    {
        createBooking($this->pdo, 1, 1, self::DATE, 16, 18);
        $id = createBooking($this->pdo, 1, 1, self::DATE, 18, 20); // starts exactly where prior ends
        $this->assertGreaterThan(0, $id);
    }

    public function test_rejects_end_before_start(): void
    {
        $this->expectException(InvalidArgumentException::class);
        createBooking($this->pdo, 1, 1, self::DATE, 20, 18);
    }

    public function test_rejects_outside_operating_hours(): void
    {
        $this->expectException(InvalidArgumentException::class);
        createBooking($this->pdo, 1, 1, self::DATE, 6, 8); // opens at 07:00
    }

    public function test_booked_hours_show_taken_in_availability(): void
    {
        createBooking($this->pdo, 1, 1, self::DATE, 18, 20);

        $grid = getAvailability($this->pdo, 1, self::DATE);
        $taken = array_map(fn($s) => $s['hour'], array_filter($grid, fn($s) => $s['taken']));
        $this->assertSame([18, 19], array_values($taken));
    }
}
