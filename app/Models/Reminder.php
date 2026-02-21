<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reminder extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'student_id', 'installment_id',
        'channel', 'scenario', 'message_sent', 'sent_to',
        'status', 'provider_message_id', 'failure_reason',
        'scheduled_at', 'sent_at', 'delivered_at', 'read_at',
    ];

    protected $casts = [
        'scheduled_at'  => 'datetime',
        'sent_at'       => 'datetime',
        'delivered_at'  => 'datetime',
        'read_at'       => 'datetime',
    ];

    public function student(): BelongsTo     { return $this->belongsTo(Student::class); }
    public function installment(): BelongsTo { return $this->belongsTo(Installment::class); }

    public function markAsSent(string $providerId = null): void
    {
        $this->update(['status' => 'sent', 'sent_at' => now(), 'provider_message_id' => $providerId]);
    }

    public function markAsRead(): void
    {
        $this->update(['status' => 'read', 'read_at' => now()]);
    }

    public function markAsFailed(string $reason): void
    {
        $this->update(['status' => 'failed', 'failure_reason' => $reason]);
    }

    public function scopePending($query)   { return $query->where('status', 'pending'); }
    public function scopeDue($query)       { return $query->where('scheduled_at', '<=', now()); }
}
