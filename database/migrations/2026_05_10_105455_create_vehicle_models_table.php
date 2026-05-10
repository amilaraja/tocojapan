<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('make_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['make_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_models');
    }
};
