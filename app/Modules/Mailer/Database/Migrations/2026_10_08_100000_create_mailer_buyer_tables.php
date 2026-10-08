<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Buyer database (TOC-BUY, addendum to SRS-TOC-01): structured details
 * extracted from enquiry emails. Only extracted fields are stored, never the
 * message text (TOC-LOG-003).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mailer_approved_senders', function (Blueprint $table) {
            // Read labelled lines such as "Country : Kenya" into the buyer database.
            $table->boolean('collect_buyer_details')->default(false)->after('field_rules');
        });

        Schema::table('mailer_processed_messages', function (Blueprint $table) {
            // Set once the buyer backfill has looked at this message (TOC-BUY-006).
            $table->timestamp('buyer_scanned_at')->nullable()->after('outcome');
        });

        Schema::create('mailer_buyers', function (Blueprint $table) {
            $table->id();
            $table->string('email', 190)->unique();
            $table->string('title', 20)->nullable();
            $table->string('first_name', 80)->nullable();
            $table->string('last_name', 120)->nullable();
            $table->string('country', 80)->nullable();
            $table->char('country_code', 2)->nullable()->index();
            $table->string('port', 120)->nullable();
            $table->string('phone', 60)->nullable();
            $table->string('phone_e164', 20)->nullable();
            $table->string('buyer_type', 20)->nullable()->index();
            $table->foreignId('approved_sender_id')->nullable()->constrained('mailer_approved_senders')->nullOnDelete();
            $table->unsignedInteger('enquiry_count')->default(0);
            $table->timestamp('first_enquiry_at')->nullable();
            $table->timestamp('last_enquiry_at')->nullable()->index();
            $table->timestamp('brevo_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('mailer_buyer_enquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->constrained('mailer_buyers')->cascadeOnDelete();
            $table->string('gmail_message_id', 64);
            $table->foreignId('approved_sender_id')->nullable()->constrained('mailer_approved_senders')->nullOnDelete();
            $table->timestamp('received_at')->nullable()->index();
            $table->string('kind', 20)->nullable();
            $table->string('make', 80)->nullable();
            $table->string('model', 120)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('drive', 5)->nullable();
            $table->string('budget', 80)->nullable();
            $table->char('country_code', 2)->nullable()->index();
            $table->string('port', 120)->nullable();
            $table->json('details')->nullable();
            $table->timestamps();

            $table->unique(['buyer_id', 'gmail_message_id']);
            $table->index(['make', 'model']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailer_buyer_enquiries');
        Schema::dropIfExists('mailer_buyers');
        Schema::table('mailer_processed_messages', fn (Blueprint $table) => $table->dropColumn('buyer_scanned_at'));
        Schema::table('mailer_approved_senders', fn (Blueprint $table) => $table->dropColumn('collect_buyer_details'));
    }
};
