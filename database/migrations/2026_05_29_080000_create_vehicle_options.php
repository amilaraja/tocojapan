<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_options', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            // null price = "ASK" (the customer can still tick the box; sales follows
            // up to quote the actual amount).
            $table->decimal('price', 10, 2)->nullable();
            $table->string('tooltip', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_options');
    }
};
