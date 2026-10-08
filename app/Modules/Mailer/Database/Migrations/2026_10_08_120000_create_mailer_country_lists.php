<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Brevo list per buyer country (TOC-BUY-011). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailer_country_lists', function (Blueprint $table) {
            $table->id();
            $table->char('country_code', 2)->unique();
            $table->unsignedBigInteger('brevo_list_id');
            $table->string('name', 190);
            $table->timestamps();
        });

        Schema::table('mailer_buyers', function (Blueprint $table) {
            // The Brevo country list this buyer was added to (null = not yet).
            $table->unsignedBigInteger('brevo_country_list_id')->nullable()->after('brevo_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('mailer_buyers', fn (Blueprint $table) => $table->dropColumn('brevo_country_list_id'));
        Schema::dropIfExists('mailer_country_lists');
    }
};
