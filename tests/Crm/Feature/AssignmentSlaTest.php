<?php

use App\Models\ContactInquiry;
use App\Modules\Crm\Domain\Assigner;
use App\Modules\Crm\Domain\BusinessCalendar;
use App\Modules\Crm\Domain\CaseActions;
use App\Modules\Crm\Domain\Crm;
use App\Modules\Crm\Domain\SlaClock;
use App\Modules\Crm\Models\CrmAgentStatus;
use App\Modules\Crm\Models\CrmCase;
use App\Modules\Crm\Support\CrmDefaults;
use App\Modules\Crm\Support\CrmSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\Crm\Support\CrmData;

beforeEach(function () {
    CrmData::reset();
    Queue::fake([\App\Modules\Crm\Jobs\SendCrmEmail::class]);
});

it('CRM-ASN-002 assigns new quote requests A, B, A', function () {
    $a = CrmData::staff();
    $b = CrmData::staff();
    $owners = collect(range(1, 3))->map(function () {
        CrmData::quote(CrmData::buyer(), CrmData::car());

        return CrmCase::latest('id')->first()->owner_id;
    });

    expect($owners->all())->toBe([$a->id, $b->id, $a->id]);
});

it('CRM-ASN-003 skips an agent at the open-case limit, and agents who are away', function () {
    $a = CrmData::staff();
    $b = CrmData::staff();
    \App\Modules\Crm\Models\CrmQueue::where('name', 'Sales')->update(['max_open_cases' => 1]);
    CrmData::quote(CrmData::buyer(), CrmData::car()); // A
    CrmData::quote(CrmData::buyer(), CrmData::car()); // B
    CrmData::quote(CrmData::buyer(), CrmData::car()); // nobody: both full

    expect(CrmCase::latest('id')->first()->owner_id)->toBeNull();

    \App\Modules\Crm\Models\CrmQueue::where('name', 'Sales')->update(['max_open_cases' => 30]);
    CrmAgentStatus::create(['user_id' => $a->id, 'away_from' => now()->subDay(), 'away_until' => now()->addDay()]);
    CrmData::quote(CrmData::buyer(), CrmData::car());
    expect(CrmCase::latest('id')->first()->owner_id)->toBe($b->id);
});

it('CRM-ASN-004 gives a returning customer to the agent of their earlier Case', function () {
    $a = CrmData::staff();
    $b = CrmData::staff();
    $buyer = CrmData::buyer();
    CrmData::quote(CrmData::buyer(), CrmData::car()); // A
    CrmData::quote($buyer, CrmData::car()); // B
    CrmData::quote($buyer, CrmData::car()); // B again, not A

    expect(CrmCase::latest('id')->first()->owner_id)->toBe($b->id);
});

it('CRM-ASN-006 keeps Cases unassigned when nobody is available and alerts the admins', function () {
    $admin = CrmData::admin();
    ContactInquiry::create(['name' => 'X', 'email' => 'x@example.com', 'message' => 'Hi']);

    expect(CrmCase::sole()->owner_id)->toBeNull()->and($admin->notifications()->count())->toBe(1);
});

it('CRM-ASN-005 admins reassign Cases and every change is logged', function () {
    $a = CrmData::staff();
    $b = CrmData::staff();
    $admin = CrmData::admin();
    CrmData::quote(CrmData::buyer(), CrmData::car());
    $case = CrmCase::sole();

    Assigner::assign($case, $b, 'A is on leave', $admin);

    expect($case->fresh()->owner_id)->toBe($b->id)
        ->and($case->activities()->where('type', 'assignment')->latest('id')->value('body'))->toContain('Reassigned from '.$a->name.' to '.$b->name);
});

