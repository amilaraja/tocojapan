<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('bl_number')->nullable()->after('shipped_at');
            $table->string('vessel_name')->nullable()->after('bl_number');
            $table->string('voyage_no', 64)->nullable()->after('vessel_name');
            $table->date('eta_at')->nullable()->after('voyage_no');
            $table->string('carrier_tracking_url')->nullable()->after('eta_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['bl_number', 'vessel_name', 'voyage_no', 'eta_at', 'carrier_tracking_url']);
        });
    }
};
