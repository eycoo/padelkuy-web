<?php

use PHPUnit\Framework\TestCase;

final class BookingTest extends TestCase
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

    public function test_valid_booking_is_persisted_with_price(): void
    {
        $id = createBooking($this->pdo, 1, 1, $this->date, 18, 20);
        $this->assertGreaterThan(0, $id);

        $b = getBooking($this->pdo, $id);
        $this->assertSame(2, $b['hours']);
        $this->assertSame(200000, $b['price']); // 2h * 100000
    }

    public function test_new_booking_is_pending_with_a_code(): void
    {
        $id = createBooking($this->pdo, 1, 1, $this->date, 18, 20);

        $b = getBooking($this->pdo, $id);
        $this->assertSame('pending', $b['status']);
        $this->assertMatchesRegularExpression('/^PDL-\d{4,}$/', $b['code']);
    }

    public function test_each_booking_gets_a_distinct_code(): void
    {
        $a = getBooking($this->pdo, createBooking($this->pdo, 1, 1, $this->date, 8, 9));
        $b = getBooking($this->pdo, createBooking($this->pdo, 1, 1, $this->date, 10, 11));

        $this->assertNotSame($a['code'], $b['code']);
    }

    public function test_overlapping_range_is_rejected(): void
    {
        createBooking($this->pdo, 1, 1, $this->date, 18, 20);

        $this->expectException(BookingConflictException::class);
        createBooking($this->pdo, 1, 1, $this->date, 19, 21); // overlaps 19
    }

    public function test_adjacent_range_is_allowed(): void
    {
        createBooking($this->pdo, 1, 1, $this->date, 16, 18);
        $id = createBooking($this->pdo, 1, 1, $this->date, 18, 20); // starts exactly where prior ends
        $this->assertGreaterThan(0, $id);
    }

    public function test_rejects_end_before_start(): void
    {
        $this->expectException(InvalidArgumentException::class);
        createBooking($this->pdo, 1, 1, $this->date, 20, 18);
    }

    public function test_rejects_a_date_in_the_past(): void
    {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $this->expectException(InvalidArgumentException::class);
        createBooking($this->pdo, 1, 1, $yesterday, 18, 20);
    }

    public function test_rejects_outside_operating_hours(): void
    {
        $this->expectException(InvalidArgumentException::class);
        createBooking($this->pdo, 1, 1, $this->date, 6, 8); // opens at 07:00
    }

    public function test_cancelled_booking_frees_the_slot_for_rebooking(): void
    {
        $id = createBooking($this->pdo, 1, 1, $this->date, 8, 10);
        cancelBooking($this->pdo, $id);

        $grid = getAvailability($this->pdo, 1, $this->date);
        $taken = array_filter($grid, fn($s) => $s['taken']);
        $this->assertCount(0, $taken, 'a cancelled booking must not hold its slots');

        $new = createBooking($this->pdo, 1, 1, $this->date, 8, 10);
        $this->assertGreaterThan(0, $new, 'the freed range can be re-booked');
    }

    public function test_booked_hours_show_taken_in_availability(): void
    {
        createBooking($this->pdo, 1, 1, $this->date, 18, 20);

        $grid = getAvailability($this->pdo, 1, $this->date);
        $taken = array_map(fn($s) => $s['hour'], array_filter($grid, fn($s) => $s['taken']));
        $this->assertSame([18, 19], array_values($taken));
    }
}
