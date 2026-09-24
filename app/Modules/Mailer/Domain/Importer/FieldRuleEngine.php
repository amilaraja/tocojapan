<?php

namespace App\Modules\Mailer\Domain\Importer;

/**
 * Per-sender field rules (TOC-EXT-006). A rule is a field plus a pattern
 * typed by an admin, e.g. "Name: (.+)". The first capture group is the
 * value. "name" is split into FIRSTNAME / LASTNAME.
 */
class FieldRuleEngine
{
    /** @var array<string, string> rule field => Brevo attribute */
    public const FIELDS = [
        'name' => 'Full name',
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'country' => 'Country',
        'phone' => 'Phone',
        'stock_ref' => 'Vehicle stock ref',
    ];

    protected const ATTRIBUTE = [
        'first_name' => 'FIRSTNAME',
        'last_name' => 'LASTNAME',
        'country' => 'COUNTRY',
        'phone' => 'PHONE',
        'stock_ref' => 'STOCK_REF',
    ];

    public static function compile(string $pattern): string
    {
        return '~'.str_replace('~', '\~', $pattern).'~miu';
    }

    /** Admin-typed pattern is a valid expression with at least one capture group. */
    public static function isValid(string $pattern): bool
    {
        if (trim($pattern) === '') {
            return false;
        }

        $re = self::compile($pattern);

        return @preg_match($re, '') !== false && preg_match('/(?<!\\\\)\((?!\?)/', $pattern) === 1;
    }

    /**
     * @param  list<array{field: string, pattern: string}>|null  $rules
     * @return array<string, string> FIRSTNAME, LASTNAME, COUNTRY, PHONE, STOCK_REF
     */
    public function apply(?array $rules, string $text): array
    {
        $out = [];

        foreach ($rules ?? [] as $rule) {
            $field = $rule['field'] ?? null;
            $pattern = (string) ($rule['pattern'] ?? '');
            if (! isset(self::FIELDS[$field]) || ! self::isValid($pattern)) {
                continue;
            }
            if (! preg_match(self::compile($pattern), $text, $m) || ! isset($m[1])) {
                continue;
            }

            $value = mb_substr(trim(preg_replace('/\s+/u', ' ', $m[1]) ?? ''), 0, 100);
            if ($value === '') {
                continue;
            }

            if ($field === 'name') {
                $parts = explode(' ', $value, 2);
                $out['FIRSTNAME'] ??= $parts[0];
                if (isset($parts[1])) {
                    $out['LASTNAME'] ??= $parts[1];
                }

                continue;
            }

            $out[self::ATTRIBUTE[$field]] ??= $value;
        }

        return $out;
    }
}
