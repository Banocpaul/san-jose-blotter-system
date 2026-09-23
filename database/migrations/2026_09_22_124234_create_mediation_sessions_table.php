<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mediation_sessions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('blotter_case_id')
                ->constrained('blotter_cases')
                ->cascadeOnDelete();

            $table->unsignedInteger('hearing_number');

            $table->date('scheduled_date');
            $table->time('scheduled_time')->nullable();

            $table->string('venue')
                ->default('Barangay Hall');

            $table->foreignId('lupon_member_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('status')
                ->default('Scheduled');

            $table->text('mediation_notes')
                ->nullable();

            $table->timestamp('completed_at')
                ->nullable();

            $table->timestamps();

            $table->unique([
                'blotter_case_id',
                'hearing_number',
            ]);

            $table->index('status');
            $table->index('scheduled_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mediation_sessions');
    }
};