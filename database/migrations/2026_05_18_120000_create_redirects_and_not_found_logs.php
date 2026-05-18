<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path', 500)->unique();
            $table->string('to_path', 1000);
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();
        });

        Schema::create('not_found_logs', function (Blueprint $table) {
            $table->id();
            $table->string('path', 500)->unique();
            $table->unsignedInteger('hits')->default(0);
            $table->string('referer', 1000)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('not_found_logs');
        Schema::dropIfExists('redirects');
    }
};
