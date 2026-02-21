<?php

namespace App\Services;

use App\Models\Installment;
use App\Models\RiskScore;
use App\Models\School;
use App\Models\Student;
use Carbon\Carbon;

class RiskScoreService
{
    /**
     * Calcule et persiste le score de risque d'un élève.
     *
     * Facteurs (total = 100 points = risque maximal) :
     *   - Taux de retard historique        : jusqu'à 40 pts
     *   - Délai moyen de paiement          : jusqu'à 25 pts
     *   - Nombre de relances reçues        : jusqu'à 20 pts
     *   - Paiements partiels               : jusqu'à 15 pts
     */
    public function compute(Student $student, ?string $academicYearId = null): RiskScore
    {
        $yearId = $academicYearId ?? $student->school->activeYear?->id;

        $installments = Installment::whereHas('feeConfig', fn($q) =>
            $q->where('class_id', $student->class_id)
              ->when($yearId, fn($q2) => $q2->where('academic_year_id', $yearId))
        )->where('due_date', '<', now())->get();

        if ($installments->isEmpty()) {
            return $this->saveScore($student, 0, ['reason' => 'Aucune échéance passée.']);
        }

        $factors    = [];
        $totalScore = 0;

        // ── Facteur 1 : Taux de retard (40 pts) ─────────────────────────
        $overdueCount = 0;
        foreach ($installments as $inst) {
            $paid = $student->payments()
                ->where('installment_id', $inst->id)
                ->where('status', 'confirmed')
                ->sum('amount');
            if ($paid < $inst->amount) $overdueCount++;
        }
        $overdueRate = $overdueCount / max(1, $installments->count());
        $score1      = (int) round($overdueRate * 40);
        $totalScore += $score1;
        $factors['overdue_rate'] = [
            'score'       => $score1,
            'overdue'     => $overdueCount,
            'total'       => $installments->count(),
            'rate_percent'=> round($overdueRate * 100, 1),
        ];

        // ── Facteur 2 : Délai moyen de paiement (25 pts) ────────────────
        $delays = [];
        foreach ($installments as $inst) {
            $payment = $student->payments()
                ->where('installment_id', $inst->id)
                ->where('status', 'confirmed')
                ->first();
            if ($payment) {
                $delay = Carbon::parse($inst->due_date)->diffInDays($payment->paid_at, false);
                $delays[] = $delay; // positif = en retard
            }
        }
        $avgDelay = count($delays) > 0 ? array_sum($delays) / count($delays) : 0;
        $score2   = (int) min(25, max(0, round(($avgDelay / 30) * 25)));
        $totalScore += $score2;
        $factors['avg_delay_days'] = ['score' => $score2, 'avg_days' => round($avgDelay, 1)];

        // ── Facteur 3 : Relances reçues (20 pts) ────────────────────────
        $reminderCount = $student->reminders()
            ->whereIn('status', ['sent', 'delivered', 'read'])
            ->where('scenario', '!=', 'manual')
            ->count();
        $score3 = (int) min(20, $reminderCount * 4);
        $totalScore += $score3;
        $factors['reminder_count'] = ['score' => $score3, 'reminders' => $reminderCount];

        // ── Facteur 4 : Paiements partiels (15 pts) ─────────────────────
        $partialCount = $student->payments()
            ->where('status', 'confirmed')
            ->whereRaw('amount < (SELECT amount FROM installments WHERE id = payments.installment_id)')
            ->count();
        $score4 = (int) min(15, $partialCount * 5);
        $totalScore += $score4;
        $factors['partial_payments'] = ['score' => $score4, 'partial_count' => $partialCount];

        $totalScore = min(100, $totalScore);

        return $this->saveScore($student, $totalScore, $factors);
    }

    /**
     * Calcule les scores pour tous les élèves d'une école.
     */
    public function computeForSchool(School $school): int
    {
        $computed = 0;
        $school->students()->where('status', 'active')->each(function (Student $student) use (&$computed) {
            $this->compute($student);
            $computed++;
        });
        return $computed;
    }

    private function saveScore(Student $student, int $score, array $factors): RiskScore
    {
        return RiskScore::updateOrCreate(
            ['student_id' => $student->id],
            [
                'score'       => $score,
                'segment'     => RiskScore::computeSegment($score),
                'factors'     => $factors,
                'computed_at' => now(),
            ]
        );
    }
}
