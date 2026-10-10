<?php

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

/*
 * Docs are built with the code (TOC-PAPI-FR-DOC-01): a partner endpoint or
 * error code that is not documented fails the build.
 */

function partnerSpec(): array
{
    return json_decode(file_get_contents(app_path('Modules/PartnerApi/docs/openapi.json')), true, flags: JSON_THROW_ON_ERROR);
}

/** @return list<string> "METHOD /path" for every partner route */
function partnerRoutes(): array
{
    $prefix = trim(config('partner_api.prefix'), '/');

    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route) => str_starts_with((string) $route->getName(), 'partner.'))
        ->flatMap(fn (RoutingRoute $route) => collect($route->methods())
            ->reject(fn ($method) => $method === 'HEAD')
            ->map(fn ($method) => $method.' '.substr($route->uri(), strlen($prefix))))
        ->values()->all();
}

it('documents every partner route in openapi.json, and nothing that does not exist', function () {
    $documented = collect(partnerSpec()['paths'])
        ->flatMap(fn ($operations, $path) => collect(array_keys($operations))
            ->filter(fn ($key) => in_array($key, ['get', 'post', 'put', 'patch', 'delete'], true))
            ->map(fn ($method) => strtoupper($method).' '.$path))
        ->sort()->values()->all();

    $routes = collect(partnerRoutes())->sort()->values()->all();

    expect($routes)->not->toBeEmpty()->and($documented)->toBe($routes);
});

it('documents every error code the module can return in guide.md', function () {
    $guide = file_get_contents(app_path('Modules/PartnerApi/docs/guide.md'));
    $source = collect(File::allFiles(app_path('Modules/PartnerApi')))
        ->filter(fn ($file) => $file->getExtension() === 'php')
        ->map(fn ($file) => $file->getContents())->implode("\n");

    preg_match_all("/PartnerApiException\(\s*\d{3},\s*'([a-z_]+)'/", $source, $thrown);
    preg_match_all("/oauthError\(\s*\d{3},\s*'([a-z_]+)'/", $source, $oauth);
    preg_match_all("/self::make\(\\\$request,\s*\d{3},\s*'([a-z_]+)'/", $source, $mapped);
    preg_match_all("/'([a-z_]+)' => '[^']+',/", file_get_contents(app_path('Modules/PartnerApi/Http/Middleware/AuthenticatePartner.php')), $denials);

    $codes = collect([...$thrown[1], ...$oauth[1], ...$mapped[1], ...$denials[1]])->unique()->values();
    expect($codes->count())->toBeGreaterThan(10);

    $missing = $codes->reject(fn ($code) => str_contains($guide, 'id="error-'.str_replace('_', '-', $code).'"'))->values()->all();
    expect($missing)->toBe([]);
});
