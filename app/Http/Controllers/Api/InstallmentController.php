<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FeeConfig;
use App\Models\Installment;
use App\Models\SchoolClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InstallmentController extends Controller
{
    public function index(Request $request, FeeConfig $feeConfig): JsonResponse
    {
        $this->authConfig($request, $feeConfig);
        return response()->json(['data' => $feeConfig->installments()->orderBy('order')->get()]);
    }

    public function store(Request $request, FeeConfig $feeConfig): JsonResponse
    {
        $this->authConfig($request, $feeConfig);

        $data = $request->validate([
            'order'         => ['required', 'integer', 'min:1'],
            'label'         => ['required', 'string', 'max:100'],
            'amount'        => ['required', 'numeric', 'min:0'],
            'due_date'      => ['required', 'date'],
            'penalty_rate'  => ['nullable', 'numeric', 'min:0', 'max:100'],
            'penalty_fixed' => ['nullable', 'numeric', 'min:0'],
        ]);

        $inst = $feeConfig->installments()->create($data);
        return response()->json(['message' => 'Tranche ajoutée.', 'data' => $inst], 201);
    }

    public function show(Request $request, Installment $installment): JsonResponse
    {
        $this->authInst($request, $installment);
        return response()->json(['data' => $installment->load('feeConfig:id,label,total_amount')]);
    }

    public function update(Request $request, Installment $installment): JsonResponse
    {
        $this->authInst($request, $installment);

        $data = $request->validate([
            'order'         => ['sometimes', 'integer', 'min:1'],
            'label'         => ['sometimes', 'string', 'max:100'],
            'amount'        => ['sometimes', 'numeric', 'min:0'],
            'due_date'      => ['sometimes', 'date'],
            'penalty_rate'  => ['nullable', 'numeric', 'min:0', 'max:100'],
            'penalty_fixed' => ['nullable', 'numeric', 'min:0'],
        ]);

        $installment->update($data);
        return response()->json(['message' => 'Tranche mise à jour.', 'data' => $installment->fresh()]);
    }

    public function destroy(Request $request, Installment $installment): JsonResponse
    {
        $this->authInst($request, $installment);

        if ($installment->payments()->where('status', 'confirmed')->exists()) {
            return response()->json(['message' => 'Impossible : des paiements confirmés existent sur cette tranche.'], 422);
        }

        $installment->delete();
        return response()->json(['message' => 'Tranche supprimée.']);
    }

    private function authConfig(Request $r, FeeConfig $f): void
    {
        $ok = SchoolClass::where('id', $f->class_id)->where('school_id', $r->user()->school_id)->exists();
        if (!$ok) abort(403);
    }

    private function authInst(Request $r, Installment $i): void
    {
        $this->authConfig($r, $i->feeConfig);
    }
}
