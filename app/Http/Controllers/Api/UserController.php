<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::where('school_id', $request->user()->school_id)
            ->orderBy('lastname')
            ->get();

        return response()->json(['data' => UserResource::collection($users)]);
    }

    public function store(Request $request): JsonResponse
    {
        $isSendInvite = (bool) $request->input('send_invite', false);

        $data = $request->validate([
            'firstname' => ['required', 'string', 'max:100'],
            'lastname'  => ['required', 'string', 'max:100'],
            'email'     => ['required', 'email', 'unique:users,email'],
            'phone'     => ['nullable', 'string', 'max:20'],
            'role'      => ['required', Rule::in(['director', 'accountant', 'teacher'])],
            'password'  => $isSendInvite ? ['nullable'] : ['required', 'string', 'min:8'],
        ]);

        // Flux invitation : générer un mot de passe temporaire et envoyer un email
        $temporaryPassword = $isSendInvite ? \Illuminate\Support\Str::random(16) : $data['password'];

        $user = User::create([
            'firstname'   => $data['firstname'],
            'lastname'    => $data['lastname'],
            'email'       => $data['email'],
            'phone'       => $data['phone'] ?? null,
            'role'        => $data['role'],
            'tenant_id'   => $request->user()->tenant_id,
            'school_id'   => $request->user()->school_id,
            'password'    => Hash::make($temporaryPassword),
            'invite_token'=> $isSendInvite ? \Illuminate\Support\Str::random(32) : null,
            'is_active'   => !$isSendInvite, // Inactif jusqu'à activation via lien
        ]);

        // Envoyer l'email d'invitation
        if ($isSendInvite) {
            try {
                \Illuminate\Support\Facades\Password::sendResetLink(['email' => $user->email]);
            } catch (\Exception $e) {
                // Email non bloquant — l'utilisateur peut être créé même si l'email échoue
                \Illuminate\Support\Facades\Log::warning("Invitation email failed for user {$user->id}: " . $e->getMessage());
            }
        }

        return response()->json([
            'message' => $isSendInvite
                ? "Invitation envoyée à {$user->email}."
                : 'Utilisateur créé.',
            'data'    => new UserResource($user),
        ], 201);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $this->authorizeSchool($request, $user);
        return response()->json(['data' => new UserResource($user)]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorizeSchool($request, $user);

        // Empêche de modifier le super_admin
        if ($user->isSuperAdmin()) {
            return response()->json(['message' => 'Impossible de modifier ce compte.'], 403);
        }

        $data = $request->validate([
            'firstname' => ['sometimes', 'string', 'max:100'],
            'lastname'  => ['sometimes', 'string', 'max:100'],
            'email'     => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'phone'     => ['nullable', 'string', 'max:20'],
            'role'      => ['sometimes', Rule::in(['director', 'accountant', 'teacher'])],
        ]);

        $user->update($data);

        return response()->json([
            'message' => 'Utilisateur mis à jour.',
            'data'    => new UserResource($user->fresh()),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorizeSchool($request, $user);

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Vous ne pouvez pas supprimer votre propre compte.'], 422);
        }

        $user->delete();

        return response()->json(['message' => 'Utilisateur supprimé.']);
    }

    /**
     * POST /api/v1/users/{user}/toggle-active
     */
    public function toggleActive(Request $request, User $user): JsonResponse
    {
        $this->authorizeSchool($request, $user);

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Vous ne pouvez pas vous désactiver vous-même.'], 422);
        }

        $user->update(['is_active' => !$user->is_active]);

        return response()->json([
            'message' => $user->is_active ? 'Compte activé.' : 'Compte désactivé.',
            'data'    => new UserResource($user->fresh()),
        ]);
    }

    private function authorizeSchool(Request $request, User $user): void
    {
        if ($user->school_id !== $request->user()->school_id) {
            abort(403, 'Accès refusé.');
        }
    }
}
