<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1e293b; background: #fff; }
        .page { padding: 20px 24px; max-width: 560px; margin: 0 auto; }

        /* En-tête */
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #0891B2; padding-bottom: 14px; margin-bottom: 14px; }
        .school-name { font-size: 15px; font-weight: bold; color: #0D2137; }
        .school-info { font-size: 9px; color: #64748b; margin-top: 3px; }
        .badge { background: #0D2137; color: #fff; padding: 4px 10px; border-radius: 4px; font-size: 10px; font-weight: bold; }

        /* Titre reçu */
        .receipt-title { background: #0891B2; color: #fff; text-align: center; padding: 8px; font-size: 13px; font-weight: bold; border-radius: 4px; margin-bottom: 14px; letter-spacing: 1px; }

        /* Infos */
        .info-grid { display: flex; gap: 10px; margin-bottom: 14px; }
        .info-box { flex: 1; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 8px 10px; }
        .info-label { font-size: 8px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px; }
        .info-value { font-size: 11px; font-weight: bold; color: #0D2137; }

        /* Ligne de paiement */
        .amount-box { background: #0D2137; color: #fff; text-align: center; padding: 12px; border-radius: 6px; margin: 14px 0; }
        .amount-label { font-size: 9px; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; }
        .amount-value { font-size: 22px; font-weight: bold; color: #10B981; margin-top: 4px; }

        /* Table détails */
        .detail-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .detail-table th { background: #f1f5f9; font-size: 9px; text-transform: uppercase; color: #64748b; padding: 5px 8px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        .detail-table td { padding: 6px 8px; border-bottom: 1px solid #f1f5f9; font-size: 10px; }
        .detail-table tr:last-child td { border-bottom: none; }

        /* Footer */
        .footer { display: flex; justify-content: space-between; align-items: flex-end; border-top: 1px solid #e2e8f0; padding-top: 10px; margin-top: 10px; }
        .qr-section { text-align: center; }
        .qr-code { font-size: 16px; font-weight: bold; font-family: monospace; background: #f8fafc; border: 1px solid #e2e8f0; padding: 6px 12px; border-radius: 4px; letter-spacing: 3px; }
        .qr-label { font-size: 8px; color: #94a3b8; margin-top: 3px; }
        .signature-section { text-align: right; }
        .signature-line { border-top: 1px solid #1e293b; width: 120px; margin-bottom: 3px; }
        .verified { background: #10B981; color: #fff; font-size: 8px; padding: 2px 8px; border-radius: 10px; display: inline-block; margin-top: 8px; }
    </style>
</head>
<body>
<div class="page">
    <!-- EN-TÊTE -->
    <div class="header">
        <div>
            <div class="school-name">{{ $school->name }}</div>
            <div class="school-info">
                @if($school->address){{ $school->address }}@endif
                @if($school->phone) · {{ $school->phone }}@endif
            </div>
            @if($school->ifu)<div class="school-info">IFU : {{ $school->ifu }}</div>@endif
        </div>
        <div>
            <div class="badge">EDU PAY PRO</div>
        </div>
    </div>

    <!-- TITRE -->
    <div class="receipt-title">✓ REÇU DE PAIEMENT OFFICIEL</div>

    <!-- INFOS REÇU + ÉLÈVE -->
    <div class="info-grid">
        <div class="info-box">
            <div class="info-label">N° Reçu</div>
            <div class="info-value">{{ $receipt_number }}</div>
        </div>
        <div class="info-box">
            <div class="info-label">Date</div>
            <div class="info-value">{{ $payment->paid_at->format('d/m/Y') }}</div>
        </div>
        <div class="info-box">
            <div class="info-label">Heure</div>
            <div class="info-value">{{ $payment->paid_at->format('H:i') }}</div>
        </div>
    </div>

    <!-- MONTANT -->
    <div class="amount-box">
        <div class="amount-label">Montant reçu</div>
        <div class="amount-value">{{ number_format($payment->amount, 0, ',', ' ') }} FCFA</div>
        @if($payment->penalty_amount > 0)
        <div style="font-size:9px;color:#fbbf24;margin-top:2px;">dont {{ number_format($payment->penalty_amount, 0, ',', ' ') }} FCFA de pénalités</div>
        @endif
    </div>

    <!-- DÉTAILS -->
    <table class="detail-table">
        <thead><tr><th>Information</th><th>Détail</th></tr></thead>
        <tbody>
            <tr><td>Élève</td><td><strong>{{ $student->full_name }}</strong></td></tr>
            <tr><td>Matricule</td><td>{{ $student->matricule }}</td></tr>
            <tr><td>Classe</td><td>{{ $student->class?->name ?? '—' }}</td></tr>
            <tr><td>Parent / Tuteur</td><td>{{ $parent?->full_name ?? '—' }}</td></tr>
            <tr><td>Tranche réglée</td><td>{{ $installment->label }}</td></tr>
            <tr><td>Mode de paiement</td><td>{{ strtoupper(str_replace('_', ' ', $payment->mode)) }}</td></tr>
            @if($payment->reference)
            <tr><td>Référence</td><td>{{ $payment->reference }}</td></tr>
            @endif
            <tr><td>Année scolaire</td><td>{{ $installment->feeConfig->academicYear->label ?? '—' }}</td></tr>
        </tbody>
    </table>

    <!-- FOOTER -->
    <div class="footer">
        <div class="qr-section">
            <div class="qr-code">{{ $qr_code }}</div>
            <div class="qr-label">Code de vérification</div>
            <div class="qr-label">edupaypro.bj/verify/{{ $qr_code }}</div>
        </div>
        <div class="signature-section">
            <div style="font-size:9px;color:#64748b;margin-bottom:20px;">Signature & Cachet</div>
            <div class="signature-line"></div>
            <div style="font-size:9px;color:#64748b;">{{ $school->director_name ?? 'Le Directeur' }}</div>
            <div class="verified">✓ DOCUMENT OFFICIEL</div>
        </div>
    </div>

    <div style="text-align:center;font-size:8px;color:#94a3b8;margin-top:10px;">
        Généré le {{ $generated_at->format('d/m/Y à H:i') }} par EDU PAY PRO · Ce document fait foi.
    </div>
</div>
</body>
</html>
