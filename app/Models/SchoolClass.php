<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchoolClass extends Model
{
    use HasUuids, HasFactory, SoftDeletes;

    protected $table = 'classes';

    protected $fillable = [
        'school_id', 'teacher_id', 'name', 'level',
        'capacity', 'room', 'sort_order',
    ];

    public function school(): BelongsTo  { return $this->belongsTo(School::class); }
    public function teacher(): BelongsTo { return $this->belongsTo(User::class, 'teacher_id'); }
    public function students(): HasMany  { return $this->hasMany(Student::class, 'class_id'); }

    public function feeConfigs(): HasMany
    {
        return $this->hasMany(FeeConfig::class, 'class_id');
    }

    public function activeFeeConfig(string $academicYearId): ?FeeConfig
    {
        return $this->feeConfigs()->where('academic_year_id', $academicYearId)->first();
    }

    public function getStudentCount(): int
    {
        return $this->students()->where('status', 'active')->count();
    }
}
