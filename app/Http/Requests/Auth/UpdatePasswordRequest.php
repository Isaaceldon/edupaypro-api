<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'new_password'     => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ];
    }

    public function messages(): array
    {
        return [
            'new_password.different'  => 'Le nouveau mot de passe doit être différent de l\'ancien.',
            'new_password.confirmed'  => 'La confirmation du nouveau mot de passe ne correspond pas.',
        ];
    }
}
