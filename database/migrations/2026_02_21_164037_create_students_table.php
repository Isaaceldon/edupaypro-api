<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->uuid('sibling_group_id')->nullable(); // Pour gérer les fratries

            $table->string('matricule')->unique();
            $table->string('firstname');
            $table->string('lastname');
            $table->date('birth_date')->nullable();
            $table->string('birth_place')->nullable();
            $table->enum('gender', ['M', 'F'])->nullable();
            $table->string('photo_url')->nullable();
            $table->string('nationality')->default('BJ');

            $table->enum('status', ['active', 'inactive', 'graduated', 'transferred'])
                  ->default('active');
            $table->date('enrollment_date')->nullable();
            $table->date('left_date')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'class_id']);
            $table->index(['school_id', 'status']);
            $table->index('matricule');
            $table->index('sibling_group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
