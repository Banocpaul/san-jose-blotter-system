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
                ['incident_type_id', 'incident_date'],
                'idx_blotter_cases_incident_type_date'
            );

            $table->index(
                ['case_stage', 'incident_date'],
                'idx_blotter_cases_stage_incident_date'
            );

            $table->index(
                ['status', 'updated_at'],
                'idx_blotter_cases_status_updated'
            );
        });

        Schema::table('residents', function (Blueprint $table) {
            $table->index(
                ['classification', 'is_active', 'deleted_at', 'last_name', 'first_name'],
                'idx_people_class_active_name'
            );
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(
                ['user_id', 'created_at'],
                'idx_audit_logs_user_created'
            );

            $table->index(
                ['module', 'created_at'],
                'idx_audit_logs_module_created'
            );

            $table->index(
                ['action', 'created_at'],
                'idx_audit_logs_action_created'
            );
        });

        Schema::table('mediation_sessions', function (Blueprint $table) {
            $table->index(
                ['blotter_case_id', 'status', 'scheduled_date'],
                'idx_mediation_sessions_case_status_date'
            );
        });
    }

    public function down(): void
    {
        Schema::table('mediation_sessions', function (Blueprint $table) {
            $table->dropIndex('idx_mediation_sessions_case_status_date');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('idx_audit_logs_user_created');
            $table->dropIndex('idx_audit_logs_module_created');
            $table->dropIndex('idx_audit_logs_action_created');
        });

        Schema::table('residents', function (Blueprint $table) {
            $table->dropIndex('idx_people_class_active_name');
        });

        Schema::table('blotter_cases', function (Blueprint $table) {
            $table->dropIndex('idx_blotter_cases_incident_type_date');
            $table->dropIndex('idx_blotter_cases_stage_incident_date');
            $table->dropIndex('idx_blotter_cases_status_updated');
        });
    }
};
