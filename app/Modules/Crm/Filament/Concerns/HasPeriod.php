<?php

namespace App\Modules\Crm\Filament\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;

/** A from / until date range in Asia/Tokyo days. */
trait HasPeriod
{
    #[Url] public ?string $from = null;

    #[Url] public ?string $until = null;

    public function mountHasPeriod(): void
    {
        $this->from ??= now('Asia/Tokyo')->subDays(29)->toDateString();
        $this->until ??= now('Asia/Tokyo')->toDateString();
    }

    public function periodFrom(): CarbonInterface
    {
        return Carbon::parse($this->from ?: now()->subDays(29)->toDateString(), 'Asia/Tokyo')->startOfDay()->utc();
    }

    public function periodTo(): CarbonInterface
    {
        return Carbon::parse($this->until ?: now()->toDateString(), 'Asia/Tokyo')->endOfDay()->utc();
    }
}
