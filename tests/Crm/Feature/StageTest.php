<?php

use App\Models\Order;
use App\Modules\Crm\Domain\CaseActions;
use App\Modules\Crm\Domain\Crm;
use App\Modules\Crm\Domain\StageEngine;
use App\Modules\Crm\Jobs\SendCrmEmail;
use App\Modules\Crm\Models\CrmCase;
use App\Modules\Crm\Models\CrmStepTemplate;
use App\Models\Supplier;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\Crm\Support\CrmData;

beforeEach(function () {
    CrmData::reset();
    Queue::fake([\App\Modules\Crm\Jobs\SendCrmEmail::class]);
    $this->agent = CrmData::staff(nickname: 'Ken');
    $this->buyer = CrmData::buyer(['email' => 'buyer@example.com']);
    $this->car = CrmData::car();
});

function saleCase(): CrmCase
{
    CrmData::quote(test()->buyer, test()->car);

    return CrmCase::sole();
}

it('CRM-STG-003 refuses to move on while required steps are open, listing them', function () {
    $case = saleCase();

    expect(fn () => StageEngine::advance($case, $this->agent))
        ->toThrow(ValidationException::class, 'Complete: Enquiry acknowledged (first reply sent), Price quote sent');
});

it('CRM-STG-004 asks for the vessel details when Vessel booked is ticked', function () {
    $case = saleCase();

    expect(fn () => StageEngine::complete($case, 'vessel_booked', $this->agent, ['vessel_name' => 'Morning Cara']))
        ->toThrow(ValidationException::class, 'Please enter: Shipping method, ETD (sailing date)');
});

it('UAT-03 quote, order and PayPal payment move the Case 01 → 02 → 03 and tick steps by themselves', function () {
    $case = saleCase();
    CaseActions::sendEmail($case, 'Price quote', 'Our price is {price}.', $this->agent, 'reply_quote');
    $order = CrmData::order($this->buyer, $this->car);
    $case->refresh();
    expect($case->stage)->toBe(2);

    $order->transitionTo('paid');

    $case->refresh();
    expect($case->stage)->toBe(2)
        ->and(StageEngine::step($case, 'payment_received')->isDone())->toBeTrue()
        ->and(StageEngine::step($case, 'receipt_sent')->isDone())->toBeTrue()
        ->and(StageEngine::missing($case, 2)->map(fn ($s) => $s->template->key)->all())->toBe(['vehicle_sold']);
    Queue::assertPushed(SendCrmEmail::class, fn ($job) => $job->event === 'payment_received' && $job->channel === 'customer_email');

    StageEngine::complete($case, 'vehicle_sold', $this->agent);
    expect($case->fresh()->stage)->toBe(3)->and($this->car->fresh()->status)->toBe('sold');
});

it('CRM-STG-009 B/L issued sets the order to Shipped with the shipping details', function () {
    $case = saleCase();
    $order = CrmData::order($this->buyer, $this->car, ['status' => 'paid', 'paid_at' => now()]);
    $case = CrmCase::sole();
    StageEngine::complete($case, 'vessel_booked', $this->agent, ['shipping_method' => 'roro', 'vessel_name' => 'Morning Cara', 'voyage_no' => '12A', 'etd' => '2026-11-01']);
    StageEngine::complete($case, 'bl_issued', $this->agent, ['bl_number' => 'BL-555', 'eta' => '2026-11-25']);

    $order->refresh();
    expect($order->status)->toBe('shipped')->and($order->bl_number)->toBe('BL-555')->and($order->vessel_name)->toBe('Morning Cara');
});

it('CRM-STG-006 shows the inspection step only for countries that need it', function () {
    $case = saleCase();
    expect(StageEngine::step($case, 'inspection'))->toBeNull();

    $case->forceFill(['dest_port_id' => CrmData::port(inspection: true)->id, 'dest_country_id' => null])->save();
    $case->unsetRelation('destPort');
    expect(StageEngine::step($case->fresh(), 'inspection'))->not->toBeNull();
});

it('UAT-10 dealer stock cannot leave stage 01 until availability is confirmed', function () {
    $supplier = Supplier::create(['name' => 'As Net', 'slug' => 'asnet-test']);
    $this->car->forceFill(['supplier_id' => $supplier->id])->save();
    CrmData::quote($this->buyer, $this->car);
    $case = CrmCase::sole();
    foreach (['acknowledged', 'quote_sent', 'order_confirmed'] as $key) {
        StageEngine::complete($case, $key, $this->agent, $key === 'quote_sent' ? ['price' => 9000] : []);
    }
    StageEngine::complete($case, 'vehicle_reserved', null, [], true);

    expect($case->fresh()->stage)->toBe(1)
        ->and(StageEngine::missing($case->fresh(), 1)->first()->template->key)->toBe('availability_confirmed');
});

