<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Relances automatiques ───────────────────────────────────────────
        Schema::create('reminders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('installment_id')->constrained()->cascadeOnDelete();

            $table->enum('channel', ['whatsapp', 'sms', 'email'])->default('whatsapp');
            $table->string('scenario');    // 'J-7', 'J-1', 'J+0', 'J+3', 'J+7'
            $table->text('message_sent'); // Message envoyé (archivé)
            $table->string('sent_to');    // Numéro ou email destinataire

            $table->enum('status', ['pending', 'sent', 'delivered', 'read', 'failed'])
                  ->default('pending');

            $table->string('provider_message_id')->nullable(); // ID retourné par WhatsApp/SMS
            $table->string('failure_reason')->nullable();

            $table->timestamp('scheduled_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            $table->index(['student_id', 'scenario']);
            $table->index(['status', 'scheduled_at']);
        });

        // ── Scores de risque IA ─────────────────────────────────────────────
        Schema::create('risk_scores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();

            $table->unsignedTinyInteger('score');         // 0-100
            $table->enum('segment', ['safe', 'risk', 'bad'])->default('safe');
            $table->json('factors');                       // Facteurs explicatifs
            $table->timestamp('computed_at');

            $table->timestamps();

            $table->index(['student_id', 'computed_at']);
            $table->index('segment');
        });

        // ── Demandes de financement ─────────────────────────────────────────
        Schema::create('financing_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $table->uuid('partner_id')->nullable();  // Partenaire financier (externe)

            $table->decimal('amount', 12, 2);
            $table->unsignedTinyInteger('duration_months');
            $table->decimal('monthly_payment', 12, 2)->nullable();
            $table->decimal('interest_rate', 5, 2)->default(0);

            $table->enum('status', [
                'pending',     // Soumis, en attente d'examen
                'reviewing',   // En cours d'analyse
                'approved',    // Approuvé
                'rejected',    // Rejeté
                'active',      // En cours de remboursement
                'completed',   // Remboursé intégralement
                'defaulted',   // En défaut
            ])->default('pending');

            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['student_id', 'status']);
        });

        // ── Journal d'audit immuable ────────────────────────────────────────
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('action');                     // ex: 'payment.created'
            $table->string('model_type')->nullable();     // ex: 'App\Models\Payment'
            $table->uuid('model_id')->nullable();         // ID de l'entité concernée
            $table->json('old_values')->nullable();       // État avant
            $table->json('new_values')->nullable();       // État après
            $table->json('metadata')->nullable();         // Infos contextuelles
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('created_at');              // Pas de updated_at — immuable

            $table->index(['tenant_id', 'action']);
            $table->index(['model_type', 'model_id']);
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('financing_requests');
        Schema::dropIfExists('risk_scores');
        Schema::dropIfExists('reminders');
    }
};
