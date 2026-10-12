<?php

use App\Modules\Crm\Domain\StageEngine;
use App\Modules\Crm\Models\CrmCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Tests\Crm\Support\CrmData;

beforeEach(function () {
    CrmData::reset();
    Queue::fake([\App\Modules\Crm\Jobs\SendCrmEmail::class]);
    $this->tmpStorage = sys_get_temp_dir().'/crm-storage-'.bin2hex(random_bytes(4));
    mkdir($this->tmpStorage.'/app/private', 0777, true);
    $this->app->useStoragePath($this->tmpStorage);
    $this->agent = CrmData::staff(nickname: 'Ken');
    $this->buyer = CrmData::buyer();
    CrmData::quote($this->buyer, CrmData::car());
    $this->case = CrmCase::sole();
});

afterEach(fn () => \Illuminate\Support\Facades\File::deleteDirectory($this->tmpStorage));

it('CRM-POR-001 lists the buyer\'s purchases with the next action', function () {
    $this->actingAs($this->buyer)->get('/my-purchases')
        ->assertOk()->assertSee($this->case->reference)->assertSee('We are preparing your price quote.');
});

it('CRM-POR-002 shows the tracker and the nickname of the contact', function () {
    $this->get($this->case->portalUrl())->assertOk()
        ->assertSee('Search &amp; Order', false)->assertSee('Your TOCO contact')->assertSee('Ken')->assertDontSee($this->agent->name);
});

it('UAT-12 / CRM-NFR-002 another Case with a wrong token is a 404', function () {
    $this->get(route('crm.portal.show', ['case' => $this->case->reference, 'token' => str_repeat('a', 48)]))->assertNotFound();
});

it('CRM-POR-005 a message from the Case reaches the timeline', function () {
    $this->post(route('crm.portal.message', [$this->case->reference, $this->case->portal_token]), ['body' => 'When can it ship?'])->assertRedirect();

    expect($this->case->activities()->where('type', 'customer_message')->where('body', 'When can it ship?')->exists())->toBeTrue();
});

it('CRM-POR-004 the buyer confirms the order after the quote', function () {
    StageEngine::complete($this->case, 'quote_sent', $this->agent, ['price' => 9000]);

    $this->post(route('crm.portal.confirm', [$this->case->reference, $this->case->portal_token, 'order_confirmed']))->assertRedirect();

    expect(StageEngine::step($this->case->fresh(), 'order_confirmed')->isDone())->toBeTrue();
});

it('lets the buyer upload a payment slip at stage 02', function () {
    $this->case->forceFill(['stage' => 2])->save();

    $this->post(route('crm.portal.slip', [$this->case->reference, $this->case->portal_token]), ['slip' => UploadedFile::fake()->image('slip.jpg')])->assertRedirect();

    expect($this->case->fresh()->getMedia('documents'))->toHaveCount(1)
        ->and($this->case->activities()->where('type', 'document')->exists())->toBeTrue();
});

it('CRM-POR-006 a guest who registers with the enquiry email sees the Case', function () {
    \App\Models\ContactInquiry::create(['name' => 'G', 'email' => 'guest@example.com', 'message' => 'about E01234']);
    $user = CrmData::buyer(['email' => 'guest@example.com']);

    $this->actingAs($user)->get('/my-purchases')->assertOk()->assertSee(CrmCase::latest('id')->first()->reference);
});
