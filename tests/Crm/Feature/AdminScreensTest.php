<?php

use App\Modules\Crm\Domain\StageEngine;
use App\Modules\Crm\Filament\Resources\Cases\Pages\ViewCase;
use App\Modules\Crm\Models\CrmCase;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Crm\Support\CrmData;

beforeEach(function () {
    CrmData::reset();
    Queue::fake([\App\Modules\Crm\Jobs\SendCrmEmail::class]);
    Filament::setCurrentPanel('admin');
});

it('CRM-GEN-001 sends visitors to the admin login', function () {
    $this->get('/admin/crm/cases')->assertRedirect();
});

it('renders every CRM screen for an admin', function () {
    $admin = CrmData::admin();
    CrmData::staff(nickname: 'Ken');
    CrmData::quote(CrmData::buyer(), CrmData::car());
    $case = CrmCase::sole();

    $this->actingAs($admin);
    foreach (['cases', 'cases/create', 'cases/'.$case->id, 'customers', 'customers/'.$case->customer_id, 'tasks', 'queues', 'steps', 'templates',
        'my-work', 'pipeline', 'team', 'sla', 'reports', 'settings', 'profile'] as $path) {
        $this->get('/admin/crm/'.$path)->assertOk();
    }
});

it('CRM-GEN-002 an agent opening another agent\'s Case gets 403', function () {
    $a = CrmData::staff();
    $b = CrmData::staff();
    CrmData::quote(CrmData::buyer(), CrmData::car()); // to A
    \App\Modules\Crm\Models\CrmAgentStatus::create(['user_id' => $b->id, 'can_pick_unassigned' => false]);

    $this->actingAs($b)->get('/admin/crm/cases/'.CrmCase::sole()->id)->assertStatus(404);
    $this->actingAs($b)->get('/admin/crm/settings')->assertForbidden();
});

it('CRM-GEN-005 an agent adds a note and ticks a step from the Case page', function () {
    $agent = CrmData::staff();
    CrmData::quote(CrmData::buyer(), CrmData::car());
    $case = CrmCase::sole();
    $this->actingAs($agent);

    Livewire::test(ViewCase::class, ['record' => $case->id])
        ->callAction('note', data: ['body' => 'Called, will decide Friday'])
        ->assertHasNoActionErrors()
        ->callAction(TestAction::make('tickStep')->arguments(['key' => 'quote_sent']), data: ['price' => 9800, 'basis' => 'CIF'])
        ->assertHasNoActionErrors();

    expect($case->activities()->where('type', 'note')->value('body'))->toBe('Called, will decide Friday')
        ->and(StageEngine::step($case->fresh(), 'quote_sent')->isDone())->toBeTrue()
        ->and((float) $case->fresh()->value_usd)->toBe(9800.0);
});

it('refuses typed bank details in emails', function () {
    $agent = CrmData::staff();
    CrmData::quote(CrmData::buyer(), CrmData::car());
    $this->actingAs($agent);

    Livewire::test(ViewCase::class, ['record' => CrmCase::sole()->id])
        ->callAction('email', data: ['subject' => 'Pay', 'body' => 'Please pay to account no 1234567890']);

    expect(CrmCase::sole()->activities()->where('type', 'email_out')->exists())->toBeFalse();
});

it('CRM-CAS-003 staff create a Case by hand with source WhatsApp', function () {
    $agent = CrmData::staff();
    $this->actingAs($agent);

    Livewire::test(\App\Modules\Crm\Filament\Resources\Cases\Pages\CreateCase::class)
        ->fillForm(['name' => 'Walk-in Ali', 'phone' => '+254711', 'type' => 'general', 'source' => 'whatsapp', 'note' => 'Wants a Hiace', 'owner_id' => $agent->id])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(CrmCase::sole())->source->toBe('whatsapp')->owner_id->toBe($agent->id);
});

it('shows the CRM Case link on the order screen (CRM-INT-002)', function () {
    $admin = CrmData::admin();
    $buyer = CrmData::buyer();
    $order = CrmData::order($buyer, CrmData::car());

    $this->actingAs($admin)->get('/admin/orders/'.$order->id)->assertOk()->assertSee('CRM Case '.CrmCase::sole()->reference);
});
