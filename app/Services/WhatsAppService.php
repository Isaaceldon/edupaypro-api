<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private string $apiUrl;
    private string $phoneId;
    private string $token;

    public function __construct()
    {
        $this->apiUrl  = config('whatsapp.api_url');
        $this->phoneId = config('whatsapp.phone_id');
        $this->token   = config('whatsapp.token');
    }

    /**
     * Envoie un message texte simple.
     */
    public function send(string $to, string $message): bool
    {
        try {
            $resp = Http::withToken($this->token)
                ->post("{$this->apiUrl}/{$this->phoneId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to'                => $this->normalizePhone($to),
                    'type'              => 'text',
                    'text'              => ['body' => $message],
                ]);

            if ($resp->successful()) return true;

            Log::warning('WhatsApp send failed', ['to' => $to, 'response' => $resp->json()]);
            return false;

        } catch (\Exception $e) {
            Log::error('WhatsApp exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Envoie un template approuvé Meta.
     * Ex: template "reminder_payment" avec variables.
     */
    public function sendTemplate(string $to, string $templateName, array $components = [], string $language = 'fr'): bool
    {
        try {
            $resp = Http::withToken($this->token)
                ->post("{$this->apiUrl}/{$this->phoneId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to'                => $this->normalizePhone($to),
                    'type'              => 'template',
                    'template'          => [
                        'name'       => $templateName,
                        'language'   => ['code' => $language],
                        'components' => $components,
                    ],
                ]);

            return $resp->successful();

        } catch (\Exception $e) {
            Log::error('WhatsApp template exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Retourne l'ID du dernier message envoyé (pour tracking).
     */
    public function getLastMessageId(string $to, string $message): ?string
    {
        try {
            $resp = Http::withToken($this->token)
                ->post("{$this->apiUrl}/{$this->phoneId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to'                => $this->normalizePhone($to),
                    'type'              => 'text',
                    'text'              => ['body' => $message],
                ]);

            return $resp->json('messages.0.id');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Normalise le numéro béninois : 97123456 → 22997123456
     */
    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) === 8) return '229' . $digits;
        if (str_starts_with($digits, '229')) return $digits;
        return $digits;
    }
}
