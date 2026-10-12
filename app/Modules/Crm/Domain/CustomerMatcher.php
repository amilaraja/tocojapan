<?php

namespace App\Modules\Crm\Domain;

use App\Models\Country;
use App\Models\User;
use App\Modules\Crm\Models\CrmCustomer;

/**
 * One Customer per email address, case-insensitive, linked to the website
 * account when one exists (CRM-CUS-001). Known details are filled in, never
 * overwritten.
 */
class CustomerMatcher
{
    /** @param array{email?: ?string, name?: ?string, phone?: ?string, country_id?: ?int, country_name?: ?string, user_id?: ?int} $details */
    public static function match(array $details): CrmCustomer
    {
        $email = self::normalize($details['email'] ?? null);
        $userId = $details['user_id'] ?? null;

        if ($userId && ! $email) {
            $email = self::normalize(User::whereKey($userId)->value('email'));
        }

        $customer = null;
        if ($userId) {
            $customer = CrmCustomer::where('user_id', $userId)->first();
        }
        if (! $customer && $email) {
            $customer = CrmCustomer::where('email', $email)->first();
        }
        $customer = self::resolveMerged($customer) ?? new CrmCustomer(['email' => $email]);

        if (! $userId && $email) {
            $userId = User::whereRaw('LOWER(email) = ?', [$email])->value('id');
        }

        $countryId = $details['country_id'] ?? null;
        if (! $countryId && ! empty($details['country_name'])) {
            $countryId = Country::whereRaw('LOWER(name) = ?', [mb_strtolower(trim($details['country_name']))])->value('id');
        }

        foreach ([
            'name' => self::clean($details['name'] ?? null),
            'phone' => self::clean($details['phone'] ?? null),
            'country_id' => $countryId,
            'country_name' => self::clean($details['country_name'] ?? null),
            'user_id' => $userId,
            'email' => $email,
        ] as $field => $value) {
            if ($value !== null && $value !== '' && blank($customer->{$field})) {
                $customer->{$field} = $value;
            }
        }
        $customer->save();

        return $customer;
    }

    /** A website account was created or verified: claim the guest Customer with that email (CRM-POR-006). */
    public static function linkUser(User $user): void
    {
        $email = self::normalize($user->email);
        if ($email && ($customer = CrmCustomer::where('email', $email)->whereNull('user_id')->first())) {
            $customer->forceFill(['user_id' => $user->id])->save();
        }
    }

    public static function forUser(User $user): ?CrmCustomer
    {
        return self::resolveMerged(CrmCustomer::where('user_id', $user->id)->first()
            ?? CrmCustomer::where('email', self::normalize($user->email))->first());
    }

    public static function normalize(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private static function resolveMerged(?CrmCustomer $customer): ?CrmCustomer
    {
        $guard = 0;
        while ($customer && $customer->merged_into_id && $guard++ < 5) {
            $customer = CrmCustomer::find($customer->merged_into_id) ?? $customer;
        }

        return $customer;
    }

    private static function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, 190);
    }
}
