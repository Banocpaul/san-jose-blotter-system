<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blotter_cases', function (Blueprint $table) {
            $table->id();

            // Official case reference
            $table->string('reference_number')->unique();

            // Incident classification
            $table->foreignId('incident_type_id')
                ->constrained('incident_types')
                ->restrictOnDelete();

            // Incident information
            $table->date('incident_date');
            $table->time('incident_time')->nullable();
            $table->string('location');
            $table->text('narrative');

            // Optional additional information
            $table->text('initial_action')->nullable();
            $table->text('remarks')->nullable();

            // Case workflow
            $table->string('status')->default('Pending');

            // Record creator
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Important timestamps
            $table->timestamp('reported_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('incident_date');
            $table->index(['status', 'incident_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blotter_cases');
    }
};