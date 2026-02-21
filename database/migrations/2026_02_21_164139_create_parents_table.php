<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained()->cascadeOnDelete();

            $table->string('firstname');
            $table->string('lastname');
            $table->enum('relationship', ['father', 'mother', 'guardian', 'other'])
                  ->default('father');
            $table->string('phone_whatsapp');      // Canal principal
            $table->string('phone_alt')->nullable(); // Téléphone alternatif
            $table->string('email')->nullable();
            $table->string('profession')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_primary')->default(true); // Contact principal
            $table->boolean('whatsapp_opted_in')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index('student_id');
            $table->index('phone_whatsapp');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parents');
    }
};
