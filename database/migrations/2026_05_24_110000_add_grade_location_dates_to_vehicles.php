<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            // Trim level / variant (e.g. Z, GT, LX). Free-form per make.
            $table->string('grade', 60)->nullable()->after('vehicle_model_id');

            // Where the vehicle physically sits. Most cases stay at the yard
            // (Yokohama/Sano) but inspection units can be elsewhere.
            $table->string('location', 80)->nullable()->after('grade');

            // Year is already in year_first_reg (smallint). Add month (1-12)
            // so the public page can render "Reg. Y/M = 2024/03".
            $table->unsignedTinyInteger('registration_month')->nullable()->after('year_first_reg');

            // Manufacture date — usually within a few months of registration
            // but tracked separately because customs/age-limit rules in some
            // destination countries count from manufacture date, not reg.
            $table->unsignedSmallInteger('manufacture_year')->nullable()->after('registration_month');
            $table->unsignedTinyInteger('manufacture_month')->nullable()->after('manufacture_year');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['grade', 'location', 'registration_month', 'manufacture_year', 'manufacture_month']);
        });
    }
};
