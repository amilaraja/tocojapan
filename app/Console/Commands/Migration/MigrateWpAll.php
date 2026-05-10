<?php

namespace App\Console\Commands\Migration;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('migrate:wp-all {--dry-run} {--exclude-auction=1}')]
#[Description('Run all WP→Laravel migration commands in order: makes/models → vehicles → pages → customers.')]
class MigrateWpAll extends Command
{
    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $excludeAuction = (bool) $this->option('exclude-auction');

        $opts = $dry ? ['--dry-run' => true] : [];

        $this->info('1/4 Makes & models');
        $this->call('migrate:wp-makes-models', $opts);

        $this->info('2/4 Vehicles');
        $this->call('migrate:wp-vehicles', $opts + ($excludeAuction ? ['--exclude-auction' => true] : []));

        $this->info('3/4 Pages');
        $this->call('migrate:wp-pages', $opts);

        $this->info('4/4 Customers');
        $this->call('migrate:wp-customers', $opts);

        $this->info('Done. Reports written to storage/migration-reports/.');

        return self::SUCCESS;
    }
}
