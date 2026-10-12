<?php

namespace App\Modules\Crm\Console;

use App\Models\ContactInquiry;
use App\Models\Order;
use App\Models\ProformaInvoice;
use App\Models\Quote;
use App\Models\SparePartInquiry;
use App\Modules\Crm\Domain\Crm;
use App\Modules\Crm\Domain\Intake;
use App\Modules\Crm\Domain\Notifier;
use App\Modules\Crm\Domain\SlaClock;
use App\Modules\Crm\Domain\Timeline;
use App\Modules\Crm\Models\CrmCase;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Go-live import (CRM-INT-001, OPEN-09 default): open records become Cases
 * at the right stage; records closed in the last 6 months come in as
 * history. Nobody is emailed, old dates start no SLA clocks, and staff get
 * the normal 3 days before an imported Case counts as stale.
 */
class CrmImport extends Command
{
    protected $signature = 'crm:import {--months=6 : Closed records younger than this come in as history} {--apply : Without it, only show what would be imported}
        {--skip=tocojapan.com,toco-int.com,mobiz.lk,amilaraja@gmail.com : Internal test senders (domains or addresses) left out}';

    protected $description = 'CRM: bring existing quotes, proformas, orders and enquiries in as Cases (go-live)';

    public function handle(): int
    {
        $since = now()->subMonths((int) $this->option('months'));
        $records = $this->records($since);

        $this->table(['Record', 'Date', 'Becomes'], $records->map(fn ($r) => [$this->label($r['record']), $r['record']->created_at?->toDateString(), $r['open'] ? 'open Case' : 'history']));

        if (! $this->option('apply')) {
            $this->info($records->count().' records. Run with --apply to import.');

            return self::SUCCESS;
        }

        Notifier::$silent = true;
        try {
            // A Case stays open if any of its records is still open.
            $open = [];
            foreach ($records as $row) {
                $case = $this->import($row['record']);
                if ($case) {
                    $open[$case->id] = ($open[$case->id] ?? false) || $row['open'];
                }
            }
            foreach (array_keys(array_filter($open, fn ($isOpen) => ! $isOpen)) as $caseId) {
                $this->closeAsHistory(CrmCase::find($caseId));
            }
        } finally {
            Notifier::$silent = false;
        }
        $this->info('Imported. Cases now: '.CrmCase::count());

        return self::SUCCESS;
    }

    /** @return Collection<int, array{record: Model, open: bool}> */
    private function records($since): Collection
    {
        $rows = collect();
        foreach (Quote::where('created_at', '>=', $since)->orWhereIn('status', ['submitted', 'in_progress', 'quoted', 'accepted'])->get() as $q) {
            $rows->push(['record' => $q, 'open' => in_array($q->status, ['submitted', 'in_progress', 'quoted', 'accepted'], true)]);
        }
        foreach (ProformaInvoice::where('created_at', '>=', $since)->orWhereDate('expires_on', '>=', now())->get() as $p) {
            $rows->push(['record' => $p, 'open' => $p->expires_on === null || \Illuminate\Support\Carbon::parse($p->expires_on)->endOfDay()->isFuture()]);
        }
        foreach (Order::where('created_at', '>=', $since)->orWhereNotIn('status', ['delivered', 'cancelled', 'refunded'])->get() as $o) {
            $rows->push(['record' => $o, 'open' => ! in_array($o->status, ['delivered', 'cancelled', 'refunded'], true)]);
        }
        foreach (ContactInquiry::where('created_at', '>=', $since)->orWhere('is_handled', false)->get() as $c) {
            $rows->push(['record' => $c, 'open' => ! $c->is_handled]);
        }
        foreach (SparePartInquiry::where('created_at', '>=', $since)->orWhere('is_handled', false)->get() as $s) {
            $rows->push(['record' => $s, 'open' => ! $s->is_handled]);
        }

        $skip = array_filter(array_map(fn ($s) => mb_strtolower(trim($s)), explode(',', (string) $this->option('skip'))));
        $internal = function (Model $record) use ($skip): bool {
            $email = mb_strtolower((string) match (true) {
                $record instanceof Quote => $record->contact_email ?: $record->user?->email,
                $record instanceof ProformaInvoice => $record->consignee_email ?: $record->user?->email,
                $record instanceof Order => $record->user?->email,
                default => $record->email,
            });
            foreach ($skip as $s) {
                if ($email === $s || str_ends_with($email, '@'.$s)) {
                    return true;
                }
            }

            return false;
        };

        // Oldest first, so a quote, its proforma and its order join one Case.
        return $rows->unique(fn ($r) => get_class($r['record']).$r['record']->getKey())
            ->filter(fn ($r) => Intake::caseFor($r['record']) === null && ! $internal($r['record']))
            ->sortBy(fn ($r) => $r['record']->created_at)->values();
    }

    private function import(Model $record): ?CrmCase
    {
        match (true) {
            $record instanceof Quote => Intake::quoteCreated($record),
            $record instanceof ProformaInvoice => Intake::proformaCreated($record),
            $record instanceof Order => $this->order($record),
            $record instanceof ContactInquiry => Intake::contactCreated($record),
            $record instanceof SparePartInquiry => Intake::sparePartCreated($record),
        };

        $case = Intake::caseFor($record);
        if (! $case) {
            return null;
        }

        $case->forceFill([
            'created_at' => min($case->created_at, $record->created_at ?? now()),
            'last_staff_activity_at' => now(),
            'last_customer_message_at' => $record->created_at,
            // Old unassigned records are listed on My work; no alert flood at go-live.
            'unassigned_alerted_at' => now(),
        ])->saveQuietly();
        // Old dates must not breach on day one: clocks restart with new activity.
        SlaClock::cancel($case);
        Timeline::log($case, 'system', 'Imported from '.$this->label($record).' ('.$record->created_at?->toDateString().')', ['import' => true], userId: null);

        return $case;
    }

    /** Every record of this Case is finished: it comes in as history. */
    private function closeAsHistory(?CrmCase $case): void
    {
        if (! $case || ! $case->isActive()) {
            return;
        }
        $order = $case->linked(Order::class);
        $won = $order instanceof Order && $order->status === 'delivered';
        $case->forceFill([
            'state' => $won ? Crm::STATE_WON : Crm::STATE_CLOSED,
            'status' => $case->isVehicleSale() ? null : 'closed',
            'reason' => $won ? null : 'Imported (already handled)',
            'closed_at' => now(),
        ])->saveQuietly();
        $case->tasks()->whereNull('done_at')->update(['done_at' => now()]);
    }

    private function order(Order $order): void
    {
        Intake::orderCreated($order);
        Intake::orderUpdated($order, ['status', 'bl_number', 'vessel_name', 'eta_at']);
    }

    private function label(Model $record): string
    {
        return match (true) {
            $record instanceof Quote => 'Quote '.$record->reference,
            $record instanceof ProformaInvoice => 'Proforma '.$record->invoice_no,
            $record instanceof Order => 'Order '.$record->order_no.' ('.$record->status.')',
            $record instanceof ContactInquiry => 'Contact #'.$record->id,
            $record instanceof SparePartInquiry => 'Spare parts #'.$record->id,
            default => class_basename($record),
        };
    }
}
