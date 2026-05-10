<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('unlocode', 5)->nullable();
            $table->decimal('rate_per_m3', 10, 2);
            $table->decimal('insurance_pct', 6, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['country_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ports');
    }
};
