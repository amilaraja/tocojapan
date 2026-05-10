<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class CifCalculateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'port_id' => ['required', 'integer', 'exists:ports,id'],
            'currency' => ['nullable', 'string', 'size:3'],

            // Either provide a vehicle slug...
            'vehicle_slug' => ['required_without_all:price_fob,m3', 'nullable', 'string', 'exists:vehicles,slug'],

            // ...or both manual fields.
            'price_fob' => ['required_without:vehicle_slug', 'nullable', 'numeric', 'min:0'],
            'm3' => ['required_without:vehicle_slug', 'nullable', 'numeric', 'min:0.01'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $hasVehicle = $this->filled('vehicle_slug');
            $hasManual = $this->filled('price_fob') && $this->filled('m3');
            if (! $hasVehicle && ! $hasManual) {
                $v->errors()->add('vehicle_slug', 'Provide either vehicle_slug or both price_fob and m3.');
            }
        });
    }
}
