<?php

namespace App\Console\Commands\Migration;

use Illuminate\Support\Facades\File;

/**
 * Tiny helper that writes a per-run JSON report under storage/migration-reports/
 * so a re-run is auditable.
 */
class MigrationReporter
{
    /** @var array<string, mixed> */
    private array $current = [];

    public function open(string $name): string
    {
        $this->current = [
            'name' => $name,
            'started_at' => now()->toIso8601String(),
            'notes' => [],
        ];

        return $name;
    }

    public function note(string $key, mixed $value): void
    {
        $this->current['notes'][$key] = $value;
    }

    public function close(string $name): void
    {
        $this->current['finished_at'] = now()->toIso8601String();

        $dir = storage_path('migration-reports');
        File::ensureDirectoryExists($dir);

        $file = $dir.'/'.$name.'-'.now()->format('Ymd-His').'.json';
        File::put($file, json_encode($this->current, JSON_PRETTY_PRINT));
    }
}
