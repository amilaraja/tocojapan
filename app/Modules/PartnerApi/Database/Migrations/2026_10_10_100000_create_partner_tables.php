<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Partner Stock API foundation (SRS-TOC-PAPI §4, FR-PRT-01..04).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->char('country', 2)->nullable();
            $table->string('type', 16)->default('listing'); // listing | selling (FR-PRT-04)
            $table->string('status', 16)->default('active'); // active | suspended | revoked
            $table->string('status_reason')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->text('notes')->nullable();

            // What they see: null = all own stock; otherwise make/body/year/price filters.
            $table->json('stock_scope')->nullable();
            $table->unsignedInteger('scope_version')->default(1);
            $table->string('field_set', 16)->default('standard');

            // Prices: as_is | percent | fixed | on_request.
            $table->string('price_mode', 16)->default('as_is');
            $table->decimal('price_markup', 12, 2)->nullable();

            // Photos (phase 1: watermarked only).
            $table->string('image_mode', 16)->default('watermarked');
            $table->unsignedSmallInteger('image_max_width')->default(1280);
            $table->boolean('image_ref_pill')->default(true);
            $table->unsignedInteger('image_daily_limit')->default(50000);

            // Limits.
            $table->unsignedInteger('rate_per_minute')->default(120);
            $table->unsignedInteger('rate_per_day')->default(20000);
            $table->json('ip_allowlist')->nullable();

            // Holds (selling partners only).
            $table->unsignedTinyInteger('max_active_holds')->default(5);
            $table->unsignedSmallInteger('hold_hours')->default(48);

            // Contract.
            $table->date('contract_starts_on')->nullable();
            $table->date('contract_ends_on')->nullable();
            $table->string('attribution_code', 16)->unique();
            $table->string('webhook_url')->nullable();
            $table->text('webhook_secret')->nullable(); // encrypted cast

            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'type']);
        });

        Schema::create('partner_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('environment', 8)->default('live'); // live | test
            $table->string('client_id', 32)->unique();
            $table->string('key_prefix', 16);
            $table->string('secret_hash');
            $table->string('previous_secret_hash')->nullable();
            $table->timestamp('previous_secret_expires_at')->nullable();
            $table->string('status', 16)->default('active'); // active | revoked
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('partner_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_client_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->json('scopes');
            $table->string('ip', 45)->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('created_at')->nullable();
        });

        // Append-only (FR-PRT-03): the model refuses updates and deletes.
        Schema::create('partner_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('partner_client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 64);
            $table->string('actor_type', 16); // user | system | partner
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('reason')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['partner_id', 'created_at']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_audit_logs');
        Schema::dropIfExists('partner_access_tokens');
        Schema::dropIfExists('partner_clients');
        Schema::dropIfExists('partners');
    }
};
