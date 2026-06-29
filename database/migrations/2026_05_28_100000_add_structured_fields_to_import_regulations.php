<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_regulations', function (Blueprint $table) {
            // Numeric max-age so we can compare against vehicle YOM and warn buyers
            // "this vehicle is too old for {country}". null = no age restriction.
            $table->unsignedSmallInteger('year_max_age')->nullable()->after('year_restriction');

            // Steering restriction at the destination. null = either is fine.
            $table->string('steering_restriction', 16)->nullable()->after('year_max_age')
                ->comment('rhd_only | lhd_only | null (any)');

            // Pre-shipment inspection requirement (text, free-form for the
            // wording variation across countries: "JEVIC", "JAAI", "JEVIC or
            // JAAI", "VTA + JEVIC", etc.).
            $table->string('inspection', 120)->nullable()->after('steering_restriction');

            // Other catch-all structured tags (e.g. "container only", "ULEZ",
            // "left-hand traffic"). Comma-separated for now; can promote to a
            // pivot if it grows past a dozen.
            $table->string('other_restrictions', 255)->nullable()->after('inspection');
        });
    }

    public function down(): void
    {
        Schema::table('import_regulations', function (Blueprint $table) {
            $table->dropColumn(['year_max_age', 'steering_restriction', 'inspection', 'other_restrictions']);
        });
    }
};
