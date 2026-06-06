<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            // Per-country mandate for the Pre-inspection Fee on the vehicle
            // detail CIF estimator. When true, the checkbox is force-ticked
            // and the customer can't opt out.
            $table->boolean('pre_inspection_required')->default(false)->after('is_active');
        });

        DB::table('countries')->where('iso2', 'LK')->update(['pre_inspection_required' => true]);
    }

    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->dropColumn('pre_inspection_required');
        });
    }
};
