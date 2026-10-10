<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** SRS §9.1: re-host (default, 1 h links) or Toco-hosted (24 h links). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->string('image_delivery', 16)->default('rehost')->after('image_daily_limit');
        });
    }

    public function down(): void
    {
        Schema::table('partners', fn (Blueprint $table) => $table->dropColumn('image_delivery'));
    }
};
