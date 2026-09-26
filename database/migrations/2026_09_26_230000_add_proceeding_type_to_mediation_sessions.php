<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mediation_sessions', function (Blueprint $table) {
            $table->string('proceeding_type', 40)
                ->default('Mediation')
                ->after('hearing_number');

            $table->index(
                ['proceeding_type', 'status', 'scheduled_date'],
                'idx_mediation_sessions_type_status_date'
            );
        });
    }

    public function down(): void
    {
        Schema::table('mediation_sessions', function (Blueprint $table) {
            $table->dropIndex(
                'idx_mediation_sessions_type_status_date'
            );

            $table->dropColumn('proceeding_type');
        });
    }
};
