<?php

namespace App\Support;

/**
 * SVG paths for the "How to Buy" 5-step block. Same source-of-truth pattern as
 * SocialPlatforms — the admin Select uses the names, the partial loops over
 * the registry to render the right glyph for each step.
 *
 * All paths assume a 24×24 viewBox. The card icon is rendered ~64px in a
 * red rounded square.
 */
class HowToBuyIcons
{
    /**
     * @return array<string, array{name: string, svg: string}>
     */
    public static function all(): array
    {
        return [
            'search' => [
                'name' => 'Search & order (magnifier)',
                'svg' => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
            ],
            'payment' => [
                'name' => 'Payment (credit card)',
                'svg' => '<rect x="2" y="6" width="20" height="12" rx="2"/><path d="M2 10h20"/><path d="M6 14h4"/>',
            ],
            'shipment' => [
                'name' => 'Car shipment (ship)',
                'svg' => '<path d="M3 18h18l-2-6H5z"/><path d="M12 12V6m-3 0h6"/><path d="M3 21c1-1 2-1 3 0s2 1 3 0 2-1 3 0 2 1 3 0 2-1 3 0"/>',
            ],
            'clearing' => [
                'name' => 'Clearing (clipboard)',
                'svg' => '<rect x="6" y="4" width="12" height="18" rx="2"/><path d="M9 4h6v3H9z"/><path d="m10 13 2 2 4-4"/>',
            ],
            'received' => [
                'name' => 'Car received (handover)',
                'svg' => '<path d="M3 17h18M5 17v-4l2-5h10l2 5v4"/><circle cx="8" cy="17" r="2"/><circle cx="16" cy="17" r="2"/><path d="M12 4v3"/>',
            ],
            'inquiry' => [
                'name' => 'Inquiry (chat bubble)',
                'svg' => '<path d="M21 12a8 8 0 1 1-3.5-6.6L21 4l-1.4 3.4A8 8 0 0 1 21 12z"/>',
            ],
            'buy_now' => [
                'name' => 'Buy now (cart)',
                'svg' => '<circle cx="9" cy="20" r="1.5"/><circle cx="17" cy="20" r="1.5"/><path d="M3 4h2l2.5 11h11l1.5-7H6"/>',
            ],
            'paypal' => [
                'name' => 'PayPal (P)',
                'svg' => '<path d="M7 4h6c3 0 5 2 4.5 5-.5 3-3 4-6 4H9l-1 7H5z"/><path d="M10 11h2c2 0 3-1 3-3"/>',
            ],
            'bank' => [
                'name' => 'Bank transfer (building)',
                'svg' => '<path d="M3 21h18M3 10h18M5 6l7-3 7 3M4 10v11M20 10v11M8 14v3M12 14v3M16 14v3"/>',
            ],
        ];
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $out = [];
        foreach (self::all() as $key => $meta) {
            $out[$key] = $meta['name'];
        }

        return $out;
    }

    public static function svg(?string $key): string
    {
        if (! $key) {
            return '';
        }

        return self::all()[$key]['svg'] ?? '';
    }
}
