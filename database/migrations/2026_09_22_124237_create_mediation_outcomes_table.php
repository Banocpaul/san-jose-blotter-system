<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mediation_outcomes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('mediation_session_id')
                ->unique()
                ->constrained('mediation_sessions')
                ->cascadeOnDelete();

            /*
             * Settled
             * Referred
             * Rescheduled
             * No Agreement
             * Dismissed
             */
            $table->string('outcome');

            $table->text('agreement_details')->nullable();

            $table->string('referral_agency')->nullable();

            $table->text('remarks')->nullable();

            $table->foreignId('recorded_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('recorded_at');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mediation_outcomes');
    }
};