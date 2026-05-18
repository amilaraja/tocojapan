<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spare_part_inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone');
            $table->string('country')->nullable();
            $table->string('address')->nullable();
            $table->string('model_name')->nullable();
            $table->string('chassis_no')->nullable();
            $table->string('year')->nullable();
            $table->string('engine_model')->nullable();
            $table->string('condition')->nullable();
            $table->string('shipping_method')->nullable();
            $table->text('parts_description');
            $table->json('attachments')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->boolean('is_handled')->default(false);
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spare_part_inquiries');
    }
};
