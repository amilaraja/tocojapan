<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // One open-ended tier with the current flat margin, so prices don't change.
        $current = fn (string $name) => (float) json_decode((string) DB::table('settings')->where('group', 'stock')->where('name', $name)->value('payload'), true);

        $this->migrator->add('stock.supplier_margin_tiers', [[
            'from_usd' => 0,
            'to_usd' => null,
            'percent' => $current('supplier_margin_percent'),
            'fixed_usd' => $current('supplier_margin_fixed_usd'),
        ]]);
    }

    public function down(): void
    {
        $this->migrator->delete('stock.supplier_margin_tiers');
    }
};
