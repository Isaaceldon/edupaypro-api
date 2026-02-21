<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskScore extends Model
{
    use HasUuids;

    protected $fillable = ['student_id', 'score', 'segment', 'factors', 'computed_at'];

    protected $casts = [
        'factors'     => 'array',
        'computed_at' => 'datetime',
    ];

    public function student(): BelongsTo { return $this->belongsTo(Student::class); }

    public function getSegment(): string  { return $this->segment; }

    public function getSegmentColor(): string
    {
        return match($this->segment) {
            'safe' => 'green',
            'risk' => 'orange',
            'bad'  => 'red',
            default => 'gray',
        };
    }

    public static function computeSegment(int $score): string
    {
        return match(true) {
            $score <= 30  => 'safe',
            $score <= 65  => 'risk',
            default       => 'bad',
        };
    }
}
