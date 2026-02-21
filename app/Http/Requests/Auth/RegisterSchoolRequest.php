<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterSchoolRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            // Directeur
            'firstname'    => ['required', 'string', 'max:100'],
            'lastname'     => ['required', 'string', 'max:100'],
            'email'        => ['required', 'email', 'unique:users,email'],
            'password'     => ['required', 'string', 'min:8', 'confirmed'],

            // École
            'school_name'  => ['required', 'string', 'max:200'],
            'school_phone' => ['nullable', 'string', 'max:20'],
            'school_email' => ['nullable', 'email'],
            'city'         => ['nullable', 'string', 'max:100'],
            'country'      => ['nullable', 'string', 'size:2'],
            'subdomain'    => ['nullable', 'string', 'max:50', 'alpha_dash', 'unique:tenants,subdomain'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique'         => 'Cet email est déjà utilisé.',
            'subdomain.unique'     => 'Ce sous-domaine est déjà pris.',
            'password.confirmed'   => 'Les mots de passe ne correspondent pas.',
            'password.min'         => 'Le mot de passe doit faire au moins 8 caractères.',
        ];
    }
}
