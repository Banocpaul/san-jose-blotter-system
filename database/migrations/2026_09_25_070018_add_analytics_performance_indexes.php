<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blotter_cases', function (Blueprint $table) {
            $table->index(
                'reported_at',
                'idx_blotter_cases_reported_at'
            );
        });

        Schema::table('case_complainants', function (Blueprint $table) {
            $table->index(
                ['sitio', 'blotter_case_id'],
                'idx_case_complainants_sitio_case'
            );
        });

        Schema::table('case_respondents', function (Blueprint $table) {
            $table->index(
                ['sitio', 'blotter_case_id'],
                'idx_case_respondents_sitio_case'
            );
        });

        Schema::table('case_assignments', function (Blueprint $table) {
            $table->index(
                [
                    'assigned_to',
                    'completed_at',
                    'blotter_case_id',
                ],
                'idx_case_assignments_assignee_active_case'
            );
        });

        Schema::table('mediation_sessions', function (Blueprint $table) {
            $table->index(
                [
                    'lupon_member_id',
                    'status',
                    'scheduled_date',
                ],
                'idx_mediation_sessions_lupon_status_date'
            );

            $table->index(
                [
                    'status',
                    'scheduled_date',
                ],
                'idx_mediation_sessions_status_date'
            );
        });

        Schema::table('mediation_outcomes', function (Blueprint $table) {
            $table->index(
                [
                    'outcome',
                    'mediation_session_id',
                ],
                'idx_mediation_outcomes_outcome_session'
            );
        });
    }

    public function down(): void
    {
        Schema::table('mediation_outcomes', function (Blueprint $table) {
            $table->dropIndex(
                'idx_mediation_outcomes_outcome_session'
            );
        });

        Schema::table('mediation_sessions', function (Blueprint $table) {
            $table->dropIndex(
                'idx_mediation_sessions_lupon_status_date'
            );

            $table->dropIndex(
                'idx_mediation_sessions_status_date'
            );
        });

        Schema::table('case_assignments', function (Blueprint $table) {
            $table->dropIndex(
                'idx_case_assignments_assignee_active_case'
            );
        });

        Schema::table('case_respondents', function (Blueprint $table) {
            $table->dropIndex(
                'idx_case_respondents_sitio_case'
            );
        });

        Schema::table('case_complainants', function (Blueprint $table) {
            $table->dropIndex(
                'idx_case_complainants_sitio_case'
            );
        });

        Schema::table('blotter_cases', function (Blueprint $table) {
            $table->dropIndex(
                'idx_blotter_cases_reported_at'
            );
        });
    }
};