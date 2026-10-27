<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

/** Vehicles get a kind and a capital allowance flag, categories can stay claimable with mileage rates, and trips are logged. */
return new class extends Migration {
    public function up(DB $db): void
    {
        $this->addColumn($db, 'money_vehicles', 'kind', "VARCHAR(12) NOT NULL DEFAULT 'car'");
        $this->addColumn($db, 'money_vehicles', 'capital_claimed', 'TINYINT(1) NOT NULL DEFAULT 0');
        $this->addColumn($db, 'money_vehicles', 'allowance_expected', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
        $this->addColumn($db, 'money_categories', 'with_mileage', 'TINYINT(1) NOT NULL DEFAULT 0');
        $this->createTable($db, 'money_trips', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            vehicle_id INT UNSIGNED NOT NULL DEFAULT 0,
            trip_date DATE NOT NULL,
            miles DECIMAL(8,1) NOT NULL DEFAULT 0,
            business TINYINT(1) NOT NULL DEFAULT 1,
            from_place VARCHAR(190) NULL,
            to_place VARCHAR(190) NULL,
            purpose VARCHAR(190) NULL,
            odo_start INT UNSIGNED NULL,
            odo_end INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            deleted_at DATETIME NULL,
            KEY idx_mt_date (trip_date),
            KEY idx_mt_vehicle (vehicle_id)');
        // Parking, tolls and similar charges can be claimed on top of the mileage rate.
        foreach ($db->table('money_categories')->get() as $c) {
            if (preg_match('/park|toll|congestion|ulez|clean air|dart charge|charge/i', (string) $c['name']) && !preg_match('/insur|service|repair|fuel|lease|rent/i', (string) $c['name'])) {
                $db->table('money_categories')->where('id', (int) $c['id'])->update(['with_mileage' => 1]);
            }
        }
    }

    public function down(DB $db): void
    {
        $this->dropTable($db, 'money_trips');
    }
};
