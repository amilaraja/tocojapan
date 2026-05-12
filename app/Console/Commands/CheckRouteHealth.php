<?php

namespace App\Console\Commands;

use App\Models\Page;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;

class CheckRouteHealth extends Command
{
    protected $signature = 'routes:health
        {--filter= : Only test routes whose URI matches this regex}
        {--auth-as= : Email of a user to sign in as for protected routes}
        {--include-admin : Also test /admin/* Filament routes}
        {--show-failures-only : Only print rows that failed}
        {--http : Hit the live HTTP server with Http::get instead of in-process dispatch}';

    protected $description = 'Hit every GET route in the app and report status, response time, and basic content health.';

    /** Resolved wildcard values, keyed by parameter name. */
    protected array $params = [];

    public function handle(): int
    {
        $this->resolveParams();

        if ($email = $this->option('auth-as')) {
            $user = User::where('email', $email)->first();
            if (! $user) {
                $this->error("No user found with email {$email}");

                return self::FAILURE;
            }
            Auth::login($user);
            $this->info('Signed in as '.$user->email.' (id '.$user->id.')');
        }

        $filter = $this->option('filter') ? '/'.$this->option('filter').'/' : null;
        $includeAdmin = (bool) $this->option('include-admin');
        $useHttp = (bool) $this->option('http');
        $showOnlyFailures = (bool) $this->option('show-failures-only');

        $tests = collect(RouteFacade::getRoutes())
            ->filter(function (Route $r) use ($filter, $includeAdmin) {
                if (! in_array('GET', $r->methods(), true)) {
                    return false;
                }
                $uri = $r->uri();
                if (str_starts_with($uri, '_ignition') || str_starts_with($uri, '_debugbar') || str_starts_with($uri, 'livewire')) {
                    return false;
                }
                if (str_starts_with($uri, 'sanctum/') || str_starts_with($uri, 'api/')) {
                    return false; // these need JSON Accept + tokens
                }
                if (! $includeAdmin && str_starts_with($uri, 'admin')) {
                    return false;
                }
                if ($filter && ! @preg_match($filter, $uri)) {
                    return false;
                }

                return true;
            })
            ->values();

        $this->info('Testing '.$tests->count().' routes...');
        $this->newLine();

        $rows = [];
        $failCount = 0;
        $bar = $this->output->createProgressBar($tests->count());
        $bar->start();

        foreach ($tests as $route) {
            $result = $this->testRoute($route, $useHttp);
            $rows[] = $result;
            if (! $result['ok']) {
                $failCount++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $display = $showOnlyFailures ? array_filter($rows, fn ($r) => ! $r['ok']) : $rows;

        $this->table(
            ['', 'Method', 'URI', 'Status', 'Time', 'Notes'],
            array_map(fn ($r) => [
                $r['ok'] ? '<fg=green>OK</>' : '<fg=red>FAIL</>',
                'GET',
                Str::limit($r['uri'], 50),
                $r['status'] ?? '-',
                isset($r['time_ms']) ? $r['time_ms'].'ms' : '-',
                Str::limit($r['notes'] ?? '', 60),
            ], $display)
        );

        $total = count($rows);
        $passed = $total - $failCount;
        $this->newLine();
        $this->line("Total: {$total}   Passed: <fg=green>{$passed}</>   Failed: <fg=red>{$failCount}</>");

        return $failCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Pre-resolve wildcard parameter values from the database so we can
     * test routes that take {vehicle}/{slug}/{order}/etc.
     */
    protected function resolveParams(): void
    {
        $vehicle = Vehicle::query()->published()->orderBy('id', 'desc')->first();
        $page = Page::query()->where('status', 'published')->first();

        $this->params = [
            'slug' => $vehicle?->slug,            // used by /vehicles/{slug} + /checkout/bank/{slug}
            'vehicle' => $vehicle?->slug,
            'cms_slug' => $page?->slug ?? 'home', // used for /{slug} CMS catch-all
            'token' => Str::random(40),
            'code' => 'USD',
            'currency' => 'USD',
            'record' => 1,
            'make' => 'toyota',
            'id' => 1,
            'hash' => sha1('test'),
        ];

        if ($user = Auth::user()) {
            $order = \App\Models\Order::query()->where('user_id', $user->id)->latest()->first();
            $quote = \App\Models\Quote::query()->where('user_id', $user->id)->latest()->first();
            $this->params['order'] = $order?->id;
            $this->params['quote'] = $quote?->id;
        }
    }

    /**
     * Test a single route. Returns ['ok' => bool, 'status' => int|null,
     * 'uri' => string, 'time_ms' => int, 'notes' => string].
     */
    protected function testRoute(Route $route, bool $useHttp): array
    {
        $uri = $this->buildUri($route);
        if ($uri === null) {
            return [
                'ok' => false,
                'uri' => $route->uri(),
                'status' => null,
                'time_ms' => 0,
                'notes' => 'Could not resolve wildcard params',
            ];
        }

        $start = microtime(true);
        try {
            if ($useHttp) {
                $base = rtrim(config('app.url'), '/');
                $response = \Illuminate\Support\Facades\Http::withOptions(['verify' => false])
                    ->timeout(15)
                    ->get($base.'/'.ltrim($uri, '/'));
                $status = $response->status();
                $body = $response->body();
            } else {
                $request = Request::create('/'.ltrim($uri, '/'), 'GET');
                $response = app(\Illuminate\Contracts\Http\Kernel::class)->handle($request);
                $status = $response->getStatusCode();
                $body = (string) $response->getContent();
                app(\Illuminate\Contracts\Http\Kernel::class)->terminate($request, $response);
            }
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'uri' => $uri,
                'status' => null,
                'time_ms' => (int) round((microtime(true) - $start) * 1000),
                'notes' => 'Exception: '.Str::limit($e->getMessage(), 80),
            ];
        }

        $time = (int) round((microtime(true) - $start) * 1000);
        $notes = [];
        $ok = $status >= 200 && $status < 400;

        if ($status >= 500) {
            $notes[] = 'SERVER ERROR';
        } elseif ($status === 404) {
            $notes[] = 'NOT FOUND';
        } elseif ($status === 403) {
            $notes[] = 'FORBIDDEN';
        } elseif ($status >= 300 && $status < 400) {
            $notes[] = 'redirect';
        }

        // Sanity content checks on 2xx responses.
        if ($ok && stripos((string) ($response->headers->get('Content-Type') ?? ''), 'text/html') !== false) {
            if (stripos($body, '<html') === false) {
                $notes[] = 'no <html>';
                $ok = false;
            }
            if (preg_match('/(?:Whoops|Fatal error|Uncaught|stack trace)/i', $body)) {
                $notes[] = 'error trace in body';
                $ok = false;
            }
            if (preg_match('/<title>\s*<\/title>/i', $body)) {
                $notes[] = 'empty <title>';
            }
        }

        return [
            'ok' => $ok,
            'uri' => $uri,
            'status' => $status,
            'time_ms' => $time,
            'notes' => implode(', ', $notes),
        ];
    }

    /**
     * Substitute {param} wildcards using resolved values. Returns null if
     * any required param has no value.
     */
    protected function buildUri(Route $route): ?string
    {
        $uri = $route->uri();
        if (! preg_match_all('/\{([a-zA-Z_]+)\??\}/', $uri, $m)) {
            return $uri;
        }
        // CMS catch-all (/{slug} → PageController::show) should resolve to a Page slug,
        // not a vehicle slug. Route name 'cms.page' is the discriminator.
        $cmsCatchAll = $route->getName() === 'cms.page';

        foreach ($m[0] as $i => $placeholder) {
            $name = $m[1][$i];
            $optional = str_ends_with($placeholder, '?}');
            $value = $cmsCatchAll && $name === 'slug'
                ? ($this->params['cms_slug'] ?? null)
                : ($this->params[$name] ?? null);

            if ($value === null) {
                if ($optional) {
                    $uri = str_replace('/'.$placeholder, '', $uri);

                    continue;
                }

                return null;
            }
            $uri = str_replace($placeholder, (string) $value, $uri);
        }

        return $uri;
    }
}
