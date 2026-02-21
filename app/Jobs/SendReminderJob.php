<?php

namespace App\Jobs;

use App\Models\Reminder;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 60; // secondes entre tentatives

    public function __construct(public Reminder $reminder) {}

    public function handle(WhatsAppService $whatsApp): void
    {
        $reminder = $this->reminder->fresh();

        if (!$reminder || $reminder->status !== 'pending') return;

        $parent = $reminder->student->primaryParent;

        if (!$parent || !$parent->whatsapp_opted_in) {
            $reminder->markAsFailed('Pas de contact WhatsApp ou opt-out.');
            return;
        }

        $phone = $parent->formatted_phone;

        if ($reminder->channel === 'whatsapp') {
            $msgId = $whatsApp->getLastMessageId($phone, $reminder->message_sent);
            if ($msgId !== null) {
                $reminder->markAsSent($msgId);
            } else {
                $reminder->markAsFailed('Échec envoi WhatsApp.');
                $this->fail(new \Exception('WhatsApp send failed for reminder ' . $reminder->id));
            }
        } elseif ($reminder->channel === 'sms') {
            // SMS via opérateur local — à brancher selon fournisseur
            Log::info("SMS fallback pour reminder {$reminder->id} → {$phone}");
            $reminder->markAsSent();
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->reminder->markAsFailed($exception->getMessage());
    }
}
