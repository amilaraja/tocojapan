<?php

namespace App\Http\Resources;

use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Vehicle
 */
class VehicleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ref_no' => $this->ref_no,
            'slug' => $this->slug,
            'title' => $this->title,
            'status' => $this->status,
            'year' => $this->year_first_reg,
            'mileage_km' => $this->mileage_km,
            'engine_cc' => $this->engine_cc,
            'fuel' => $this->fuel,
            'transmission' => $this->transmission,
            'drive' => $this->drive,
            'steering_side' => $this->steering_side,
            'doors' => $this->doors,
            'seats' => $this->seats,
            'exterior_color' => $this->exterior_color,
            'interior_color' => $this->interior_color,
            'dimensions_cm' => [
                'length' => $this->length_cm,
                'width' => $this->width_cm,
                'height' => $this->height_cm,
            ],
            'm3' => $this->m3,
            'price' => [
                'fob' => $this->price_fob,
                'currency' => $this->currency,
                'on_request' => $this->price_on_request,
            ],
            'warranty_period' => $this->warranty_period,
            'make' => $this->whenLoaded('make', fn () => [
                'id' => $this->make->id,
                'slug' => $this->make->slug,
                'name' => $this->make->name,
            ]),
            'model' => $this->whenLoaded('vehicleModel', fn () => [
                'id' => $this->vehicleModel->id,
                'slug' => $this->vehicleModel->slug,
                'name' => $this->vehicleModel->name,
            ]),
            'body_type' => $this->whenLoaded('bodyType', fn () => $this->bodyType ? [
                'id' => $this->bodyType->id,
                'slug' => $this->bodyType->slug,
                'name' => $this->bodyType->name,
            ] : null),
            'photos' => $this->getMedia('photos')->map(fn ($media) => [
                'url' => $media->getUrl(),
                'thumb_url' => $media->getUrl(),
            ])->all(),
            'features' => $this->features,
            'description' => $this->when($request->routeIs('api.v1.vehicles.show'), $this->description),
            'published_at' => $this->published_at,
        ];
    }
}
