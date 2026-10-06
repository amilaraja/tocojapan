<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('id')->constrained('suppliers')->nullOnDelete();
            // The supplier's own vehicle id. (supplier_id, supplier_ref) is the
            // sync key: re-imports update the same row, so slug/URL never change.
            $table->string('supplier_ref', 64)->nullable()->after('supplier_id');
            // Price exactly as the supplier sent it (e.g. JPY retail) — price_fob
            // is derived from it with the supplier's pricing rule and re-derived
            // when the exchange rate moves.
            $table->decimal('source_price', 14, 2)->nullable()->after('price_fob_discount');
            $table->string('source_currency', 3)->nullable()->after('source_price');
            // Supplier-hosted photo URLs (hotlinked, never downloaded).
            $table->json('external_photos')->nullable()->after('features');
            // Raw supplier extras shown on the detail page (auction sheet, score, yard location…).
            $table->json('supplier_meta')->nullable()->after('external_photos');
            // sha1 of the mapped attributes — lets a re-import skip unchanged rows.
            $table->string('supplier_hash', 40)->nullable()->after('supplier_meta');
            $table->timestamp('supplier_synced_at')->nullable()->after('supplier_hash');
            // Admin override: keep manual price/spec edits; the sync only manages availability.
            $table->boolean('sync_locked')->default(false)->after('supplier_synced_at');
            $table->timestamp('delisted_at')->nullable()->after('sold_at');

            $table->unique(['supplier_id', 'supplier_ref']);
            $table->index(['supplier_id', 'status']);
        });

        $own = DB::table('suppliers')->where('slug', 'toco')->value('id');
        DB::table('vehicles')->whereNull('supplier_id')->update(['supplier_id' => $own]);
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropUnique(['supplier_id', 'supplier_ref']);
            $table->dropIndex(['supplier_id', 'status']);
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropColumn([
                'supplier_ref', 'source_price', 'source_currency', 'external_photos', 'supplier_meta',
                'supplier_hash', 'supplier_synced_at', 'sync_locked', 'delisted_at',
            ]);
        });
    }
};
