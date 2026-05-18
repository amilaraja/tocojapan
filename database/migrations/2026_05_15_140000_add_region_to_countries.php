<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->string('region')->nullable()->after('name')->index();
        });

        $regions = [
            'Asia' => ['Sri Lanka'],
            'Africa' => ['Botswana', 'Burundi', 'DR Congo', 'Kenya', 'Madagascar', 'Malawi', 'Mauritius', 'Mozambique', 'Namibia', 'Rwanda', 'Tanzania', 'Uganda', 'Zambia', 'Zimbabwe'],
            'Caribbean' => ['Bahamas', 'Guyana', 'Jamaica', 'Trinidad and Tobago'],
            'Europe' => ['Ireland', 'United Kingdom'],
            'Oceania' => ['New Zealand', 'Papua New Guinea'],
        ];

        foreach ($regions as $region => $names) {
            \Illuminate\Support\Facades\DB::table('countries')
                ->whereIn('name', $names)
                ->update(['region' => $region]);
        }
    }

    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->dropColumn('region');
        });
    }
};
