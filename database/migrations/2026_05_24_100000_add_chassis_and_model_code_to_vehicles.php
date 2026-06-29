<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('chassis_number')->nullable()->after('ref_no')->index();
            $table->string('model_code', 60)->nullable()->after('chassis_number');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropIndex(['chassis_number']);
            $table->dropColumn(['chassis_number', 'model_code']);
        });
    }
};
