<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $vehicleId = $this->route('vehicle');

        return [
            'client_id' => ['sometimes', 'integer', 'exists:users,id'],
            'plate' => ['sometimes', 'string', 'max:10', "unique:vehicles,plate,{$vehicleId}"],
            'brand' => ['sometimes', 'string', 'max:60'],
            'model' => ['sometimes', 'string', 'max:60'],
            'year' => ['sometimes', 'integer', 'min:1900', 'max:' . (date('Y') + 1)],
            'color' => ['nullable', 'string', 'max:30'],
        ];
    }
}
