<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * TenantMiddleware
 *
 * Résout le tenant courant à partir du token Sanctum de l'utilisateur connecté.
 * Injecte le tenant_id dans le container IoC pour le global scope BelongsToTenant.
 */
class TenantMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        $tenant = Tenant::find($user->tenant_id);

        if (!$tenant || !$tenant->is_active) {
            return response()->json([
                'message' => 'Compte suspendu ou introuvable. Contactez le support.',
            ], 403);
        }

        // Injecte dans le container pour BelongsToTenant scope
        app()->instance('current_tenant_id', $tenant->id);
        app()->instance('current_tenant', $tenant);

        // Ajoute le tenant sur la requête pour un accès facile dans les controllers
        $request->merge(['_tenant' => $tenant]);

        return $next($request);
    }
}