it('CRM-STG-007 moves back one stage with a reason and reopens later steps', function () {
    $case = saleCase();
    CrmData::order($this->buyer, $this->car, ['status' => 'paid', 'paid_at' => now()]);
    $case = CrmCase::sole();
    StageEngine::complete($case, 'vehicle_sold', $this->agent);
    expect($case->fresh()->stage)->toBe(3);

    StageEngine::moveBack($case->fresh(), 'payment reversed', $this->agent);
    $case = $case->fresh();
    expect($case->stage)->toBe(2)
        ->and($case->activities()->where('type', 'stage')->latest('id')->value('body'))->toContain('payment reversed');
});

it('UAT-06 Documents received then Car received closes the Case as Won; the review mail follows 3 days later', function () {
    $case = saleCase();
    CrmData::order($this->buyer, $this->car, ['status' => 'paid', 'paid_at' => now()]);
    $case = CrmCase::sole();
    StageEngine::complete($case, 'vehicle_sold', $this->agent);
    foreach (['export_certificate', 'delivered_to_port'] as $k) {
        StageEngine::complete($case->fresh(), $k, $this->agent);
    }
    StageEngine::complete($case->fresh(), 'vessel_booked', $this->agent, ['shipping_method' => 'roro', 'vessel_name' => 'V', 'etd' => '2026-11-01']);
    StageEngine::complete($case->fresh(), 'bl_issued', $this->agent, ['bl_number' => 'BL1', 'eta' => '2026-11-20']);
    StageEngine::complete($case->fresh(), 'documents_couriered', $this->agent, ['courier' => 'DHL', 'tracking_number' => '123']);
    expect($case->fresh()->stage)->toBe(4);

    StageEngine::complete($case->fresh(), 'documents_received', null);
    StageEngine::complete($case->fresh(), 'vessel_arrived', $this->agent, ['arrival_date' => '2026-11-20']);
    StageEngine::complete($case->fresh(), 'car_received', null);

    $case = $case->fresh();
    expect($case->state)->toBe(Crm::STATE_WON)->and(Order::sole()->status)->toBe('delivered');

    $this->travel(4)->days();
    $this->artisan('crm:tick')->assertSuccessful();
    Queue::assertPushed(SendCrmEmail::class, fn ($job) => $job->event === 'case_won' && $job->channel === 'customer_email');
});

it('CRM-CAS-007 refuses Lost without a reason', function () {
    $case = saleCase();

    expect(fn () => CaseActions::lose($case, null, null, $this->agent))->toThrow(ValidationException::class);
    CaseActions::lose($case, 'Price', 'Too expensive', $this->agent);
    expect($case->fresh()->state)->toBe(Crm::STATE_LOST);
});

it('CRM-STG-002 a mini step an admin adds appears on new Cases', function () {
    CrmStepTemplate::create(['stage' => 1, 'key' => 'photos_sent', 'label' => 'Photos sent to customer', 'required' => false, 'customer_visible' => true, 'sort' => 15]);

    expect(StageEngine::step(saleCase(), 'photos_sent'))->not->toBeNull();
});

it('shows buyers the nickname, never the real name, and replies go to the owner', function () {
    $case = saleCase();
    CaseActions::sendEmail($case, 'Hello', "Hi {customer_name}\n{sender_name}", $this->agent);

    Queue::assertPushed(SendCrmEmail::class, function ($job) {
        return $job->channel === 'customer_email' && $job->event === 'manual'
            && str_contains($job->mail->text, 'Ken') && ! str_contains($job->mail->text, $this->agent->name)
            && $job->mail->fromName === 'Ken · TOCO Japan' && $job->mail->replyToEmail === $this->agent->email;
    });
});

it('replaces the old order-status email with the CRM one', function () {
    \Illuminate\Support\Facades\Notification::fake();
    saleCase();
    $order = CrmData::order($this->buyer, $this->car);

    $order->transitionTo('paid');

    \Illuminate\Support\Facades\Notification::assertNotSentTo($this->buyer, \App\Notifications\OrderStatusChanged::class);
});
