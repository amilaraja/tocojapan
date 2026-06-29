<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VehicleListRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:120'],
            'make' => ['nullable', 'string', 'max:60'],
            'vehicle_model' => ['nullable', 'string', 'max:60'],
            'body_type' => ['nullable', 'string', 'max:60'],
            'year_from' => ['nullable', 'integer', 'between:1980,2030'],
            'year_to' => ['nullable', 'integer', 'between:1980,2030'],
            'price_from' => ['nullable', 'numeric', 'min:0'],
            'price_to' => ['nullable', 'numeric', 'min:0'],
            'mileage_min' => ['nullable', 'integer', 'min:0'],
            'mileage_max' => ['nullable', 'integer', 'min:0'],
            'engine_from' => ['nullable', 'integer', 'min:0'],
            'engine_to' => ['nullable', 'integer', 'min:0'],
            'transmission' => ['nullable', 'in:automatic,manual,cvt'],
            'fuel' => ['nullable', 'in:petrol,diesel,hybrid,electric,lpg'],
            'steering' => ['nullable', 'in:right,left'],
            'drive' => ['nullable', 'in:2wd,4wd,awd'],
            'featured' => ['nullable', 'boolean'],
            'discounted' => ['nullable', 'boolean'],
            'new_only' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'in:price_asc,price_desc,year_desc,year_asc,latest'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:60'],
        ];
    }
}
