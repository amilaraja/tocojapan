<?php

namespace App\Modules\Mailer\Domain\Importer;

use App\Models\User;
use App\Modules\Mailer\Domain\Brevo\BrevoUnavailable;
use App\Modules\Mailer\Domain\Brevo\ContactSync;
use App\Modules\Mailer\Models\ApprovedSender;
use App\Modules\Mailer\Models\ContactImport;
use App\Modules\Mailer\Models\ImportRun;
use App\Modules\Mailer\Models\ImportState;
use App\Modules\Mailer\Models\ProcessedMessage;
use App\Modules\Mailer\Notifications\ImportFailingAlert;
use App\Modules\Mailer\Support\MailerAccess;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * One import run (TOC-IMP-002 to 009, TOC-LOG-001/002).
 *
 * - Runs never overlap: an atomic claim on mailer_import_state.lock_until;
 *   a second run logs "skipped: already running" (IMP-007).
 * - Only messages From an active approved sender are processed; others
 *   leave no record (IMP-004).
 * - A message id is processed at most once (unique key, IMP-005). Each
 *   message is one DB transaction: its audit rows and processed marker are
 *   written together, so a failure midway loses nothing (IMP-006).
 * - The checkpoint only moves after a fully successful run.
 * - 3 consecutive failures email every Mailer Admin once (IMP-009).
 */
class ImportRunner
{
    /** Longer than any run can take (runs stop themselves after run_seconds). */
    protected const LOCK_MINUTES = 3;

    protected const MAX_MESSAGES_PER_RUN = 500;

    public const MORE_WAITING = 'More messages are waiting; the next run carries on.';

    public function __construct(
        protected MailboxReader $reader,
        protected SenderMatcher $matcher,
        protected Extraction $extraction,
        protected ContactSync $contacts,
    ) {}

    public function run(string $trigger = ImportRun::TRIGGER_SCHEDULE): ImportRun
    {
        $state = $this->state();

        if (! $this->claim($state)) {
            return ImportRun::create([
                'trigger' => $trigger, 'started_at' => now(), 'finished_at' => now(),
                'status' => ImportRun::STATUS_SKIPPED, 'error' => 'skipped: already running',
            ]);
        }

        $run = ImportRun::create(['trigger' => $trigger, 'started_at' => now(), 'status' => ImportRun::STATUS_RUNNING]);

        try {
            $senders = ApprovedSender::query()->where('active', true)->get();

            $note = null;

            if ($senders->isNotEmpty()) {
                $historyId = $this->reader->currentHistoryId();
                [$latest, $complete] = $this->processIds($this->newMessageIds($state, $senders), $senders, $run);

                if ($complete) {
                    $state->forceFill([
                        'last_history_id' => $historyId ?? $state->last_history_id,
                        'last_checkpoint_at' => max($latest ?? $run->started_at, $state->last_checkpoint_at ?? $run->started_at),
                    ])->save();
                } else {
                    // Time budget used up: keep the checkpoint so the next run carries on.
                    $note = self::MORE_WAITING;
                }
            }

            $this->finish($run, ImportRun::STATUS_SUCCESS, $note);
            $state->forceFill(['consecutive_failures' => 0, 'failure_alert_sent_at' => null])->save();
        } catch (Throwable $e) {
            $this->fail($run, $state, $e);
        } finally {
            $state->forceFill(['lock_until' => null])->save();
        }

        return $run->refresh();
    }

