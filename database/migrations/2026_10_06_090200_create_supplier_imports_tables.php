<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per uploaded feed file. Lifecycle:
        // queued → staging → previewed → (approved) applying → completed
        // with failed / cancelled as terminal side exits.
        Schema::create('supplier_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('queued')->index();
            // full = the file is the supplier's complete stock (anything missing is delisted);
            // partial = add/update only, never delist.
            $table->string('mode', 10)->default('full');
            $table->string('original_name')->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            // Preview counts + applied counts: new, updated, price_up, price_down,
            // unchanged, relisted, delisted, locked, makes_created, models_created.
            $table->json('stats')->nullable();
            $table->json('errors_sample')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        // Parsed rows of one import, kept until the import is applied so the
        // preview and the apply step read exactly the same data.
        Schema::create('supplier_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_import_id')->constrained('supplier_imports')->cascadeOnDelete();
            $table->string('supplier_ref', 64);
            $table->unsignedInteger('line_no');
            $table->json('payload');
            $table->string('hash', 40);
            // new | update | unchanged | relist | locked — set by the preview step.
            $table->string('action', 12)->nullable();
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->decimal('old_price_fob', 12, 2)->nullable();
            $table->decimal('new_price_fob', 12, 2)->nullable();
            $table->boolean('applied')->default(false);

            $table->unique(['supplier_import_id', 'supplier_ref']);
            $table->index(['supplier_import_id', 'applied']);
            $table->index(['supplier_import_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_import_rows');
        Schema::dropIfExists('supplier_imports');
    }
};
