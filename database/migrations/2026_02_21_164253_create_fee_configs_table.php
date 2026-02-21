<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignUuid('academic_year_id')->constrained()->cascadeOnDelete();

            $table->string('label');                      // ex: "Frais de scolarité"
            $table->enum('type', [
                'tuition',       // Frais de scolarité
                'registration',  // Frais d'inscription
                'canteen',       // Cantine
                'transport',     // Transport
                'uniform',       // Uniforme
                'exam',          // Examens
                'other'          // Autre
            ])->default('tuition');

            $table->decimal('total_amount', 12, 2);
            $table->char('currency', 3)->default('XOF');  // FCFA
            $table->boolean('is_mandatory')->default(true);
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['class_id', 'academic_year_id']);
        });

        Schema::create('installments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('fee_config_id')->constrained()->cascadeOnDelete();

            $table->integer('order');                      // Numéro de tranche (1, 2, 3...)
            $table->string('label');                      // ex: "1ère tranche — Septembre"
            $table->decimal('amount', 12, 2);
            $table->date('due_date');
            $table->decimal('penalty_rate', 5, 2)->default(0); // % par mois de retard
            $table->decimal('penalty_fixed', 12, 2)->default(0); // Montant fixe alternatif

            $table->timestamps();

            $table->index('fee_config_id');
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installments');
        Schema::dropIfExists('fee_configs');
    }
};
