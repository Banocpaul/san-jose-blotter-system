<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_respondents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('blotter_case_id')
                ->constrained('blotter_cases')
                ->cascadeOnDelete();

            $table->foreignId('resident_id')
                ->nullable()
                ->constrained('residents')
                ->nullOnDelete();

            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('suffix')->nullable();

            $table->string('contact_number', 30)->nullable();
            $table->text('address')->nullable();

            $table->timestamps();

            $table->index('resident_id');
            $table->index('last_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_respondents');
    }
};