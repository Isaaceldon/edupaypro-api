<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'firstname'     => $this->firstname,
            'lastname'      => $this->lastname,
            'full_name'     => $this->full_name,
            'email'         => $this->email,
            'phone'         => $this->phone,
            'role'          => $this->role,
            'role_label'    => $this->getRoleLabel(),
            'is_active'     => $this->is_active,
            'two_fa_enabled'=> $this->two_fa_enabled,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'school'        => $this->whenLoaded('school', fn() => [
                'id'   => $this->school->id,
                'name' => $this->school->name,
                'logo' => $this->school->logo_url,
            ]),
            'permissions' => [
                'can_manage_users'    => $this->canManageUsers(),
                'can_manage_payments' => $this->canManagePayments(),
                'is_director'         => $this->isDirector(),
                'is_super_admin'      => $this->isSuperAdmin(),
            ],
            'created_at'    => $this->created_at->toIso8601String(),
        ];
    }

    private function getRoleLabel(): string
    {
        return match($this->role) {
            'super_admin' => 'Super Administrateur',
            'director'    => 'Directeur',
            'accountant'  => 'Comptable',
            'teacher'     => 'Enseignant',
            default       => ucfirst($this->role),
        };
    }
}
