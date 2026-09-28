<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Add Record Status
        |--------------------------------------------------------------------------
        |
        | record_status represents the overall state of the blotter record:
        |
        | Open
        | Resolved
        | Closed
        |
        | case_stage continues to represent the detailed Katarungang
        | Pambarangay workflow stage.
        |
        | The existing status column is intentionally preserved temporarily
        | so existing modules continue working during the transition.
        |
        */

        Schema::table('blotter_cases', function (Blueprint $table) {
            $table->string('record_status', 30)
                ->default('Open')
                ->after('case_stage');

            $table->index(
                ['record_status', 'updated_at'],
                'idx_blotter_cases_record_status_updated'
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Backfill Existing Records
        |--------------------------------------------------------------------------
        |
        | Legacy Status              -> Record Status
        |
        | Pending                    -> Open
        | Under Investigation        -> Open
        | For Mediation              -> Open
        | Settled                    -> Resolved
        | Resolved                   -> Resolved
        | Referred                   -> Closed
        | Dismissed                  -> Closed
        |
        */

        DB::table('blotter_cases')->update([
            'record_status' => DB::raw("
                CASE
                    WHEN status IN (
                        'Settled',
                        'Resolved'
                    ) THEN 'Resolved'

                    WHEN status IN (
                        'Referred',
                        'Dismissed'
                    ) THEN 'Closed'

                    ELSE 'Open'
                END
            "),
        ]);
    }

    public function down(): void
    {
        Schema::table('blotter_cases', function (Blueprint $table) {
            $table->dropIndex(
                'idx_blotter_cases_record_status_updated'
            );

            $table->dropColumn(
                'record_status'
            );
        });
    }
};