<?php

use PHPUnit\Framework\TestCase;

final class BookingAdminTest extends TestCase
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

        $this->pdo->exec("INSERT INTO venues (id, name, city, price_per_hour) VALUES
            (1, 'Venue One', 'Jakarta', 100000),
            (2, 'Venue Two', 'Bandung', 200000)");
        $this->pdo->exec("INSERT INTO courts (id, venue_id, label) VALUES (1, 1, 'A'), (2, 2, 'A')");
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash) VALUES
            (1, 'Alice', 'a@e.com', 'x'),
            (2, 'Bob',   'b@e.com', 'x')");
    }

    public function test_lists_bookings_across_all_users(): void
    {
        createBooking($this->pdo, 1, 1, self::DATE, 8, 9);   // Alice @ venue 1
        createBooking($this->pdo, 2, 2, self::DATE, 10, 11); // Bob   @ venue 2

        $all = listAllBookings($this->pdo);
        $this->assertCount(2, $all);
    }

    public function test_includes_booker_name(): void
    {
        createBooking($this->pdo, 1, 1, self::DATE, 8, 9);

        $all = listAllBookings($this->pdo);
        $this->assertSame('Alice', $all[0]['user_name']);
        $this->assertSame('Venue One', $all[0]['venue_name']);
    }

    public function test_filters_by_venue(): void
    {
        createBooking($this->pdo, 1, 1, self::DATE, 8, 9);   // venue 1
        createBooking($this->pdo, 2, 2, self::DATE, 10, 11); // venue 2

        $only = listAllBookings($this->pdo, ['venue_id' => 2]);
        $this->assertCount(1, $only);
        $this->assertSame('Venue Two', $only[0]['venue_name']);
    }

    public function test_filters_by_date(): void
    {
        createBooking($this->pdo, 1, 1, '2026-06-10', 8, 9);
        createBooking($this->pdo, 1, 1, '2026-06-11', 8, 9);

        $only = listAllBookings($this->pdo, ['date' => '2026-06-11']);
        $this->assertCount(1, $only);
        $this->assertSame('2026-06-11', $only[0]['date']);
    }

    public function test_cancel_deletes_and_frees_the_slot(): void
    {
        $id = createBooking($this->pdo, 1, 1, self::DATE, 8, 10);

        $this->assertTrue(cancelBooking($this->pdo, $id));
        $this->assertNull(getBooking($this->pdo, $id));

        $grid = getAvailability($this->pdo, 1, self::DATE);
        $taken = array_filter($grid, fn($s) => $s['taken']);
        $this->assertCount(0, $taken, 'cancelled hours must become free again');
    }

    public function test_cancel_returns_false_for_missing(): void
    {
        $this->assertFalse(cancelBooking($this->pdo, 999999));
    }
}
