<?php

namespace App\Modules\Crm\Domain;

use App\Models\User;
use App\Modules\Crm\Jobs\SendCrmEmail;
use App\Modules\Crm\Mail\CrmMail;
use App\Modules\Crm\Models\CrmAgentStatus;
use App\Modules\Crm\Models\CrmCase;
use App\Modules\Crm\Models\CrmNotificationLog;
use App\Modules\Crm\Support\CrmAccess;
use App\Modules\Crm\Support\CrmSettings;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The notification matrix (SRS §8.1, CRM-NTF-001..006). Every send is checked
 * against crm_notification_rules, logged, and can never break the caller.
 */
class Notifier
{
    /** Events that always email immediately, even for staff on "digest only" (CRM-NTF-004). */
    public const URGENT = ['sla_breached', 'case_assigned', 'case_created', 'vehicle_conflict', 'case_unassigned'];

    /** Customer email template per event. */
    public const CUSTOMER_TEMPLATES = [
        'case_created' => 'acknowledgement',
        'stage_changed' => 'stage_changed',
        'payment_received' => 'payment_received',
        'vessel_booked' => 'vessel_booked',
        'bl_issued' => 'bl_issued',
        'documents_couriered' => 'documents_couriered',
        'vessel_arrived' => 'vessel_arrived',
        'payment_due' => 'payment_due',
        'case_won' => 'won_thanks',
    ];

    /** Cases that got a step email in this request: their stage email is skipped (one email per change). */
    private static array $customerMailed = [];

    /** @var array<string, bool>|null */
    private static ?array $rules = null;

    /** Set during the go-live import: nothing is sent to anyone. */
    public static bool $silent = false;

