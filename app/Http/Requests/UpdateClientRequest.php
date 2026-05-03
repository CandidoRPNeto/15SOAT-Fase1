<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clientId = $this->route('client');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', "unique:users,email,{$clientId}"],
            'password' => ['sometimes', 'string', 'min:8'],
            'cpf_cnpj' => ['sometimes', 'string', 'max:18', "unique:users,cpf_cnpj,{$clientId}"],
            'phone' => ['nullable', 'string', 'max:20'],
        ];
    }
}
