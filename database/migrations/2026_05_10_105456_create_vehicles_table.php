<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('ref_no')->unique();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('status')->default('draft')->index();

            $table->foreignId('make_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_model_id')->constrained()->restrictOnDelete();
            $table->foreignId('body_type_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedSmallInteger('year_first_reg')->index();
            $table->unsignedInteger('mileage_km')->nullable();
            $table->unsignedInteger('engine_cc')->nullable();
            $table->string('fuel')->nullable();
            $table->string('transmission')->nullable();
            $table->string('drive')->nullable();
            $table->string('steering_side')->default('right');
            $table->string('exterior_color')->nullable();
            $table->string('interior_color')->nullable();
            $table->unsignedTinyInteger('doors')->nullable();
            $table->unsignedTinyInteger('seats')->nullable();

            $table->decimal('length_cm', 8, 2)->nullable();
            $table->decimal('width_cm', 8, 2)->nullable();
            $table->decimal('height_cm', 8, 2)->nullable();
            $table->decimal('m3', 10, 4)->nullable();

            $table->decimal('price_fob', 12, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->boolean('price_on_request')->default(false);

            $table->string('warranty_period')->nullable();
            $table->json('features')->nullable();
            $table->longText('description')->nullable();

            $table->json('seo')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['make_id', 'vehicle_model_id', 'year_first_reg']);
            $table->index(['status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
