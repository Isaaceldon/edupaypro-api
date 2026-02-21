<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\TenantMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        // Middleware globaux API
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        // Alias de middleware
        $middleware->alias([
            'tenant' => TenantMiddleware::class,
            'role'   => CheckRole::class,
        ]);

        // Réponses JSON pour les erreurs d'auth sur les routes API
        $middleware->redirectGuestsTo(fn(Request $request) => $request->expectsJson()
            ? null   // retourne 401 automatiquement
            : route('login')
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {

        // Toujours renvoyer du JSON sur les routes /api/*
        $exceptions->shouldRenderJsonWhen(
            fn(Request $request) => $request->is('api/*')
        );

        // Format des erreurs de validation
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Les données envoyées sont invalides.',
                    'errors'  => $e->errors(),
                ], 422);
            }
        });

        // 404 en JSON
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Ressource introuvable.'], 404);
            }
        });

        // 403 en JSON
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => $e->getMessage() ?: 'Accès refusé.'], 403);
            }
        });
    })
    ->create();
