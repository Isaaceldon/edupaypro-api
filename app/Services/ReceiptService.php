<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Receipt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ReceiptService
{
    public function __construct(private WhatsAppService $whatsApp) {}

    /**
     * Génère le reçu PDF et l'enregistre.
     * Utilise barryvdh/laravel-dompdf.
     */
    public function generate(Payment $payment): Receipt
    {
        $payment->load(['student.class', 'student.primaryParent', 'installment.feeConfig', 'student.school']);

        $school  = $payment->student->school;
        $number  = $school->nextReceiptNumber();
        $qrToken = Receipt::generateQrToken();

        // Données pour le template PDF
        $data = [
            'receipt_number'  => $number,
            'qr_code'         => $qrToken,
            'school'          => $school,
            'student'         => $payment->student,
            'payment'         => $payment,
            'installment'     => $payment->installment,
            'parent'          => $payment->student->primaryParent,
            'generated_at'    => now(),
        ];

        // Génère le PDF avec DomPDF
        $pdf  = \Barryvdh\DomPDF\Facade\Pdf::loadView('receipts.pdf', $data)
                    ->setPaper('a5', 'portrait');
        $path = "schools/{$school->id}/receipts/{$number}.pdf";
        Storage::disk('public')->put($path, $pdf->output());

        $receipt = Receipt::create([
            'payment_id' => $payment->id,
            'number'     => $number,
            'qr_code'    => $qrToken,
            'pdf_path'   => $path,
            'pdf_url'    => Storage::disk('public')->url($path),
        ]);

        return $receipt;
    }

    /**
     * Envoie le reçu PDF au parent via WhatsApp.
     */
    public function sendViaWhatsApp(Receipt $receipt): bool
    {
        $receipt->load('payment.student.primaryParent');
        $parent = $receipt->payment->student->primaryParent;

        if (!$parent || !$parent->whatsapp_opted_in) {
            return false;
        }

        $phone   = $parent->formatted_phone;
        $student = $receipt->payment->student;
        $message = "🧾 *Reçu de paiement — EDU PAY PRO*\n\n"
            . "Élève : *{$student->full_name}*\n"
            . "Reçu N° : *{$receipt->number}*\n"
            . "Montant : *" . number_format($receipt->payment->amount, 0, ',', ' ') . " FCFA*\n"
            . "Date : *" . $receipt->payment->paid_at->format('d/m/Y à H:i') . "*\n\n"
            . "✅ Paiement enregistré avec succès.\n"
            . "🔍 Vérifiez l'authenticité sur : https://edupaypro.bj/verify/{$receipt->qr_code}";

        $sent = $this->whatsApp->send($phone, $message);

        if ($sent) {
            $receipt->update([
                'sent_whatsapp'  => true,
                'sent_at'        => now(),
                'sent_to_phone'  => $phone,
            ]);
        }

        return $sent;
    }
}
