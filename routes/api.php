<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SchoolController;
use App\Http\Controllers\Api\AcademicYearController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\SchoolClassController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\ParentContactController;
use App\Http\Controllers\Api\FeeConfigController;
use App\Http\Controllers\Api\InstallmentController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReceiptController;
use App\Http\Controllers\Api\ReminderController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — EDU PAY PRO v1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // ═══════════════════════════════════════════════════════════════════
    // MODULE A — AUTH (public — pas de middleware tenant)
    // ═══════════════════════════════════════════════════════════════════
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login',    [AuthController::class, 'login']);
        // Mot de passe oublié / réinitialisation (public)
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password',  [AuthController::class, 'resetPassword']);

        // Routes protégées par Sanctum
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout',          [AuthController::class, 'logout']);
            Route::get('me',               [AuthController::class, 'me']);
            Route::put('me',               [AuthController::class, 'updateProfile']);
            Route::put('me/password',      [AuthController::class, 'updatePassword']);
        });
    });

    // Vérification de reçu (public — accessible sans auth)
    Route::get('receipts/verify/{qrCode}', [ReceiptController::class, 'verify']);

    // ═══════════════════════════════════════════════════════════════════
    // ROUTES PROTÉGÉES — auth:sanctum + tenant resolved
    // ═══════════════════════════════════════════════════════════════════
    Route::middleware(['auth:sanctum', 'tenant'])->group(function () {

        // ── MODULE A — Gestion école & utilisateurs ─────────────────────
        Route::prefix('schools')->group(function () {
            Route::get('current',         [SchoolController::class, 'show']);
            Route::put('current',         [SchoolController::class, 'update']);
            Route::post('logo',           [SchoolController::class, 'uploadLogo']);
            Route::post('whatsapp/test',  [SchoolController::class, 'testWhatsapp']);
        });

        Route::apiResource('academic-years', AcademicYearController::class);
        Route::post('academic-years/{academicYear}/activate', [AcademicYearController::class, 'activate']);

        Route::apiResource('users', UserController::class)
             ->middleware('role:director');
        Route::post('users/{user}/toggle-active', [UserController::class, 'toggleActive'])
             ->middleware('role:director');

        // ── MODULE B — Élèves & parents ─────────────────────────────────
        Route::apiResource('classes', SchoolClassController::class);

        Route::apiResource('students', StudentController::class);
        Route::post('students/import', [StudentController::class, 'import']);
        Route::get('students/{student}/balance', [StudentController::class, 'balance']);
        Route::get('students/{student}/overdue',  [StudentController::class, 'overdueInstallments']);
        Route::post('students/{student}/assign-sibling-group', [StudentController::class, 'assignSiblingGroup']);

        Route::apiResource('students.parents', ParentContactController::class)
             ->shallow();

        // ── MODULE C — Frais & échéanciers ─────────────────────────────
        Route::apiResource('fee-configs', FeeConfigController::class)
             ->middleware('role:director,accountant');
        Route::post('fee-configs/{feeConfig}/duplicate', [FeeConfigController::class, 'duplicate'])
             ->middleware('role:director');

        Route::apiResource('fee-configs.installments', InstallmentController::class)
             ->shallow()
             ->middleware('role:director,accountant');

        // ── MODULE D — Paiements ────────────────────────────────────────
        Route::apiResource('payments', PaymentController::class)
             ->middleware('role:director,accountant');
        Route::post('payments/{payment}/cancel', [PaymentController::class, 'cancel'])
             ->middleware('role:director,accountant');
        Route::post('payments/{payment}/confirm-mobile-money', [PaymentController::class, 'confirmMobileMoney'])
             ->middleware('role:director,accountant');

        // ── MODULE E — Reçus ────────────────────────────────────────────
        Route::get('receipts/{receipt}',        [ReceiptController::class, 'show']);
        Route::get('receipts/{receipt}/pdf',    [ReceiptController::class, 'downloadPdf']);
        Route::post('receipts/{receipt}/send',  [ReceiptController::class, 'sendWhatsApp'])
             ->middleware('role:director,accountant');

        // ── MODULE F — Relances ─────────────────────────────────────────
        Route::get('reminders',                       [ReminderController::class, 'index']);
        Route::post('reminders/trigger-manual',       [ReminderController::class, 'triggerManual'])
             ->middleware('role:director,accountant');
        Route::get('reminders/stats',                 [ReminderController::class, 'stats']);
        Route::put('reminders/scenarios',             [ReminderController::class, 'updateScenarios'])
             ->middleware('role:director');

        // Webhook WhatsApp (sans auth, sécurisé par token)
        Route::post('webhooks/whatsapp', [ReminderController::class, 'whatsappWebhook'])
             ->withoutMiddleware(['auth:sanctum', 'tenant']);
        Route::get('webhooks/whatsapp',  [ReminderController::class, 'whatsappVerify'])
             ->withoutMiddleware(['auth:sanctum', 'tenant']);

        // ── MODULE G — Dashboard & Rapports ────────────────────────────
        Route::get('dashboard',           [DashboardController::class, 'index']);
        Route::get('dashboard/overdue',   [DashboardController::class, 'overdueStudents']);
        Route::get('dashboard/monthly',   [DashboardController::class, 'monthlyEvolution']);
        Route::get('dashboard/forecast',  [DashboardController::class, 'forecast']);

        Route::get('reports/financial',        [ReportController::class, 'financial'])
             ->middleware('role:director,accountant');
        Route::get('reports/financial/export', [ReportController::class, 'exportFinancial'])
             ->middleware('role:director,accountant');
        Route::get('reports/students',         [ReportController::class, 'students'])
             ->middleware('role:director,accountant');

        // ── ADMIN système (super_admin uniquement) ──────────────────────
        Route::middleware('role:super_admin')->prefix('admin')->group(function () {
            Route::get('audit-logs', function () {
                return \App\Models\AuditLog::withoutGlobalScopes()
                    ->latest('created_at')->paginate(50);
            });
        });
    });
});
