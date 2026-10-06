<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_regulations', function (Blueprint $table) {
            // Accepted payment modes for the country / ports of this rule:
            // ["lc", "other"]. "lc" unlocks the proforma invoice for buyers.
            $table->json('payment_modes')->nullable()->after('time_of_shipment');
        });
    }

    public function down(): void
    {
        Schema::table('import_regulations', function (Blueprint $table) {
            $table->dropColumn('payment_modes');
        });
    }
};
