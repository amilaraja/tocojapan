<?php

namespace App\Modules\Crm\Jobs;

use App\Modules\Crm\Mail\CrmMail;
use App\Modules\Crm\Models\CrmNotificationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends one CRM email on the crm queue, retried, and logged as sent or
 * failed (CRM-NFR-004, NTF-005). A mail failure never reaches the caller.
 */
class SendCrmEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public int $timeout = 40;

    public function __construct(
        public string $to,
        public CrmMail $mail,
        public string $event,
        public string $channel,
        public ?int $caseId = null,
    ) {
        $this->onQueue('crm');
    }

    public function handle(): void
    {
        Mail::to($this->to)->send($this->mail);
        $this->log('sent');
    }

    public function failed(Throwable $e): void
    {
        $this->log('failed', $e->getMessage());
    }

    private function log(string $status, ?string $error = null): void
    {
        CrmNotificationLog::create([
            'case_id' => $this->caseId,
            'event' => $this->event,
            'channel' => $this->channel,
            'recipient' => $this->to,
            'status' => $status,
            'error' => $error ? mb_substr($error, 0, 500) : null,
        ]);
    }
}
