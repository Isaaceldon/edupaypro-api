<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcademicYearController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $years = $request->user()->school
            ->academicYears()
            ->orderByDesc('start_date')
            ->get();

        return response()->json(['data' => $years]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'label'      => ['required', 'string', 'max:20'],
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after:start_date'],
            'notes'      => ['nullable', 'string'],
        ]);

        $year = $request->user()->school->academicYears()->create($data);

        return response()->json([
            'message' => 'Année scolaire créée.',
            'data'    => $year,
        ], 201);
    }

    public function show(Request $request, AcademicYear $academicYear): JsonResponse
    {
        $this->authorizeSchool($request, $academicYear);
        return response()->json(['data' => $academicYear]);
    }

    public function update(Request $request, AcademicYear $academicYear): JsonResponse
    {
        $this->authorizeSchool($request, $academicYear);

        $data = $request->validate([
            'label'      => ['sometimes', 'string', 'max:20'],
            'start_date' => ['sometimes', 'date'],
            'end_date'   => ['sometimes', 'date', 'after:start_date'],
            'notes'      => ['nullable', 'string'],
        ]);

        $academicYear->update($data);

        return response()->json([
            'message' => 'Année scolaire mise à jour.',
            'data'    => $academicYear->fresh(),
        ]);
    }

    public function destroy(Request $request, AcademicYear $academicYear): JsonResponse
    {
        $this->authorizeSchool($request, $academicYear);

        if ($academicYear->is_active) {
            return response()->json([
                'message' => 'Impossible de supprimer l\'année scolaire active.',
            ], 422);
        }

        $academicYear->delete();

        return response()->json(['message' => 'Année scolaire supprimée.']);
    }

    /**
     * POST /api/v1/academic-years/{id}/activate
     * Active une année et désactive toutes les autres.
     */
    public function activate(Request $request, AcademicYear $academicYear): JsonResponse
    {
        $this->authorizeSchool($request, $academicYear);
        $academicYear->activate();

        return response()->json([
            'message' => "Année \"{$academicYear->label}\" activée.",
            'data'    => $academicYear->fresh(),
        ]);
    }

    private function authorizeSchool(Request $request, AcademicYear $year): void
    {
        if ($year->school_id !== $request->user()->school_id) {
            abort(403, 'Accès refusé.');
        }
    }
}
