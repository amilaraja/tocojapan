<?php

namespace App\Modules\Mailer\Console;

use App\Modules\Mailer\Domain\Buyers\BuyerBackfill;
use Illuminate\Console\Command;

/** TOC-BUY-006: build the buyer database from already-imported messages, then fill Brevo. */
class BuyersBackfill extends Command
{
    protected $signature = 'mailer:buyers:backfill
        {--brevo : Fill the buyer details into Brevo (phase 2) instead of reading messages}
        {--seconds=0 : Stop after this many seconds (0 = run until done)}
        {--limit=0 : Stop after this many messages or buyers (0 = no limit)}
        {--status : Only show progress}';

    protected $description = 'Read buyer details from already-imported enquiry emails (read-only), or fill them into Brevo with --brevo';

    public function handle(BuyerBackfill $backfill): int
    {
        if ($this->option('status')) {
            $this->table(['', 'count'], collect($backfill->status())->map(fn ($v, $k) => [str_replace('_', ' ', $k), $v])->values()->all());

            return self::SUCCESS;
        }

        $seconds = (int) $this->option('seconds');
        $deadline = $seconds > 0 ? microtime(true) + $seconds : PHP_FLOAT_MAX;
        $limit = (int) $this->option('limit') ?: PHP_INT_MAX;

        if ($this->option('brevo')) {
            if (! $backfill->brevoReady()) {
                $this->error('Run mailer:brevo:setup first: the buyer fields do not exist in Brevo yet.');

                return self::FAILURE;
            }
            $total = ['done' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
            do {
                $r = $backfill->syncBrevo(min($deadline, microtime(true) + 30), $limit - $total['done']);
                foreach ($r as $k => $v) {
                    $total[$k] += $v;
                }
                $this->line(sprintf('Brevo: %d done (%d updated, %d skipped, %d failed), %d left', $total['done'], $total['updated'], $total['skipped'], $total['failed'], $backfill->status()['brevo_left']));
            } while ($r['done'] > 0 && $total['done'] < $limit && microtime(true) < $deadline);

            return self::SUCCESS;
        }

        $done = 0;
        do {
            $n = $backfill->readMessages(min($deadline, microtime(true) + 30), $limit - $done);
            $done += $n;
            $s = $backfill->status();
            $this->line("Messages read: {$done}, left: {$s['messages_left']}, buyers: {$s['buyers']}");
        } while ($n > 0 && $done < $limit && microtime(true) < $deadline);

        return self::SUCCESS;
    }
}
