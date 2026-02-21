<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MobileMoneyService
{
    /**
     * Initie un paiement MTN MoMo.
     * Retourne ['success' => bool, 'reference' => string, 'message' => string]
     */
    public function initiateMTN(float $amount, string $phone, string $externalId): array
    {
        try {
            $baseUrl = config('mobilemoney.mtn.base_url');
            $subKey  = config('mobilemoney.mtn.subscription_key');
            $apiUser = config('mobilemoney.mtn.api_user');
            $apiKey  = config('mobilemoney.mtn.api_key');
            $env     = config('mobilemoney.mtn.target_env');

            // Token OAuth2
            $tokenResp = Http::withBasicAuth($apiUser, $apiKey)
                ->withHeaders(['Ocp-Apim-Subscription-Key' => $subKey])
                ->post("{$baseUrl}/collection/token/");

            if (!$tokenResp->successful()) {
                throw new \Exception('MTN token failed: ' . $tokenResp->body());
            }
            $token = $tokenResp->json('access_token');

            // Requête de paiement
            $ref = strtoupper(\Illuminate\Support\Str::uuid());
            $resp = Http::withToken($token)
                ->withHeaders([
                    'X-Reference-Id'            => $ref,
                    'X-Target-Environment'      => $env,
                    'Ocp-Apim-Subscription-Key' => $subKey,
                    'Content-Type'              => 'application/json',
                ])
                ->post("{$baseUrl}/collection/v1_0/requesttopay", [
                    'amount'       => (string) $amount,
                    'currency'     => 'XOF',
                    'externalId'   => $externalId,
                    'payer'        => ['partyIdType' => 'MSISDN', 'partyId' => $phone],
                    'payerMessage' => 'Paiement frais scolaires EDU PAY PRO',
                    'payeeNote'    => 'Frais scolaires',
                ]);

            if ($resp->status() === 202) {
                return ['success' => true, 'reference' => $ref, 'message' => 'Paiement initié. En attente de confirmation.'];
            }

            return ['success' => false, 'reference' => null, 'message' => 'Échec initiation MTN: ' . $resp->body()];

        } catch (\Exception $e) {
            Log::error('MTN MoMo error: ' . $e->getMessage());
            return ['success' => false, 'reference' => null, 'message' => $e->getMessage()];
        }
    }

    /**
     * Vérifie le statut d'une transaction MTN.
     */
    public function checkStatusMTN(string $reference): array
    {
        try {
            $baseUrl = config('mobilemoney.mtn.base_url');
            $subKey  = config('mobilemoney.mtn.subscription_key');
            $apiUser = config('mobilemoney.mtn.api_user');
            $apiKey  = config('mobilemoney.mtn.api_key');
            $env     = config('mobilemoney.mtn.target_env');

            $tokenResp = Http::withBasicAuth($apiUser, $apiKey)
                ->withHeaders(['Ocp-Apim-Subscription-Key' => $subKey])
                ->post("{$baseUrl}/collection/token/");

            $token = $tokenResp->json('access_token');

            $resp = Http::withToken($token)
                ->withHeaders(['X-Target-Environment' => $env, 'Ocp-Apim-Subscription-Key' => $subKey])
                ->get("{$baseUrl}/collection/v1_0/requesttopay/{$reference}");

            $status = $resp->json('status'); // SUCCESSFUL | FAILED | PENDING

            return [
                'status'  => strtolower($status),
                'success' => $status === 'SUCCESSFUL',
                'data'    => $resp->json(),
            ];
        } catch (\Exception $e) {
            Log::error('MTN status check error: ' . $e->getMessage());
            return ['status' => 'error', 'success' => false, 'data' => []];
        }
    }

    /**
     * Initie un paiement Moov Money.
     */
    public function initiateMoov(float $amount, string $phone, string $externalId): array
    {
        try {
            $baseUrl    = config('mobilemoney.moov.base_url');
            $merchantId = config('mobilemoney.moov.merchant_id');
            $secret     = config('mobilemoney.moov.secret');

            $resp = Http::withHeaders([
                'Authorization' => 'Bearer ' . base64_encode("{$merchantId}:{$secret}"),
                'Content-Type'  => 'application/json',
            ])->post("{$baseUrl}/flooz/pay", [
                'merchant'   => $merchantId,
                'amount'     => $amount,
                'phone'      => $phone,
                'externalId' => $externalId,
                'currency'   => 'XOF',
                'description'=> 'Frais scolaires EDU PAY PRO',
            ]);

            if ($resp->successful()) {
                $ref = $resp->json('transactionId') ?? $externalId;
                return ['success' => true, 'reference' => $ref, 'message' => 'Paiement Moov initié.'];
            }

            return ['success' => false, 'reference' => null, 'message' => 'Échec Moov: ' . $resp->body()];

        } catch (\Exception $e) {
            Log::error('Moov Money error: ' . $e->getMessage());
            return ['success' => false, 'reference' => null, 'message' => $e->getMessage()];
        }
    }
}
