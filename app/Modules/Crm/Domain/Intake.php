<?php

namespace App\Modules\Crm\Domain;

use App\Models\ContactInquiry;
use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\ProformaInvoice;
use App\Models\Quote;
use App\Models\QuoteMessage;
use App\Models\SparePartInquiry;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleHold;
use App\Modules\Crm\Models\CrmCase;
use App\Modules\Crm\Models\CrmCaseLink;
use App\Modules\Crm\Models\CrmCustomer;
use App\Modules\Crm\Support\CrmAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Every website enquiry becomes a Case or joins an open one (CRM-CAS-001,
 * 002, SRS §2.1), from model events on the existing records. Runs inside
 * the customer's request but can never break it (CRM-NFR-004).
 */
class Intake
{
    public static function safely(string $what, callable $callback): void
    {
        if (OrderSync::$writing) {
            return;
        }
        try {
            $callback();
        } catch (Throwable $e) {
            Log::error('CRM intake failed: '.$what, ['error' => $e->getMessage()]);
            report($e);
        }
    }

    public static function quoteCreated(Quote $quote): void
    {
        $customer = CustomerMatcher::match([
            'email' => $quote->contact_email, 'name' => $quote->contact_name, 'phone' => $quote->contact_phone,
            'country_id' => $quote->country_id, 'user_id' => $quote->user_id,
        ]);
        self::joinOrOpen($customer, $quote, 'quote', [
            'vehicle_id' => $quote->vehicle_id,
            'dest_port_id' => $quote->port_id,
            'dest_country_id' => $quote->country_id,
            'value_usd' => $quote->cif_total ?: $quote->price_quoted,
            'value_basis' => $quote->cif_total ? 'CIF' : ($quote->price_quoted ? 'FOB' : null),
        ], (string) $quote->message);
    }

    /** Staff priced or the customer accepted the quote in the existing quote screens. */
    /** @param list<string> $changed */
    public static function quoteUpdated(Quote $quote, array $changed): void
    {
        $case = self::caseFor($quote);
        if (! $case || ! array_intersect($changed, ['status', 'price_quoted', 'cif_total'])) {
            return;
        }
        $price = $quote->cif_total ?: $quote->price_quoted;
        if ($price && array_intersect($changed, ['price_quoted', 'cif_total'])) {
            $case->forceFill(['value_usd' => $price, 'value_basis' => $quote->cif_total ? 'CIF' : 'FOB'])->saveQuietly();
        }
        if (in_array($quote->status, ['quoted', 'accepted'], true) && $price) {
            StageEngine::complete($case, 'quote_sent', null, ['price' => $price, 'basis' => $quote->cif_total ? 'CIF' : 'FOB', 'quote' => $quote->reference], true);
        }
        if ($quote->status === 'accepted') {
            StageEngine::complete($case, 'order_confirmed', null, ['quote' => $quote->reference], true);
        }
    }

    public static function quoteMessageCreated(QuoteMessage $message): void
    {
        $quote = $message->quote;
        $case = $quote ? self::caseFor($quote) : null;
        if (! $case) {
            return;
        }
        $author = $message->user_id ? User::find($message->user_id) : null;
        $isStaff = $author && $author->id !== $quote->user_id && ($author->canAccessPanel(filament()->getPanel('admin')) || CrmAccess::canUse($author));

        if ($message->is_internal) {
            Timeline::log($case, 'note', (string) $message->body, ['quote' => $quote->reference], userId: $author?->id);
        } elseif ($isStaff) {
            Timeline::log($case, 'staff_message', (string) $message->body, ['quote' => $quote->reference], userId: $author->id, visibleToCustomer: true);
            CaseActions::staffResponded($case, $author);
        } else {
            CaseActions::customerMessaged($case, (string) $message->body, 'customer_message', ['quote' => $quote->reference]);
        }
    }

    /** LC proforma invoices: the LC path (SRS §3.2 "LC proforma invoice issued"). */
    public static function proformaCreated(ProformaInvoice $proforma): void
    {
        $customer = CustomerMatcher::match([
            'email' => $proforma->consignee_email, 'name' => $proforma->consignee_name, 'phone' => $proforma->consignee_phone,
            'user_id' => $proforma->user_id,
        ]);
        $port = $proforma->dest_port_id ? \App\Models\Port::find($proforma->dest_port_id) : null;
        $case = self::joinOrOpen($customer, $proforma, 'proforma', [
            'vehicle_id' => $proforma->vehicle_id,
            'dest_port_id' => $proforma->dest_port_id,
            'dest_country_id' => $port?->country_id,
            'payment_mode' => 'lc',
            'value_usd' => $proforma->total_cif,
            'value_basis' => 'CIF',
        ]);

        $case->forceFill(['payment_mode' => 'lc', 'value_usd' => $proforma->total_cif ?: $case->value_usd, 'value_basis' => 'CIF'])->saveQuietly();
        $case->unsetRelation('steps');
        StageEngine::complete($case, 'proforma_issued', null, ['proforma' => $proforma->invoice_no], true);
        StageEngine::complete($case, 'quote_sent', null, ['price' => $proforma->total_cif, 'basis' => 'CIF', 'proforma' => $proforma->invoice_no], true);
        self::reservedByHold($case, VehicleHold::active()->where('proforma_invoice_id', $proforma->id)->first());
    }

