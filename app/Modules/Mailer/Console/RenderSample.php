<?php

namespace App\Modules\Mailer\Console;

use App\Modules\Mailer\Domain\Campaigns\CampaignRenderer;
use App\Modules\Mailer\Domain\Vehicles\VehicleSource;
use App\Modules\Mailer\Models\Banner;
use App\Modules\Mailer\Models\Campaign;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/** Renders a sample email with N real available vehicles. Saves nothing to the database. */
class RenderSample extends Command
{
    protected $signature = 'mailer:render-sample {n=6 : Number of vehicles (2 to 12)}';

    protected $description = 'Render a sample TOCO Mailer email to storage/app/mailer/sample-{n}.html';

    public function handle(VehicleSource $source, CampaignRenderer $renderer): int
    {
        $n = (int) $this->argument('n');
        if ($n < 1 || $n > (int) config('mailer.campaign.max_vehicles')) {
            $this->error('Choose between 1 and '.config('mailer.campaign.max_vehicles').' vehicles.');

            return self::FAILURE;
        }

        $vehicles = collect($source->search()->items())->take($n);
        if ($vehicles->count() < $n) {
            $vehicles = $vehicles->merge(collect($source->search([], 2)->items()))->take($n);
        }

        $campaign = new Campaign([
            'name' => "Sample {$n}",
            'slug' => 'sample-'.$n,
            'subject' => "This week's picks from TOCO International",
            'preview_text' => 'Hot deals and new arrivals this week. Inspected in Japan, shipped worldwide.',
            'kicker' => 'THIS WEEK’S PICKS',
            'headline' => 'Fresh stock and price cuts, ready to ship',
            'intro' => 'Every vehicle below has been inspected by our team in Japan. Prices are FOB in US dollars. Reply to this email or tap a vehicle to get a full quote to your port.',
        ]);
        $campaign->setRelation('banner', Banner::query()->active()->latest()->first());

        $html = $renderer->renderWith($campaign, $vehicles);
        $path = "mailer/sample-{$n}.html";
        Storage::disk('local')->put($path, $html);

        $this->info(sprintf('%d vehicles, %.1f KB → %s', $vehicles->count(), strlen($html) / 1024, Storage::disk('local')->path($path)));

        return self::SUCCESS;
    }
}
