<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Webhook outbox (FR-SYNC-02, §8.5) and public hold ids for the holds API.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->string('delivery_id', 40)->unique();
            $table->string('event', 40);
            $table->string('subject', 60)->nullable(); // stock_no or hold id, for coalescing
            $table->json('payload');
            $table->string('status', 12)->default('pending'); // pending | delivered | failed
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->unsignedSmallInteger('last_status_code')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_attempt_at']);
            $table->index(['partner_id', 'status', 'created_at']);
        });

        Schema::table('vehicle_holds', function (Blueprint $table) {
            $table->string('public_id', 32)->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_holds', fn (Blueprint $table) => $table->dropColumn('public_id'));
        Schema::dropIfExists('partner_webhook_deliveries');
    }
};
