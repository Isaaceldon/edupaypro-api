<?php

namespace App\Services;

use App\Jobs\SendReminderJob;
use App\Models\Installment;
use App\Models\Reminder;
use App\Models\School;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ReminderService
{
    /**
     * Scénarios de relance avec leur délai (en jours relatifs à l'échéance).
     * Valeur négative = avant échéance, positive = après.
     */
    private array $scenarios = [
        'J-7'  => -7,
        'J-1'  => -1,
        'J+0'  =>  0,
        'J+3'  =>  3,
        'J+7'  =>  7,
    ];

    /**
     * Génère les messages de relance selon le scénario.
     */
    private function buildMessage(string $scenario, Student $student, Installment $installment): string
    {
        $name    = $student->full_name;
        $amount  = number_format($installment->amount, 0, ',', ' ');
        $due     = Carbon::parse($installment->due_date)->format('d/m/Y');
        $balance = number_format($student->getBalance(), 0, ',', ' ');

        return match($scenario) {
            'J-7' =>
                "📚 *EDU PAY PRO — Rappel de paiement*\n\n"
                . "Bonjour, parent de *{$name}*,\n\n"
                . "Nous vous rappelons que la tranche *{$installment->label}* "
                . "de *{$amount} FCFA* est due le *{$due}*.\n\n"
                . "💳 Pour payer : WhatsApp-nous ou contactez la comptabilité.\n"
                . "_Solde restant : {$balance} FCFA_",

            'J-1' =>
                "⚠️ *EDU PAY PRO — Échéance demain !*\n\n"
                . "Cher(e) parent de *{$name}*,\n\n"
                . "La tranche *{$installment->label}* de *{$amount} FCFA* "
                . "est due *demain le {$due}*.\n\n"
                . "Merci de régulariser avant demain pour éviter toute perturbation.\n"
                . "📞 Besoin d'aide ? Répondez à ce message.",

            'J+0' =>
                "🔴 *EDU PAY PRO — Paiement dû aujourd'hui*\n\n"
                . "Parent de *{$name}*,\n\n"
                . "La tranche *{$installment->label}* de *{$amount} FCFA* "
                . "est due *aujourd'hui*.\n\n"
                . "Veuillez régler dès aujourd'hui pour éviter les pénalités.\n"
                . "✅ Tapez *BALANCE* pour voir votre solde.",

            'J+3' =>
                "🚨 *EDU PAY PRO — Retard de paiement*\n\n"
                . "Parent de *{$name}*,\n\n"
                . "La tranche *{$installment->label}* de *{$amount} FCFA* "
                . "aurait dû être réglée le *{$due}*.\n\n"
                . "⏰ Vous êtes en retard de *3 jours*. Des pénalités peuvent s'appliquer.\n"
                . "Contactez-nous de toute urgence.",

            'J+7' =>
                "‼️ *EDU PAY PRO — RETARD URGENT*\n\n"
                . "Parent de *{$name}*,\n\n"
                . "Malgré nos rappels, la tranche *{$installment->label}* de *{$amount} FCFA* "
                . "reste impayée depuis le *{$due}* (7 jours de retard).\n\n"
                . "Sans régularisation sous 48h, le dossier sera transmis à la direction.\n"
                . "📞 Contactez-nous *immédiatement*.",

            default => "EDU PAY PRO : Rappel paiement {$installment->label} — {$amount} FCFA dû le {$due}.",
        };
    }

    /**
     * Planifie toutes les relances pour une école entière.
     * Appelé par le scheduler quotidien.
     */
    public function scheduleForSchool(School $school): int
    {
        $year = $school->activeYear;
        if (!$year) return 0;

        $created = 0;

        // Récupère tous les élèves actifs avec leurs échéances non payées
        $students = $school->students()
            ->where('status', 'active')
            ->with(['primaryParent', 'class'])
            ->get();

        foreach ($students as $student) {
            $created += $this->scheduleForStudent($student, $year->id);
        }

        Log::info("Reminders scheduled for school {$school->id}: {$created} new reminders.");
        return $created;
    }

    /**
     * Planifie les relances pour un élève donné.
     */
    public function scheduleForStudent(Student $student, string $academicYearId): int
    {
        $paidInstallmentIds = $student->payments()
            ->where('status', 'confirmed')
            ->pluck('installment_id');

        $installments = Installment::whereHas('feeConfig', fn($q) =>
            $q->where('class_id', $student->class_id)
              ->where('academic_year_id', $academicYearId)
        )->whereNotIn('id', $paidInstallmentIds)->get();

        $created = 0;

        foreach ($installments as $installment) {
            foreach ($this->scenarios as $scenario => $dayOffset) {
                $scheduledAt = Carbon::parse($installment->due_date)->addDays($dayOffset)->setTime(8, 0);

                // Ne pas planifier dans le passé (sauf J+0, J+3, J+7 si déjà en retard)
                if ($scheduledAt->isPast() && $dayOffset < 0) continue;

                // Éviter les doublons
                $exists = Reminder::where('student_id', $student->id)
                    ->where('installment_id', $installment->id)
                    ->where('scenario', $scenario)
                    ->whereIn('status', ['pending', 'sent', 'delivered'])
                    ->exists();

                if ($exists) continue;

                Reminder::create([
                    'student_id'     => $student->id,
                    'installment_id' => $installment->id,
                    'channel'        => $student->primaryParent?->whatsapp_opted_in ? 'whatsapp' : 'sms',
                    'scenario'       => $scenario,
                    'message_sent'   => $this->buildMessage($scenario, $student, $installment),
                    'sent_to'        => $student->primaryParent?->phone_whatsapp ?? '',
                    'status'         => 'pending',
                    'scheduled_at'   => $scheduledAt,
                ]);

                $created++;
            }
        }

        return $created;
    }

    /**
     * Dispatch les relances dues (called par le scheduler toutes les heures).
     */
    public function dispatchDue(): int
    {
        $due = Reminder::pending()->due()->with('student.primaryParent')->get();
        $dispatched = 0;

        foreach ($due as $reminder) {
            SendReminderJob::dispatch($reminder)->onQueue('reminders');
            $dispatched++;
        }

        return $dispatched;
    }

    /**
     * Relance manuelle pour un élève ou une tranche.
     */
    public function sendManual(Student $student, Installment $installment, string $channel = 'whatsapp'): Reminder
    {
        $reminder = Reminder::create([
            'student_id'     => $student->id,
            'installment_id' => $installment->id,
            'channel'        => $channel,
            'scenario'       => 'manual',
            'message_sent'   => $this->buildMessage('J+0', $student, $installment),
            'sent_to'        => $student->primaryParent?->phone_whatsapp ?? '',
            'status'         => 'pending',
            'scheduled_at'   => now(),
        ]);

        SendReminderJob::dispatch($reminder)->onQueue('reminders');

        return $reminder;
    }
}