    /** @param array<string, mixed> $context */
    public static function event(string $event, CrmCase $case, array $context = []): void
    {
        if (self::$silent) {
            return;
        }
        try {
            if (! ($context['only_customer'] ?? false)) {
                self::staff($event, $case, $context);
            }
            self::customer($event, $case, $context);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public static function enabled(string $event, string $channel): bool
    {
        self::$rules ??= DB::table('crm_notification_rules')->get()
            ->mapWithKeys(fn ($rule) => [$rule->event.'|'.$rule->channel => (bool) $rule->enabled])->all();

        return self::$rules[$event.'|'.$channel] ?? false;
    }

    public static function flushRules(): void
    {
        self::$rules = null;
        self::$customerMailed = [];
    }

    /** @param array<string, mixed> $context */
    private static function staff(string $event, CrmCase $case, array $context): void
    {
        [$title, $body] = self::describe($event, $case, $context);
        $url = $context['url'] ?? self::caseUrl($case);

        // Who hears about it (SRS §8.1).
        $owner = $case->owner_id ? User::find($case->owner_id) : null;
        $admins = CrmAccess::admins();
        $primary = match ($event) {
            'task_due' => collect([isset($context['assignee_id']) ? User::find($context['assignee_id']) : $owner]),
            'vehicle_conflict' => collect([$owner]),
            default => collect([$owner]),
        };
        $primary = $primary->filter();

        $adminInApp = match ($event) {
            'case_created', 'customer_message' => $owner === null,
            'case_assigned' => (bool) ($context['reassigned'] ?? false),
            'sla_breached', 'payment_received', 'case_won', 'case_lost', 'case_unassigned' => true,
            'case_stale' => (bool) ($context['admin'] ?? false),
            default => false,
        };
        $adminEmail = match ($event) {
            // Unassigned work goes to the CRM admins until someone owns it (CRM-INT-004).
            'case_created', 'customer_message' => $owner === null,
            'sla_breached', 'case_unassigned' => true,
            'case_stale' => (bool) ($context['admin'] ?? false),
            default => false,
        };

        // Stale at 5 days goes to admins only (the owner heard at 3 days); unassigned has no owner.
        if (($event === 'case_stale' && ($context['admin'] ?? false)) || $event === 'case_unassigned') {
            $primary = collect();
        }

        $inApp = $primary->merge($adminInApp ? $admins : [])->unique('id');
        $emails = $primary->merge($adminEmail ? $admins : [])->unique('id');

        if (self::enabled($event, 'inapp') && $inApp->isNotEmpty()) {
            self::inApp($inApp, $title, $body, $url, $event, $case);
        }

        if (self::enabled($event, 'email')) {
            if ($adminEmail) {
                foreach (self::extraAdminEmails() as $address) {
                    self::queue($address, new CrmMail(mailSubject: $title.' · '.$case->reference, text: $body, button: ['label' => 'Open in CRM', 'url' => $url]), $event, 'email', $case->id);
                }
            }
            foreach ($emails as $user) {
                if (! $user->email || ! self::wantsImmediate($user, $event)) {
                    continue;
                }
                self::queue($user->email, new CrmMail(
                    mailSubject: $title.' · '.$case->reference,
                    text: $body,
                    button: ['label' => 'Open in CRM', 'url' => $url],
                ), $event, 'email', $case->id);
            }
        }
    }

    /** @param array<string, mixed> $context */
    private static function customer(string $event, CrmCase $case, array $context): void
    {
        $template = self::CUSTOMER_TEMPLATES[$event] ?? null;
        if ($template === null || ! self::enabled($event, 'customer_email') || ($context['skip_customer'] ?? false)) {
            return;
        }
        // Never to spam, duplicates or merged Cases (CRM-NTF-006).
        if ($case->merged_into_id || ($case->state === Crm::STATE_CLOSED && in_array($case->reason, ['Spam', 'Duplicate'], true))) {
            return;
        }
        if ($event === 'stage_changed' && isset(self::$customerMailed[$case->id])) {
            return;
        }

        $to = CustomerMatcher::normalize($case->customer?->email);
        if ($to === null) {
            return;
        }

        $rendered = TemplateRenderer::template($template, $case, $context['placeholders'] ?? []);
        if ($rendered === null) {
            return;
        }

        self::sendToCustomer($case, $rendered['subject'], $rendered['body'], $event);
        if ($event !== 'stage_changed') {
            self::$customerMailed[$case->id] = true;
        }
    }

    /** Customer email with branding, tracker and Reply-To the owner (CRM-NTF-003). Also used by "Email from Case". */
    public static function sendToCustomer(CrmCase $case, string $subject, string $body, string $event = 'manual', ?User $from = null): void
    {
        $to = CustomerMatcher::normalize($case->customer?->email);
        if ($to === null) {
            return;
        }
        $owner = $from ?? $case->owner;

        $nickname = TemplateRenderer::nickname($case, $owner);
        $team = (string) CrmSettings::get('email.sender_name');

        self::queue($to, new CrmMail(
            mailSubject: $subject,
            text: $body,
            // Replies reach the owner; the buyer sees the nickname, never the real name.
            replyToEmail: $owner?->email ?: (string) config('mail.from.address'),
            replyToName: $nickname ? $nickname.' · TOCO Japan' : $team,
            fromName: $nickname ? $nickname.' · TOCO Japan' : $team,
            tracker: $case->isVehicleSale() ? ['stage' => $case->stage, 'labels' => Crm::STAGES] : null,
            button: ['label' => $case->isVehicleSale() ? 'See your purchase' : 'See your enquiry', 'url' => $case->portalUrl()],
            forCustomer: true,
        ), $event, 'customer_email', $case->id);
    }

    /** @param Collection<int, User> $users */
    private static function inApp(Collection $users, string $title, string $body, string $url, string $event, CrmCase $case): void
    {
        try {
            Notification::make()
                ->title($title)
                ->body(mb_strimwidth($body, 0, 300, '…'))
                ->icon(self::icon($event))
                ->status(in_array($event, ['sla_breached', 'vehicle_conflict'], true) ? 'danger' : (in_array($event, ['sla_warning', 'case_stale', 'task_due'], true) ? 'warning' : 'info'))
                ->actions([Action::make('open')->label('Open')->url($url)->markAsRead()])
                ->sendToDatabase($users);
            foreach ($users as $user) {
                CrmNotificationLog::create(['case_id' => $case->id, 'event' => $event, 'channel' => 'inapp', 'recipient' => (string) $user->email, 'status' => 'sent']);
            }
        } catch (Throwable $e) {
            Log::error('CRM in-app notification failed', ['event' => $event, 'error' => $e->getMessage()]);
        }
    }

    public static function queue(string $to, CrmMail $mail, string $event, string $channel, ?int $caseId = null): void
    {
        if (self::$silent) {
            return;
        }
        try {
            SendCrmEmail::dispatch($to, $mail, $event, $channel, $caseId);
        } catch (Throwable $e) {
            CrmNotificationLog::create(['case_id' => $caseId, 'event' => $event, 'channel' => $channel, 'recipient' => $to, 'status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);
        }
    }

    /** @return list<string> */
    public static function extraAdminEmails(): array
    {
        $staff = CrmAccess::admins()->pluck('email')->map(fn ($e) => mb_strtolower((string) $e))->all();
        $list = preg_split('/[\s,;]+/', (string) CrmSettings::get('alerts.extra_admin_emails'), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_diff(array_unique(array_filter(array_map('mb_strtolower', $list), fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))), $staff));
    }

    private static function wantsImmediate(User $user, string $event): bool
    {
        if (in_array($event, self::URGENT, true)) {
            return true;
        }

        return CrmAgentStatus::where('user_id', $user->id)->value('notify_mode') !== 'digest';
    }

    /** @param array<string, mixed> $context @return array{0: string, 1: string} */
    public static function describe(string $event, CrmCase $case, array $context = []): array
    {
        $case->loadMissing(['customer', 'vehicle']);
        $who = $case->customer?->displayName() ?? 'Customer';
        $what = $case->vehicle ? trim($case->vehicle->title).' ('.$case->vehicle->stock_no.')' : (Crm::TYPES[$case->type] ?? 'Enquiry');
        $summary = "{$who} · {$what}";

        return match ($event) {
            'case_created' => ['New '.mb_strtolower(Crm::TYPES[$case->type] ?? 'case').($case->owner_id ? ' assigned to you' : ' (unassigned)'), $summary."\nSource: ".(Crm::SOURCES[$case->source] ?? $case->source).(isset($context['message']) ? "\n\n".$context['message'] : '')],
            'case_assigned' => ['Case assigned to you', $summary.(isset($context['note']) && $context['note'] ? "\nNote: ".$context['note'] : '')],
            'customer_message' => ['New message from '.$who, $summary."\n\n".($context['message'] ?? '')],
            'sla_warning' => ['Reply due soon: '.($context['measure_label'] ?? 'SLA'), $summary."\nDue ".($context['due'] ?? '')],
            'sla_breached' => ['SLA breached: '.($context['measure_label'] ?? 'SLA'), $summary."\nWas due ".($context['due'] ?? '').($case->owner ? "\nOwner: ".$case->owner->name : "\nNot assigned")],
            'case_stale' => ['No activity for '.($context['days'] ?? 3).' days', $summary.($case->owner ? "\nOwner: ".$case->owner->name : '')],
            'task_due' => ['Task due: '.($context['task'] ?? ''), $summary],
            'stage_changed' => ['Moved to '.Crm::stageLabel($case->stage), $summary],
            'payment_received' => ['Payment received', $summary],
            'vessel_booked' => ['Vessel booked', $summary],
            'bl_issued' => ['B/L issued', $summary],
            'documents_couriered' => ['Documents couriered', $summary],
            'vessel_arrived' => ['Vessel arrived', $summary],
            'case_won' => ['Case won', $summary],
            'case_lost' => ['Case lost: '.($case->reason ?? ''), $summary],
            'payment_due' => ['Payment due soon', $summary],
            'case_unassigned' => ['Nobody owns this Case yet', $summary."\nWaiting since ".($context['since'] ?? '')],
            'daily_digest' => ['Daily CRM summary', $context['message'] ?? ''],
            'vehicle_conflict' => [$context['title'] ?? 'Vehicle taken by another buyer', $summary."\n".($context['message'] ?? '')],
            default => [ucfirst(str_replace('_', ' ', $event)), $summary],
        };
    }

    public static function caseUrl(CrmCase $case): string
    {
        return url('/crm/cases/'.$case->id);
    }

    private static function icon(string $event): string
    {
        return match ($event) {
            'customer_message' => 'heroicon-o-chat-bubble-left-right',
            'sla_warning', 'sla_breached' => 'heroicon-o-clock',
            'payment_received' => 'heroicon-o-banknotes',
            'vessel_booked', 'bl_issued', 'vessel_arrived' => 'heroicon-o-truck',
            'vehicle_conflict' => 'heroicon-o-exclamation-triangle',
            'task_due' => 'heroicon-o-check-circle',
            default => 'heroicon-o-inbox',
        };
    }
}
