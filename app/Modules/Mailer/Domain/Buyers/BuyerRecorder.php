<?php

namespace App\Modules\Mailer\Domain\Buyers;

use App\Modules\Mailer\Domain\Importer\ParsedMessage;
use App\Modules\Mailer\Models\ApprovedSender;
use App\Modules\Mailer\Models\Buyer;
use App\Modules\Mailer\Models\BuyerEnquiry;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

/**
 * Keeps the buyer database (TOC-BUY-003/004): one buyer per email, one
 * enquiry per message. A newer message updates the buyer's details; an
 * older one (backfill) only fills what is still empty, so the order in
 * which messages are read does not matter. Recording a message twice is
 * a no-op.
 */
class BuyerRecorder
{
    public const PROFILE_FIELDS = ['title', 'first_name', 'last_name', 'country', 'country_code', 'port', 'phone', 'phone_e164', 'buyer_type'];

    /** @param  array<string, mixed>  $details  BuyerDetailsParser::parse() output */
    public function record(string $email, ParsedMessage $message, ApprovedSender $sender, array $details): Buyer
    {
        try {
            return $this->write($email, $message, $sender, $details);
        } catch (UniqueConstraintViolationException) {
            // The importer and the backfill created the same buyer at the same moment: the row exists now.
            return $this->write($email, $message, $sender, $details);
        }
    }

    /** @param  array<string, mixed>  $details */
    protected function write(string $email, ParsedMessage $message, ApprovedSender $sender, array $details): Buyer
    {
        $email = mb_strtolower(trim($email));
        $at = $message->receivedAt ?? now()->toImmutable();

        $buyer = Buyer::query()->firstOrNew(['email' => $email]);
        $isNewer = ! $buyer->exists || $buyer->last_enquiry_at === null || $at->greaterThanOrEqualTo($buyer->last_enquiry_at);

        foreach (self::PROFILE_FIELDS as $field) {
            $value = $details[$field] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            if ($isNewer || blank($buyer->{$field})) {
                $buyer->{$field} = $value;
            }
        }
        $buyer->approved_sender_id ??= $sender->id;
        if (! $buyer->first_enquiry_at || $at->lessThan($buyer->first_enquiry_at)) {
            $buyer->first_enquiry_at = Carbon::instance($at);
        }
        if ($isNewer) {
            $buyer->last_enquiry_at = Carbon::instance($at);
        }
        $buyer->save();

        $enquiry = BuyerEnquiry::query()->firstOrCreate(
            ['buyer_id' => $buyer->id, 'gmail_message_id' => $message->id],
            [
                'approved_sender_id' => $sender->id,
                'received_at' => $at,
                'kind' => $details['kind'] ?? 'other',
                'make' => $details['make'] ?? null,
                'model' => $details['model'] ?? null,
                'year' => $details['year'] ?? null,
                'drive' => $details['drive'] ?? null,
                'budget' => $details['budget'] ?? null,
                'country_code' => $details['country_code'] ?? null,
                'port' => $details['port'] ?? null,
                'details' => ($details['details'] ?? []) ?: null,
            ],
        );

        if ($enquiry->wasRecentlyCreated) {
            $buyer->forceFill(['enquiry_count' => $buyer->enquiries()->count()])->save();
        }

        return $buyer;
    }

    /**
     * Brevo contact attributes for a buyer (TOC-BUY-005). Only sent once
     * `mailer:brevo:setup` has created them.
     *
     * @return array<string, string|int>
     */
    public static function brevoAttributes(Buyer $buyer): array
    {
        $last = $buyer->latestEnquiry()->first();

        return array_filter([
            'PORT' => $buyer->port,
            'BUYER_TYPE' => $buyer->buyer_type ? (Buyer::TYPES[$buyer->buyer_type] ?? $buyer->buyer_type) : null,
            'COUNTRY_CODE' => $buyer->country_code,
            'LAST_MAKE' => $last?->make,
            'LAST_MODEL' => $last?->model,
            'LAST_YEAR' => $last?->year,
            'ENQUIRY_COUNT' => $buyer->enquiry_count ?: null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * FIRSTNAME / LASTNAME / COUNTRY / PHONE from the buyer details, for
     * senders without hand-written field rules. Field rules win.
     *
     * @param  array<string, mixed>  $details
     * @return array<string, string>
     */
    public static function contactFields(array $details): array
    {
        return array_filter([
            'FIRSTNAME' => $details['first_name'] ?? null,
            'LASTNAME' => $details['last_name'] ?? null,
            'COUNTRY' => $details['country'] ?? null,
            'PHONE' => $details['phone_e164'] ?? ($details['phone'] ?? null),
        ], fn ($v) => $v !== null && $v !== '');
    }
}
