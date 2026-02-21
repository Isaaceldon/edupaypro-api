<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('installment_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('recorded_by')->constrained('users'); // Comptable qui a enregistré

            $table->decimal('amount', 12, 2);             // Montant réellement payé
            $table->decimal('penalty_amount', 12, 2)->default(0); // Pénalité incluse
            $table->char('currency', 3)->default('XOF');

            $table->enum('mode', [
                'cash',          // Espèces
                'mtn_momo',      // MTN Mobile Money
                'moov_money',    // Moov Money
                'wire',          // Virement bancaire
                'cheque',        // Chèque
            ])->default('cash');

            $table->string('reference')->nullable();       // Référence externe (MoMo ref, etc.)
            $table->string('mobile_money_ref')->nullable(); // Référence opérateur Mobile Money

            $table->enum('status', [
                'pending',       // En attente de confirmation (Mobile Money)
                'confirmed',     // Paiement confirmé
                'cancelled',     // Annulé
                'refunded',      // Remboursé
            ])->default('confirmed');

            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignUuid('cancelled_by')->nullable()->constrained('users');

            $table->timestamp('paid_at');
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['student_id', 'status']);
            $table->index('installment_id');
            $table->index('paid_at');
            $table->index('mode');
        });

        Schema::create('receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_id')->constrained()->cascadeOnDelete();

            $table->string('number')->unique();            // ex: "REC-2025-000042"
            $table->string('qr_code');                    // Token de vérification
            $table->string('pdf_path')->nullable();       // Chemin storage local
            $table->string('pdf_url')->nullable();        // URL publique temporaire

            $table->boolean('sent_whatsapp')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->string('sent_to_phone')->nullable();

            $table->timestamps();

            $table->index('payment_id');
            $table->index('number');
            $table->index('qr_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('payments');
    }
};
