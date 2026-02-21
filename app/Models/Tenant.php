<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasUuids, HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'subdomain',
        'plan',
        'is_active',
        'settings',
        'trial_ends_at',
    ];

    protected $casts = [
        'is_active'     => 'boolean',
        'settings'      => 'array',
        'trial_ends_at' => 'datetime',
    ];

    // ── Relations ─────────────────────────────────────────────────────────

    public function schools(): HasMany
    {
        return $this->hasMany(School::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    // ── Méthodes métier ───────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isOnTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function getActiveSchool(): ?School
    {
        return $this->schools()->first();
    }
}
