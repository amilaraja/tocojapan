<?php

use App\Modules\Crm\Support\CrmDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** TOCO CRM tables (SRS-TOC-02 §11.3). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_customers', function (Blueprint $table) {
            $table->id();
            $table->string('email', 190)->nullable()->unique();
            $table->string('name', 190)->nullable();
            $table->string('phone', 60)->nullable();
            $table->string('whatsapp', 60)->nullable();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->string('country_name', 120)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('merged_into_id')->nullable()->constrained('crm_customers')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('phone');
            $table->index('user_id');
        });

        Schema::create('crm_queues', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->json('case_types')->nullable();
            $table->json('sources')->nullable();
            $table->unsignedSmallInteger('max_open_cases')->default(30);
            $table->unsignedBigInteger('last_assigned_user_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('crm_queue_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('queue_id')->constrained('crm_queues')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('max_open_cases')->nullable();
            $table->timestamps();
            $table->unique(['queue_id', 'user_id']);
        });

        Schema::create('crm_cases', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->string('type', 20);
            $table->string('source', 30);
            $table->foreignId('customer_id')->constrained('crm_customers');
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('dest_country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->foreignId('dest_port_id')->nullable()->constrained('ports')->nullOnDelete();
            $table->string('payment_mode', 20)->nullable();
            $table->foreignId('queue_id')->nullable()->constrained('crm_queues')->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('stage')->nullable();
            $table->string('status', 30)->nullable();
            $table->string('state', 20)->default('open');
            $table->string('subject', 255)->nullable();
            $table->decimal('value_usd', 12, 2)->nullable();
            $table->string('value_basis', 3)->nullable();
            $table->string('reason', 80)->nullable();
            $table->text('reason_note')->nullable();
            $table->timestamp('hold_until')->nullable();
            $table->timestamp('next_follow_up_at')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('last_customer_message_at')->nullable();
            $table->timestamp('last_staff_response_at')->nullable();
            $table->timestamp('last_staff_activity_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('stage_entered_at')->nullable();
            $table->timestamp('stale_at')->nullable();
            $table->timestamp('stale_admin_at')->nullable();
            $table->timestamp('unassigned_alerted_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('merged_into_id')->nullable()->constrained('crm_cases')->nullOnDelete();
            $table->string('portal_token', 64)->nullable()->unique();
            $table->timestamps();
            $table->index(['state', 'owner_id']);
            $table->index(['type', 'stage']);
            $table->index('vehicle_id');
            $table->index('next_follow_up_at');
        });

        Schema::create('crm_case_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('crm_cases')->cascadeOnDelete();
            $table->morphs('linkable');
            $table->timestamps();
            $table->unique(['linkable_type', 'linkable_id']);
        });

        Schema::create('crm_step_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('stage');
            $table->string('key', 60)->unique();
            $table->string('label', 190);
            $table->string('customer_label', 190)->nullable();
            $table->boolean('required')->default(true);
            $table->boolean('customer_visible')->default(false);
            $table->string('responsible_role', 30)->nullable();
            $table->string('condition', 30)->nullable();
            $table->json('fields')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('crm_case_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('crm_cases')->cascadeOnDelete();
            $table->foreignId('step_template_id')->constrained('crm_step_templates')->cascadeOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('done_at')->nullable();
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('done_automatically')->default(false);
            $table->json('data')->nullable();
            $table->timestamps();
            $table->unique(['case_id', 'step_template_id']);
        });

        Schema::create('crm_stage_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('crm_cases')->cascadeOnDelete();
            $table->unsignedTinyInteger('stage');
            $table->timestamp('entered_at');
            $table->timestamp('left_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 255)->nullable();
            $table->index(['case_id', 'stage']);
        });

        Schema::create('crm_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('crm_cases')->cascadeOnDelete();
            $table->string('type', 30);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject', 255)->nullable();
            $table->text('body')->nullable();
            $table->json('meta')->nullable();
            $table->boolean('visible_to_customer')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['case_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });

        Schema::create('crm_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('crm_cases')->cascadeOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 190);
            $table->timestamp('due_at');
            $table->timestamp('reminded_at')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
            $table->index(['assignee_id', 'done_at', 'due_at']);
        });

        Schema::create('crm_agent_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('nickname', 40)->nullable();
            $table->date('away_from')->nullable();
            $table->date('away_until')->nullable();
            $table->string('timezone', 60)->default('Asia/Tokyo');
            $table->string('notify_mode', 20)->default('immediate');
            $table->boolean('can_pick_unassigned')->default(true);
            $table->timestamps();
        });

        Schema::create('crm_holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('name', 120);
            $table->timestamps();
        });

        Schema::create('crm_sla_timers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('crm_cases')->cascadeOnDelete();
            $table->string('measure', 30);
            $table->unsignedInteger('target_minutes');
            $table->timestamp('started_at');
            $table->timestamp('due_at');
            $table->timestamp('warn_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('warned_at')->nullable();
            $table->timestamp('breached_at')->nullable();
            $table->timestamp('met_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['measure', 'met_at', 'breached_at']);
            $table->index(['case_id', 'measure']);
        });

        Schema::create('crm_email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('name', 120);
            $table->string('audience', 20)->default('customer');
            $table->boolean('system')->default(false);
            $table->string('subject', 255);
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('crm_notification_rules', function (Blueprint $table) {
            $table->id();
            $table->string('event', 60);
            $table->string('channel', 20);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['event', 'channel']);
        });

        Schema::create('crm_notification_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->nullable()->constrained('crm_cases')->cascadeOnDelete();
            $table->string('event', 60);
            $table->string('channel', 20);
            $table->string('recipient', 190);
            $table->string('status', 20);
            $table->string('error', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['case_id', 'created_at']);
        });

        Schema::create('crm_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 10);
            $table->string('label', 120);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('crm_saved_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('list', 40);
            $table->string('name', 80);
            $table->json('filters');
            $table->timestamps();
        });

        Schema::create('crm_inbound_messages', function (Blueprint $table) {
            $table->id();
            $table->string('message_id', 190)->unique();
            $table->foreignId('case_id')->nullable()->constrained('crm_cases')->nullOnDelete();
            $table->string('from_email', 190)->nullable();
            $table->string('subject', 255)->nullable();
            $table->timestamp('received_at')->nullable();
            $table->string('outcome', 20);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('crm_settings', function (Blueprint $table) {
            $table->string('key', 80)->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        CrmDefaults::install();
    }

    public function down(): void
    {
        foreach (['crm_settings', 'crm_inbound_messages', 'crm_saved_views', 'crm_reasons', 'crm_notification_log', 'crm_notification_rules',
            'crm_email_templates', 'crm_sla_timers', 'crm_holidays', 'crm_agent_status', 'crm_tasks', 'crm_activities', 'crm_stage_history',
            'crm_case_steps', 'crm_step_templates', 'crm_case_links', 'crm_cases', 'crm_queue_members', 'crm_queues', 'crm_customers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
