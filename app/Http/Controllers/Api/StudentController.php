<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\SchoolClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StudentController extends Controller
{
    /**
     * GET /api/v1/students
     * Filtres: class_id, status, search (nom/matricule), sibling_group_id
     */
    public function index(Request $request): JsonResponse
    {
        $query = Student::where('school_id', $request->user()->school_id)
            ->with(['class:id,name', 'primaryParent:id,student_id,firstname,lastname,phone_whatsapp'])
            ->withSum(['payments' => fn($q) => $q->where('status','confirmed')], 'amount');

        if ($request->filled('class_id'))   $query->where('class_id', $request->class_id);
        if ($request->filled('status'))     $query->where('status', $request->status);
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) =>
                $q->where('firstname', 'like', "%$s%")
                  ->orWhere('lastname',  'like', "%$s%")
                  ->orWhere('matricule', 'like', "%$s%")
            );
        }

        $students = $query->orderBy('lastname')->orderBy('firstname')
            ->paginate($request->integer('per_page', 25));

        return response()->json($students);
    }

    /**
     * POST /api/v1/students
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'firstname'       => ['required', 'string', 'max:100'],
            'lastname'        => ['required', 'string', 'max:100'],
            'class_id'        => ['required', 'uuid', 'exists:classes,id'],
            'gender'          => ['nullable', 'in:M,F'],
            'birth_date'      => ['nullable', 'date'],
            'birth_place'     => ['nullable', 'string', 'max:100'],
            'nationality'     => ['nullable', 'string', 'size:2'],
            'enrollment_date' => ['nullable', 'date'],
            'notes'           => ['nullable', 'string'],
            // Parent principal obligatoire à la création
            'parent' => ['required', 'array'],
            'parent.firstname'       => ['required', 'string', 'max:100'],
            'parent.lastname'        => ['required', 'string', 'max:100'],
            'parent.phone_whatsapp'  => ['required', 'string', 'max:20'],
            'parent.relationship'    => ['nullable', 'in:father,mother,guardian,other'],
            'parent.email'           => ['nullable', 'email'],
        ]);

        // Vérifier que la classe appartient à l'école
        $class = SchoolClass::where('id', $data['class_id'])
            ->where('school_id', $request->user()->school_id)
            ->firstOrFail();

        DB::beginTransaction();
        try {
            $student = Student::create([
                ...\Illuminate\Support\Arr::except($data, ['parent']),
                'school_id'  => $request->user()->school_id,
                'matricule'  => Student::generateMatricule($request->user()->school_id, $data['class_id']),
                'status'     => 'active',
                'enrollment_date' => $data['enrollment_date'] ?? now()->toDateString(),
            ]);

            $student->parents()->create([
                ...$data['parent'],
                'is_primary' => true,
                'whatsapp_opted_in' => true,
            ]);

            DB::commit();

            return response()->json([
                'message' => "Élève {$student->full_name} ajouté.",
                'data'    => $student->load(['class:id,name', 'primaryParent']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur lors de la création.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/v1/students/{id}
     */
    public function show(Request $request, Student $student): JsonResponse
    {
        $this->auth($request, $student);

        $student->load([
            'class:id,name,level',
            'parents',
            'payments' => fn($q) => $q->with('installment:id,label,amount,due_date')->latest('paid_at')->limit(10),
            'riskScore',
        ]);

        return response()->json([
            'data' => array_merge($student->toArray(), [
                'balance' => $student->getBalance(),
                'overdue_count' => $student->overdueInstallments()->count(),
            ]),
        ]);
    }

    /**
     * PUT /api/v1/students/{id}
     */
    public function update(Request $request, Student $student): JsonResponse
    {
        $this->auth($request, $student);

        $data = $request->validate([
            'firstname'       => ['sometimes', 'string', 'max:100'],
            'lastname'        => ['sometimes', 'string', 'max:100'],
            'class_id'        => ['sometimes', 'uuid', 'exists:classes,id'],
            'gender'          => ['nullable', 'in:M,F'],
            'birth_date'      => ['nullable', 'date'],
            'birth_place'     => ['nullable', 'string', 'max:100'],
            'photo_url'       => ['nullable', 'string'],
            'status'          => ['sometimes', 'in:active,inactive,graduated,transferred'],
            'notes'           => ['nullable', 'string'],
        ]);

        if (isset($data['class_id'])) {
            SchoolClass::where('id', $data['class_id'])
                ->where('school_id', $request->user()->school_id)
                ->firstOrFail();
        }

        $student->update($data);

        return response()->json([
            'message' => 'Élève mis à jour.',
            'data'    => $student->fresh()->load(['class:id,name', 'primaryParent']),
        ]);
    }

    /**
     * DELETE /api/v1/students/{id}
     * Soft delete — l'historique est conservé
     */
    public function destroy(Request $request, Student $student): JsonResponse
    {
        $this->auth($request, $student);
        $student->update(['status' => 'inactive']);
        $student->delete();
        return response()->json(['message' => 'Élève archivé.']);
    }

    /**
     * POST /api/v1/students/import
     * Import en masse depuis Excel/CSV
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file'     => ['required', 'file', 'mimes:xlsx,csv', 'max:5120'],
            'class_id' => ['required', 'uuid', 'exists:classes,id'],
        ]);

        $class = SchoolClass::where('id', $request->class_id)
            ->where('school_id', $request->user()->school_id)
            ->firstOrFail();

        // Lecture du fichier (format CSV simple)
        $file    = $request->file('file');
        $rows    = array_map('str_getcsv', file($file->getPathname()));
        $headers = array_map('trim', array_shift($rows));

        $created = 0; $errors = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $i => $row) {
                if (count($row) < 2) continue;
                $line = array_combine($headers, array_map('trim', $row));
                $firstname = $line['prenom'] ?? $line['firstname'] ?? null;
                $lastname  = $line['nom']    ?? $line['lastname']  ?? null;
                $phone     = $line['telephone'] ?? $line['phone']  ?? null;

                if (!$firstname || !$lastname) {
                    $errors[] = "Ligne " . ($i + 2) . " : prénom ou nom manquant.";
                    continue;
                }

                $student = Student::create([
                    'school_id'      => $request->user()->school_id,
                    'class_id'       => $class->id,
                    'firstname'      => $firstname,
                    'lastname'       => strtoupper($lastname),
                    'matricule'      => Student::generateMatricule($request->user()->school_id, $class->id),
                    'gender'         => $line['sexe'] ?? $line['gender'] ?? null,
                    'birth_date'     => !empty($line['date_naissance']) ? $line['date_naissance'] : null,
                    'status'         => 'active',
                    'enrollment_date'=> now()->toDateString(),
                ]);

                if ($phone) {
                    $parentName = explode(' ', $line['parent'] ?? 'Parent Inconnu', 2);
                    $student->parents()->create([
                        'firstname'       => $parentName[0],
                        'lastname'        => $parentName[1] ?? '',
                        'phone_whatsapp'  => $phone,
                        'is_primary'      => true,
                        'whatsapp_opted_in' => true,
                    ]);
                }
                $created++;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur import.', 'error' => $e->getMessage()], 500);
        }

        return response()->json([
            'message' => "{$created} élève(s) importé(s) avec succès.",
            'created' => $created,
            'errors'  => $errors,
        ]);
    }

    /**
     * GET /api/v1/students/{id}/balance
     */
    public function balance(Request $request, Student $student): JsonResponse
    {
        $this->auth($request, $student);
        $yearId = $request->query('academic_year_id');

        return response()->json([
            'data' => [
                'student_id'   => $student->id,
                'student_name' => $student->full_name,
                'balance'      => $student->getBalance($yearId),
                'overdue'      => $student->overdueInstallments($yearId)->map(fn($i) => [
                    'id'          => $i->id,
                    'label'       => $i->label,
                    'amount'      => $i->amount,
                    'due_date'    => $i->due_date,
                    'days_overdue'=> $i->getDaysOverdue(),
                    'penalty'     => $i->getPenalty($student),
                ]),
            ],
        ]);
    }

    /**
     * GET /api/v1/students/{id}/overdue
     */
    public function overdueInstallments(Request $request, Student $student): JsonResponse
    {
        $this->auth($request, $student);
        return response()->json(['data' => $student->overdueInstallments()]);
    }

    /**
     * POST /api/v1/students/{id}/assign-sibling-group
     * Regroupe des élèves en fratrie
     */
    public function assignSiblingGroup(Request $request, Student $student): JsonResponse
    {
        $this->auth($request, $student);

        $data = $request->validate([
            'sibling_ids'       => ['required', 'array', 'min:1'],
            'sibling_ids.*'     => ['uuid', 'exists:students,id'],
            'sibling_group_id'  => ['nullable', 'uuid'],
        ]);

        $groupId = $data['sibling_group_id'] ?? $student->sibling_group_id ?? (string) Str::uuid();

        // Assigner le groupe à tous les élèves concernés
        $ids = array_merge([$student->id], $data['sibling_ids']);
        Student::whereIn('id', $ids)
            ->where('school_id', $request->user()->school_id)
            ->update(['sibling_group_id' => $groupId]);

        return response()->json([
            'message'          => count($ids) . ' élèves liés en fratrie.',
            'sibling_group_id' => $groupId,
        ]);
    }

    private function auth(Request $r, Student $s): void
    {
        if ($s->school_id !== $r->user()->school_id) abort(403);
    }
}
