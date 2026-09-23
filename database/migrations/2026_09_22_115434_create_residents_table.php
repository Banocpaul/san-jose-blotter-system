<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('residents', function (Blueprint $table) {
            $table->id();

            // System-generated identifier
            $table->string('resident_code')->unique();

            // Personal information
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('suffix')->nullable();

            $table->string('sex', 30)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('civil_status', 50)->nullable();

            // Contact information
            $table->string('contact_number', 30)->nullable();
            $table->string('email')->nullable();

            // Address
            $table->string('house_number')->nullable();
            $table->string('street')->nullable();
            $table->string('purok')->nullable();
            $table->text('address_details')->nullable();

            // Barangay information
            $table->boolean('is_registered_voter')->nullable();
            $table->string('classification')->nullable();
            $table->string('vulnerable_sector')->nullable();

            // Emergency contact
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_number', 30)->nullable();

            // Optional identification
            $table->string('government_id_type')->nullable();
            $table->string('government_id_number')->nullable();

            // Record status
            $table->boolean('is_active')->default(true);

            // User that encoded the resident
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('last_name');
            $table->index('purok');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('residents');
    }
};