    /** Buy now: the order confirms the car and is the invoice (SRS §2.1, §3.2). */
    public static function orderCreated(Order $order): void
    {
        $customer = CustomerMatcher::match([
            'user_id' => $order->user_id, 'name' => $order->ship_to_name, 'phone' => $order->ship_to_phone, 'country_id' => $order->dest_country_id,
        ]);
        $case = self::joinOrOpen($customer, $order, 'buy_now', [
            'vehicle_id' => $order->vehicle_id,
            'dest_port_id' => $order->dest_port_id,
            'dest_country_id' => $order->dest_country_id,
            'payment_mode' => $order->payment_provider,
            'value_usd' => $order->cif_total ?: $order->amount_usd,
            'value_basis' => $order->cif_total ? 'CIF' : 'FOB',
        ]);
        $case->forceFill([
            'payment_mode' => $order->payment_provider ?: $case->payment_mode,
            'value_usd' => $order->cif_total ?: $order->amount_usd ?: $case->value_usd,
        ])->saveQuietly();

        $ref = ['order' => $order->order_no];
        StageEngine::complete($case, 'acknowledged', null, $ref + ['note' => 'Order confirmation sent by the website'], true, ['keep_first_response' => true]);
        StageEngine::complete($case, 'quote_sent', null, $ref + ['price' => $order->cif_total ?: $order->amount_usd, 'basis' => $order->cif_total ? 'CIF' : 'FOB'], true);
        StageEngine::complete($case, 'order_confirmed', null, $ref, true);
        // The order holds the car (checkout claims it when holds are enforced).
        StageEngine::complete($case, 'vehicle_reserved', null, $ref, true);
        StageEngine::complete($case, 'invoice_issued', null, $ref, true);
        if ($order->isPaid()) {
            self::orderPaid($case, $order);
        }
    }

    /** Status changed on the existing order screens or by PayPal (CRM-STG-009, INT-003). */
    /** @param list<string> $changed */
    public static function orderUpdated(Order $order, array $changed): void
    {
        if (! array_intersect($changed, ['status', 'bl_number', 'vessel_name', 'eta_at'])) {
            return;
        }
        $case = self::caseFor($order);
        if (! $case) {
            return;
        }
        $context = ['from_order' => true];

        if (in_array('status', $changed, true) && in_array($order->status, ['cancelled', 'refunded'], true)) {
            // A cancelled checkout is not always a lost buyer: the owner decides.
            Timeline::log($case, 'record', 'Order '.$order->order_no.' '.$order->status.'. Decide whether the sale is lost or continues.', ['order' => $order->order_no], userId: null);
            Notifier::event('customer_message', $case, ['message' => 'Order '.$order->order_no.' was '.$order->status.'.']);

            return;
        }

        if ($order->isPaid()) {
            self::orderPaid($case, $order);
        }
        if (in_array($order->status, ['shipped', 'delivered'], true) || $order->bl_number) {
            if ($order->vessel_name) {
                StageEngine::complete($case, 'vessel_booked', null, ['shipping_method' => 'roro', 'vessel_name' => $order->vessel_name, 'voyage_no' => $order->voyage_no, 'order' => $order->order_no], true, $context);
            }
            if ($order->bl_number) {
                StageEngine::complete($case, 'bl_issued', null, ['bl_number' => $order->bl_number, 'eta' => $order->eta_at?->toDateString(), 'order' => $order->order_no], true, $context);
            }
        }
        if ($order->status === 'delivered') {
            foreach (['export_certificate', 'inspection', 'vessel_booked', 'delivered_to_port', 'bl_issued', 'documents_couriered', 'documents_received', 'vessel_arrived', 'car_received'] as $key) {
                StageEngine::complete($case->fresh(), $key, null, ['order' => $order->order_no, 'note' => 'Order marked delivered'], true, $context + ['skip_customer' => true]);
            }
        }
    }