it('CRM-SLA-001 a 2-business-hour target set Friday 17:30 JST is due Monday 10:30 JST', function () {
    CrmSettings::set('calendar.hours', [1 => ['09:00', '18:00'], 2 => ['09:00', '18:00'], 3 => ['09:00', '18:00'], 4 => ['09:00', '18:00'], 5 => ['09:00', '18:00']]);
    $friday = Carbon::parse('2026-10-16 17:30', 'Asia/Tokyo');

    $due = (new BusinessCalendar)->addMinutes($friday, 120);

    expect($due->setTimezone('Asia/Tokyo')->format('D Y-m-d H:i'))->toBe('Mon 2026-10-19 10:30');
});

it('skips Japanese public holidays', function () {
    CrmSettings::set('calendar.hours', [1 => ['09:00', '18:00'], 2 => ['09:00', '18:00'], 3 => ['09:00', '18:00'], 4 => ['09:00', '18:00'], 5 => ['09:00', '18:00']]);
    CrmDefaults::holidays();
    $friday = Carbon::parse('2026-11-20 17:00', 'Asia/Tokyo'); // Mon 23 Nov is Labour Thanksgiving Day

    expect((new BusinessCalendar)->addMinutes($friday, 120)->setTimezone('Asia/Tokyo')->format('D H:i'))->toBe('Tue 10:00');
});

it('UAT-02 warns the owner at 75 % and alerts owner and admins at the breach', function () {
    $agent = CrmData::staff();
    $admin = CrmData::admin();
    CrmData::quote(CrmData::buyer(), CrmData::car());

    $this->travel(91)->minutes();
    $this->artisan('crm:tick');
    expect($agent->notifications()->where('data->title', 'like', 'Reply due soon%')->count())->toBe(1);

    $this->travel(30)->minutes();
    $this->artisan('crm:tick');
    expect($agent->notifications()->where('data->title', 'like', 'SLA breached%')->count())->toBe(1)
        ->and($admin->notifications()->where('data->title', 'like', 'SLA breached%')->count())->toBe(1)
        ->and(SlaClock::colour(CrmCase::sole()))->toBe('danger');
});

it('CRM-SLA-002 a Case waiting on the customer does not breach', function () {
    CrmData::staff();
    CrmData::quote(CrmData::buyer(), CrmData::car());
    $case = CrmCase::sole();
    CaseActions::setState($case, Crm::STATE_WAITING, null);

    $this->travel(2)->days();
    $this->artisan('crm:tick');

    expect($case->slaTimers()->whereNotNull('breached_at')->count())->toBe(0);
});

it('UAT-09 stale after 3 days for the owner, 5 days for admins', function () {
    $agent = CrmData::staff();
    $admin = CrmData::admin();
    CrmData::quote(CrmData::buyer(), CrmData::car());

    $this->travel(3)->days();
    $this->travel(1)->minutes();
    $this->artisan('crm:tick');
    expect(CrmCase::sole()->stale_at)->not->toBeNull()
        ->and($agent->notifications()->where('data->title', 'like', 'No activity for 3 days%')->count())->toBe(1);

    $this->travel(2)->days();
    $this->artisan('crm:tick');
    expect($admin->notifications()->where('data->title', 'like', 'No activity for 5 days%')->count())->toBe(1);
});

it('CRM-ACT-005 an open Case needs a follow-up date', function () {
    CrmData::quote(CrmData::buyer(), CrmData::car());
    expect(fn () => CaseActions::setFollowUp(CrmCase::sole(), null))->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('CRM-GEN-002 an agent only sees their own Cases', function () {
    $a = CrmData::staff();
    $b = CrmData::staff();
    CrmData::quote(CrmData::buyer(), CrmData::car());
    CrmData::quote(CrmData::buyer(), CrmData::car());
    \App\Modules\Crm\Models\CrmAgentStatus::create(['user_id' => $b->id, 'can_pick_unassigned' => false]);

    expect(\App\Modules\Crm\Support\CrmAccess::scopeCases(CrmCase::query(), $b)->pluck('owner_id')->unique()->all())->toBe([$b->id]);
});
