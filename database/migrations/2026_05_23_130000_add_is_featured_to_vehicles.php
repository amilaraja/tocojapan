<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('status');
            $table->index(['is_featured', 'status', 'published_at'], 'vehicles_featured_idx');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropIndex('vehicles_featured_idx');
            $table->dropColumn('is_featured');
        });
    }
};
