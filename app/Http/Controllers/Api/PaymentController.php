<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\Student;
use App\Services\MobileMoneyService;
use App\Services\ReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function __construct(
        private MobileMoneyService $mobileMoney,
        private ReceiptService $receipts
    ) {}

    /**
     * GET /api/v1/payments
     * Filtres : student_id, installment_id, mode, status, date_from, date_to
     */
    public function index(Request $request): JsonResponse
    {
        $schoolId = $request->user()->school_id;

        $query = Payment::whereHas('student', fn($q) => $q->where('school_id', $schoolId))
            ->with([
                'student:id,firstname,lastname,matricule,class_id',
                'installment:id,label,amount,due_date',
                'recordedBy:id,firstname,lastname',
                'receipt:id,payment_id,number,sent_whatsapp',
            ]);

        if ($request->filled('student_id'))    $query->where('student_id', $request->student_id);
        if ($request->filled('installment_id'))$query->where('installment_id', $request->installment_id);
        if ($request->filled('mode'))          $query->where('mode', $request->mode);
        if ($request->filled('status'))        $query->where('status', $request->status);
        if ($request->filled('date_from'))     $query->whereDate('paid_at', '>=', $request->date_from);
        if ($request->filled('date_to'))       $query->whereDate('paid_at', '<=', $request->date_to);

        $payments = $query->latest('paid_at')
            ->paginate($request->integer('per_page', 25));

        return response()->json($payments);
    }

    /**
     * POST /api/v1/payments
     * Crée un paiement + génère le reçu + envoie WhatsApp
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id'     => ['required', 'uuid', 'exists:students,id'],
            'installment_id' => ['required', 'uuid', 'exists:installments,id'],
            'amount'         => ['required', 'numeric', 'min:1'],
            'mode'           => ['required', 'in:cash,mtn_momo,moov_money,wire,cheque'],
            'reference'      => ['nullable', 'string', 'max:100'],
            'phone'          => ['required_if:mode,mtn_momo', 'required_if:mode,moov_money', 'nullable', 'string', 'max:20'],
            'paid_at'        => ['nullable', 'date'],
            'notes'          => ['nullable', 'string'],
            'send_receipt_whatsapp' => ['boolean'],
        ]);

        // Vérifier que l'élève appartient à l'école
        $student = Student::where('id', $data['student_id'])
            ->where('school_id', $request->user()->school_id)
            ->firstOrFail();

        $installment = Installment::findOrFail($data['installment_id']);

        // Calculer la pénalité éventuelle
        $penalty = $installment->getPenalty($student);

        DB::beginTransaction();
        try {
            // --- Paiement Mobile Money : d'abord initier la transaction ---
            $mobileRef = null;
            $status    = 'confirmed';

            if (in_array($data['mode'], ['mtn_momo', 'moov_money']) && !empty($data['phone'])) {
                $result = $data['mode'] === 'mtn_momo'
                    ? $this->mobileMoney->initiateMTN($data['amount'], $data['phone'], (string) \Illuminate\Support\Str::uuid())
                    : $this->mobileMoney->initiateMoov($data['amount'], $data['phone'], (string) \Illuminate\Support\Str::uuid());

                if (!$result['success']) {
                    DB::rollBack();
                    return response()->json(['message' => 'Échec initiation Mobile Money : ' . $result['message']], 422);
                }

                $mobileRef = $result['reference'];
                $status    = 'pending'; // confirmé via webhook
            }

            $payment = Payment::create([
                'student_id'       => $student->id,
                'installment_id'   => $installment->id,
                'recorded_by'      => $request->user()->id,
                'amount'           => $data['amount'],
                'penalty_amount'   => $penalty,
                'mode'             => $data['mode'],
                'reference'        => $data['reference'] ?? null,
                'mobile_money_ref' => $mobileRef,
                'status'           => $status,
                'paid_at'          => $data['paid_at'] ?? now(),
                'notes'            => $data['notes'] ?? null,
            ]);

            $receipt = null;

            // Générer le reçu immédiatement si paiement confirmé
            if ($status === 'confirmed') {
                $receipt = $this->receipts->generate($payment);

                if ($request->boolean('send_receipt_whatsapp', true)) {
                    $this->receipts->sendViaWhatsApp($receipt);
                }
            }

            DB::commit();

            return response()->json([
                'message' => $status === 'confirmed'
                    ? 'Paiement enregistré et reçu envoyé.'
                    : 'Paiement initié. En attente de confirmation Mobile Money.',
                'data' => [
                    'payment' => $payment->load(['student:id,firstname,lastname', 'installment:id,label']),
                    'receipt' => $receipt,
                    'mobile_money_pending' => $status === 'pending',
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur lors du paiement.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/v1/payments/{id}
     */
    public function show(Request $request, Payment $payment): JsonResponse
    {
        $this->auth($request, $payment);

        $payment->load([
            'student:id,firstname,lastname,matricule',
            'installment.feeConfig:id,label,type',
            'recordedBy:id,firstname,lastname',
            'cancelledBy:id,firstname,lastname',
            'receipt',
        ]);

        return response()->json(['data' => $payment]);
    }

    /**
     * PUT /api/v1/payments/{id}
     * Seule la note et la référence sont modifiables après création
     */
    public function update(Request $request, Payment $payment): JsonResponse
    {
        $this->auth($request, $payment);

        if ($payment->status === 'cancelled') {
            return response()->json(['message' => 'Impossible de modifier un paiement annulé.'], 422);
        }

        $data = $request->validate([
            'notes'     => ['nullable', 'string'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        $payment->update($data);
        return response()->json(['message' => 'Paiement mis à jour.', 'data' => $payment->fresh()]);
    }

    /**
     * DELETE (soft) — non utilisé directement, utiliser cancel
     */
    public function destroy(Request $request, Payment $payment): JsonResponse
    {
        return response()->json(['message' => 'Utilisez POST /payments/{id}/cancel pour annuler.'], 405);
    }

    /**
     * POST /api/v1/payments/{id}/cancel
     */
    public function cancel(Request $request, Payment $payment): JsonResponse
    {
        $this->auth($request, $payment);

        if ($payment->status === 'cancelled') {
            return response()->json(['message' => 'Ce paiement est déjà annulé.'], 422);
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $payment->cancel($data['reason'], $request->user()->id);

        return response()->json([
            'message' => 'Paiement annulé.',
            'data'    => $payment->fresh(),
        ]);
    }

    /**
     * POST /api/v1/payments/{id}/confirm-mobile-money
     * Appelé manuellement si le webhook n'arrive pas
     */
    public function confirmMobileMoney(Request $request, Payment $payment): JsonResponse
    {
        $this->auth($request, $payment);

        if ($payment->status !== 'pending') {
            return response()->json(['message' => 'Ce paiement n\'est pas en attente de confirmation.'], 422);
        }

        // Vérifier le statut auprès de l'opérateur
        $result = $this->mobileMoney->checkStatusMTN($payment->mobile_money_ref);

        if ($result['success']) {
            DB::beginTransaction();
            try {
                $payment->update(['status' => 'confirmed']);
                $receipt = $this->receipts->generate($payment);
                $this->receipts->sendViaWhatsApp($receipt);
                DB::commit();

                return response()->json([
                    'message' => 'Paiement confirmé. Reçu généré et envoyé.',
                    'data'    => ['payment' => $payment->fresh(), 'receipt' => $receipt],
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json(['message' => 'Erreur confirmation.', 'error' => $e->getMessage()], 500);
            }
        }

        return response()->json([
            'message' => 'Paiement non encore confirmé par l\'opérateur.',
            'status'  => $result['status'],
        ], 202);
    }

    private function auth(Request $r, Payment $p): void
    {
        if ($p->student->school_id !== $r->user()->school_id) abort(403);
    }
}
