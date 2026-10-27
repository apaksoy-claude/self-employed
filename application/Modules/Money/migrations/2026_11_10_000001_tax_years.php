<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

/**
 * Tax rules for each tax year, and your own figures for each year (other income, tax already paid on it, payments on account
 * made and hours worked at home). Rates that were changed in Settings > Money before this update are kept, for the current year.
 */
return new class extends Migration {
    public function up(DB $db): void
    {
        $this->createTable($db, 'money_tax_years', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            year VARCHAR(7) NOT NULL,
            rules TEXT NULL,
            other_income DECIMAL(12,2) NOT NULL DEFAULT 0,
            other_tax DECIMAL(12,2) NOT NULL DEFAULT 0,
            poa_paid DECIMAL(12,2) NOT NULL DEFAULT 0,
            home_hours INT NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_mty_year (year)');
        if (!$db->hasTable('settings')) {
            return;
        }
        $map = ['personal_allowance' => 'personal_allowance', 'taper_from' => 'taper_from', 'basic_band' => 'basic_band', 'higher_limit' => 'higher_limit', 'basic_rate' => 'basic_rate',
            'higher_rate' => 'higher_rate', 'additional_rate' => 'additional_rate', 'class4_lower' => 'c4_lower', 'class4_upper' => 'c4_upper', 'class4_main' => 'c4_main',
            'class4_upper_rate' => 'c4_upper_rate', 'mile_high' => 'mile_high', 'mile_low' => 'mile_low', 'mile_threshold' => 'mile_threshold', 'mile_moto' => 'mile_moto'];
        $changed = [];
        foreach ($map as $old => $new) {
            $v = $db->table('settings')->where('name', 'money.' . $old)->value('value');
            if ($v !== null && $v !== '' && is_numeric($v)) {
                $changed[$new] = (float) $v;
            }
        }
        $y = (int) gmdate('Y');
        $start = gmdate('md') >= '0406' ? $y : $y - 1;
        $year = sprintf('%d-%02d', $start, ($start + 1) % 100);
        if ($changed && !$db->table('money_tax_years')->where('year', $year)->exists()) {
            $builtin = \AP\Modules\Money\TaxRules::BUILTIN;
            $rules = $changed + ($builtin[$year] ?? end($builtin));
            $db->table('money_tax_years')->insert(['year' => $year, 'rules' => json_encode($rules), 'updated_at' => gmdate('Y-m-d H:i:s')]);
        }
    }

    public function down(DB $db): void
    {
        $this->dropTable($db, 'money_tax_years');
    }
};
