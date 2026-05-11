<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->timestamp('fb_shared_at')->nullable()->after('published_at');
            $table->string('fb_post_id', 191)->nullable()->after('fb_shared_at');
            $table->string('fb_post_url', 255)->nullable()->after('fb_post_id');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['fb_shared_at', 'fb_post_id', 'fb_post_url']);
        });
    }
};
