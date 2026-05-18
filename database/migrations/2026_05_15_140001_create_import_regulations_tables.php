<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_regulations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->string('year_restriction')->nullable();
            $table->string('time_of_shipment')->nullable();
            $table->text('comments')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('import_regulation_port', function (Blueprint $table) {
            $table->foreignId('import_regulation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('port_id')->constrained()->cascadeOnDelete();
            $table->primary(['import_regulation_id', 'port_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_regulation_port');
        Schema::dropIfExists('import_regulations');
    }
};
