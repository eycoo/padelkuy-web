<?php

use PHPUnit\Framework\TestCase;

final class CourtAdminTest extends TestCase
{
    private PDO $pdo;
    private int $venueId;

    protected function setUp(): void
    {
        $this->pdo = db();
        $this->pdo->exec('DELETE FROM bookings');
        $this->pdo->exec('DELETE FROM courts');
        $this->pdo->exec('DELETE FROM venues');
        $this->pdo->exec('DELETE FROM users');

        $this->pdo->exec("INSERT INTO venues (id, name, city, price_per_hour) VALUES (1, 'V', 'Jakarta', 100000)");
        $this->venueId = 1;
    }

    public function test_create_persists_under_venue_and_returns_id(): void
    {
        $id = createCourt($this->pdo, $this->venueId, 'A');
        $this->assertGreaterThan(0, $id);

        $courts = listCourts($this->pdo, $this->venueId);
        $this->assertCount(1, $courts);
        $this->assertSame('A', $courts[0]['label']);
        $this->assertSame($this->venueId, $courts[0]['venue_id']);
    }

    public function test_list_returns_only_that_venues_courts(): void
    {
        $this->pdo->exec("INSERT INTO venues (id, name, city, price_per_hour) VALUES (2, 'Other', 'Bandung', 100000)");
        createCourt($this->pdo, 1, 'A');
        createCourt($this->pdo, 1, 'B');
        createCourt($this->pdo, 2, 'A');

        $this->assertCount(2, listCourts($this->pdo, 1));
        $this->assertCount(1, listCourts($this->pdo, 2));
    }

    public function test_delete_removes_court_and_cascades_bookings(): void
    {
        $courtId = createCourt($this->pdo, $this->venueId, 'A');
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash) VALUES (1, 'U', 'u@e.com', 'x')");
        $this->pdo->exec("INSERT INTO bookings (court_id, user_id, date, start_hour, end_hour)
                          VALUES ($courtId, 1, '2026-06-10', 8, 9)");

        $this->assertTrue(deleteCourt($this->pdo, $courtId));
        $this->assertCount(0, listCourts($this->pdo, $this->venueId));

        $bookings = $this->pdo->query("SELECT COUNT(*) FROM bookings WHERE court_id = $courtId")->fetchColumn();
        $this->assertSame(0, (int) $bookings, 'bookings must cascade with the court');
    }

    public function test_delete_returns_false_for_missing(): void
    {
        $this->assertFalse(deleteCourt($this->pdo, 999999));
    }

    public function test_rejects_empty_label(): void
    {
        $this->expectException(InvalidArgumentException::class);
        createCourt($this->pdo, $this->venueId, '   ');
    }

    // --- #46 court type + rich name ---

    public function test_create_with_type_and_rich_name(): void
    {
        createCourt($this->pdo, $this->venueId, 'Lapangan 1', 'outdoor');
        $court = listCourts($this->pdo, $this->venueId)[0];
        $this->assertSame('Lapangan 1', $court['label']);
        $this->assertSame('outdoor', $court['type']);
    }

    public function test_type_defaults_to_indoor(): void
    {
        createCourt($this->pdo, $this->venueId, 'A');
        $this->assertSame('indoor', listCourts($this->pdo, $this->venueId)[0]['type']);
    }

    public function test_rejects_bad_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        createCourt($this->pdo, $this->venueId, 'A', 'rooftop');
    }

    public function test_update_changes_label_and_type(): void
    {
        $id = createCourt($this->pdo, $this->venueId, 'A', 'indoor');
        $this->assertTrue(updateCourt($this->pdo, $id, 'Lapangan 2', 'outdoor'));

        $court = listCourts($this->pdo, $this->venueId)[0];
        $this->assertSame('Lapangan 2', $court['label']);
        $this->assertSame('outdoor', $court['type']);
    }

    public function test_update_returns_false_for_missing(): void
    {
        $this->assertFalse(updateCourt($this->pdo, 999999, 'X', 'indoor'));
    }
}
