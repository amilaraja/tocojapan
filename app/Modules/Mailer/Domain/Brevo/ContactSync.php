<?php

namespace App\Modules\Mailer\Domain\Brevo;

use App\Modules\Mailer\Models\ApprovedSender;
use App\Modules\Mailer\Models\ContactImport;
use App\Modules\Mailer\Support\MailerSettings;

/**
 * Creates or updates one Brevo contact (TOC-BRV-001 to 006).
 *
 * - GET first. Blacklisted → skipped "unsubscribed"; hard bounced →
 *   skipped "bounced". The blacklist flag is never written (rule 5).
 * - SOURCE and TOCO_LAST_ENQUIRY_AT on every import; TOCO_IMPORTED_AT only
 *   when Brevo has none; FIRSTNAME/LASTNAME/COUNTRY/PHONE only when empty
 *   in Brevo (rule 6).
 * - Direct: POST /contacts with updateEnabled and the sender's lists.
 *   Confirm first: double opt-in, unless the contact is already on every
 *   target list (then only attributes are updated).
 * - BrevoUnavailable (after retries) is re-thrown so the import run fails
 *   and the message is retried next run; other errors are "failed".
 */
class ContactSync
{
    /** Brevo attribute names for extracted fields. */
    public const FIELD_ATTRIBUTES = ['FIRSTNAME', 'LASTNAME', 'COUNTRY', 'PHONE'];

    public function __construct(protected BrevoClient $client) {}

    /** Buyer attributes (TOC-BUY-005), created in Brevo by mailer:brevo:setup. */
    public const BUYER_ATTRIBUTES = ['PORT', 'BUYER_TYPE', 'COUNTRY_CODE', 'LAST_MAKE', 'LAST_MODEL', 'LAST_YEAR', 'ENQUIRY_COUNT'];

    /**
     * @param  array<string, string>  $fields  extracted FIRSTNAME, LASTNAME, COUNTRY, PHONE, STOCK_REF
     * @param  array<string, string|int>  $buyer  buyer attributes; sent only once they exist in Brevo
     */
    public function sync(string $email, array $fields, ApprovedSender $sender, array $buyer = []): SyncResult
    {
        $existing = $this->client->get('contacts/'.rawurlencode($email));
        $retries = $this->client->lastRetries;

        if ($existing->successful()) {
            $contact = $existing->json();

            if (! empty($contact['emailBlacklisted'])) {
                return new SyncResult(ContactImport::OUTCOME_SKIPPED, 'unsubscribed', $existing->status(), $retries);
            }
            if (! empty($contact['statistics']['hardBounces'])) {
                return new SyncResult(ContactImport::OUTCOME_SKIPPED, 'bounced', $existing->status(), $retries);
            }
        } elseif ($existing->status() !== 404) {
            return new SyncResult(ContactImport::OUTCOME_FAILED, 'brevo_error', $existing->status(), $retries);
        } else {
            $contact = null;
        }

        $attributes = $this->attributes($contact['attributes'] ?? [], $fields, $sender, isNew: $contact === null) + $this->buyerAttributes($buyer);
        $lists = array_values(array_map('intval', $sender->brevo_list_ids ?? []));

        if ($sender->consent_mode === ApprovedSender::CONSENT_CONFIRM
            && array_diff($lists, array_map('intval', $contact['listIds'] ?? [])) !== []) {
            $response = $this->client->post('contacts/doubleOptinConfirmation', [
                'email' => $email,
                'attributes' => (object) $attributes,
                'includeListIds' => $lists,
                'templateId' => (int) $sender->doi_template_id,
                'redirectionUrl' => (string) $sender->doi_redirect_url,
            ]);

            return $response->successful()
                ? new SyncResult($contact === null ? ContactImport::OUTCOME_ADDED : ContactImport::OUTCOME_UPDATED, 'confirmation_sent', $response->status(), $this->client->lastRetries)
                : new SyncResult(ContactImport::OUTCOME_FAILED, 'brevo_error', $response->status(), $this->client->lastRetries);
        }

        $payload = [
            'email' => $email,
            'attributes' => (object) $attributes,
            'updateEnabled' => true,
        ];
        // Confirm-first contacts already on every list only get attributes.
        if ($sender->consent_mode !== ApprovedSender::CONSENT_CONFIRM && $lists !== []) {
            $payload['listIds'] = $lists;
        }

        $response = $this->client->post('contacts', $payload);

        if (! $response->successful()) {
            return new SyncResult(ContactImport::OUTCOME_FAILED, 'brevo_error', $response->status(), $this->client->lastRetries);
        }

        return new SyncResult(
            $contact === null ? ContactImport::OUTCOME_ADDED : ContactImport::OUTCOME_UPDATED,
            null,
            $response->status(),
            $this->client->lastRetries,
        );
    }