    /**
     * One backfill batch of up to 100 messages (TOC-IMP-008). The cursor's
     * page token only moves after the batch succeeds, so a stopped worker
     * resumes at the same batch.
     */
    public function backfillBatch(): ?ImportRun
    {
        $state = $this->state();
        $cursor = $state->backfill_cursor ?? [];

        if (($cursor['status'] ?? null) !== 'running') {
            return null;
        }
        if (! $this->claim($state)) {
            return null; // the job retries later
        }

        $run = ImportRun::create(['trigger' => ImportRun::TRIGGER_BACKFILL, 'started_at' => now(), 'status' => ImportRun::STATUS_RUNNING]);

        try {
            $senders = ApprovedSender::query()->where('active', true)->get();
            $query = $this->matcher->query($senders, CarbonImmutable::parse($cursor['from']));
            $page = $this->reader->search($query, $cursor['page_token'] ?? null, (int) config('mailer.import.backfill_batch_size', 100));

            [, $complete] = $this->processIds($page['ids'], $senders, $run);

            $cursor['messages_seen'] = ($cursor['messages_seen'] ?? 0) + $run->scanned;
            // Only move to the next page once this one is fully done; a partial
            // batch is simply run again (processed messages are skipped).
            if ($complete) {
                $cursor['page_token'] = $page['nextPageToken'];
                $cursor['batches_done'] = ($cursor['batches_done'] ?? 0) + 1;
                if ($page['nextPageToken'] === null) {
                    $cursor['status'] = 'done';
                    $cursor['finished_at'] = now()->toIso8601String();
                }
            }
            $state->forceFill(['backfill_cursor' => $cursor])->save();

            $this->finish($run, ImportRun::STATUS_SUCCESS, $complete ? null : self::MORE_WAITING);
            $state->forceFill(['consecutive_failures' => 0, 'failure_alert_sent_at' => null])->save();
        } catch (Throwable $e) {
            $this->fail($run, $state, $e);
        } finally {
            $state->forceFill(['lock_until' => null])->save();
        }

        return $run->refresh();
    }

    public function state(): ImportState
    {
        return ImportState::query()->firstOrCreate(['mailbox' => $this->mailboxKey()]);
    }

    /** @return list<string> */
    protected function newMessageIds(ImportState $state, Collection $senders): array
    {
        if ($state->last_history_id) {
            try {
                $ids = $this->reader->idsSinceHistory($state->last_history_id);

                // Headers only, to keep non-approved mail out entirely (IMP-004).
                return array_values(array_filter(
                    $ids,
                    fn (string $id) => ! $this->alreadyProcessed([$id]) && $this->matcher->match($this->reader->fromAddress($id), $senders) !== null,
                ));
            } catch (HistoryExpired) {
                // fall through to a search from the checkpoint
            }
        }

        // One day of overlap: re-seen messages are skipped by the unique id.
        $after = ($state->last_checkpoint_at ?? now())->copy()->subDay();
        $ids = [];
        $token = null;
        do {
            $page = $this->reader->search($this->matcher->query($senders, $after), $token, 100);
            array_push($ids, ...$page['ids']);
            $token = $page['nextPageToken'];
        } while ($token && count($ids) < self::MAX_MESSAGES_PER_RUN);

        return array_reverse($ids); // Gmail lists newest first; process oldest first

    }

    /**
     * Fetch and process messages one at a time, oldest first as given,
     * until the time budget is used (TOC-NFR-005: stay inside the job limit).
     *
     * @param  list<string>  $ids
     * @return array{0: CarbonImmutable|null, 1: bool} latest received time processed, and whether all ids were done
     */
    protected function processIds(array $ids, Collection $senders, ImportRun $run): array
    {
        $done = $this->alreadyProcessed($ids);
        $todo = array_values(array_diff(array_unique($ids), $done));
        $budget = (int) config('mailer.import.run_seconds', 40);
        $started = now();

        $latest = null;
        $counts = ['scanned' => 0, 'created' => 0, 'updated' => 0, 'failed' => 0, 'skipped' => []];

        foreach ($todo as $id) {
            if ($started->diffInSeconds(now(), true) >= $budget) {
                return [$latest, false];
            }

            $message = $this->reader->fetch($id);
            $sender = $this->matcher->match($message->from, $senders);
            if (! $sender) {
                continue;
            }

            $counts['scanned']++;
            $this->processMessage($message, $sender, $run, $counts);
            $latest = $message->receivedAt && (! $latest || $message->receivedAt->gt($latest)) ? $message->receivedAt : $latest;

            $run->forceFill([
                'scanned' => $counts['scanned'], 'created' => $counts['created'], 'updated' => $counts['updated'],
                'failed' => $counts['failed'], 'skipped' => $counts['skipped'],
            ])->save();
        }

        return [$latest, true];
    }

