<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'country' => $this->whenLoaded('country', fn () => [
                'id' => $this->country->id,
                'iso2' => $this->country->iso2,
                'name' => $this->country->name,
            ]),
            'locale' => $this->locale,
            'preferred_currency' => $this->preferred_currency,
            'roles' => $this->getRoleNames(),
            'email_verified' => $this->email_verified_at !== null,
        ];
    }
}
