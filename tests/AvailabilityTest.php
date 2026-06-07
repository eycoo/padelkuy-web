<?php

use PHPUnit\Framework\TestCase;

final class AvailabilityTest extends TestCase
{
    private PDO $pdo;
    private int $courtId;
    private int $userId;

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
        $this->courtId = 1;
        $this->userId = 1;
    }

    private function book(int $start, int $end, string $date = '2026-06-10'): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO bookings (court_id, user_id, date, start_hour, end_hour) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$this->courtId, $this->userId, $date, $start, $end]);
    }

    private function takenHours(array $grid): array
    {
        return array_map(fn($s) => $s['hour'], array_filter($grid, fn($s) => $s['taken']));
    }

    public function test_empty_day_is_all_free(): void
    {
        $grid = getAvailability($this->pdo, $this->courtId, '2026-06-10');
        $this->assertCount(14, $grid, 'hours 07..20 inclusive = 14 slots');
        $this->assertSame([], $this->takenHours($grid));
    }

    public function test_range_flags_only_covered_hours(): void
    {
        $this->book(18, 20); // covers 18 and 19, not 20
        $grid = getAvailability($this->pdo, $this->courtId, '2026-06-10');
        $this->assertSame([18, 19], array_values($this->takenHours($grid)));
    }

    public function test_bookings_on_other_dates_do_not_affect_grid(): void
    {
        $this->book(8, 10, '2026-06-11');
        $grid = getAvailability($this->pdo, $this->courtId, '2026-06-10');
        $this->assertSame([], $this->takenHours($grid));
    }

    public function test_venue_availability_returns_each_court(): void
    {
        $this->pdo->exec("INSERT INTO courts (id, venue_id, label) VALUES (2, 1, 'B')");
        $courts = getVenueAvailability($this->pdo, 1, '2026-06-10');
        $this->assertCount(2, $courts);
        $this->assertSame('A', $courts[0]['label']);
        $this->assertCount(14, $courts[0]['slots']);
    }
}
