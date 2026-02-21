<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeeConfig extends Model
{
    use HasUuids, HasFactory, SoftDeletes;

    protected $table = 'fee_configs';

    protected $fillable = [
        'class_id', 'academic_year_id', 'label', 'type',
        'total_amount', 'currency', 'is_mandatory', 'notes',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'is_mandatory' => 'boolean',
    ];

    // ── Relations ─────────────────────────────────────────────────────────

    public function class(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class)->orderBy('order');
    }

    // ── Méthodes métier ───────────────────────────────────────────────────

    public function getTotalDue(): float
    {
        return (float) $this->total_amount;
    }

    /**
     * Vérifie que la somme des tranches = total_amount.
     */
    public function isBalanced(): bool
    {
        return abs($this->installments->sum('amount') - $this->total_amount) < 0.01;
    }
}
