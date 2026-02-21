<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\Reminder;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * GET /api/v1/dashboard
     * KPIs principaux de l'école
     */
    public function index(Request $request): JsonResponse
    {
        $school = $request->user()->school->load('activeYear');
        $yearId = $request->query('academic_year_id') ?? $school->activeYear?->id;

        if (!$yearId) {
            return response()->json(['message' => 'Aucune année scolaire active.'], 422);
        }

        $schoolId = $school->id;

        // ── Élèves ───────────────────────────────────────────────────────
        $totalStudents  = Student::where('school_id', $schoolId)->where('status', 'active')->count();
        $totalClasses   = $school->classes()->count();

        // ── Financier : montant total dû ─────────────────────────────────
        $totalDue = Installment::whereHas('feeConfig', fn($q) =>
            $q->whereHas('class', fn($q2) => $q2->where('school_id', $schoolId))
              ->where('academic_year_id', $yearId)
        )->sum('amount');

        // ── Paiements confirmés ──────────────────────────────────────────
        $paymentsBase = Payment::whereHas('student', fn($q) => $q->where('school_id', $schoolId))
            ->whereHas('installment.feeConfig', fn($q) => $q->where('academic_year_id', $yearId))
            ->where('status', 'confirmed');

        $totalCollected = (clone $paymentsBase)->sum('amount');
        $paymentCount   = (clone $paymentsBase)->count();

        // ── Ce mois ──────────────────────────────────────────────────────
        $thisMonth = (clone $paymentsBase)
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount');

        // ── Taux de recouvrement ─────────────────────────────────────────
        $recoveryRate = $totalDue > 0 ? round(($totalCollected / $totalDue) * 100, 1) : 0;

        // ── Élèves en retard ─────────────────────────────────────────────
        $paidInstallmentIds = (clone $paymentsBase)->pluck('installment_id');

        $overdueCount = Student::where('school_id', $schoolId)->where('status', 'active')
            ->whereHas('class.feeConfigs', fn($q) =>
                $q->where('academic_year_id', $yearId)
                  ->whereHas('installments', fn($q2) =>
                      $q2->whereNotIn('id', $paidInstallmentIds)->where('due_date', '<', now())
                  )
            )->count();

        // ── Relances ─────────────────────────────────────────────────────
        $remindersBase  = Reminder::whereHas('student', fn($q) => $q->where('school_id', $schoolId));
        $remindersSent  = (clone $remindersBase)->whereIn('status', ['sent', 'delivered', 'read'])->count();
        $remindersRead  = (clone $remindersBase)->where('status', 'read')->count();
        $openRate       = $remindersSent > 0 ? round(($remindersRead / $remindersSent) * 100, 1) : 0;

        // ── Derniers paiements ───────────────────────────────────────────
        $recentPayments = Payment::whereHas('student', fn($q) => $q->where('school_id', $schoolId))
            ->with(['student:id,firstname,lastname', 'installment:id,label'])
            ->where('status', 'confirmed')
            ->latest('paid_at')->limit(5)->get();

        return response()->json([
            'data' => [
                'academic_year'   => $school->activeYear,
                'students'        => ['total' => $totalStudents, 'classes' => $totalClasses, 'overdue' => $overdueCount],
                'financial'       => [
                    'total_due'       => $totalDue,
                    'total_collected' => $totalCollected,
                    'remaining'       => max(0, $totalDue - $totalCollected),
                    'this_month'      => $thisMonth,
                    'recovery_rate'   => $recoveryRate,
                    'payment_count'   => $paymentCount,
                ],
                'reminders'       => ['sent' => $remindersSent, 'read' => $remindersRead, 'open_rate' => $openRate],
                'recent_payments' => $recentPayments,
            ],
        ]);
    }

    /**
     * GET /api/v1/dashboard/overdue
     * Liste des élèves en retard avec détail
     */
    public function overdueStudents(Request $request): JsonResponse
    {
        $school = $request->user()->school;
        $yearId = $request->query('academic_year_id') ?? $school->activeYear?->id;

        $paidIds = Payment::whereHas('student', fn($q) => $q->where('school_id', $school->id))
            ->where('status', 'confirmed')
            ->pluck('installment_id');

        $overdueInstallments = Installment::whereHas('feeConfig', fn($q) =>
            $q->whereHas('class', fn($q2) => $q2->where('school_id', $school->id))
              ->where('academic_year_id', $yearId)
        )->whereNotIn('id', $paidIds)
         ->where('due_date', '<', now())
         ->with(['feeConfig.class:id,name', 'payments' => fn($q) => $q->where('status','confirmed')])
         ->orderBy('due_date')
         ->get();

        // Regrouper par élève
        $byStudent = [];
        foreach ($overdueInstallments as $inst) {
            $partiallyPaid = $inst->payments->sum('amount');
            $remaining     = $inst->amount - $partiallyPaid;
            if ($remaining <= 0) continue;

            // Trouver les élèves concernés (tous ceux de la classe)
            $students = Student::where('class_id', $inst->feeConfig->class_id)
                ->where('school_id', $school->id)
                ->where('status', 'active')
                ->with('primaryParent:id,student_id,firstname,lastname,phone_whatsapp')
                ->get();

            foreach ($students as $student) {
                $paid = $student->payments()->where('installment_id', $inst->id)->where('status','confirmed')->sum('amount');
                $due  = $inst->amount - $paid;
                if ($due <= 0) continue;

                $byStudent[$student->id]['student'] ??= $student;
                $byStudent[$student->id]['overdue'][] = [
                    'installment_id' => $inst->id,
                    'label'          => $inst->label,
                    'amount_due'     => $due,
                    'due_date'       => $inst->due_date,
                    'days_overdue'   => $inst->getDaysOverdue(),
                    'penalty'        => $inst->getPenalty($student),
                ];
            }
        }

        $result = collect($byStudent)->values()->map(fn($row) => [
            'student'       => $row['student'],
            'overdue_items' => $row['overdue'],
            'total_due'     => collect($row['overdue'])->sum('amount_due'),
            'total_penalty' => collect($row['overdue'])->sum('penalty'),
        ])->sortByDesc('total_due')->values();

        return response()->json(['data' => $result, 'total' => $result->count()]);
    }

    /**
     * GET /api/v1/dashboard/monthly
     * Évolution mensuelle des encaissements sur l'année
     */
    public function monthlyEvolution(Request $request): JsonResponse
    {
        $school = $request->user()->school;
        $yearId = $request->query('academic_year_id') ?? $school->activeYear?->id;
        $year   = $request->query('year', now()->year);

        $monthly = Payment::whereHas('student', fn($q) => $q->where('school_id', $school->id))
            ->where('status', 'confirmed')
            ->whereYear('paid_at', $year)
            ->selectRaw('MONTH(paid_at) as month, YEAR(paid_at) as year, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('year', 'month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $months = collect(range(1, 12))->map(fn($m) => [
            'month'  => $m,
            'label'  => Carbon::create($year, $m)->translatedFormat('M'),
            'total'  => $monthly->get($m)?->total ?? 0,
            'count'  => $monthly->get($m)?->count ?? 0,
        ]);

        return response()->json(['data' => $months, 'year' => $year]);
    }

    /**
     * GET /api/v1/dashboard/forecast
     * Prévision de trésorerie sur les 3 prochains mois
     */
    public function forecast(Request $request): JsonResponse
    {
        $school = $request->user()->school;
        $yearId = $school->activeYear?->id;

        if (!$yearId) return response()->json(['data' => []]);

        // Tranches à venir non payées
        $paidIds = Payment::whereHas('student', fn($q) => $q->where('school_id', $school->id))
            ->where('status', 'confirmed')
            ->pluck('installment_id');

        $upcoming = Installment::whereHas('feeConfig', fn($q) =>
            $q->whereHas('class', fn($q2) => $q2->where('school_id', $school->id))
              ->where('academic_year_id', $yearId)
        )->whereNotIn('id', $paidIds)
         ->where('due_date', '>=', now())
         ->where('due_date', '<=', now()->addMonths(3))
         ->selectRaw('YEAR(due_date) as year, MONTH(due_date) as month, SUM(amount) as expected')
         ->groupBy('year', 'month')
         ->orderBy('year')->orderBy('month')
         ->get();

        $forecast = $upcoming->map(fn($row) => [
            'month'    => Carbon::create($row->year, $row->month)->translatedFormat('F Y'),
            'expected' => $row->expected,
        ]);

        return response()->json(['data' => $forecast]);
    }
}
