<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'firstname' => ['sometimes', 'string', 'max:100'],
            'lastname'  => ['sometimes', 'string', 'max:100'],
            'email'     => ['sometimes', 'email', Rule::unique('users')->ignore($this->user()->id)],
            'phone'     => ['nullable', 'string', 'max:20'],
        ];
    }
}
