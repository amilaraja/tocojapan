<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('port_id')->nullable()->constrained()->nullOnDelete();

            $table->string('contact_name');
            $table->string('contact_email');
            $table->string('contact_phone')->nullable();
            $table->longText('message')->nullable();

            $table->string('status')->default('submitted')->index();
            // submitted | in_progress | quoted | accepted | declined | archived

            $table->decimal('price_quoted', 12, 2)->nullable();
            $table->decimal('cif_total', 12, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->date('valid_until')->nullable();
            $table->longText('internal_notes')->nullable();

            $table->timestamp('last_admin_reply_at')->nullable();
            $table->timestamp('last_customer_reply_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
