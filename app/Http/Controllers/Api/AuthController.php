<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterSchoolRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\School;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * POST /api/v1/auth/register
     * Crée un tenant, une école et le premier compte Directeur.
     */
    public function register(RegisterSchoolRequest $request): JsonResponse
    {
        $data = $request->validated();

        DB::beginTransaction();
        try {
            // 1. Créer le tenant
            $tenant = Tenant::create([
                'name'          => $data['school_name'],
                'subdomain'     => $data['subdomain'] ?? null,
                'plan'          => 'standard',
                'is_active'     => true,
                'trial_ends_at' => now()->addDays(30),
            ]);

            // 2. Créer l'école
            $school = School::create([
                'tenant_id'    => $tenant->id,
                'name'         => $data['school_name'],
                'city'         => $data['city'] ?? null,
                'country'      => $data['country'] ?? 'BJ',
                'phone'        => $data['school_phone'] ?? null,
                'email'        => $data['school_email'] ?? $data['email'],
                'director_name'=> $data['firstname'] . ' ' . $data['lastname'],
            ]);

            // 3. Créer le compte Directeur
            $user = User::create([
                'tenant_id'  => $tenant->id,
                'school_id'  => $school->id,
                'firstname'  => $data['firstname'],
                'lastname'   => $data['lastname'],
                'email'      => $data['email'],
                'password'   => Hash::make($data['password']),
                'role'       => 'director',
                'is_active'  => true,
            ]);

            DB::commit();

            $token = $user->createToken('auth_token', ['*'], now()->addDays(30))->plainTextToken;

            return response()->json([
                'message' => 'Compte créé avec succès. Bienvenue sur EDU PAY PRO !',
                'data'    => [
                    'user'   => new UserResource($user),
                    'school' => $school,
                    'token'  => $token,
                    'trial_ends_at' => $tenant->trial_ends_at,
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur lors de la création du compte.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/v1/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json([
                'message' => 'Identifiants incorrects.',
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'message' => 'Votre compte est désactivé. Contactez votre directeur.',
            ], 403);
        }

        // Vérifier que le tenant est actif
        $tenant = Tenant::find($user->tenant_id);
        if (!$tenant || !$tenant->is_active) {
            return response()->json([
                'message' => 'Votre abonnement est suspendu. Contactez le support EDU PAY PRO.',
            ], 403);
        }

        // Révoquer les anciens tokens si souhaité
        if ($request->boolean('revoke_others')) {
            $user->tokens()->delete();
        }

        $user->update(['last_login_at' => now()]);
        $token = $user->createToken('auth_token', ['*'], now()->addDays(30))->plainTextToken;

        return response()->json([
            'message' => 'Connexion réussie.',
            'data'    => [
                'user'  => new UserResource($user->load('school')),
                'token' => $token,
            ],
        ]);
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnexion réussie.']);
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => new UserResource($request->user()->load('school')),
        ]);
    }

    /**
     * PUT /api/v1/auth/me
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $request->user()->update($request->validated());

        return response()->json([
            'message' => 'Profil mis à jour.',
            'data'    => new UserResource($request->user()->fresh()),
        ]);
    }

    /**
     * PUT /api/v1/auth/me/password
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Mot de passe actuel incorrect.'], 422);
        }

        $user->update(['password' => Hash::make($request->new_password)]);
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return response()->json(['message' => 'Mot de passe modifié avec succès.']);
    }
}
