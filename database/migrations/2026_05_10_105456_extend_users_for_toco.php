<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->foreignId('country_id')->nullable()->after('phone')->constrained()->nullOnDelete();
            $table->string('locale', 10)->default('en')->after('country_id');
            $table->string('preferred_currency', 3)->default('USD')->after('locale');
            $table->timestamp('last_login_at')->nullable()->after('preferred_currency');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('country_id');
            $table->dropColumn(['phone', 'locale', 'preferred_currency', 'last_login_at']);
        });
    }
};
