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
use Illuminate\Support\Str;

class Student extends Model
{
    use HasUuids, HasFactory, SoftDeletes, HasAuditLog;

    protected $fillable = [
        'school_id', 'class_id', 'sibling_group_id',
        'matricule', 'firstname', 'lastname',
        'birth_date', 'birth_place', 'gender', 'nationality',
        'photo_url', 'status',
        'enrollment_date', 'left_date', 'notes',
    ];

    protected $casts = [
        'birth_date'      => 'date',
        'enrollment_date' => 'date',
        'left_date'       => 'date',
    ];

    // ── Relations ─────────────────────────────────────────────────────────

    public function school(): BelongsTo   { return $this->belongsTo(School::class); }
    public function class(): BelongsTo    { return $this->belongsTo(SchoolClass::class, 'class_id'); }
    public function parents(): HasMany    { return $this->hasMany(ParentContact::class); }
    public function primaryParent(): HasOne { return $this->hasOne(ParentContact::class)->where('is_primary', true); }
    public function payments(): HasMany   { return $this->hasMany(Payment::class); }
    public function reminders(): HasMany  { return $this->hasMany(Reminder::class); }
    public function riskScore(): HasOne   { return $this->hasOne(RiskScore::class)->latest('computed_at'); }

    public function siblings(): \Illuminate\Database\Eloquent\Collection
    {
        if (!$this->sibling_group_id) return collect();
        return static::where('sibling_group_id', $this->sibling_group_id)
                     ->where('id', '!=', $this->id)->get();
    }

    // ── Accessors ─────────────────────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        return "{$this->firstname} {$this->lastname}";
    }

    // ── Méthodes métier ───────────────────────────────────────────────────

    /**
     * Calcule le solde restant dû pour l'année active.
     */
    public function getBalance(?string $academicYearId = null): float
    {
        $yearId = $academicYearId ?? $this->school->activeYear?->id;

        $totalDue = Installment::query()
            ->whereHas('feeConfig', fn ($q) =>
                $q->where('class_id', $this->class_id)
                  ->when($yearId, fn ($q2) => $q2->where('academic_year_id', $yearId))
            )
            ->sum('amount');

        $totalPaid = $this->payments()
            ->where('status', 'confirmed')
            ->sum('amount');

        return max(0, $totalDue - $totalPaid);
    }

    /**
     * Retourne les échéances en retard pour cet élève.
     */
    public function overdueInstallments(?string $academicYearId = null): \Illuminate\Database\Eloquent\Collection
    {
        $yearId = $academicYearId ?? $this->school->activeYear?->id;
        $paidInstallmentIds = $this->payments()->where('status', 'confirmed')
                                               ->pluck('installment_id');

        return Installment::query()
            ->whereHas('feeConfig', fn ($q) =>
                $q->where('class_id', $this->class_id)
                  ->when($yearId, fn ($q2) => $q2->where('academic_year_id', $yearId))
            )
            ->whereNotIn('id', $paidInstallmentIds)
            ->where('due_date', '<', now())
            ->orderBy('due_date')
            ->get();
    }

    public function getRiskScore(): int
    {
        return $this->riskScore?->score ?? 0;
    }

    /**
     * Génère le matricule automatiquement.
     * Format : EPP-{ANNEE}-{CODE_CLASSE}-{SEQUENCE}
     */
    public static function generateMatricule(string $schoolId, string $classId): string
    {
        $year  = now()->year;
        $seq   = static::where('school_id', $schoolId)->count() + 1;
        $class = SchoolClass::find($classId);
        $code  = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $class?->name ?? 'CLS'), 0, 3));

        return sprintf('EPP-%d-%s-%05d', $year, $code, $seq);
    }
}
