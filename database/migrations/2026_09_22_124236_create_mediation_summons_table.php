<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mediation_summons', function (Blueprint $table) {
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
             * Other
             */
            $table->string('recipient_type');

            $table->string('recipient_name');

            /*
             * Not Prepared
             * Prepared
             * Served
             * Received
             * Failed Delivery
             */
            $table->string('status')
                ->default('Not Prepared');

            $table->timestamp('prepared_at')->nullable();
            $table->timestamp('served_at')->nullable();
            $table->timestamp('received_at')->nullable();

            $table->text('delivery_notes')->nullable();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mediation_summons');
    }
};