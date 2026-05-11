<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('dest_country_id')->nullable()->after('vehicle_id')->constrained('countries')->nullOnDelete();
            $table->foreignId('dest_port_id')->nullable()->after('dest_country_id')->constrained('ports')->nullOnDelete();
            $table->string('ship_to_name')->nullable()->after('dest_port_id');
            $table->string('ship_to_phone', 40)->nullable()->after('ship_to_name');
            $table->string('ship_to_address_line1')->nullable()->after('ship_to_phone');
            $table->string('ship_to_address_line2')->nullable()->after('ship_to_address_line1');
            $table->string('ship_to_city', 80)->nullable()->after('ship_to_address_line2');
            $table->string('ship_to_state', 80)->nullable()->after('ship_to_city');
            $table->string('ship_to_postcode', 20)->nullable()->after('ship_to_state');
            $table->decimal('cif_freight', 12, 2)->nullable()->after('amount_usd');
            $table->decimal('cif_insurance', 12, 2)->nullable()->after('cif_freight');
            $table->decimal('cif_total', 12, 2)->nullable()->after('cif_insurance');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dest_country_id');
            $table->dropConstrainedForeignId('dest_port_id');
            $table->dropColumn([
                'ship_to_name', 'ship_to_phone', 'ship_to_address_line1', 'ship_to_address_line2',
                'ship_to_city', 'ship_to_state', 'ship_to_postcode',
                'cif_freight', 'cif_insurance', 'cif_total',
            ]);
        });
    }
};
