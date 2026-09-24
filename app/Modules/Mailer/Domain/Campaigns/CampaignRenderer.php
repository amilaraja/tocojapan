<?php

namespace App\Modules\Mailer\Domain\Campaigns;

use App\Modules\Mailer\Domain\Vehicles\EmailImageService;
use App\Modules\Mailer\Domain\Vehicles\VehicleDTO;
use App\Modules\Mailer\Models\Campaign;
use App\Modules\Mailer\Models\CampaignVehicle;
use App\Modules\Mailer\Support\MailerSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

/**
 * Builds the campaign email HTML (TOC-TPL-*). The Campaign Builder preview
 * and the Brevo push both call render(), so they are byte-identical
 * (TOC-CB-003). Vehicles come from the campaign's snapshots, which
 * VehicleRecheck refreshes before a push.
 */
class CampaignRenderer
{
    /** Base rules inlined onto elements; @media rules stay in the <style> block. */
    protected const BASE_CSS = 'body{margin:0;padding:0;-webkit-text-size-adjust:100%;} a{color:#111114;}';

    /** Brevo tags must reach Brevo literally; shielded from the HTML serializer. */
    protected const BREVO_TAGS = ['{{ mirror }}' => 'BREVOTAGMIRROR', '{{ unsubscribe }}' => 'BREVOTAGUNSUBSCRIBE'];

    public const LOGO_PATH = 'email-assets/branding/toco-logo-300.png';

    public function __construct(
        protected MailerSettings $settings,
        protected EmailImageService $images,
        protected UtmTagger $utm,
    ) {}

    public function render(Campaign $campaign): string
    {
        $vehicles = $campaign->vehicles()->get()
            ->map(fn (CampaignVehicle $cv) => VehicleDTO::fromArray($cv->snapshot));

        return $this->renderWith($campaign, $vehicles);
    }

    /** @param  Collection<int, VehicleDTO>  $vehicles  in display order */
    public function renderWith(Campaign $campaign, Collection $vehicles): string
    {
        $settings = $this->settings->all();
        $slug = (string) ($campaign->slug ?: 'campaign');

        $html = view('mailer::email.campaign', [
            'subject' => (string) $campaign->subject,
            'previewText' => (string) $campaign->preview_text,
            'kicker' => $campaign->kicker,
            'headline' => $campaign->headline,
            'intro' => $campaign->intro,
            'settings' => $settings,
            'homeUrl' => 'https://tocojapan.com/',
            'logoUrl' => $this->logoUrl($settings['logo_path'] ?? null),
            'navLinks' => array_values(array_filter($settings['nav_links'] ?? [], fn ($l) => filled($l['label'] ?? null) && filled($l['url'] ?? null))),
            'banner' => $this->banner($campaign, $settings),
            'rows' => $vehicles->values()->map(fn (VehicleDTO $v) => $this->card($v))->chunk(2)->map->values()->all(),
            'ctaUrl' => $campaign->cta_url ?: $settings['cta_url'],
            'phoneHref' => preg_replace('/[^\d+]/', '', (string) $settings['footer_phone']),
            'whatsappHref' => preg_replace('/\D/', '', (string) $settings['footer_whatsapp']),
            ...$this->splitFraud((string) $settings['fraud_text']),
        ])->render();

        $html = $this->utm->tag($html, $slug);

        return $this->inline($html);
    }

    /** @return array<string, string|null> */
    protected function card(VehicleDTO $v): array
    {
        $price = $v->priceFob !== null ? '$'.number_format($v->priceFob) : 'ASK';

        return [
            'ref' => (string) ($v->stockRef ?? $v->id),
            'title' => $v->title,
            'meta' => $v->metaLine(),
            'badge' => $v->badgeLabel(),
            'badgeColor' => $v->badge === VehicleDTO::BADGE_HOT_DEAL ? '#E30613' : '#111114',
            'price' => $price,
            'oldPrice' => $v->previousPrice !== null && $v->priceFob !== null ? '$'.number_format($v->previousPrice) : null,
            'url' => $v->url,
            'img' => $this->images->urlFor($v),
            'alt' => $v->title.' – '.($v->priceFob !== null ? $price.' FOB' : 'price on request'),
        ];
    }

    /** @return array{url: string, alt: string, link: string}|null */
    protected function banner(Campaign $campaign, array $settings): ?array
    {
        $banner = $campaign->banner;
        if (! $banner) {
            return null;
        }

        return [
            'url' => $banner->url(),
            'alt' => (string) $banner->alt_text,
            'link' => $banner->link_url ?: 'https://tocojapan.com/vehicles',
        ];
    }

    protected function logoUrl(?string $path): string
    {
        $disk = Storage::disk('public');

        if ($path && $disk->exists($path)) {
            return $disk->url($path);
        }

        if (! $disk->exists(self::LOGO_PATH)) {
            $disk->put(self::LOGO_PATH, (string) file_get_contents(dirname(__DIR__, 2).'/resources/assets/toco-logo-300.png'));
        }

        return $disk->url(self::LOGO_PATH);
    }

    /** "Beware of fraudsters. Always verify…" → bold first sentence, as in the design. */
    protected function splitFraud(string $text): array
    {
        if (preg_match('/^(.{3,60}?[.!])\s+(.+)$/s', trim($text), $m)) {
            return ['fraudLead' => $m[1], 'fraudRest' => $m[2]];
        }

        return ['fraudLead' => null, 'fraudRest' => $text];
    }

    protected function inline(string $html): string
    {
        $html = strtr($html, self::BREVO_TAGS);
        $html = (new CssToInlineStyles)->convert($html, self::BASE_CSS);

        return strtr($html, array_flip(self::BREVO_TAGS));
    }
}
