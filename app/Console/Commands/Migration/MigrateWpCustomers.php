<?php

namespace App\Console\Commands\Migration;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Signature('migrate:wp-customers {--dry-run} {--limit=0}')]
#[Description('Import WP users (role=customer) into Laravel users with the customer role and a randomised password.')]
class MigrateWpCustomers extends Command
{
    public function handle(MigrationReporter $reporter): int
    {
        $dry = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');

        $reporter->open('customers');

        // Fetch wp_users + wp_usermeta for role lookup. WP stores roles in
        // a serialised array keyed by `wp_capabilities` in usermeta.
        $q = DB::connection('wp')->table('users as u')
            ->leftJoin('usermeta as m', function ($j) {
                $j->on('m.user_id', '=', 'u.ID')->where('m.meta_key', 'wp_capabilities');
            })
            ->select('u.ID', 'u.user_login', 'u.user_email', 'u.display_name', 'u.user_registered', 'm.meta_value as caps');

        if ($limit > 0) {
            $q->limit($limit);
        }

        $written = 0;
        $skipped = 0;

        foreach ($q->lazy(500) as $row) {
            $caps = is_string($row->caps) ? @unserialize($row->caps) : null;
            $isCustomer = is_array($caps) && (
                array_key_exists('customer', $caps) || array_key_exists('subscriber', $caps)
            );

            if (! $isCustomer) {
                $skipped++;

                continue;
            }

            $email = trim((string) $row->user_email);
            if ($email === '') {
                $skipped++;

                continue;
            }

            if (! $dry) {
                $user = User::updateOrCreate(
                    ['email' => $email],
                    [
                        'name' => $row->display_name ?: $row->user_login,
                        'password' => bcrypt(Str::random(32)), // they reset on first login
                        'email_verified_at' => $row->user_registered ?: now(),
                    ]
                );

                if (! $user->hasRole('customer')) {
                    $user->assignRole('customer');
                }
            }

            $written++;
        }

        $reporter->note('written', $written);
        $reporter->note('skipped_non_customer_or_no_email', $skipped);
        $reporter->note('dry_run', $dry);
        $reporter->close('customers');

        $this->info(($dry ? 'DRY: ' : '')."Customers written={$written}, skipped={$skipped}");
        $this->warn('All migrated customers received a random password — they must use the password-reset flow on first login.');

        return self::SUCCESS;
    }
}
