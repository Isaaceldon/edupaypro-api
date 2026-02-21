<?php
// ─────────────────────────────────────────────────────────────────────────────
// AcademicYear.php
// ─────────────────────────────────────────────────────────────────────────────
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AcademicYear extends Model
{
    use HasUuids, HasFactory, SoftDeletes;

    protected $fillable = ['school_id', 'label', 'start_date', 'end_date', 'is_active', 'notes'];

    protected $casts = [
        'is_active'  => 'boolean',
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function school(): BelongsTo { return $this->belongsTo(School::class); }
    public function feeConfigs(): HasMany { return $this->hasMany(FeeConfig::class); }

    /**
     * Active cette année et désactive toutes les autres de l'école.
     */
    public function activate(): void
    {
        $this->school->academicYears()->update(['is_active' => false]);
        $this->update(['is_active' => true]);
    }
}
