<?php

use App\Models\User;
use App\Modules\PartnerApi\Models\PartnerAuditLog;
use Tests\PartnerApi\Support\Partners;

it('records partner creation and every settings change with before and after (TOC-PAPI-FR-PRT-03)', function () {
    $admin = User::factory()->create(['name' => 'Toco Admin']);
    $this->actingAs($admin);

    $partner = Partners::make();
    $partner->update(['price_mode' => 'percent', 'price_markup' => 8, 'webhook_secret' => 'whsec_123']);

    $created = PartnerAuditLog::where('action', 'partner.created')->sole();
    expect($created->actor_name)->toBe('Toco Admin')
        ->and($created->after)->toHaveKey('attribution_code')
        ->and($created->after)->not->toHaveKey('webhook_secret');

    $updated = PartnerAuditLog::where('action', 'partner.updated')->sole();
    expect($updated->before['price_mode'])->toBe('as_is')
        ->and($updated->after['price_mode'])->toBe('percent')
        ->and($updated->after['webhook_secret'])->toBe('(changed)')
        ->and(json_encode($updated->getAttributes()))->not->toContain('whsec_123');
});

it('records key creation, rotation and revocation without the secret', function () {
    [$client, $secret] = Partners::client();
    $newSecret = $client->rotate();
    $client->revoke('Leaked in a public repository');

    expect(PartnerAuditLog::pluck('action')->all())
        ->toContain('client.created', 'client.rotated', 'client.revoked');

    $all = PartnerAuditLog::all()->toJson();
    expect($all)->not->toContain($secret)->not->toContain($newSecret)
        ->and(PartnerAuditLog::where('action', 'client.revoked')->value('reason'))->toBe('Leaked in a public repository');
});

it('cannot be edited or deleted', function () {
    Partners::make();
    $row = PartnerAuditLog::firstOrFail();

    expect(fn () => $row->update(['action' => 'tampered']))->toThrow(LogicException::class)
        ->and(fn () => $row->delete())->toThrow(LogicException::class);
});

it('logs suspend and revoke with the reason, and revoke kills every key', function () {
    [$client] = Partners::client();
    $partner = $client->partner;

    $partner->suspend('Unpaid invoice');
    $partner->revoke('Contract terminated');

    $changes = PartnerAuditLog::where('action', 'partner.updated')->get();
    expect($changes->pluck('after.status')->all())->toBe(['suspended', 'revoked'])
        ->and($changes->last()->after['status_reason'])->toBe('Contract terminated')
        ->and($client->fresh()->status)->toBe('revoked');
});
