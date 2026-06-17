<?php

use PHPUnit\Framework\TestCase;

// Covers court schedules CRUD (#47): the per-court, per-day-band bookable hours
// and pricing rows edited in the admin court cards.
final class ScheduleTest extends TestCase
{
    private PDO $pdo;
    private int $courtId;

    protected function setUp(): void
    {
        $this->pdo = db();
        $this->pdo->exec('DELETE FROM court_schedules');
        $this->pdo->exec('DELETE FROM bookings');
        $this->pdo->exec('DELETE FROM courts');
        $this->pdo->exec('DELETE FROM venues');
        $this->pdo->exec("INSERT INTO venues (id, name, city, price_per_hour) VALUES (1, 'V', 'Jakarta', 100000)");
        $this->courtId = createCourt($this->pdo, 1, 'A');
    }

    public function test_set_then_list_round_trips_normalised(): void
    {
        setSchedules($this->pdo, $this->courtId, [
            ['day' => 'EVERYDAY', 'start' => '07:00', 'end' => '08:00', 'price' => 150000],
            ['day' => 'SAT-SUN', 'start' => '15:00', 'end' => '18:00', 'price' => 200000],
        ]);

        $rows = listSchedules($this->pdo, $this->courtId);
        $this->assertCount(2, $rows);
        $this->assertSame('everyday', $rows[0]['day_band']);
        $this->assertSame(7, $rows[0]['start_hour']);
        $this->assertSame(8, $rows[0]['end_hour']);
        $this->assertSame(150000, $rows[0]['price']);
        $this->assertSame('sat_sun', $rows[1]['day_band']);
        $this->assertSame(15, $rows[1]['start_hour']);
    }

    public function test_accepts_integer_hours(): void
    {
        setSchedules($this->pdo, $this->courtId, [
            ['day' => 'mon_fri', 'start' => 9, 'end' => 12, 'price' => 120000],
        ]);
        $this->assertSame(9, listSchedules($this->pdo, $this->courtId)[0]['start_hour']);
    }

    public function test_set_replaces_previous(): void
    {
        setSchedules($this->pdo, $this->courtId, [['day' => 'EVERYDAY', 'start' => 7, 'end' => 8, 'price' => 100]]);
        setSchedules($this->pdo, $this->courtId, [['day' => 'EVERYDAY', 'start' => 9, 'end' => 10, 'price' => 200]]);

        $rows = listSchedules($this->pdo, $this->courtId);
        $this->assertCount(1, $rows);
        $this->assertSame(9, $rows[0]['start_hour']);
    }

    public function test_empty_clears_schedules(): void
    {
        setSchedules($this->pdo, $this->courtId, [['day' => 'EVERYDAY', 'start' => 7, 'end' => 8, 'price' => 100]]);
        setSchedules($this->pdo, $this->courtId, []);
        $this->assertCount(0, listSchedules($this->pdo, $this->courtId));
    }

    public function test_rejects_end_before_start(): void
    {
        $this->expectException(InvalidArgumentException::class);
        setSchedules($this->pdo, $this->courtId, [['day' => 'EVERYDAY', 'start' => 10, 'end' => 9, 'price' => 100]]);
    }

    public function test_rejects_non_positive_price(): void
    {
        $this->expectException(InvalidArgumentException::class);
        setSchedules($this->pdo, $this->courtId, [['day' => 'EVERYDAY', 'start' => 7, 'end' => 8, 'price' => 0]]);
    }

    public function test_rejects_bad_band(): void
    {
        $this->expectException(InvalidArgumentException::class);
        setSchedules($this->pdo, $this->courtId, [['day' => 'WEEKEND', 'start' => 7, 'end' => 8, 'price' => 100]]);
    }

    public function test_rejects_half_hour(): void
    {
        $this->expectException(InvalidArgumentException::class);
        setSchedules($this->pdo, $this->courtId, [['day' => 'EVERYDAY', 'start' => '07:30', 'end' => '08:00', 'price' => 100]]);
    }

    public function test_rejects_out_of_range_hours(): void
    {
        $this->expectException(InvalidArgumentException::class);
        setSchedules($this->pdo, $this->courtId, [['day' => 'EVERYDAY', 'start' => 7, 'end' => 25, 'price' => 100]]);
    }

    public function test_bad_row_rolls_back_whole_set(): void
    {
        setSchedules($this->pdo, $this->courtId, [['day' => 'EVERYDAY', 'start' => 7, 'end' => 8, 'price' => 100]]);
        try {
            setSchedules($this->pdo, $this->courtId, [
                ['day' => 'EVERYDAY', 'start' => 9, 'end' => 10, 'price' => 100],
                ['day' => 'EVERYDAY', 'start' => 10, 'end' => 9, 'price' => 100], // bad
            ]);
            $this->fail('expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            // the original single row must survive (transactional replace)
            $rows = listSchedules($this->pdo, $this->courtId);
            $this->assertCount(1, $rows);
            $this->assertSame(7, $rows[0]['start_hour']);
        }
    }

    public function test_cascade_on_court_delete(): void
    {
        setSchedules($this->pdo, $this->courtId, [['day' => 'EVERYDAY', 'start' => 7, 'end' => 8, 'price' => 100]]);
        deleteCourt($this->pdo, $this->courtId);
        $n = $this->pdo->query("SELECT COUNT(*) FROM court_schedules WHERE court_id = {$this->courtId}")->fetchColumn();
        $this->assertSame(0, (int) $n);
    }
}
