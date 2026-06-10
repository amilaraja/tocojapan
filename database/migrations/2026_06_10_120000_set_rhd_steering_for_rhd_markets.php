<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Left-side-traffic destinations where Japanese RHD stock is the norm.
     * Excludes the right-hand-traffic countries we currently publish
     * (Madagascar, Rwanda, Burundi, DR Congo), which stay "any".
     */
    private const RHD_MARKETS = [
        'LK', 'KE', 'TZ', 'UG', 'ZM', 'ZW', 'BW', 'NA', 'MW', 'MZ', 'MU',
        'BS', 'GY', 'JM', 'TT', 'IE', 'GB', 'NZ', 'PG',
    ];

    public function up(): void
    {
        $ids = DB::table('countries')->whereIn('iso2', self::RHD_MARKETS)->pluck('id');

        DB::table('import_regulations')
            ->whereIn('country_id', $ids)
            ->whereNull('steering_restriction')
            ->update(['steering_restriction' => 'rhd_only']);
    }

    public function down(): void
    {
        $ids = DB::table('countries')->whereIn('iso2', self::RHD_MARKETS)->pluck('id');

        DB::table('import_regulations')
            ->whereIn('country_id', $ids)
            ->where('steering_restriction', 'rhd_only')
            ->update(['steering_restriction' => null]);
    }
};
