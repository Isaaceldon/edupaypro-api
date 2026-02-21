<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasUuids;

    // Immuable : jamais de update ou delete
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'tenant_id', 'action',
        'model_type', 'model_id',
        'old_values', 'new_values', 'metadata',
        'ip_address', 'user_agent', 'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo   { return $this->belongsTo(User::class); }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }

    // Interdit toute modification
    public function update(array $attributes = [], array $options = []): bool { return false; }
    public function delete(): bool { return false; }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->created_at = $m->created_at ?? now());
    }
}
