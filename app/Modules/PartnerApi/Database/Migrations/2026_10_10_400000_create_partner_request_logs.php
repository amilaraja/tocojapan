<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per partner API call or image fetch (SRS §8.6): request id,
 * client, endpoint, status, latency, bytes. Never secrets, tokens or full
 * image URLs. Kept 12 months.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_request_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('request_id', 40);
            $table->string('kind', 8); // api | image
            $table->string('method', 8);
            $table->string('endpoint', 64); // route name, never the URL
            $table->unsignedSmallInteger('status');
            $table->unsignedInteger('duration_ms');
            $table->unsignedInteger('bytes')->default(0);
            $table->boolean('counted')->default(true); // false for 304s (not against the daily quota)
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['partner_id', 'created_at']);
            $table->index(['partner_id', 'kind', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_request_logs');
    }
};
