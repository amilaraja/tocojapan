<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ref_no is the external / auction reference and is optional in the
        // admin form (stock_no is the internal required ID). The original
        // migration declared it ->unique() without ->nullable(), which made
        // MySQL treat the column as NOT NULL and broke any "create vehicle"
        // attempt that didn't fill in ref_no. MySQL unique indexes already
        // allow multiple NULLs, so the index stays.
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('ref_no')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('ref_no')->nullable(false)->change();
        });
    }
};
