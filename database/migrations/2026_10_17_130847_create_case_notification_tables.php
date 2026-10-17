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
        Schema::create('case_notification_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blotter_case_id')->constrained()->cascadeOnDelete();
            $table->string('party_type', 20);
            $table->unsignedBigInteger('party_id');
            $table->text('email');
            $table->uuid('revision');
            $table->boolean('enabled')->default(true);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('consent_recorded_at');
            $table->timestamps();
            $table->unique(['blotter_case_id', 'party_type', 'party_id'], 'case_party_contact_unique');
        });

        Schema::create('case_notification_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('case_notification_contact_id')->constrained(indexName: 'case_notice_contact_fk')->cascadeOnDelete();
            $table->uuid('contact_revision');
            $table->text('recipient_email');
            $table->string('event', 40);
            $table->text('message');
            $table->string('status', 30)->default('queued')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('case_notification_deliveries');
        Schema::dropIfExists('case_notification_contacts');
    }
};