    /**
     * Fill in details on an existing Brevo contact without touching its
     * lists, SOURCE or dates (buyer backfill, TOC-BUY-006). Same rules as
     * sync(): blacklisted / bounced contacts are skipped and names,
     * country and phone are only written when empty in Brevo. A contact
     * that is not in Brevo is left alone.
     *
     * @param  array<string, string>  $fields
     * @param  array<string, string|int>  $buyer
     */
    public function enrich(string $email, array $fields, array $buyer): SyncResult
    {
        $existing = $this->client->get('contacts/'.rawurlencode($email));
        if ($existing->status() === 404) {
            return new SyncResult(ContactImport::OUTCOME_SKIPPED, 'not_in_brevo', 404, $this->client->lastRetries);
        }
        if (! $existing->successful()) {
            return new SyncResult(ContactImport::OUTCOME_FAILED, 'brevo_error', $existing->status(), $this->client->lastRetries);
        }
        $contact = $existing->json();
        if (! empty($contact['emailBlacklisted'])) {
            return new SyncResult(ContactImport::OUTCOME_SKIPPED, 'unsubscribed', $existing->status(), $this->client->lastRetries);
        }
        if (! empty($contact['statistics']['hardBounces'])) {
            return new SyncResult(ContactImport::OUTCOME_SKIPPED, 'bounced', $existing->status(), $this->client->lastRetries);
        }

        $current = $contact['attributes'] ?? [];
        $attributes = $this->buyerAttributes($buyer);
        foreach (self::FIELD_ATTRIBUTES as $name) {
            if (filled($fields[$name] ?? null) && blank($current[$name] ?? null)) {
                $attributes[$name] = $fields[$name];
            }
        }
        if ($attributes === []) {
            return new SyncResult(ContactImport::OUTCOME_SKIPPED, 'nothing_to_add', $existing->status(), $this->client->lastRetries);
        }

        $response = $this->client->put('contacts/'.rawurlencode($email), ['attributes' => (object) $attributes]);

        return $response->successful()
            ? new SyncResult(ContactImport::OUTCOME_UPDATED, null, $response->status(), $this->client->lastRetries)
            : new SyncResult(ContactImport::OUTCOME_FAILED, 'brevo_error', $response->status(), $this->client->lastRetries);
    }

    /**
     * @param  array<string, string|int>  $buyer
     * @return array<string, string|int>
     */
    protected function buyerAttributes(array $buyer): array
    {
        if ($buyer === [] || ! app(MailerSettings::class)->get('brevo_buyer_attributes')) {
            return [];
        }

        return array_intersect_key($buyer, array_flip(self::BUYER_ATTRIBUTES));
    }

    /**
     * @param  array<string, mixed>  $current  attributes already in Brevo
     * @param  array<string, string>  $fields
     * @return array<string, string>
     */
    protected function attributes(array $current, array $fields, ApprovedSender $sender, bool $isNew): array
    {
        $today = now((string) config('mailer.display_timezone'))->toDateString();

        $attributes = [
            'SOURCE' => $sender->label,
            'TOCO_LAST_ENQUIRY_AT' => $today,
        ];

        if ($isNew || blank($current['TOCO_IMPORTED_AT'] ?? null)) {
            $attributes['TOCO_IMPORTED_AT'] = $today;
        }

        foreach (self::FIELD_ATTRIBUTES as $name) {
            if (filled($fields[$name] ?? null) && blank($current[$name] ?? null)) {
                $attributes[$name] = $fields[$name];
            }
        }

        return $attributes;
    }
}
