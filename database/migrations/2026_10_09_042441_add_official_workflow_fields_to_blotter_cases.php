<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blotter_cases', function (Blueprint $table) {
            $table->timestamp('mediation_requested_at')->nullable();
            $table->text('mediation_request_reason')->nullable();
            $table->json('pangkat_members')->nullable();
            $table->timestamp('pangkat_constituted_at')->nullable();
            $table->string('disposition', 100)->nullable();
            $table->text('disposition_reason')->nullable();
            $table->string('referral_agency')->nullable();
            $table->text('further_action_documentation')->nullable();
            $table->foreignId('disposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disposed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('blotter_cases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('disposed_by');
            $table->dropColumn(['mediation_requested_at', 'mediation_request_reason', 'pangkat_members',
                'pangkat_constituted_at', 'disposition', 'disposition_reason', 'referral_agency',
                'further_action_documentation', 'disposed_at']);
        });
    }
};
