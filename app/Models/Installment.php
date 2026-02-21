<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Installment extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'fee_config_id', 'order', 'label',
        'amount', 'due_date', 'penalty_rate', 'penalty_fixed',
    ];

    protected $casts = [
        'amount'        => 'decimal:2',
        'penalty_rate'  => 'decimal:2',
        'penalty_fixed' => 'decimal:2',
        'due_date'      => 'date',
    ];

    // ── Relations ─────────────────────────────────────────────────────────

    public function feeConfig(): BelongsTo
    {
        return $this->belongsTo(FeeConfig::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    // ── Méthodes métier ───────────────────────────────────────────────────

    public function isPastDue(): bool
    {
        return $this->due_date->isPast();
    }

    public function getDaysOverdue(): int
    {
        if (!$this->isPastDue()) return 0;
        return (int) $this->due_date->diffInDays(now());
    }

    /**
     * Calcule la pénalité pour un élève donné.
     * Utilise soit le taux %, soit le montant fixe.
     */
    public function getPenalty(Student $student): float
    {
        if (!$this->isPastDue()) return 0;

        $paidAmount = $student->payments()
            ->where('installment_id', $this->id)
            ->where('status', 'confirmed')
            ->sum('amount');

        $remaining = $this->amount - $paidAmount;
        if ($remaining <= 0) return 0;

        if ($this->penalty_fixed > 0) {
            return (float) $this->penalty_fixed;
        }

        if ($this->penalty_rate > 0) {
            $months = ceil($this->getDaysOverdue() / 30);
            return round($remaining * ($this->penalty_rate / 100) * $months, 2);
        }

        return 0;
    }
}
