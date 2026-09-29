<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Normalize Existing Settlement Statuses
        |--------------------------------------------------------------------------
        |
        | Old document workflow:
        | Pending -> Finalized -> Resolved
        |
        | New settlement workflow:
        | Active -> Completed
        |
        */

        DB::table('case_resolutions')
            ->whereIn(
                'status',
                [
                    'Pending',
                    'Finalized',
                ]
            )
            ->update([
                'status' => 'Active',
            ]);

        DB::table('case_resolutions')
            ->where(
                'status',
                'Resolved'
            )
            ->update([
                'status' => 'Completed',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Normalize Resolution Type
        |--------------------------------------------------------------------------
        */

        DB::table('case_resolutions')
            ->where(function ($query) {
                $query
                    ->whereNull('resolution_type')
                    ->orWhere(
                        'resolution_type',
                        '<>',
                        'Amicable Settlement'
                    );
            })
            ->update([
                'resolution_type' =>
                    'Amicable Settlement',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Default For Future Rows
        |--------------------------------------------------------------------------
        */

        DB::statement(
            "ALTER TABLE case_resolutions
             MODIFY COLUMN status VARCHAR(30)
             NOT NULL DEFAULT 'Active'"
        );
    }

    public function down(): void
    {
        /*
         * A rollback cannot reconstruct whether an Active record was formerly
         * Pending or Finalized, so Active is restored to Pending.
         */

        DB::table('case_resolutions')
            ->where(
                'status',
                'Active'
            )
            ->update([
                'status' => 'Pending',
            ]);

        DB::table('case_resolutions')
            ->where(
                'status',
                'Completed'
            )
            ->update([
                'status' => 'Resolved',
            ]);

        DB::statement(
            "ALTER TABLE case_resolutions
             MODIFY COLUMN status VARCHAR(30)
             NOT NULL DEFAULT 'Pending'"
        );
    }
};
