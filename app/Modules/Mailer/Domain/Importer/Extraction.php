<?php

namespace App\Modules\Mailer\Domain\Importer;

use App\Modules\Mailer\Models\ApprovedSender;
use App\Modules\Mailer\Models\IgnoreRule;
use App\Modules\Mailer\Support\MailerSettings;

/**
 * Pure extraction pipeline, shared by the importer and the Rule Tester
 * (TOC-EXT-002 to 008). Makes no Brevo request and writes nothing.
 */
class Extraction
{
    /** TOC-EXT-004 system local parts (a "+tag" suffix is ignored). */
    public const SYSTEM_LOCAL_PARTS = ['noreply', 'no-reply', 'donotreply', 'do-not-reply', 'mailer-daemon', 'postmaster', 'bounce', 'bounces', 'notifications', 'notification'];

    public function __construct(
        protected AddressExtractor $extractor,
        protected AddressValidator $validator,
        protected FieldRuleEngine $rules,
        protected MailerSettings $settings,
    ) {}

    /**
     * @param  list<array{value: string, type: string}>|null  $ignoreRules  defaults to the ignore list table
     * @return array{addresses: list<array{email: string, keep: bool, reason: ?string}>, fields: array<string, string>}
     */
    public function run(ParsedMessage $message, ApprovedSender $sender, ?array $ignoreRules = null): array
    {
        $ownDomains = array_map('strtolower', (array) $this->settings->get('own_domains', []));
        $ignoreRules ??= IgnoreRule::query()->get(['value', 'type'])->toArray();
        $senderAddresses = array_filter([
            strtolower((string) $message->from),
            $sender->match_type === ApprovedSender::MATCH_ADDRESS ? strtolower($sender->match_value) : null,
        ]);

        $limit = max(1, (int) ($sender->max_per_message ?: config('mailer.import.default_max_per_message', 3)));
        $kept = 0;
        $addresses = [];

        foreach ($this->extractor->extract($message, (bool) $sender->use_reply_to) as $email) {
            $reason = $this->exclusion($email, $ownDomains, $senderAddresses, $ignoreRules)
                ?? $this->validator->problem($email);

            if ($reason === null && $kept >= $limit) {
                $reason = 'over_limit';
            }
            if ($reason === null) {
                $kept++;
            }

            $addresses[] = ['email' => $email, 'keep' => $reason === null, 'reason' => $reason];
        }

        return [
            'addresses' => $addresses,
            'fields' => $this->rules->apply($sender->field_rules, $message->bodyText()),
        ];
    }

    /**
     * @param  list<string>  $ownDomains
     * @param  list<string>  $senderAddresses
     * @param  list<array{value: string, type: string}>  $ignoreRules
     */
    protected function exclusion(string $email, array $ownDomains, array $senderAddresses, array $ignoreRules): ?string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        foreach ($ownDomains as $own) {
            if ($domain === $own || str_ends_with($domain, '.'.$own)) {
                return 'own_domain';
            }
        }

        if (in_array($email, $senderAddresses, true)) {
            return 'sender_address';
        }

        if (in_array(explode('+', $local)[0], self::SYSTEM_LOCAL_PARTS, true)) {
            return 'system_address';
        }

        foreach ($ignoreRules as $rule) {
            $value = strtolower(ltrim((string) $rule['value'], '@'));
            if ($rule['type'] === 'domain' ? ($domain === $value || str_ends_with($domain, '.'.$value)) : $email === $value) {
                return 'ignored';
            }
        }

        return null;
    }
}