    private static function orderPaid(CrmCase $case, Order $order): void
    {
        $context = ['from_order' => true];
        StageEngine::complete($case, 'invoice_issued', null, ['order' => $order->order_no], true, $context);
        StageEngine::complete($case, 'payment_received', null, [
            'payment_method' => $order->payment_provider === 'paypal' ? 'paypal' : 'bank_transfer',
            'amount' => $order->amount_usd,
            'order' => $order->order_no,
        ], true, $context);
    }

    public static function orderMessageCreated(OrderMessage $message): void
    {
        $order = $message->order;
        $case = $order ? self::caseFor($order) : null;
        if (! $case) {
            return;
        }
        if ($message->from_admin) {
            $author = $message->user_id ? User::find($message->user_id) : null;
            Timeline::log($case, 'staff_message', (string) $message->body, ['order' => $order->order_no], userId: $author?->id, visibleToCustomer: true);
            CaseActions::staffResponded($case, $author);
        } else {
            CaseActions::customerMessaged($case, (string) $message->body, 'customer_message', ['order' => $order->order_no]);
        }
    }

    public static function contactCreated(ContactInquiry $inquiry): void
    {
        $customer = CustomerMatcher::match(['email' => $inquiry->email, 'name' => $inquiry->name, 'phone' => $inquiry->phone]);
        $text = trim(($inquiry->subject ? $inquiry->subject."\n\n" : '').$inquiry->message);
        // A message naming one of our stock numbers is about a car (SRS §2.1).
        $vehicle = self::vehicleMentioned($text);

        self::joinOrOpen($customer, $inquiry, 'contact_form', [
            'type' => $vehicle ? Crm::TYPE_VEHICLE : Crm::TYPE_GENERAL,
            'vehicle_id' => $vehicle?->id,
            'subject' => $inquiry->subject,
        ], $text);
    }

    public static function sparePartCreated(SparePartInquiry $inquiry): void
    {
        $customer = CustomerMatcher::match([
            'email' => $inquiry->email, 'name' => $inquiry->name, 'phone' => $inquiry->phone, 'country_name' => $inquiry->country,
        ]);
        $details = array_filter([
            'Vehicle' => trim($inquiry->model_name.' '.$inquiry->year),
            'Chassis no.' => $inquiry->chassis_no,
            'Engine' => $inquiry->engine_model,
            'Condition' => $inquiry->condition,
            'Shipping' => $inquiry->shipping_method,
        ]);
        $text = trim((string) $inquiry->parts_description)."\n\n".collect($details)->map(fn ($v, $k) => "{$k}: {$v}")->implode("\n");

        self::joinOrOpen($customer, $inquiry, 'spare_parts', [
            'type' => Crm::TYPE_PARTS,
            'subject' => 'Spare parts: '.mb_strimwidth(trim($inquiry->model_name.' '.$inquiry->parts_description), 0, 120, '…'),
        ], trim($text));
    }

    /** A hold was placed: its Case has the car reserved; other buyers' Cases on that car are warned. */
    public static function holdCreated(VehicleHold $hold): void
    {
        $case = self::caseForHold($hold);
        if ($case) {
            self::reservedByHold($case, $hold);
        }
        self::warnOthers($hold->vehicle_id, $case, 'reserved');
    }

    /** The car was sold: its buyer's Case ticks "marked sold"; others are warned (proposal p.2). */
    public static function vehicleSold(Vehicle $vehicle): void
    {
        $hold = VehicleHold::where('vehicle_id', $vehicle->id)->where('state', VehicleHold::STATE_CONVERTED)->latest('ended_at')->first();
        $case = $hold ? self::caseForHold($hold) : null;
        if (! $case) {
            // One open Case at stage 02+ on this car: that is the buyer.
            $candidates = CrmCase::active()->where('vehicle_id', $vehicle->id)->where('stage', '>=', 2)->get();
            $case = $candidates->count() === 1 ? $candidates->first() : null;
        }
        if ($case) {
            StageEngine::complete($case, 'vehicle_sold', null, $hold ? ['hold_id' => $hold->id] : [], true);
        }
        self::warnOthers($vehicle->id, $case, 'sold');
    }

