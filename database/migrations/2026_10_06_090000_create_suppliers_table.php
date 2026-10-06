<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 60)->unique();
            // Toco's own yard stock. Exactly one supplier carries this flag; it
            // owns the homepage, new-arrival badges, Mailer and Facebook flows.
            $table->boolean('is_own_stock')->default(false);
            // Feed format handled by App\Suppliers\SupplierImporter (null = manual entry only).
            $table->string('feed_format', 40)->nullable();
            $table->boolean('is_active')->default(true);
            // Lower = listed first on /vehicles (own stock above supplier stock).
            $table->unsignedSmallInteger('sort_priority')->default(100);
            // Code prefixed to the supplier's vehicle id to form the public stock no (e.g. OP-29225).
            $table->string('stock_prefix', 10)->nullable();
            // Pricing, publishing and safety rules — see App\Models\Supplier::DEFAULT_SETTINGS.
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('suppliers')->insert([
            [
                'name' => 'Toco own stock', 'slug' => 'toco', 'is_own_stock' => true, 'feed_format' => null,
                'is_active' => true, 'sort_priority' => 0, 'stock_prefix' => null, 'settings' => null,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'name' => 'OnePrice', 'slug' => 'oneprice', 'is_own_stock' => false, 'feed_format' => 'oneprice_csv',
                'is_active' => true, 'sort_priority' => 50, 'stock_prefix' => 'OP', 'settings' => null,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'name' => 'JWT', 'slug' => 'jwt', 'is_own_stock' => false, 'feed_format' => null,
                'is_active' => true, 'sort_priority' => 50, 'stock_prefix' => 'JWT', 'settings' => null,
                'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
