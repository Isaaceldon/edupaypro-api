<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class School extends Model
{
    use HasUuids, HasFactory, SoftDeletes, HasAuditLog;

    protected $fillable = [
        'tenant_id', 'name', 'logo_url', 'address', 'city', 'country',
        'phone', 'email', 'website', 'rccm', 'ifu',
        'director_name', 'signature_url', 'stamp_url', 'receipt_prefix',
    ];

    // ── Relations ─────────────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function academicYears(): HasMany
    {
        return $this->hasMany(AcademicYear::class);
    }

    public function activeYear(): HasOne
    {
        return $this->hasOne(AcademicYear::class)->where('is_active', true);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class, 'school_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    // ── Méthodes métier ───────────────────────────────────────────────────

    public function getActiveYear(): ?AcademicYear
    {
        return $this->activeYear;
    }

    /**
     * Retourne les statistiques financières de l'école.
     */
    public function getStats(string $academicYearId = null): array
    {
        $yearId = $academicYearId ?? $this->activeYear?->id;

        $payments = Payment::query()
            ->whereHas('student', fn ($q) => $q->where('school_id', $this->id))
            ->where('status', 'confirmed')
            ->when($yearId, fn ($q) => $q->whereHas('installment.feeConfig', fn ($q2) =>
                $q2->where('academic_year_id', $yearId)
            ))
            ->selectRaw('SUM(amount) as total_collected, COUNT(*) as payment_count')
            ->first();

        $totalDue = Installment::query()
            ->whereHas('feeConfig', fn ($q) =>
                $q->whereHas('class', fn ($q2) => $q2->where('school_id', $this->id))
                  ->when($yearId, fn ($q2) => $q2->where('academic_year_id', $yearId))
            )
            ->sum('amount');

        $collected = $payments->total_collected ?? 0;

        return [
            'total_due'       => $totalDue,
            'total_collected' => $collected,
            'remaining'       => $totalDue - $collected,
            'recovery_rate'   => $totalDue > 0 ? round(($collected / $totalDue) * 100, 1) : 0,
            'payment_count'   => $payments->payment_count ?? 0,
        ];
    }

    /**
     * Génère le prochain numéro de reçu séquentiel.
     */
    public function nextReceiptNumber(): string
    {
        $prefix = $this->receipt_prefix ?? 'REC';
        $year   = now()->year;
        $last   = Receipt::query()
            ->whereHas('payment.student', fn ($q) => $q->where('school_id', $this->id))
            ->where('number', 'like', "{$prefix}-{$year}-%")
            ->orderByDesc('number')
            ->value('number');

        $seq = $last ? (int) substr($last, strrpos($last, '-') + 1) + 1 : 1;

        return sprintf('%s-%d-%06d', $prefix, $year, $seq);
    }
}
