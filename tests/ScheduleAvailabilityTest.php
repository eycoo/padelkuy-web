<?php

use PHPUnit\Framework\TestCase;

// Covers #48: court schedules drive bookable hours + per-hour pricing, with a
// fallback to the fixed 07:00-20:00 grid + flat venue price when a court has no
// schedules.
final class ScheduleAvailabilityTest extends TestCase
{
    private PDO $pdo;
    private int $courtId;

    protected function setUp(): void
    {
        $this->pdo = db();
        $this->pdo->exec('DELETE FROM payments');
        $this->pdo->exec('DELETE FROM court_schedules');
        $this->pdo->exec('DELETE FROM bookings');
        $this->pdo->exec('DELETE FROM courts');
        $this->pdo->exec('DELETE FROM venues');
        $this->pdo->exec('DELETE FROM users');
        $this->pdo->exec("INSERT INTO venues (id, name, city, price_per_hour) VALUES (1, 'V', 'Jakarta', 100000)");
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash) VALUES (1, 'U', 'u@e.com', 'x')");
        $this->courtId = createCourt($this->pdo, 1, 'A');
    }

    // Next future date (>= +1 day) whose weekday matches $isoWeekday (1=Mon..7=Sun).
    private function futureDateForWeekday(int $isoWeekday): string
    {
        $d = new DateTime('+1 day');
        while ((int) $d->format('N') !== $isoWeekday) {
            $d->modify('+1 day');
        }
        return $d->format('Y-m-d');
    }

    private function hours(array $grid): array
    {
        return array_map(fn ($s) => $s['hour'], $grid);
    }

    // --- fallback (no schedules) keeps old behaviour ---

    public function test_fallback_hours_and_price_when_no_schedules(): void
    {
        $this->assertSame(range(7, 20), bookableHoursForDate($this->pdo, $this->courtId, '2026-06-10'));
        $this->assertSame(100000, priceForHour($this->pdo, $this->courtId, '2026-06-10', 9));

        $grid = getAvailability($this->pdo, $this->courtId, '2026-06-10');
        $this->assertCount(14, $grid);
        $this->assertSame(100000, $grid[0]['price']);
    }

    // --- schedules define availability ---

    public function test_everyday_schedule_limits_bookable_hours(): void
    {
        setSchedules($this->pdo, $this->courtId, [['day' => 'EVERYDAY', 'start' => 7, 'end' => 9, 'price' => 150000]]);
        $this->assertSame([7, 8], bookableHoursForDate($this->pdo, $this->courtId, '2026-06-10'));

        $grid = getAvailability($this->pdo, $this->courtId, '2026-06-10');
        $this->assertSame([7, 8], $this->hours($grid));
        $this->assertSame(150000, $grid[0]['price']);
    }

    public function test_mon_fri_band_empty_on_weekend(): void
    {
        setSchedules($this->pdo, $this->courtId, [['day' => 'MON-FRI', 'start' => 9, 'end' => 12, 'price' => 120000]]);
        $weekday = $this->futureDateForWeekday(3); // Wednesday
        $saturday = $this->futureDateForWeekday(6);

        $this->assertSame([9, 10, 11], bookableHoursForDate($this->pdo, $this->courtId, $weekday));
        $this->assertSame([], bookableHoursForDate($this->pdo, $this->courtId, $saturday));
    }

    // --- pricing precedence: a specific band beats everyday ---

    public function test_specific_band_price_beats_everyday(): void
    {
        setSchedules($this->pdo, $this->courtId, [
            ['day' => 'EVERYDAY', 'start' => 7, 'end' => 9, 'price' => 100000],
            ['day' => 'SAT-SUN', 'start' => 7, 'end' => 9, 'price' => 200000],
        ]);
        $saturday = $this->futureDateForWeekday(6);
        $weekday  = $this->futureDateForWeekday(3);

        $this->assertSame(200000, priceForHour($this->pdo, $this->courtId, $saturday, 7));
        $this->assertSame(100000, priceForHour($this->pdo, $this->courtId, $weekday, 7));
    }

    // --- booking respects schedule hours + pricing ---

    public function test_booking_price_is_schedule_aware(): void
    {
        setSchedules($this->pdo, $this->courtId, [['day' => 'EVERYDAY', 'start' => 7, 'end' => 12, 'price' => 150000]]);
        $date = $this->futureDateForWeekday(3);

        $b = getBooking($this->pdo, createBooking($this->pdo, 1, $this->courtId, $date, 7, 9));
        $this->assertSame(300000, $b['price']); // 2h * 150000, not the 100000 venue rate
    }

    public function test_booking_rejected_outside_schedule(): void
    {
        setSchedules($this->pdo, $this->courtId, [['day' => 'EVERYDAY', 'start' => 7, 'end' => 9, 'price' => 150000]]);
        $date = $this->futureDateForWeekday(3);

        $this->expectException(InvalidArgumentException::class);
        createBooking($this->pdo, 1, $this->courtId, $date, 9, 11); // 9,10 not in schedule
    }

    public function test_booking_allowed_inside_schedule(): void
    {
        setSchedules($this->pdo, $this->courtId, [['day' => 'EVERYDAY', 'start' => 7, 'end' => 12, 'price' => 150000]]);
        $date = $this->futureDateForWeekday(3);
        $this->assertGreaterThan(0, createBooking($this->pdo, 1, $this->courtId, $date, 10, 12));
    }
}
