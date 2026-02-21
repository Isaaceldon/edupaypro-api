<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasUuids, HasFactory, Notifiable, HasApiTokens, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'school_id',
        'firstname', 'lastname', 'email', 'phone', 'password',
        'role', 'is_active',
        'two_fa_secret', 'two_fa_enabled',
        'last_login_at',
    ];

    protected $hidden = [
        'password', 'remember_token', 'two_fa_secret',
    ];

    protected $casts = [
        'is_active'           => 'boolean',
        'two_fa_enabled'      => 'boolean',
        'email_verified_at'   => 'datetime',
        'last_login_at'       => 'datetime',
    ];

    // ── Relations ─────────────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    // ── Accessors ─────────────────────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        return "{$this->firstname} {$this->lastname}";
    }

    // ── Méthodes de permissions ───────────────────────────────────────────

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isDirector(): bool
    {
        return in_array($this->role, ['super_admin', 'director']);
    }

    public function isAccountant(): bool
    {
        return in_array($this->role, ['super_admin', 'director', 'accountant']);
    }

    public function isTeacher(): bool
    {
        return $this->role === 'teacher';
    }

    public function canManagePayments(): bool
    {
        return in_array($this->role, ['super_admin', 'director', 'accountant']);
    }

    public function canManageUsers(): bool
    {
        return in_array($this->role, ['super_admin', 'director']);
    }

    public function hasPermission(string $permission): bool
    {
        $permissions = [
            'super_admin' => ['*'],
            'director'    => [
                'school.view', 'school.edit',
                'users.view', 'users.manage',
                'students.view', 'students.manage',
                'fees.view', 'fees.manage',
                'payments.view', 'payments.manage',
                'reports.view', 'reports.export',
                'dashboard.view',
            ],
            'accountant'  => [
                'students.view', 'students.manage',
                'fees.view',
                'payments.view', 'payments.manage',
                'reports.view',
                'dashboard.view',
            ],
            'teacher'     => [
                'students.view',
                'dashboard.view',
            ],
        ];

        $rolePermissions = $permissions[$this->role] ?? [];

        return in_array('*', $rolePermissions) || in_array($permission, $rolePermissions);
    }
}
