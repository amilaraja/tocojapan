<?php

namespace App\Modules\Mailer\Domain\Buyers;

use App\Modules\Mailer\Domain\Brevo\BrevoUnavailable;
use App\Modules\Mailer\Domain\Brevo\ContactSync;
use App\Modules\Mailer\Domain\Importer\Extraction;
use App\Modules\Mailer\Domain\Importer\MailboxReader;
use App\Modules\Mailer\Domain\Importer\MessageGone;
use App\Modules\Mailer\Models\ApprovedSender;
use App\Modules\Mailer\Models\Buyer;
use App\Modules\Mailer\Models\ProcessedMessage;
use App\Modules\Mailer\Support\MailerSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Builds the buyer database from messages imported before buyer details
 * existed (TOC-BUY-006), then fills the new details into Brevo.
 *
 * Phase 1 re-reads each processed message once (read-only, as the importer
 * does) and records its buyers; buyer_scanned_at marks it done, so the
 * work resumes where it stopped. Phase 2 fills empty Brevo fields on
 * existing contacts only (ContactSync::enrich): no lists, no SOURCE, no
 * dates, and never names that are already set (rule 6).
 */
class BuyerBackfill
{
    public function __construct(
        protected MailboxReader $reader,
        protected Extraction $extraction,
        protected BuyerRecorder $recorder,
        protected ContactSync $contacts,
        protected MailerSettings $settings,
    ) {}

    /** @return array{messages_total: int, messages_left: int, buyers: int, brevo_left: int} */
    public function status(): array
    {
        $senders = $this->senderIds();

        return [
            'messages_total' => ProcessedMessage::query()->whereIn('approved_sender_id', $senders)->count(),
            'messages_left' => $this->pendingMessages($senders)->count(),
            'buyers' => Buyer::query()->count(),
            'brevo_left' => $this->brevoReady() ? $this->pendingBrevo()->count() : 0,
        ];
    }

    /**
     * Phase 1. Returns the number of messages handled; stops at $deadline (unix time).
     */
    public function readMessages(float $deadline, int $limit = PHP_INT_MAX): int
    {
        $senders = ApprovedSender::query()->where('collect_buyer_details', true)->get()->keyBy('id');
        if ($senders->isEmpty()) {
            return 0;
        }
        $done = 0;

        while ($done < $limit && microtime(true) < $deadline) {
            $batch = $this->pendingMessages($senders->keys()->all())->orderBy('id')->limit(min(50, $limit - $done))->get();
            if ($batch->isEmpty()) {
                break;
            }
            foreach ($batch as $processed) {
                if ($done >= $limit || microtime(true) >= $deadline) {
                    break 2;
                }
                $sender = $senders[$processed->approved_sender_id];
                try {
                    $message = $this->reader->fetch($processed->gmail_message_id);
                    $result = $this->extraction->run($message, $sender);

                    DB::transaction(function () use ($result, $message, $sender) {
                        if (! BuyerDetailsParser::hasDetails($result['buyer'])) {
                            return;
                        }
                        foreach ($result['addresses'] as $address) {
                            if ($address['keep']) {
                                $this->recorder->record($address['email'], $message, $sender, $result['buyer']);
                            }
                        }
                    });
                } catch (MessageGone) {
                    // Deleted from the mailbox since: nothing to read.
                }
                $processed->forceFill(['buyer_scanned_at' => now()])->save();
                $done++;
            }
        }

        return $done;
    }

    /**
     * Phase 2. Returns the number of buyers handled; stops at $deadline.
     *
     * @return array{done: int, updated: int, skipped: int, failed: int}
     */
    public function syncBrevo(float $deadline, int $limit = PHP_INT_MAX): array
    {
        $out = ['done' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        if (! $this->brevoReady()) {
            return $out;
        }

        while ($out['done'] < $limit && microtime(true) < $deadline) {
            $batch = $this->pendingBrevo()->orderBy('id')->limit(min(50, $limit - $out['done']))->get();
            if ($batch->isEmpty()) {
                break;
            }
            foreach ($batch as $buyer) {
                if ($out['done'] >= $limit || microtime(true) >= $deadline) {
                    break 2;
                }
                try {
                    $result = $this->contacts->enrich(
                        $buyer->email,
                        BuyerRecorder::contactFields($buyer->only(['first_name', 'last_name', 'country', 'phone', 'phone_e164'])),
                        BuyerRecorder::brevoAttributes($buyer),
                    );
                } catch (BrevoUnavailable) {
                    break 2; // Brevo is down: stop and resume later
                }
                $key = match ($result->outcome) {
                    'updated' => 'updated',
                    'failed' => 'failed',
                    default => 'skipped',
                };
                $out[$key]++;
                // Failed ones are retried next time; everything else is done.
                if ($result->outcome !== 'failed') {
                    // Written without touching updated_at, which marks "changed since the last sync".
                    Buyer::query()->whereKey($buyer->id)->toBase()->update(['brevo_synced_at' => now()]);
                }
                $out['done']++;
            }
        }

        return $out;
    }

    public function brevoReady(): bool
    {
        return (bool) $this->settings->get('brevo_buyer_attributes') && filled($this->settings->brevoApiKey());
    }

    /** @return list<int> */
    protected function senderIds(): array
    {
        return ApprovedSender::query()->where('collect_buyer_details', true)->pluck('id')->all();
    }

    /**
     * @param  list<int>  $senders
     * @return Builder<ProcessedMessage>
     */
    protected function pendingMessages(array $senders): Builder
    {
        return ProcessedMessage::query()->whereIn('approved_sender_id', $senders)->whereNull('buyer_scanned_at');
    }

    /** @return Builder<Buyer> */
    protected function pendingBrevo(): Builder
    {
        return Buyer::query()->where(fn ($q) => $q->whereNull('brevo_synced_at')->orWhereColumn('brevo_synced_at', '<', 'updated_at'));
    }
}
