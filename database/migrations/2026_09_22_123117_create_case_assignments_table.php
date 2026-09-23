<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('blotter_case_id')
                ->constrained('blotter_cases')
                ->cascadeOnDelete();

            $table->foreignId('assigned_to')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('assigned_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->text('assignment_notes')->nullable();

            $table->timestamp('assigned_at');
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index([
                'blotter_case_id',
                'completed_at'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_assignments');
    }
};