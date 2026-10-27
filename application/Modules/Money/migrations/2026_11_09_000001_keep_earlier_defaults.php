<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

/**
 * New installs start with an empty business name (the site name is used) and no Uber in the "Paid by" list.
 * Sites already in use keep what they had: if they never saved those settings, the old starting values are written now.
 */
return new class extends Migration {
    public function up(DB $db): void
    {
        if (!$db->hasTable('settings') || !$db->hasTable('money_expenses')) {
            return;
        }
        $inUse = $db->table('money_expenses')->exists() || ($db->hasTable('money_invoices') && $db->table('money_invoices')->exists()) || ($db->hasTable('money_income') && $db->table('money_income')->exists());
        if (!$inUse) {
            return;
        }
        $now = gmdate('Y-m-d H:i:s');
        $keep = [
            'money.biz_name' => 'AP Services',
            'money.paid_by' => json_encode([['label' => 'Bank Transfer'], ['label' => 'Cash'], ['label' => 'Card'], ['label' => 'Uber'], ['label' => 'Stripe'], ['label' => 'PayPal']]),
        ];
        foreach ($keep as $name => $value) {
            if (!$db->table('settings')->where('name', $name)->exists()) {
                $db->table('settings')->insert(['name' => $name, 'value' => (string) $value, 'updated_at' => $now]);
            }
        }
    }

    public function down(DB $db): void
    {
    }
};
