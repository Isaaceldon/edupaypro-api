<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * GET /api/v1/reports/financial
     * Rapport financier complet par classe + type de frais
     */
    public function financial(Request $request): JsonResponse
    {
        $school = $request->user()->school;
        $yearId = $request->query('academic_year_id') ?? $school->activeYear?->id;

        if (!$yearId) return response()->json(['message' => 'Année scolaire non trouvée.'], 422);

        // Synthèse par classe
        $byClass = DB::table('classes as c')
            ->where('c.school_id', $school->id)
            ->leftJoin('fee_configs as fc', fn($j) =>
                $j->on('fc.class_id', 'c.id')->where('fc.academic_year_id', $yearId)
            )
            ->leftJoin('installments as i', 'i.fee_config_id', 'fc.id')
            ->leftJoin('payments as p', fn($j) =>
                $j->on('p.installment_id', 'i.id')->where('p.status', 'confirmed')
            )
            ->selectRaw('
                c.id as class_id, c.name as class_name,
                COUNT(DISTINCT p.student_id) as paying_students,
                COALESCE(SUM(DISTINCT i.amount * (SELECT COUNT(*) FROM students WHERE class_id = c.id AND status = "active")), 0) as total_due,
                COALESCE(SUM(p.amount), 0) as total_collected
            ')
            ->groupBy('c.id', 'c.name')
            ->orderBy('c.sort_order')
            ->get()
            ->map(fn($row) => array_merge((array)$row, [
                'recovery_rate' => $row->total_due > 0
                    ? round(($row->total_collected / $row->total_due) * 100, 1)
                    : 0,
            ]));

        // Synthèse par mode de paiement
        $byMode = Payment::whereHas('student', fn($q) => $q->where('school_id', $school->id))
            ->whereHas('installment.feeConfig', fn($q) => $q->where('academic_year_id', $yearId))
            ->where('status', 'confirmed')
            ->selectRaw('mode, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('mode')
            ->get();

        // Totaux globaux
        $totalDue = $byClass->sum('total_due');
        $totalCollected = $byClass->sum('total_collected');

        return response()->json([
            'data' => [
                'academic_year_id' => $yearId,
                'generated_at'     => now(),
                'totals' => [
                    'total_due'       => $totalDue,
                    'total_collected' => $totalCollected,
                    'remaining'       => max(0, $totalDue - $totalCollected),
                    'recovery_rate'   => $totalDue > 0 ? round(($totalCollected / $totalDue) * 100, 1) : 0,
                ],
                'by_class' => $byClass,
                'by_mode'  => $byMode,
            ],
        ]);
    }

    /**
     * GET /api/v1/reports/financial/export
     * Export CSV du rapport financier
     */
    public function exportFinancial(Request $request)
    {
        $school = $request->user()->school;
        $yearId = $request->query('academic_year_id') ?? $school->activeYear?->id;

        $payments = Payment::whereHas('student', fn($q) => $q->where('school_id', $school->id))
            ->whereHas('installment.feeConfig', fn($q) => $q->where('academic_year_id', $yearId))
            ->where('status', 'confirmed')
            ->with([
                'student:id,firstname,lastname,matricule,class_id',
                'student.class:id,name',
                'installment:id,label,due_date',
                'recordedBy:id,firstname,lastname',
                'receipt:id,payment_id,number',
            ])
            ->latest('paid_at')
            ->get();

        $csv  = "\xEF\xBB\xBF"; // BOM UTF-8 pour Excel
        $csv .= "N° Reçu;Matricule;Nom Complet;Classe;Tranche;Mode;Montant (FCFA);Date Paiement;Enregistré par\n";

        foreach ($payments as $p) {
            $csv .= implode(';', [
                $p->receipt?->number  ?? '',
                $p->student->matricule,
                $p->student->full_name,
                $p->student->class?->name ?? '',
                $p->installment->label,
                strtoupper($p->mode),
                number_format($p->amount, 0, ',', ' '),
                $p->paid_at->format('d/m/Y H:i'),
                $p->recordedBy?->full_name ?? '',
            ]) . "\n";
        }

        $filename = "rapport-financier-{$school->name}-" . now()->format('Ymd') . ".csv";

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * GET /api/v1/reports/students
     * Rapport des élèves avec leur statut de paiement
     */
    public function students(Request $request): JsonResponse
    {
        $school = $request->user()->school;
        $yearId = $request->query('academic_year_id') ?? $school->activeYear?->id;

        $students = Student::where('school_id', $school->id)
            ->where('status', 'active')
            ->with([
                'class:id,name',
                'primaryParent:id,student_id,firstname,lastname,phone_whatsapp',
                'riskScore:id,student_id,score,segment',
            ])
            ->withSum(['payments' => fn($q) => $q->where('status', 'confirmed')], 'amount')
            ->orderBy('lastname')
            ->get()
            ->map(fn($s) => [
                'id'          => $s->id,
                'matricule'   => $s->matricule,
                'full_name'   => $s->full_name,
                'class'       => $s->class?->name,
                'parent'      => $s->primaryParent?->full_name,
                'parent_phone'=> $s->primaryParent?->phone_whatsapp,
                'paid'        => $s->payments_sum_amount ?? 0,
                'balance'     => $s->getBalance($yearId),
                'risk_score'  => $s->riskScore?->score ?? 0,
                'risk_segment'=> $s->riskScore?->segment ?? 'unknown',
            ]);

        return response()->json([
            'data'  => $students,
            'total' => $students->count(),
        ]);
    }

    // Stubs pour resourceful routing
    public function index(Request $r): JsonResponse   { return response()->json(['message' => 'Utilisez /reports/financial ou /reports/students'], 200); }
    public function store(Request $r): JsonResponse   { return response()->json(['message' => 'N/A'], 405); }
    public function show(Request $r, $id): JsonResponse { return response()->json(['message' => 'N/A'], 405); }
    public function update(Request $r, $id): JsonResponse { return response()->json(['message' => 'N/A'], 405); }
    public function destroy(Request $r, $id): JsonResponse { return response()->json(['message' => 'N/A'], 405); }
}