    /**
     * Join the open Case of this customer for the same car (vehicle sale) or
     * the open Case of the same type (support), else open a new one.
     *
     * @param  array<string, mixed>  $attributes
     */
    private static function joinOrOpen(CrmCustomer $customer, Model $record, string $source, array $attributes, ?string $message = null): CrmCase
    {
        $type = $attributes['type'] ?? Crm::TYPE_VEHICLE;
        unset($attributes['type']);

        $existing = self::caseFor($record);
        if ($existing) {
            return $existing;
        }

        $query = CrmCase::active()->where('customer_id', $customer->id)->where('type', $type);
        $case = match (true) {
            $type === Crm::TYPE_VEHICLE && ! empty($attributes['vehicle_id']) => (clone $query)->where('vehicle_id', $attributes['vehicle_id'])->latest('id')->first(),
            $type !== Crm::TYPE_VEHICLE => (clone $query)->latest('id')->first(),
            default => null,
        };

        if ($case) {
            self::link($case, $record);
            // Fill what the Case did not know yet; never overwrite.
            foreach (['dest_port_id', 'dest_country_id', 'payment_mode', 'value_usd', 'value_basis'] as $field) {
                if (blank($case->{$field}) && filled($attributes[$field] ?? null)) {
                    $case->{$field} = $attributes[$field];
                }
            }
            $case->saveQuietly();
            Timeline::log($case, 'record', 'New '.(Crm::SOURCES[$source] ?? $source).' joined this Case: '.self::recordLabel($record), userId: null);
            if ($message !== null && trim($message) !== '') {
                CaseActions::customerMessaged($case, $message, 'customer_message', ['record' => self::recordLabel($record)]);
            }

            return $case;
        }

        $case = CaseActions::open($customer, array_filter($attributes, fn ($v) => $v !== null && $v !== '') + ['type' => $type, 'source' => $source], ['message' => $message]);
        self::link($case, $record);

        return $case->fresh();
    }

    private static function link(CrmCase $case, Model $record): void
    {
        CrmCaseLink::firstOrCreate(['linkable_type' => $record->getMorphClass(), 'linkable_id' => $record->getKey()], ['case_id' => $case->id]);
    }

    /** The Case a website record belongs to (following merges). */
    public static function caseFor(Model $record): ?CrmCase
    {
        $link = CrmCaseLink::where('linkable_type', $record->getMorphClass())->where('linkable_id', $record->getKey())->first();
        $case = $link ? CrmCase::find($link->case_id) : null;
        for ($guard = 0; $case && $case->merged_into_id && $guard < 5; $guard++) {
            $case = CrmCase::find($case->merged_into_id);
        }

        return $case;
    }

    private static function caseForHold(VehicleHold $hold): ?CrmCase
    {
        if ($hold->order_id && ($order = Order::find($hold->order_id)) && ($case = self::caseFor($order))) {
            return $case;
        }
        if ($hold->proforma_invoice_id && ($proforma = ProformaInvoice::find($hold->proforma_invoice_id)) && ($case = self::caseFor($proforma))) {
            return $case;
        }

        return $hold->buyer_ref ? CrmCase::where('reference', $hold->buyer_ref)->first() : null;
    }

    private static function reservedByHold(CrmCase $case, ?VehicleHold $hold): void
    {
        if ($hold && $hold->isActive() && (int) $hold->vehicle_id === (int) $case->vehicle_id) {
            StageEngine::complete($case, 'vehicle_reserved', null, ['hold_id' => $hold->id], true);
        }
    }

    /** Owners of other open Cases on this car are warned (proposal: "warnings before a car is promised twice"). */
    private static function warnOthers(?int $vehicleId, ?CrmCase $buyerCase, string $what): void
    {
        if (! $vehicleId) {
            return;
        }
        $vehicle = Vehicle::withTrashed()->find($vehicleId);
        CrmCase::active()->where('vehicle_id', $vehicleId)->when($buyerCase, fn ($q) => $q->where('id', '!=', $buyerCase->id))->get()
            ->each(function (CrmCase $other) use ($vehicle, $what, $buyerCase) {
                $message = 'Stock '.$vehicle?->stock_no.' was just '.$what.($buyerCase ? ' for '.$buyerCase->reference : '').'. Offer this buyer another car or tell them.';
                Timeline::log($other, 'system', $message, ['vehicle_conflict' => $what], userId: null);
                Notifier::event('vehicle_conflict', $other, ['title' => 'Car '.$what.' for another buyer', 'message' => $message]);
            });
    }

    private static function vehicleMentioned(string $text): ?Vehicle
    {
        if (! preg_match_all('/\b([A-Z]{1,3}\d{4,6})\b/', strtoupper($text), $matches)) {
            return null;
        }

        return Vehicle::whereIn('stock_no', array_unique($matches[1]))->first();
    }

    private static function recordLabel(Model $record): string
    {
        return match (true) {
            $record instanceof Quote => 'Quote '.$record->reference,
            $record instanceof ProformaInvoice => 'Proforma '.$record->invoice_no,
            $record instanceof Order => 'Order '.$record->order_no,
            $record instanceof ContactInquiry => 'Contact form #'.$record->id,
            $record instanceof SparePartInquiry => 'Spare-part request #'.$record->id,
            default => class_basename($record).' #'.$record->getKey(),
        };
    }
}
