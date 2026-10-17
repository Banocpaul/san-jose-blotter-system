<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('case_jurisdiction_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blotter_case_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('decision', 30);
            $table->text('authority_basis');
            $table->text('venue_basis');
            $table->string('referral_agency')->nullable();
            $table->string('facts_fingerprint', 64);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('case_jurisdiction_assessments');
    }
};
