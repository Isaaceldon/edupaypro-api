<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('logo_url')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->default('BJ');   // ISO 3166-1 alpha-2
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('rccm')->nullable();          // Registre Commerce
            $table->string('ifu')->nullable();           // Identifiant Fiscal Unique
            $table->string('director_name')->nullable();
            $table->string('signature_url')->nullable(); // Signature scannée pour reçus
            $table->string('stamp_url')->nullable();     // Cachet pour reçus
            $table->string('receipt_prefix')->default('REC'); // Préfixe numéro de reçu

            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