    /** @param  array<string, mixed>  $counts */
    protected function processMessage(ParsedMessage $message, ApprovedSender $sender, ImportRun $run, array &$counts): void
    {
        $result = $this->extraction->run($message, $sender);
        $local = $counts;

        // BrevoUnavailable escapes the transaction: nothing for this message
        // is kept and it is retried on the next run.
        DB::transaction(function () use ($message, $sender, $run, $result, &$local) {
            foreach ($result['addresses'] as $address) {
                if (! $address['keep']) {
                    $this->audit($address['email'], $message, $sender, $run, ContactImport::OUTCOME_SKIPPED, $address['reason'], null, $result['fields']);
                    $local['skipped'][$address['reason']] = ($local['skipped'][$address['reason']] ?? 0) + 1;

                    continue;
                }

                $sync = $this->contacts->sync($address['email'], $result['fields'], $sender);
                $this->audit($address['email'], $message, $sender, $run, $sync->outcome, $sync->reason, $sync->status, $result['fields']);

                match ($sync->outcome) {
                    ContactImport::OUTCOME_ADDED => $local['created']++,
                    ContactImport::OUTCOME_UPDATED => $local['updated']++,
                    ContactImport::OUTCOME_FAILED => $local['failed']++,
                    default => $local['skipped'][$sync->reason] = ($local['skipped'][$sync->reason] ?? 0) + 1,
                };
            }

            ProcessedMessage::create([
                'gmail_message_id' => $message->id,
                'approved_sender_id' => $sender->id,
                'received_at' => $message->receivedAt,
                'run_id' => $run->id,
                'outcome' => $result['addresses'] === [] ? 'no_addresses' : 'processed',
            ]);
        });

        $counts = $local;
    }

    /** @param  array<string, string>  $fields */
    protected function audit(string $email, ParsedMessage $message, ApprovedSender $sender, ImportRun $run, string $outcome, ?string $reason, ?int $status, array $fields): void
    {
        ContactImport::create([
            'email' => $email,
            'gmail_message_id' => $message->id,
            'approved_sender_id' => $sender->id,
            'run_id' => $run->id,
            'outcome' => $outcome,
            'reason' => $reason,
            'brevo_status_code' => $status,
            'fields' => $fields ?: null,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  list<string>  $ids
     * @return list<string>
     */
    protected function alreadyProcessed(array $ids): array
    {
        return $ids === [] ? [] : ProcessedMessage::query()->whereIn('gmail_message_id', $ids)->pluck('gmail_message_id')->all();
    }

    /** Atomic: only one process can move lock_until from empty/expired to the future. */
    protected function claim(ImportState $state): bool
    {
        $claimed = ImportState::query()
            ->whereKey($state->id)
            ->where(fn ($q) => $q->whereNull('lock_until')->orWhere('lock_until', '<', now()))
            ->update(['lock_until' => now()->addMinutes(self::LOCK_MINUTES)]) === 1;

        if ($claimed) {
            $state->refresh();

            // A worker killed mid-run leaves its run at "running": close it.
            ImportRun::query()
                ->where('status', ImportRun::STATUS_RUNNING)
                ->where('started_at', '<', now()->subMinutes(self::LOCK_MINUTES))
                ->update(['status' => ImportRun::STATUS_FAILED, 'finished_at' => now(), 'error' => 'Stopped before finishing (time limit). Its messages are picked up by the next run.']);
        }

        return $claimed;
    }

    protected function finish(ImportRun $run, string $status, ?string $error = null): void
    {
        $run->forceFill(['status' => $status, 'finished_at' => now(), 'error' => $error])->save();
    }

    protected function fail(ImportRun $run, ImportState $state, Throwable $e): void
    {
        $message = $e instanceof MailboxUnavailable || $e instanceof BrevoUnavailable
            ? $e->getMessage()
            : 'The import stopped because of an unexpected error ('.class_basename($e).').';

        Log::warning('Mailer: import run failed', ['run' => $run->id, 'error' => $e->getMessage(), 'class' => $e::class]);

        $this->finish($run, ImportRun::STATUS_FAILED, $message);

        $failures = $state->consecutive_failures + 1;
        $state->forceFill(['consecutive_failures' => $failures])->save();

        if ($failures >= (int) config('mailer.import.alert_after_failures', 3) && $state->failure_alert_sent_at === null) {
            $admins = User::permission(MailerAccess::ADMIN)->get();
            if ($admins->isNotEmpty()) {
                Notification::send($admins, new ImportFailingAlert($failures, $message));
            }
            $state->forceFill(['failure_alert_sent_at' => now()])->save();
        }
    }

    protected function mailboxKey(): string
    {
        return strtolower((string) (app(\App\Modules\Mailer\Support\MailerSettings::class)->get('mailbox') ?: 'default'));
    }
}
