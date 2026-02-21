<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Receipt extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'payment_id', 'number', 'qr_code',
        'pdf_path', 'pdf_url',
        'sent_whatsapp', 'sent_at', 'sent_to_phone',
    ];

    protected $casts = [
        'sent_whatsapp' => 'boolean',
        'sent_at'       => 'datetime',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Génère un token QR code unique et non-séquentiel.
     */
    public static function generateQrToken(): string
    {
        return strtoupper(Str::random(6)) . '-' . now()->format('ymd');
    }

    /**
     * Vérifie l'authenticité d'un reçu par son QR code.
     */
    public static function verify(string $qrCode): ?self
    {
        return static::where('qr_code', $qrCode)->with('payment.student')->first();
    }
}
