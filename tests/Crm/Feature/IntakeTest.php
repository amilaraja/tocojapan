<?php

use App\Models\ContactInquiry;
use App\Models\ProformaInvoice;
use App\Models\QuoteMessage;
use App\Models\SparePartInquiry;
use App\Modules\Crm\Domain\Crm;
use App\Modules\Crm\Domain\StageEngine;
use App\Modules\Crm\Jobs\SendCrmEmail;
use App\Modules\Crm\Models\CrmCase;
use App\Modules\Crm\Models\CrmCustomer;
use Illuminate\Support\Facades\Queue;
use Tests\Crm\Support\CrmData;

beforeEach(function () {
    CrmData::reset();
    Queue::fake([\App\Modules\Crm\Jobs\SendCrmEmail::class]);
});

it('CRM-CAS-001 turns a contact form into a general Case with source Contact form', function () {
    ContactInquiry::create(['name' => 'Amina', 'email' => 'Amina@Example.com', 'subject' => 'Shipping to Kenya', 'message' => 'How long does shipping take?']);

    $case = CrmCase::sole();
    expect($case->type)->toBe(Crm::TYPE_GENERAL)
        ->and($case->source)->toBe('contact_form')
        ->and($case->status)->toBe('new')
        ->and($case->reference)->toMatch('/^C-\d{4}-00001$/')
        ->and($case->customer->email)->toBe('amina@example.com')
        ->and($case->next_follow_up_at)->not->toBeNull()
        ->and($case->activities()->where('type', 'customer_message')->value('body'))->toContain('How long does shipping take?');
});

it('makes a vehicle sale from a contact form that names one of our stock numbers', function () {
    $car = CrmData::car(['stock_no' => 'E02062']);
    ContactInquiry::create(['name' => 'Ben', 'email' => 'ben@example.com', 'subject' => 'Price', 'message' => 'Is e02062 still available?']);

    expect(CrmCase::sole())->type->toBe(Crm::TYPE_VEHICLE)->vehicle_id->toBe($car->id)->stage->toBe(1);
});

it('CRM-CAS-002 joins a quote and a later proforma for the same car into one Case', function () {
    $buyer = CrmData::buyer();
    $car = CrmData::car();
    $port = CrmData::port();
    CrmData::quote($buyer, $car, ['port_id' => $port->id]);
    ProformaInvoice::create(['invoice_no' => 'PI-1', 'user_id' => $buyer->id, 'vehicle_id' => $car->id, 'dest_port_id' => $port->id,
        'consignee_name' => $buyer->name, 'consignee_email' => $buyer->email, 'consignee_address' => 'Nairobi', 'consignee_phone' => '+254700', 'snapshot' => [], 'price_fob' => 9000, 'total_cif' => 10500, 'issued_on' => now(), 'expires_on' => now()->addDays(2)]);

    $case = CrmCase::sole();
    expect($case->links()->count())->toBe(2)
        ->and($case->payment_mode)->toBe('lc')
        ->and((float) $case->value_usd)->toBe(10500.0)
        ->and(StageEngine::step($case, 'proforma_issued')->isDone())->toBeTrue()
        ->and(StageEngine::step($case, 'quote_sent')->isDone())->toBeTrue();
});

it('CRM-CUS-001 matches customers by email, case-insensitive, and links the account', function () {
    $buyer = CrmData::buyer(['email' => 'kofi@example.com']);
    ContactInquiry::create(['name' => 'Kofi', 'email' => 'KOFI@example.com', 'message' => 'Hello']);
    CrmData::quote($buyer, CrmData::car());

    expect(CrmCustomer::count())->toBe(1)->and(CrmCustomer::sole()->user_id)->toBe($buyer->id)
        ->and(CrmCase::count())->toBe(2);
});

it('CRM-ACT-008 adds customer quote replies to the timeline and staff replies count as the first response', function () {
    $agent = CrmData::staff();
    $buyer = CrmData::buyer();
    $quote = CrmData::quote($buyer, CrmData::car());
    $case = CrmCase::sole();

    QuoteMessage::create(['quote_id' => $quote->id, 'user_id' => $buyer->id, 'body' => 'Any discount?']);
    expect($case->activities()->where('type', 'customer_message')->where('body', 'Any discount?')->exists())->toBeTrue();

    QuoteMessage::create(['quote_id' => $quote->id, 'user_id' => $agent->id, 'body' => 'Best price US$ 9,000.']);
    $case->refresh();
    expect($case->first_response_at)->not->toBeNull()
        ->and(StageEngine::step($case, 'acknowledged')->isDone())->toBeTrue();
});

it('UAT-07 sends spare-part requests to the Spare parts queue with support statuses', function () {
    $partsAgent = CrmData::staff(queue: 'Spare parts');
    CrmData::staff(queue: 'Sales');
    SparePartInquiry::create(['name' => 'Raj', 'email' => 'raj@example.com', 'phone' => '+254700000', 'address' => 'Nairobi', 'country' => 'Kenya', 'model_name' => 'Hiace', 'parts_description' => 'Front bumper']);

    $case = CrmCase::sole();
    expect($case->type)->toBe(Crm::TYPE_PARTS)->and($case->owner_id)->toBe($partsAgent->id)->and($case->stage)->toBeNull()->and($case->status)->toBe('new');
});

it('acknowledges new enquiries to the customer and alerts the owner (SRS §8.1)', function () {
    $agent = CrmData::staff();
    CrmData::quote(CrmData::buyer(['email' => 'buyer@example.com']), CrmData::car());

    Queue::assertPushed(SendCrmEmail::class, fn ($job) => $job->to === 'buyer@example.com' && $job->event === 'case_created' && $job->channel === 'customer_email');
    Queue::assertPushed(SendCrmEmail::class, fn ($job) => $job->to === $agent->email && $job->event === 'case_created' && $job->channel === 'email');
    expect($agent->notifications()->count())->toBe(1);
});

it('CRM-NFR-004 never breaks a customer submission when the CRM fails', function () {
    \Illuminate\Support\Facades\Schema::drop('crm_case_links');

    $inquiry = ContactInquiry::create(['name' => 'X', 'email' => 'x@example.com', 'message' => 'Hi']);

    expect($inquiry->exists)->toBeTrue();
});
