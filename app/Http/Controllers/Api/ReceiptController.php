<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Receipt;
use App\Services\ReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReceiptController extends Controller
{
    public function __construct(private ReceiptService $service) {}

    /**
     * GET /api/v1/receipts/{id}
     */
    public function show(Request $request, Receipt $receipt): JsonResponse
    {
        $this->auth($request, $receipt);
        $receipt->load(['payment.student:id,firstname,lastname,matricule', 'payment.installment:id,label']);
        return response()->json(['data' => $receipt]);
    }

    /**
     * GET /api/v1/receipts/{id}/pdf  — télécharger le PDF
     */
    public function downloadPdf(Request $request, Receipt $receipt)
    {
        $this->auth($request, $receipt);

        if (!$receipt->pdf_path || !Storage::disk('public')->exists($receipt->pdf_path)) {
            // Régénérer si manquant
            $receipt = $this->service->generate($receipt->payment);
        }

        return Storage::disk('public')->download(
            $receipt->pdf_path,
            "Recu-{$receipt->number}.pdf"
        );
    }

    /**
     * POST /api/v1/receipts/{id}/send  — renvoyer via WhatsApp
     */
    public function sendWhatsApp(Request $request, Receipt $receipt): JsonResponse
    {
        $this->auth($request, $receipt);

        $sent = $this->service->sendViaWhatsApp($receipt);

        return response()->json([
            'message' => $sent ? 'Reçu envoyé via WhatsApp.' : 'Échec envoi WhatsApp. Vérifiez le numéro du parent.',
            'sent'    => $sent,
        ]);
    }

    /**
     * GET /api/v1/receipts/verify/{qrCode}  — vérification publique
     */
    public function verify(string $qrCode): JsonResponse
    {
        $receipt = Receipt::verify($qrCode);

        if (!$receipt) {
            return response()->json(['valid' => false, 'message' => 'Reçu introuvable ou invalide.'], 404);
        }

        return response()->json([
            'valid'   => true,
            'receipt' => [
                'number'     => $receipt->number,
                'school'     => $receipt->payment->student->school->name,
                'student'    => $receipt->payment->student->full_name,
                'amount'     => $receipt->payment->amount,
                'paid_at'    => $receipt->payment->paid_at,
                'mode'       => $receipt->payment->mode,
                'verified_at'=> now(),
            ],
        ]);
    }

    // Index/store/update/destroy non utilisés pour les reçus
    public function index(Request $r): JsonResponse   { return response()->json(['message'=>'Utilisez /payments/{id}'], 405); }
    public function store(Request $r): JsonResponse   { return response()->json(['message'=>'Les reçus sont auto-générés.'], 405); }
    public function update(Request $r, $id): JsonResponse { return response()->json(['message'=>'Les reçus sont immuables.'], 405); }
    public function destroy(Request $r, $id): JsonResponse { return response()->json(['message'=>'Les reçus ne peuvent pas être supprimés.'], 405); }

    private function auth(Request $r, Receipt $receipt): void
    {
        if ($receipt->payment->student->school_id !== $r->user()->school_id) abort(403);
    }
}
