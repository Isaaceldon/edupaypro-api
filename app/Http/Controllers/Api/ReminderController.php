<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Installment;
use App\Models\Reminder;
use App\Models\Student;
use App\Services\ReminderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReminderController extends Controller
{
    public function __construct(private ReminderService $service) {}

    /**
     * GET /api/v1/reminders
     * Filtres : student_id, scenario, status, channel, date_from
     */
    public function index(Request $request): JsonResponse
    {
        $schoolId = $request->user()->school_id;

        $query = Reminder::whereHas('student', fn($q) => $q->where('school_id', $schoolId))
            ->with([
                'student:id,firstname,lastname,matricule',
                'installment:id,label,due_date,amount',
            ]);

        if ($request->filled('student_id')) $query->where('student_id', $request->student_id);
        if ($request->filled('scenario'))   $query->where('scenario', $request->scenario);
        if ($request->filled('status'))     $query->where('status', $request->status);
        if ($request->filled('channel'))    $query->where('channel', $request->channel);
        if ($request->filled('date_from'))  $query->where('scheduled_at', '>=', $request->date_from);

        $reminders = $query->latest('scheduled_at')
            ->paginate($request->integer('per_page', 50));

        return response()->json($reminders);
    }

    /**
     * GET /api/v1/reminders/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $schoolId = $request->user()->school_id;

        $base = fn() => Reminder::whereHas('student', fn($q) => $q->where('school_id', $schoolId));

        return response()->json(['data' => [
            'total'     => $base()->count(),
            'pending'   => $base()->where('status', 'pending')->count(),
            'sent'      => $base()->where('status', 'sent')->count(),
            'delivered' => $base()->where('status', 'delivered')->count(),
            'read'      => $base()->where('status', 'read')->count(),
            'failed'    => $base()->where('status', 'failed')->count(),
            'by_scenario' => $base()
                ->selectRaw('scenario, count(*) as total, sum(status="read") as read_count')
                ->groupBy('scenario')
                ->get(),
            'open_rate' => $this->computeOpenRate($base),
        ]]);
    }

    /**
     * POST /api/v1/reminders/trigger-manual
     * Déclenche une relance manuelle
     */
    public function triggerManual(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id'     => ['required', 'uuid', 'exists:students,id'],
            'installment_id' => ['required', 'uuid', 'exists:installments,id'],
            'channel'        => ['nullable', 'in:whatsapp,sms,email'],
        ]);

        $student = Student::where('id', $data['student_id'])
            ->where('school_id', $request->user()->school_id)
            ->firstOrFail();

        $installment = Installment::findOrFail($data['installment_id']);

        $reminder = $this->service->sendManual(
            $student,
            $installment,
            $data['channel'] ?? 'whatsapp'
        );

        return response()->json([
            'message' => 'Relance planifiée et envoyée.',
            'data'    => $reminder,
        ], 201);
    }

    /**
     * PUT /api/v1/reminders/scenarios
     * Permet au directeur de personnaliser les scénarios (bientôt)
     */
    public function updateScenarios(Request $request): JsonResponse
    {
        // Stub — sera implémenté avec la table school_settings
        return response()->json(['message' => 'Scénarios mis à jour (fonctionnalité bientôt disponible).']);
    }

    /**
     * POST /api/v1/webhooks/whatsapp
     * Webhook Meta : mise à jour des statuts (sent, delivered, read)
     */
    public function whatsappWebhook(Request $request): JsonResponse
    {
        $body = $request->all();

        // Vérification signature Meta (optionnel en dev)
        foreach ($body['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $statuses = $change['value']['statuses'] ?? [];
                foreach ($statuses as $statusUpdate) {
                    $msgId  = $statusUpdate['id'];
                    $status = $statusUpdate['status']; // sent | delivered | read | failed

                    $reminder = Reminder::where('provider_message_id', $msgId)->first();
                    if (!$reminder) continue;

                    match($status) {
                        'sent'      => $reminder->update(['status' => 'sent']),
                        'delivered' => $reminder->update(['status' => 'delivered', 'delivered_at' => now()]),
                        'read'      => $reminder->markAsRead(),
                        'failed'    => $reminder->markAsFailed($statusUpdate['errors'][0]['message'] ?? 'Unknown'),
                        default     => null,
                    };
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * GET /api/v1/webhooks/whatsapp
     * Vérification du webhook par Meta
     */
    public function whatsappVerify(Request $request): mixed
    {
        $mode      = $request->query('hub_mode');
        $token     = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === config('whatsapp.verify_token')) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response()->json(['error' => 'Forbidden'], 403);
    }

    private function computeOpenRate(\Closure $base): float
    {
        $sent = $base()->whereIn('status', ['sent', 'delivered', 'read'])->count();
        $read = $base()->where('status', 'read')->count();
        return $sent > 0 ? round(($read / $sent) * 100, 1) : 0;
    }

    // Stubs obligatoires pour resourceful routing
    public function store(Request $r): JsonResponse   { return response()->json(['message' => 'Utilisez /reminders/trigger-manual'], 405); }
    public function show(Request $r, $id): JsonResponse { return response()->json(['message' => 'N/A'], 405); }
    public function update(Request $r, $id): JsonResponse { return response()->json(['message' => 'N/A'], 405); }
    public function destroy(Request $r, $id): JsonResponse { return response()->json(['message' => 'N/A'], 405); }
}
