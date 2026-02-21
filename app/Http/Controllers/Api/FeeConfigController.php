<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FeeConfig;
use App\Models\Installment;
use App\Models\SchoolClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeeConfigController extends Controller
{
    /**
     * GET /api/v1/fee-configs
     * Filtres: class_id, academic_year_id, type
     */
    public function index(Request $request): JsonResponse
    {
        $schoolId = $request->user()->school_id;

        $query = FeeConfig::whereHas('class', fn($q) => $q->where('school_id', $schoolId))
            ->with(['class:id,name,level', 'academicYear:id,label', 'installments'])
            ->withCount('installments');

        if ($request->filled('class_id'))        $query->where('class_id', $request->class_id);
        if ($request->filled('academic_year_id')) $query->where('academic_year_id', $request->academic_year_id);
        if ($request->filled('type'))             $query->where('type', $request->type);

        return response()->json(['data' => $query->get()]);
    }

    /**
     * POST /api/v1/fee-configs
     * Crée la config + les tranches en une seule requête
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'class_id'        => ['required', 'uuid', 'exists:classes,id'],
            'academic_year_id'=> ['required', 'uuid', 'exists:academic_years,id'],
            'label'           => ['required', 'string', 'max:200'],
            'type'            => ['required', 'in:tuition,registration,canteen,transport,uniform,exam,other'],
            'total_amount'    => ['required', 'numeric', 'min:0'],
            'currency'        => ['nullable', 'string', 'size:3'],
            'is_mandatory'    => ['boolean'],
            'notes'           => ['nullable', 'string'],
            'installments'    => ['required', 'array', 'min:1'],
            'installments.*.order'        => ['required', 'integer', 'min:1'],
            'installments.*.label'        => ['required', 'string', 'max:100'],
            'installments.*.amount'       => ['required', 'numeric', 'min:0'],
            'installments.*.due_date'     => ['required', 'date'],
            'installments.*.penalty_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'installments.*.penalty_fixed'=> ['nullable', 'numeric', 'min:0'],
        ]);

        // Vérifier que la classe appartient à l'école
        SchoolClass::where('id', $data['class_id'])
            ->where('school_id', $request->user()->school_id)
            ->firstOrFail();

        // Vérifier que la somme des tranches = total_amount
        $sum = collect($data['installments'])->sum('amount');
        if (abs($sum - $data['total_amount']) > 0.01) {
            return response()->json([
                'message' => "La somme des tranches ({$sum}) doit être égale au montant total ({$data['total_amount']}).",
                'errors'  => ['installments' => ['Somme des tranches incorrecte.']],
            ], 422);
        }

        DB::beginTransaction();
        try {
            $config = FeeConfig::create(\Illuminate\Support\Arr::except($data, ['installments']));

            foreach ($data['installments'] as $inst) {
                $config->installments()->create($inst);
            }

            DB::commit();

            return response()->json([
                'message' => "Configuration de frais créée.",
                'data'    => $config->load(['class:id,name', 'academicYear:id,label', 'installments']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur lors de la création.', 'error' => $e->getMessage()], 500);
        }
    }

    public function show(Request $request, FeeConfig $feeConfig): JsonResponse
    {
        $this->auth($request, $feeConfig);
        return response()->json(['data' => $feeConfig->load(['class:id,name,level', 'academicYear:id,label', 'installments'])]);
    }

    public function update(Request $request, FeeConfig $feeConfig): JsonResponse
    {
        $this->auth($request, $feeConfig);

        $data = $request->validate([
            'label'        => ['sometimes', 'string', 'max:200'],
            'type'         => ['sometimes', 'in:tuition,registration,canteen,transport,uniform,exam,other'],
            'total_amount' => ['sometimes', 'numeric', 'min:0'],
            'currency'     => ['nullable', 'string', 'size:3'],
            'is_mandatory' => ['boolean'],
            'notes'        => ['nullable', 'string'],
        ]);

        $feeConfig->update($data);

        return response()->json([
            'message' => 'Configuration mise à jour.',
            'data'    => $feeConfig->fresh()->load(['class:id,name', 'installments']),
        ]);
    }

    public function destroy(Request $request, FeeConfig $feeConfig): JsonResponse
    {
        $this->auth($request, $feeConfig);

        // Vérifier si des paiements existent sur ces tranches
        $hasPay = Installment::where('fee_config_id', $feeConfig->id)
            ->whereHas('payments', fn($q) => $q->where('status', 'confirmed'))
            ->exists();

        if ($hasPay) {
            return response()->json(['message' => 'Impossible de supprimer : des paiements existent sur cette configuration.'], 422);
        }

        $feeConfig->installments()->delete();
        $feeConfig->delete();

        return response()->json(['message' => 'Configuration de frais supprimée.']);
    }

    /**
     * POST /api/v1/fee-configs/{id}/duplicate
     * Duplique une config vers une autre année scolaire
     */
    public function duplicate(Request $request, FeeConfig $feeConfig): JsonResponse
    {
        $this->auth($request, $feeConfig);

        $data = $request->validate([
            'academic_year_id' => ['required', 'uuid', 'exists:academic_years,id'],
            'class_id'         => ['nullable', 'uuid', 'exists:classes,id'],
        ]);

        DB::beginTransaction();
        try {
            $newConfig = $feeConfig->replicate();
            $newConfig->academic_year_id = $data['academic_year_id'];
            if (!empty($data['class_id'])) $newConfig->class_id = $data['class_id'];
            $newConfig->save();

            foreach ($feeConfig->installments as $inst) {
                $newInst = $inst->replicate();
                $newInst->fee_config_id = $newConfig->id;
                $newInst->save();
            }

            DB::commit();
            return response()->json([
                'message' => 'Configuration dupliquée avec succès.',
                'data'    => $newConfig->load(['class:id,name', 'academicYear:id,label', 'installments']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur duplication.', 'error' => $e->getMessage()], 500);
        }
    }

    private function auth(Request $r, FeeConfig $f): void
    {
        $schoolId = $r->user()->school_id;
        $ok = SchoolClass::where('id', $f->class_id)->where('school_id', $schoolId)->exists();
        if (!$ok) abort(403);
    }
}
