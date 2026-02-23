<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SchoolController extends Controller
{
    /**
     * GET /api/v1/schools/current
     */
    public function show(Request $request): JsonResponse
    {
        $school = $request->user()->school->load('activeYear');

        return response()->json([
            'data' => array_merge($school->toArray(), [
                'stats' => $school->getStats(),
            ]),
        ]);
    }

    /**
     * PUT /api/v1/schools/current
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'name'          => ['sometimes', 'string', 'max:200'],
            'address'       => ['nullable', 'string'],
            'city'          => ['nullable', 'string', 'max:100'],
            'phone'         => ['nullable', 'string', 'max:20'],
            'email'         => ['nullable', 'email'],
            'website'       => ['nullable', 'url'],
            'rccm'          => ['nullable', 'string', 'max:50'],
            'ifu'           => ['nullable', 'string', 'max:50'],
            'director_name' => ['nullable', 'string', 'max:200'],
            'receipt_prefix'=> ['nullable', 'string', 'max:10', 'alpha'],
        ]);

        $school = $request->user()->school;
        $school->update($request->validated());

        return response()->json([
            'message' => 'École mise à jour.',
            'data'    => $school->fresh(),
        ]);
    }

    /**
     * POST /api/v1/schools/logo
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => ['required', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp'],
        ]);

        $school = $request->user()->school;

        // Supprimer l'ancien logo
        if ($school->logo_url) {
            Storage::disk('public')->delete($school->logo_url);
        }

        $path = $request->file('logo')->store("schools/{$school->id}/logos", 'public');
        $school->update(['logo_url' => $path]);

        return response()->json([
            'message'  => 'Logo mis à jour.',
            'logo_url' => Storage::disk('public')->url($path),
        ]);
    }

    /**
     * POST /api/v1/schools/whatsapp/test
     * Teste la connexion à l'API WhatsApp Meta avec les credentials de l'école.
     */
    public function testWhatsapp(Request $request): JsonResponse
    {
        $school = $request->user()->school;

        if (!$school->whatsapp_phone_id || !$school->whatsapp_token) {
            return response()->json([
                'message' => 'Configuration WhatsApp incomplète. Renseignez le Phone Number ID et le token.',
            ], 422);
        }

        try {
            $response = \Illuminate\Support\Facades\Http::withToken($school->whatsapp_token)
                ->get("https://graph.facebook.com/v18.0/{$school->whatsapp_phone_id}");

            if ($response->successful()) {
                return response()->json([
                    'message' => 'Connexion WhatsApp opérationnelle ✅',
                    'data'    => ['phone_number_id' => $school->whatsapp_phone_id],
                ]);
            }

            return response()->json([
                'message' => 'Token invalide ou accès refusé par Meta.',
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Impossible de joindre l\'API Meta. Vérifiez votre connexion.',
            ], 503);
        }
    }
}
