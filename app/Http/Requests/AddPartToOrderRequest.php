<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddPartToOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'part_id' => ['required', 'integer', 'exists:parts,id'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
