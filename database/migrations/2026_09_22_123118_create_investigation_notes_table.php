<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investigation_notes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('blotter_case_id')
                ->constrained('blotter_cases')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->text('note');

            $table->text('action_taken')->nullable();

            $table->timestamp('noted_at');

            $table->timestamps();

            $table->index([
                'blotter_case_id',
                'noted_at'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investigation_notes');
    }
};