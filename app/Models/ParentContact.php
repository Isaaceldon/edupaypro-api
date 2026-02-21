<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ParentContact extends Model
{
    use HasUuids, HasFactory, SoftDeletes;

    protected $table = 'parents';

    protected $fillable = [
        'student_id', 'firstname', 'lastname', 'relationship',
        'phone_whatsapp', 'phone_alt', 'email', 'profession',
        'address', 'is_primary', 'whatsapp_opted_in',
    ];

    protected $casts = [
        'is_primary'        => 'boolean',
        'whatsapp_opted_in' => 'boolean',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->firstname} {$this->lastname}";
    }

    /**
     * Formate le numéro WhatsApp au format international Bénin.
     * ex: 97123456 → +22997123456
     */
    public function getFormattedPhoneAttribute(): string
    {
        $phone = preg_replace('/\D/', '', $this->phone_whatsapp);
        if (strlen($phone) === 8) {
            return '+229' . $phone;
        }
        return '+' . ltrim($phone, '+');
    }
}
