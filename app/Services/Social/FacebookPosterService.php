<?php

namespace App\Services\Social;

use App\Models\Vehicle;
use App\Settings\SocialSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookPosterService
{
    protected const API_VERSION = 'v19.0';
    protected const BASE_URL = 'https://graph.facebook.com';

    public function __construct(protected SocialSettings $settings) {}

    /**
     * Post a vehicle to the configured Facebook page.
     *
     * @return array{success: bool, post_id?: string, post_url?: string, error?: string}
     */
    public function shareVehicle(Vehicle $vehicle): array
    {
        if (! $this->settings->facebook_enabled) {
            return ['success' => false, 'error' => 'Facebook sharing is disabled in settings.'];
        }

        $pageId = $this->settings->facebook_page_id;
        $token = $this->settings->facebook_page_access_token;

        if (! $pageId || ! $token) {
            return ['success' => false, 'error' => 'Facebook Page ID or access token is not configured.'];
        }

        $message = $this->renderMessage($vehicle);
        $imageUrl = $this->resolveImageUrl($vehicle);

        $endpoint = $imageUrl
            ? self::BASE_URL.'/'.self::API_VERSION."/{$pageId}/photos"
            : self::BASE_URL.'/'.self::API_VERSION."/{$pageId}/feed";

        $payload = ['message' => $message, 'access_token' => $token];
        if ($imageUrl) {
            $payload['url'] = $imageUrl;
        } else {
            $payload['link'] = $this->vehicleUrl($vehicle);
        }

        try {
            $response = Http::asForm()
                ->timeout(20)
                ->post($endpoint, $payload);
        } catch (\Throwable $e) {
            Log::error('Facebook share request threw', ['vehicle_id' => $vehicle->id, 'error' => $e->getMessage()]);

            return ['success' => false, 'error' => 'Network error: '.$e->getMessage()];
        }

        if (! $response->ok()) {
            $err = $response->json('error.message') ?? $response->body();
            Log::error('Facebook share failed', ['vehicle_id' => $vehicle->id, 'status' => $response->status(), 'error' => $err]);

            return ['success' => false, 'error' => is_string($err) ? $err : 'Facebook API returned '.$response->status()];
        }

        $data = $response->json();
        $postId = $data['post_id'] ?? $data['id'] ?? null;

        if (! $postId) {
            return ['success' => false, 'error' => 'No post_id returned from Facebook.'];
        }

        return [
            'success' => true,
            'post_id' => (string) $postId,
            'post_url' => "https://www.facebook.com/{$postId}",
        ];
    }

    protected function renderMessage(Vehicle $vehicle): string
    {
        $template = $this->settings->facebook_post_template;
        $price = $vehicle->price_on_request
            ? 'Price on request'
            : ($vehicle->currency.' '.number_format((float) $vehicle->effectivePriceFob(), 0));

        return strtr($template, [
            '{title}' => $vehicle->title ?? '',
            '{ref_no}' => $vehicle->ref_no ?? '',
            '{year}' => $vehicle->year_first_reg ?? '',
            '{mileage}' => $vehicle->mileage_km !== null ? number_format($vehicle->mileage_km) : '',
            '{engine_cc}' => $vehicle->engine_cc ?? '',
            '{price}' => $price,
            '{url}' => $this->vehicleUrl($vehicle),
        ]);
    }

    protected function vehicleUrl(Vehicle $vehicle): string
    {
        return url('/vehicles/'.$vehicle->slug);
    }

    protected function resolveImageUrl(Vehicle $vehicle): ?string
    {
        if (! method_exists($vehicle, 'getFirstMediaUrl')) {
            return null;
        }

        $url = $vehicle->getFirstMediaUrl('photos');
        if (! $url) {
            return null;
        }

        // Facebook needs a publicly fetchable absolute URL.
        if (str_starts_with($url, 'http')) {
            return $url;
        }

        return url($url);
    }
}
