<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:users,id'],
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'notes' => ['nullable', 'string'],
            'services' => ['sometimes', 'array'],
            'services.*.service_id' => ['required_with:services', 'integer', 'exists:services,id'],
            'services.*.quantity' => ['sometimes', 'integer', 'min:1'],
            'items' => ['sometimes', 'array'],
            'items.*.item_id' => ['required_with:items', 'integer', 'exists:items,id'],
            'items.*.quantity' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
