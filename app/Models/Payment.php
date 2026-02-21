<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasUuids, HasFactory, SoftDeletes, HasAuditLog;

    protected $fillable = [
        'student_id', 'installment_id', 'recorded_by',
        'amount', 'penalty_amount', 'currency',
        'mode', 'reference', 'mobile_money_ref',
        'status', 'cancellation_reason', 'cancelled_at', 'cancelled_by',
        'paid_at', 'notes',
    ];

    protected $casts = [
        'amount'         => 'decimal:2',
        'penalty_amount' => 'decimal:2',
        'paid_at'        => 'datetime',
        'cancelled_at'   => 'datetime',
    ];

    // ── Relations ─────────────────────────────────────────────────────────

    public function student(): BelongsTo     { return $this->belongsTo(Student::class); }
    public function installment(): BelongsTo { return $this->belongsTo(Installment::class); }
    public function recordedBy(): BelongsTo  { return $this->belongsTo(User::class, 'recorded_by'); }
    public function cancelledBy(): BelongsTo { return $this->belongsTo(User::class, 'cancelled_by'); }
    public function receipt(): HasOne        { return $this->hasOne(Receipt::class); }

    // ── Méthodes métier ───────────────────────────────────────────────────

    public function isPartial(): bool
    {
        return $this->amount < $this->installment->amount;
    }

    public function cancel(string $reason, string $userId): void
    {
        $this->update([
            'status'               => 'cancelled',
            'cancellation_reason'  => $reason,
            'cancelled_at'         => now(),
            'cancelled_by'         => $userId,
        ]);
    }

    public function getTotalAmount(): float
    {
        return (float) $this->amount + (float) $this->penalty_amount;
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeConfirmed($query)    { return $query->where('status', 'confirmed'); }
    public function scopeThisMonth($query)    { return $query->whereMonth('paid_at', now()->month); }
}
