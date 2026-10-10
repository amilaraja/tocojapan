<?php

use App\Modules\PartnerApi\Domain\Auth\ClientSecret;

it('makes 256-bit secrets with a readable prefix, hashed with Argon2id (TOC-PAPI-§8.1)', function () {
    $secret = ClientSecret::generate('live');

    expect($secret)->toMatch('/^tcp_live_[A-Za-z0-9_-]{43}$/')
        ->and(ClientSecret::prefix($secret))->toBe(substr($secret, 0, 13))
        ->and(ClientSecret::generate('live'))->not->toBe($secret);

    $hash = ClientSecret::hash($secret);
    expect($hash)->toStartWith('$argon2id$')
        ->and(ClientSecret::verify($secret, $hash))->toBeTrue()
        ->and(ClientSecret::verify($secret.'x', $hash))->toBeFalse();
});

it('makes distinct client ids and access tokens', function () {
    expect(ClientSecret::generateClientId())->toMatch('/^tcc_[0-9a-f]{20}$/')
        ->and(ClientSecret::generateAccessToken())->toMatch('/^tcat_[A-Za-z0-9_-]{43}$/');
});
