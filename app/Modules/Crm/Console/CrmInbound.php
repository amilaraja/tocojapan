<?php

namespace App\Modules\Crm\Console;

use App\Modules\Crm\Domain\CaseActions;
use App\Modules\Crm\Domain\CustomerMatcher;
use App\Modules\Crm\Domain\InboundText;
use App\Modules\Crm\Models\CrmCase;
use App\Modules\Crm\Models\CrmCustomer;
use App\Modules\Crm\Models\CrmInboundMessage;
use App\Modules\Crm\Support\CrmSettings;
use App\Modules\Mailer\Domain\Importer\MailboxReader;
use App\Modules\Mailer\Domain\Importer\MailboxUnavailable;
use Illuminate\Console\Command;
use Throwable;

/**
 * Buyer replies come back into their Case (plan P7, proposal "buyer replies
 * coming back in"). Reads the Mailer's mailbox through its read-only Gmail
 * reader: nothing is changed in the mailbox. A message joins a Case by the
 * [C-YYYY-NNNNN] reference in its subject, else by the sender's latest open
 * Case. Other mail is ignored; every message id is looked at once.
 */
class CrmInbound extends Command
{
    protected $signature = 'crm:inbound {--days=2 : How far back to look}';

    protected $description = 'CRM: add buyer email replies from the mailbox to their Cases (every 5 minutes)';

    public function handle(MailboxReader $mailbox): int
    {
        if (! CrmSettings::get('inbound.enabled') || ! $mailbox->isConfigured()) {
            return self::SUCCESS;
        }

        $days = max(1, (int) $this->option('days'));
        $added = 0;
        try {
            $page = null;
            for ($pages = 0; $pages < 5; $pages++) {
                $result = $mailbox->search("in:inbox newer_than:{$days}d", $page, 100);
                foreach ($result['ids'] as $id) {
                    $added += $this->consider($mailbox, (string) $id) ? 1 : 0;
                }
                if (! ($page = $result['nextPageToken'] ?? null)) {
                    break;
                }
            }
        } catch (MailboxUnavailable $e) {
            $this->warn($e->getMessage());

            return self::SUCCESS;
        }

        $this->line("Replies added: {$added}");

        return self::SUCCESS;
    }

    private function consider(MailboxReader $mailbox, string $id): bool
    {
        if (CrmInboundMessage::where('message_id', $id)->exists()) {
            return false;
        }

        try {
            // Cheap check first: only fetch the body for known customers or our references.
            $from = CustomerMatcher::normalize($mailbox->fromAddress($id));
            $customer = $from ? CrmCustomer::where('email', $from)->whereNull('merged_into_id')->first() : null;

            $message = $customer ? $mailbox->fetch($id) : null;
            $case = $message ? $this->caseFor($message->subject, $customer) : null;

            if (! $message || ! $case || $this->isOurOwn($from)) {
                CrmInboundMessage::create(['message_id' => $id, 'from_email' => $from, 'outcome' => 'ignored']);

                return false;
            }

            $text = InboundText::reply($message->bodyText());
            CaseActions::customerMessaged($case, $text !== '' ? $text : '(empty message)', 'email_in', ['subject' => $message->subject, 'from' => $from]);
            CrmInboundMessage::create([
                'message_id' => $id, 'case_id' => $case->id, 'from_email' => $from,
                'subject' => mb_substr((string) $message->subject, 0, 255), 'received_at' => $message->receivedAt, 'outcome' => 'added',
            ]);

            return true;
        } catch (MailboxUnavailable $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            CrmInboundMessage::firstOrCreate(['message_id' => $id], ['outcome' => 'error']);

            return false;
        }
    }

    private function caseFor(?string $subject, CrmCustomer $customer): ?CrmCase
    {
        if ($subject && preg_match('/\b(C-\d{4}-\d{5})\b/', $subject, $m)) {
            $case = CrmCase::where('reference', $m[1])->where('customer_id', $customer->id)->first();
            if ($case) {
                return $case->merged_into_id ? CrmCase::find($case->merged_into_id) : $case;
            }
        }

        return CrmCase::active()->where('customer_id', $customer->id)->latest('last_activity_at')->first();
    }

    /** Our own staff writing from the same domain are not buyer replies. */
    private function isOurOwn(?string $from): bool
    {
        $domain = strtolower((string) substr((string) strrchr((string) config('mail.from.address'), '@'), 1));

        return $from !== null && $domain !== '' && str_ends_with($from, '@'.$domain);
    }
}
