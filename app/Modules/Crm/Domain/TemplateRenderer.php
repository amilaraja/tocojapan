<?php

namespace App\Modules\Crm\Domain;

use App\Modules\Crm\Models\CrmCase;
use App\Modules\Crm\Models\CrmEmailTemplate;
use App\Modules\Crm\Support\CrmSettings;
use App\Settings\PaymentSettings;

/**
 * Fills {placeholders} in email templates (CRM-ACT-002). Bank details only
 * ever come from Site settings → Payment: staff write {bank_details}, never
 * the account numbers themselves (proposal WEB-TOC-02 p.6).
 */
class TemplateRenderer
{
    public const PLACEHOLDERS = [
        'customer_name', 'reference', 'vehicle', 'vehicle_phrase', 'vehicle_url', 'stock_no', 'price', 'port', 'country', 'stage',
        'stage_name', 'stage_description', 'owner_name', 'sender_name', 'portal_url', 'vessel', 'voyage', 'etd', 'eta', 'bl_number',
        'courier', 'tracking_number', 'amount', 'payment_method', 'date', 'due_date', 'bank_details', 'review_url',
    ];

    /** @param array<string, mixed> $extra @return array{subject: string, body: string} */
    public static function template(string $key, CrmCase $case, array $extra = []): ?array
    {
        $template = CrmEmailTemplate::byKey($key);

        return $template ? self::render($template->subject, $template->body, $case, $extra) : null;
    }

    /** @param array<string, mixed> $extra @return array{subject: string, body: string} */
    public static function render(string $subject, string $body, CrmCase $case, array $extra = []): array
    {
        $values = self::values($case, $extra);
        $fill = fn (string $text) => preg_replace_callback('/\{([a-z_]+)\}/', fn ($m) => array_key_exists($m[1], $values) ? (string) $values[$m[1]] : $m[0], $text);

        $subject = trim((string) $fill($subject));
        // The reference in the subject lets buyer replies find their Case (plan P7).
        if (! str_contains($subject, $case->reference)) {
            $subject .= ' ['.$case->reference.']';
        }

        return ['subject' => $subject, 'body' => (string) $fill($body)];
    }

    /** @param array<string, mixed> $extra @return array<string, string> */
    public static function values(CrmCase $case, array $extra = []): array
    {
        $case->loadMissing(['customer', 'vehicle', 'destPort.country', 'destCountry', 'owner']);
        $steps = StageEngine::stepData($case);
        $vehicle = $case->vehicle;
        $tz = 'Asia/Tokyo';
        $date = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->timezone($tz)->format('j M Y') : '';
        $nickname = self::nickname($case);

        $price = $case->value_usd !== null ? 'US$ '.number_format((float) $case->value_usd).($case->value_basis ? ' '.$case->value_basis : '') : '';

        $values = [
            'customer_name' => $case->customer?->name ?: 'Customer',
            'reference' => $case->reference,
            'vehicle' => $vehicle ? trim($vehicle->title) : 'your vehicle',
            'vehicle_phrase' => $vehicle ? ' about '.trim($vehicle->title).' (stock no. '.$vehicle->stock_no.')' : '',
            'vehicle_url' => $vehicle ? url('/vehicles/'.$vehicle->slug) : '',
            'stock_no' => (string) ($vehicle?->stock_no ?? ''),
            'price' => $price,
            'port' => $case->destPort?->name ?? '',
            'country' => $case->destCountry?->name ?? $case->destPort?->country?->name ?? '',
            'stage' => Crm::stageLabel($case->stage),
            'stage_name' => Crm::STAGES[$case->stage] ?? '',
            'stage_description' => Crm::STAGE_DESCRIPTIONS[$case->stage] ?? '',
            // Buyers only ever see the nickname, never the staff member's real name.
            'owner_name' => $nickname ?? (string) CrmSettings::get('email.sender_name'),
            'sender_name' => $nickname ? $nickname."\nTOCO International Co., Ltd" : (string) CrmSettings::get('email.sender_name'),
            'portal_url' => $case->portalUrl(),
            'vessel' => (string) ($steps['vessel_booked']['vessel_name'] ?? ''),
            'voyage' => isset($steps['vessel_booked']['voyage_no']) ? 'voyage '.$steps['vessel_booked']['voyage_no'] : '',
            'etd' => $date($steps['vessel_booked']['etd'] ?? null),
            'eta' => $date($steps['bl_issued']['eta'] ?? null),
            'bl_number' => (string) ($steps['bl_issued']['bl_number'] ?? ''),
            'courier' => (string) ($steps['documents_couriered']['courier'] ?? ''),
            'tracking_number' => (string) ($steps['documents_couriered']['tracking_number'] ?? ''),
            'amount' => isset($steps['payment_received']['amount']) && $steps['payment_received']['amount'] !== '' ? 'US$ '.number_format((float) $steps['payment_received']['amount'], 2) : $price,
            'payment_method' => Crm::PAYMENT_MODES[$steps['payment_received']['payment_method'] ?? $case->payment_mode] ?? '',
            'date' => $date(now()),
            'due_date' => $date($case->slaTimers()->where('measure', 'payment')->latest('id')->value('due_at') ?? now()->addDays(CrmSettings::int('sla.payment_due_days'))),
            'bank_details' => self::bankDetails(),
            'review_url' => (string) (CrmSettings::get('email.review_url') ?: url('/customer-reviews')),
        ];

        return array_merge($values, array_map(fn ($v) => (string) $v, $extra));
    }

    /** Nickname of the Case owner when nicknames are shown, else null. */
    public static function nickname(CrmCase $case, ?\App\Models\User $from = null): ?string
    {
        if (! CrmSettings::get('email.show_nickname')) {
            return null;
        }

        return \App\Modules\Crm\Models\CrmAgentStatus::nicknameFor($from?->id ?? $case->owner_id);
    }

    public static function bankDetails(): string
    {
        try {
            return trim((string) app(PaymentSettings::class)->bank_account_details);
        } catch (\Throwable) {
            return '';
        }
    }
}
