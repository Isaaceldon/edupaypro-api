<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchoolClassController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $classes = SchoolClass::where('school_id', $request->user()->school_id)
            ->withCount(['students' => fn($q) => $q->where('status', 'active')])
            ->with('teacher:id,firstname,lastname')
            ->orderBy('sort_order')->orderBy('name')
            ->get();

        return response()->json(['data' => $classes]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:50'],
            'level'      => ['nullable', 'string', 'max:50'],
            'capacity'   => ['nullable', 'integer', 'min:1', 'max:200'],
            'room'       => ['nullable', 'string', 'max:50'],
            'teacher_id' => ['nullable', 'uuid', 'exists:users,id'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $exists = SchoolClass::where('school_id', $request->user()->school_id)
            ->where('name', $data['name'])->exists();

        if ($exists) {
            return response()->json(['message' => "Classe \"{$data['name']}\" déjà existante.", 'errors' => ['name' => ["Nom déjà utilisé."]]], 422);
        }

        $class = SchoolClass::create([...$data, 'school_id' => $request->user()->school_id]);

        return response()->json(['message' => "Classe créée.", 'data' => $class->load('teacher:id,firstname,lastname')], 201);
    }

    public function show(Request $request, SchoolClass $class): JsonResponse
    {
        $this->auth($request, $class);
        $class->load('teacher:id,firstname,lastname')->loadCount(['students' => fn($q) => $q->where('status', 'active')]);
        return response()->json(['data' => $class]);
    }

    public function update(Request $request, SchoolClass $class): JsonResponse
    {
        $this->auth($request, $class);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:50'], 'level' => ['nullable', 'string', 'max:50'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:200'], 'room' => ['nullable', 'string', 'max:50'],
            'teacher_id' => ['nullable', 'uuid', 'exists:users,id'], 'sort_order' => ['nullable', 'integer'],
        ]);
        $class->update($data);
        return response()->json(['message' => 'Classe mise à jour.', 'data' => $class->fresh()->load('teacher:id,firstname,lastname')]);
    }

    public function destroy(Request $request, SchoolClass $class): JsonResponse
    {
        $this->auth($request, $class);
        $n = $class->students()->where('status', 'active')->count();
        if ($n > 0) return response()->json(['message' => "Impossible : {$n} élève(s) actif(s) dans cette classe."], 422);
        $class->delete();
        return response()->json(['message' => 'Classe supprimée.']);
    }

    private function auth(Request $r, SchoolClass $c): void
    {
        if ($c->school_id !== $r->user()->school_id) abort(403);
    }
}
