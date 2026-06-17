<?php

use PHPUnit\Framework\TestCase;

// Covers #50: saveVenueBundle persists the whole admin editor (venue +
// facilities + images + courts + each court's schedules) in one transaction.
final class BulkVenueSaveTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = db();
        $this->pdo->exec('DELETE FROM court_schedules');
        $this->pdo->exec('DELETE FROM payments');
        $this->pdo->exec('DELETE FROM bookings');
        $this->pdo->exec('DELETE FROM venue_images');
        $this->pdo->exec('DELETE FROM venue_facilities');
        $this->pdo->exec('DELETE FROM courts');
        $this->pdo->exec('DELETE FROM venues');
    }

    private function bundle(array $overrides = []): array
    {
        return array_merge([
            'name' => 'The Club',
            'city' => 'Jakarta',
            'price_per_hour' => 150000,
            'description' => 'Nice place',
            'facilities' => ['Shower', 'Parking'],
            'images' => ['assets/images/a.jpg', 'assets/images/b.jpg'],
            'courts' => [
                ['label' => 'Lapangan 1', 'type' => 'indoor', 'schedules' => [
                    ['day' => 'EVERYDAY', 'start' => '07:00', 'end' => '09:00', 'price' => 150000],
                ]],
                ['label' => 'Lapangan 2', 'type' => 'outdoor', 'schedules' => []],
            ],
        ], $overrides);
    }

    public function test_create_from_scratch_persists_all_nested_data(): void
    {
        $venue = saveVenueBundle($this->pdo, null, $this->bundle());

        $this->assertSame('The Club', $venue['name']);
        $this->assertSame(['Shower', 'Parking'], $venue['facilities']);
        $this->assertSame(['assets/images/a.jpg', 'assets/images/b.jpg'], $venue['images']);
        $this->assertCount(2, $venue['courts']);
        $this->assertSame('Lapangan 1', $venue['courts'][0]['label']);
        $this->assertCount(1, $venue['courts'][0]['schedules']);
        $this->assertSame(7, $venue['courts'][0]['schedules'][0]['start_hour']);
    }

    public function test_update_replaces_children_and_drops_missing_courts(): void
    {
        $created = saveVenueBundle($this->pdo, null, $this->bundle());

        // Keep only the first court (by id), rename it, swap facilities.
        $keepId = $created['courts'][0]['id'];
        $updated = saveVenueBundle($this->pdo, $created['id'], $this->bundle([
            'facilities' => ['Cafe'],
            'courts' => [
                ['id' => $keepId, 'label' => 'Lapangan A', 'type' => 'outdoor', 'schedules' => []],
            ],
        ]));

        $this->assertSame(['Cafe'], $updated['facilities']);
        $this->assertCount(1, $updated['courts'], 'the unlisted court must be removed');
        $this->assertSame('Lapangan A', $updated['courts'][0]['label']);
        $this->assertSame('outdoor', $updated['courts'][0]['type']);
        $this->assertCount(0, $updated['courts'][0]['schedules']);

        // The second court is gone from the DB entirely.
        $courtCount = $this->pdo->query("SELECT COUNT(*) FROM courts WHERE venue_id = {$created['id']}")->fetchColumn();
        $this->assertSame(1, (int) $courtCount);
    }

    public function test_rollback_on_bad_nested_row_leaves_db_untouched(): void
    {
        $bad = $this->bundle([
            'courts' => [
                ['label' => 'Lapangan 1', 'type' => 'indoor', 'schedules' => [
                    ['day' => 'EVERYDAY', 'start' => 10, 'end' => 9, 'price' => 100], // invalid
                ]],
            ],
        ]);

        try {
            saveVenueBundle($this->pdo, null, $bad);
            $this->fail('expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM venues')->fetchColumn());
            $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM courts')->fetchColumn());
            $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM venue_facilities')->fetchColumn());
        }
    }
}
