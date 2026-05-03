<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'cpf_cnpj' => ['required', 'string', 'max:18', 'unique:users,cpf_cnpj'],
            'phone' => ['nullable', 'string', 'max:20'],
        ];
    }
}
