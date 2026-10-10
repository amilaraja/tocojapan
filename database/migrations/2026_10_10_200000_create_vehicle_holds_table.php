<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vehicle holds (SRS-TOC-PAPI FR-HLD-01..08). One active hold per car is
 * guaranteed by the unique active_vehicle_id: it equals vehicle_id while
 * the hold is active and is null otherwise (NULLs never collide).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('active_vehicle_id')->nullable()->unique();

            // web_paypal | web_tt | proforma | admin | partner
            $table->string('channel', 16);
            $table->foreignId('partner_id')->nullable()->constrained('partners')->nullOnDelete();
            // Listing partner credited for the buyer (e.g. a JWT Japan inquiry).
            $table->foreignId('referral_partner_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // website buyer
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); // admin
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('proforma_invoice_id')->nullable()->constrained()->nullOnDelete();

            $table->string('buyer_ref', 120);
            $table->char('destination_country', 2)->nullable();
            $table->decimal('price_snapshot', 12, 2)->nullable();
            $table->char('currency', 3)->default('USD');
            $table->string('idempotency_key', 120)->nullable();

            // active | released | expired | converted
            $table->string('state', 12)->default('active');
            $table->timestamp('expires_at')->nullable(); // null while active = paid, awaiting staff confirmation
            $table->timestamp('ended_at')->nullable();
            $table->string('ended_reason')->nullable();
            $table->unsignedSmallInteger('extensions')->default(0);
            $table->timestamp('expiry_warned_at')->nullable();
            $table->timestamps();

            $table->index(['state', 'expires_at']);
            $table->index(['partner_id', 'state']);
            $table->index(['vehicle_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_holds');
    }
};
