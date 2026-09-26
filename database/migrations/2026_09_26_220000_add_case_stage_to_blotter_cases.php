<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blotter_cases', function (Blueprint $table) {
            $table->string('case_stage', 80)
                ->default('New');

            $table->index(
                ['case_stage', 'updated_at'],
                'idx_blotter_cases_stage_updated'
            );
        });

        // Backfill existing records so the new Case Management module is
        // immediately consistent with the workflow status already stored.
        DB::table('blotter_cases')
            ->where('status', 'Pending')
            ->update(['case_stage' => 'New']);

        DB::table('blotter_cases')
            ->where('status', 'Under Investigation')
            ->update(['case_stage' => 'Under Assessment']);

        DB::table('blotter_cases')
            ->where('status', 'For Mediation')
            ->update(['case_stage' => 'For Mediation']);

        DB::table('blotter_cases')
            ->whereIn('status', ['Settled', 'Resolved'])
            ->update(['case_stage' => 'Settled/Resolved']);

        DB::table('blotter_cases')
            ->where('status', 'Referred')
            ->update(['case_stage' => 'For Further Action/CFA']);

        DB::table('blotter_cases')
            ->where('status', 'Dismissed')
            ->update(['case_stage' => 'Closed']);
    }

    public function down(): void
    {
        Schema::table('blotter_cases', function (Blueprint $table) {
            $table->dropIndex('idx_blotter_cases_stage_updated');
            $table->dropColumn('case_stage');
        });
    }
};
