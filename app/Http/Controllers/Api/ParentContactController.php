<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ParentContact;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParentContactController extends Controller
{
    public function index(Request $request, Student $student): JsonResponse
    {
        $this->authStudent($request, $student);
        return response()->json(['data' => $student->parents()->orderByDesc('is_primary')->get()]);
    }

    public function store(Request $request, Student $student): JsonResponse
    {
        $this->authStudent($request, $student);

        $data = $request->validate([
            'firstname'        => ['required', 'string', 'max:100'],
            'lastname'         => ['required', 'string', 'max:100'],
            'relationship'     => ['required', 'in:father,mother,guardian,other'],
            'phone_whatsapp'   => ['required', 'string', 'max:20'],
            'phone_alt'        => ['nullable', 'string', 'max:20'],
            'email'            => ['nullable', 'email'],
            'profession'       => ['nullable', 'string', 'max:100'],
            'address'          => ['nullable', 'string'],
            'is_primary'       => ['boolean'],
            'whatsapp_opted_in'=> ['boolean'],
        ]);

        // Si ce contact est principal, retirer le flag des autres
        if (!empty($data['is_primary'])) {
            $student->parents()->update(['is_primary' => false]);
        }

        $parent = $student->parents()->create($data);

        return response()->json(['message' => 'Contact parent ajouté.', 'data' => $parent], 201);
    }

    public function show(Request $request, ParentContact $parent): JsonResponse
    {
        $this->authParent($request, $parent);
        return response()->json(['data' => $parent]);
    }

    public function update(Request $request, ParentContact $parent): JsonResponse
    {
        $this->authParent($request, $parent);

        $data = $request->validate([
            'firstname'        => ['sometimes', 'string', 'max:100'],
            'lastname'         => ['sometimes', 'string', 'max:100'],
            'relationship'     => ['sometimes', 'in:father,mother,guardian,other'],
            'phone_whatsapp'   => ['sometimes', 'string', 'max:20'],
            'phone_alt'        => ['nullable', 'string', 'max:20'],
            'email'            => ['nullable', 'email'],
            'profession'       => ['nullable', 'string', 'max:100'],
            'address'          => ['nullable', 'string'],
            'is_primary'       => ['boolean'],
            'whatsapp_opted_in'=> ['boolean'],
        ]);

        if (!empty($data['is_primary'])) {
            $parent->student->parents()->where('id', '!=', $parent->id)->update(['is_primary' => false]);
        }

        $parent->update($data);

        return response()->json(['message' => 'Contact parent mis à jour.', 'data' => $parent->fresh()]);
    }

    public function destroy(Request $request, ParentContact $parent): JsonResponse
    {
        $this->authParent($request, $parent);

        if ($parent->is_primary && $parent->student->parents()->count() === 1) {
            return response()->json(['message' => 'Impossible de supprimer le seul contact parent.'], 422);
        }

        $parent->delete();
        return response()->json(['message' => 'Contact parent supprimé.']);
    }

    private function authStudent(Request $r, Student $s): void
    {
        if ($s->school_id !== $r->user()->school_id) abort(403);
    }

    private function authParent(Request $r, ParentContact $p): void
    {
        if ($p->student->school_id !== $r->user()->school_id) abort(403);
    }
}
