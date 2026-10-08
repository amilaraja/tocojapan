<?php

namespace App\Modules\Mailer\Domain\Buyers;

use App\Modules\Mailer\Domain\Importer\ParsedMessage;

/**
 * Reads labelled lines ("Country : Kenya") from an enquiry email into
 * buyer and vehicle details (TOC-BUY-001). Pure: writes nothing.
 *
 * The buyer's free-text message is skipped on purpose: only extracted
 * fields may be stored (TOC-LOG-003), and labels typed inside a message
 * must not be mistaken for form fields.
 */
class BuyerDetailsParser
{
    /** Form label (lower case, no trailing dots) => field. Covers Japanese Car Trade and common portal wording. */
    public const LABELS = [
        'name' => 'name', 'full name' => 'name', 'sender name' => 'name', 'customer name' => 'name', 'your name' => 'name', 'contact name' => 'name',
        'country' => 'country', 'your country' => 'country', 'country of residence' => 'country', 'destination country' => 'country',
        'port of destination' => 'port', 'destination port' => 'port', 'port' => 'port', 'discharge port' => 'port', 'nearest port' => 'port',
        'phone/mobile' => 'phone', 'phone' => 'phone', 'mobile' => 'phone', 'tel' => 'phone', 'telephone' => 'phone', 'whatsapp' => 'phone',
        'phone number' => 'phone', 'mobile number' => 'phone', 'contact number' => 'phone', 'contact no' => 'phone',
        'i am' => 'buyer_type', 'buyer type' => 'buyer_type', 'customer type' => 'buyer_type', 'you are' => 'buyer_type',
        'make' => 'make', 'maker' => 'make', 'manufacturer' => 'make', 'brand' => 'make',
        'model name' => 'model', 'model' => 'model',
        'mfg. year' => 'year', 'mfg year' => 'year', 'year' => 'year', 'manufacture year' => 'year', 'model year' => 'year', 'year of manufacture' => 'year', 'registration year' => 'year',
        'drive' => 'drive', 'steering' => 'drive', 'handle' => 'drive',
        'budget' => 'budget', 'price range' => 'budget', 'your budget' => 'budget',
        'transmission' => 'transmission', 'fuel type' => 'fuel', 'fuel' => 'fuel', 'color' => 'color', 'colour' => 'color',
        'engine cc' => 'engine_cc', 'engine size' => 'engine_cc', 'cc' => 'engine_cc', 'vehicle type' => 'vehicle_type', 'body type' => 'vehicle_type',
        'grade' => 'grade', 'chassis no' => 'chassis_no', 'chassis number' => 'chassis_no', 'engine model' => 'engine_model',
        'model code' => 'model_code', 'ship by' => 'ship_by', 'shipping method' => 'ship_by', 'mileage' => 'mileage',
    ];

    /** A line with one of these labels starts the buyer's own text, skipped until the next section header. */
    public const FREE_TEXT_LABELS = ['message', 'messages', 'comment', 'comments', 'your message', 'enquiry', 'inquiry', 'additional information', 'remarks'];

    /** Fields kept on the enquiry's "details" (the rest are columns). */
    public const DETAIL_FIELDS = ['transmission', 'fuel', 'color', 'engine_cc', 'vehicle_type', 'grade', 'chassis_no', 'engine_model', 'model_code', 'ship_by', 'mileage'];

    public function __construct(protected BuyerNormalizer $normalize) {}

    /**
     * @return array{
     *   title?: ?string, first_name?: ?string, last_name?: ?string, country?: ?string, country_code?: ?string,
     *   port?: ?string, phone?: ?string, phone_e164?: ?string, buyer_type?: ?string,
     *   kind: string, make?: ?string, model?: ?string, year?: ?int, drive?: ?string, budget?: ?string,
     *   details: array<string, mixed>
     * }
     */
    public function parse(ParsedMessage $message): array
    {
        $raw = $this->labelled($message->bodyText());
        $n = $this->normalize;

        $out = ['kind' => $this->kind((string) $message->subject), 'details' => []];
        if (isset($raw['name'])) {
            $out += $n->name($raw['name']);
        }
        if (isset($raw['country'])) {
            $out['country'] = $n->text($raw['country'], 80);
            $out['country_code'] = $n->countryCode($raw['country']);
        }
        $out['port'] = $n->text($raw['port'] ?? null);
        if (isset($raw['phone']) && ! in_array(mb_strtolower(trim($raw['phone'])), BuyerNormalizer::NO_PHONE, true)) {
            $out['phone'] = $n->text($raw['phone'], 60);
            $out['phone_e164'] = $n->phoneE164($raw['phone'], $out['country_code'] ?? null);
        }
        $out['buyer_type'] = $n->buyerType($raw['buyer_type'] ?? null);
        $out['make'] = $n->text($raw['make'] ?? null, 80);
        $out['model'] = $n->text($raw['model'] ?? null);
        $out['year'] = $n->year($raw['year'] ?? null);
        $out['drive'] = $n->drive($raw['drive'] ?? null);
        $out['budget'] = $n->text($raw['budget'] ?? null, 80);

        foreach (self::DETAIL_FIELDS as $field) {
            if (($v = $n->text($raw[$field] ?? null)) !== null) {
                $out['details'][$field] = $v;
            }
        }
        // Reference numbers anywhere in the email are extracted values, not text.
        $text = $message->bodyText().' '.$message->subject;
        if (preg_match_all('/\bJCT-?\d{4,}\b/i', $text, $m)) {
            $out['details']['portal_refs'] = array_values(array_unique(array_map('strtoupper', $m[0])));
        }
        if (preg_match_all('/\bE\d{5}\b/', $text, $m)) {
            $out['details']['toco_stock_refs'] = array_values(array_unique($m[0]));
        }

        return $out;
    }

    /**
     * True when the message holds at least one buyer or vehicle field.
     *
     * @param  array<string, mixed>  $parsed
     */
    public static function hasDetails(array $parsed): bool
    {
        foreach (['first_name', 'country', 'phone', 'port', 'make', 'model'] as $key) {
            if (! empty($parsed[$key])) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, string> field => raw value (first occurrence wins) */
    protected function labelled(string $text): array
    {
        $out = [];
        $inFreeText = false;

        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            if (! preg_match('/^\s*([^\s:][^:]{0,40}?)\s*:\s*(.*)$/u', $line, $m)) {
                continue;
            }
            $label = rtrim(mb_strtolower(trim(preg_replace('/\s+/u', ' ', $m[1]) ?? '')), '.');
            $value = trim($m[2]);

            if (in_array($label, self::FREE_TEXT_LABELS, true)) {
                $inFreeText = true;

                continue;
            }
            $field = self::LABELS[$label] ?? null;
            // Only a section header with no value ("Sender Information :") ends the
            // buyer's text, so "Budget : 5000" typed inside a message is not a form field.
            if ($inFreeText && $value === '') {
                $inFreeText = false;
            }
            if ($inFreeText || $field === null || $value === '') {
                continue;
            }
            $out[$field] ??= $value;
        }

        return $out;
    }

    protected function kind(string $subject): string
    {
        $s = mb_strtolower($subject);

        return match (true) {
            str_contains($s, 'auction') => 'auction',
            (bool) preg_match('/buyers?\'?\s*(inquiry|enquiry|request)|request for/', $s) => 'request',
            (bool) preg_match('/inquiry|enquiry|quote|price/', $s) => 'stock',
            default => 'other',
        };
    }
}
