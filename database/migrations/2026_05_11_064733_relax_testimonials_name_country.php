<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
            $table->string('country')->nullable()->change();
            $table->string('vehicle_label', 120)->nullable()->after('country');
        });
    }

    public function down(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            $table->dropColumn('vehicle_label');
            $table->string('name')->nullable(false)->change();
            $table->string('country')->nullable(false)->change();
        });
    }
};
