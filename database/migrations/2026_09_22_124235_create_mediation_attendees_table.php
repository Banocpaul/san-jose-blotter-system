<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mediation_attendees', function (Blueprint $table) {
            $table->id();

            $table->foreignId('mediation_session_id')
                ->constrained('mediation_sessions')
                ->cascadeOnDelete();

            $table->foreignId('resident_id')
                ->nullable()
                ->constrained('residents')
                ->nullOnDelete();

            /*
             * Complainant
             * Respondent
             * Witness
             * Lupon
             * Other
             */
            $table->string('participant_type');

            /*
             * Keep a snapshot of the name even if linked
             * to an existing resident.
             */
            $table->string('name');

            $table->boolean('is_present')
                ->default(false);

            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->index([
                'mediation_session_id',
                'participant_type',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mediation_attendees');
    }
};