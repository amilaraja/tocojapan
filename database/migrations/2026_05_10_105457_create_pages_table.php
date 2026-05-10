<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('template_key')->index();
            $table->string('slug')->unique();
            $table->string('title');
            $table->json('data')->nullable();
            $table->string('status')->default('draft')->index();
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->string('seo_image')->nullable();
            $table->string('locale', 10)->default('en')->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
