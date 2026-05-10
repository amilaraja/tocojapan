<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuoteStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'contact_name' => ['required', 'string', 'max:120'],
            'contact_email' => ['required', 'email', 'max:200'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'port_id' => ['nullable', 'integer', 'exists:ports,id'],
            'message' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
