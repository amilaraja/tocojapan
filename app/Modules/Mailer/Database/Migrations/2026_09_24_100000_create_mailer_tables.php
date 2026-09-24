<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TOCO Mailer tables (SRS-TOC-01 section 8.1). All prefixed mailer_.
 *
 * TOC-LOG-003: no column anywhere holds a message body or attachment.
 * Vehicle references are plain indexed ids (no FK) so the module can never
 * block or cascade into the existing vehicles table (TOC-VEH-001).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailer_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->boolean('encrypted')->default(false);
            $table->timestamps();
        });

        Schema::create('mailer_approved_senders', function (Blueprint $table) {
            $table->id();
            $table->string('label', 120);
            $table->string('match_value', 190);
            $table->string('match_type', 10); // address | domain
            $table->boolean('active')->default(true);
            $table->json('brevo_list_ids')->nullable();
            $table->string('consent_mode', 10)->default('direct'); // direct | confirm
            $table->unsignedBigInteger('doi_template_id')->nullable();
            $table->string('doi_redirect_url', 500)->nullable();
            $table->boolean('use_reply_to')->default(false);
            $table->unsignedTinyInteger('max_per_message')->default(3);
            $table->json('field_rules')->nullable();
            $table->timestamps();

            $table->unique('match_value');
        });

        Schema::create('mailer_ignore_rules', function (Blueprint $table) {
            $table->id();
            $table->string('value', 190)->unique();
            $table->string('type', 10); // address | domain
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('mailer_import_runs', function (Blueprint $table) {
            $table->id();
            $table->string('trigger', 10); // schedule | manual | backfill
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->string('status', 10); // running | success | failed | skipped
            $table->unsignedInteger('scanned')->default(0);
            $table->unsignedInteger('created')->default(0);
            $table->unsignedInteger('updated')->default(0);
            $table->json('skipped')->nullable(); // {reason: count}
            $table->unsignedInteger('failed')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index('started_at');
        });

        Schema::create('mailer_processed_messages', function (Blueprint $table) {
            $table->id();
            $table->string('gmail_message_id', 64)->unique(); // TOC-IMP-005
            $table->foreignId('approved_sender_id')->nullable()->constrained('mailer_approved_senders')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('run_id')->nullable()->constrained('mailer_import_runs')->nullOnDelete();
            $table->string('outcome', 20);
            $table->timestamps();
        });

        Schema::create('mailer_contact_imports', function (Blueprint $table) {
            $table->id();
            $table->string('email', 190)->index();
            $table->string('gmail_message_id', 64)->nullable()->index();
            $table->foreignId('approved_sender_id')->nullable()->constrained('mailer_approved_senders')->nullOnDelete();
            $table->foreignId('run_id')->nullable()->constrained('mailer_import_runs')->nullOnDelete();
            $table->string('outcome', 20); // added | updated | skipped | failed
            $table->string('reason', 40)->nullable();
            $table->unsignedSmallInteger('brevo_status_code')->nullable();
            $table->json('fields')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::create('mailer_import_state', function (Blueprint $table) {
            $table->id();
            $table->string('mailbox', 190)->unique();
            $table->string('last_history_id', 40)->nullable();
            $table->timestamp('last_checkpoint_at')->nullable();
            $table->json('backfill_cursor')->nullable();
            $table->timestamp('lock_until')->nullable();
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->timestamp('failure_alert_sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('mailer_banners', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('path', 255);
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->unsignedInteger('bytes');
            $table->string('link_url', 500)->nullable();
            $table->string('alt_text', 255);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::create('mailer_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 160)->unique();
            $table->string('subject', 120)->nullable();
            $table->string('preview_text', 140)->nullable();
            $table->string('kicker', 80)->nullable();
            $table->string('headline', 150)->nullable();
            $table->text('intro')->nullable();
            $table->foreignId('banner_id')->nullable()->constrained('mailer_banners')->nullOnDelete();
            $table->unsignedBigInteger('sender_id')->nullable(); // Brevo sender id
            $table->json('list_ids')->nullable(); // Brevo list ids
            $table->string('cta_url', 500)->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('brevo_campaign_id')->nullable();
            $table->timestamp('pushed_at')->nullable();
            $table->string('pushed_html_hash', 64)->nullable();
            $table->json('stats')->nullable();
            $table->timestamp('stats_synced_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('mailer_campaign_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('mailer_campaigns')->cascadeOnDelete();
            $table->unsignedBigInteger('vehicle_id')->index();
            $table->string('stock_ref', 40)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->json('snapshot');
            $table->timestamp('added_at')->nullable();

            $table->unique(['campaign_id', 'vehicle_id']);
        });

        Schema::create('mailer_email_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vehicle_id')->index();
            $table->string('stock_ref', 40)->nullable();
            $table->string('source_url_hash', 64);
            $table->string('path', 255);
            $table->unsignedInteger('bytes');
            $table->timestamp('generated_at');

            $table->unique(['vehicle_id', 'source_url_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailer_email_images');
        Schema::dropIfExists('mailer_campaign_vehicles');
        Schema::dropIfExists('mailer_campaigns');
        Schema::dropIfExists('mailer_banners');
        Schema::dropIfExists('mailer_import_state');
        Schema::dropIfExists('mailer_contact_imports');
        Schema::dropIfExists('mailer_processed_messages');
        Schema::dropIfExists('mailer_import_runs');
        Schema::dropIfExists('mailer_ignore_rules');
        Schema::dropIfExists('mailer_approved_senders');
        Schema::dropIfExists('mailer_settings');
    }
};
