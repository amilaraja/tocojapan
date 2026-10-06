<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // LC proforma invoices customers generate from a vehicle page. Prices
        // and vehicle details are snapshotted so the PDF never changes later.
        Schema::create('proforma_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', 40)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('dest_port_id')->nullable()->constrained('ports')->nullOnDelete();
            $table->string('consignee_name', 120);
            $table->text('consignee_address');
            $table->string('consignee_phone', 40);
            $table->string('consignee_email', 190);
            $table->decimal('price_fob', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('insurance', 12, 2)->default(0);
            $table->decimal('freight', 12, 2)->default(0);
            $table->decimal('total_cif', 12, 2);
            $table->json('snapshot');
            $table->date('issued_on');
            $table->date('expires_on');
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proforma_invoices');
    }
};
