<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class CaseReferenceService
{
    public function generate(): string
    {
        $year = (int) now()->format('Y');

        return DB::transaction(function () use ($year) {

            // Ensure a sequence exists for the current year.
            DB::statement(
                'INSERT INTO case_sequences (`year`, `current_number`, `created_at`, `updated_at`)
                 VALUES (?, 0, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE `year` = `year`',
                [$year]
            );

            // Lock the row so two requests cannot receive the same number.
            $sequence = DB::table('case_sequences')
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            $nextNumber = $sequence->current_number + 1;

            DB::table('case_sequences')
                ->where('year', $year)
                ->update([
                    'current_number' => $nextNumber,
                    'updated_at' => now(),
                ]);

            return sprintf(
                'BSJ-%d-%06d',
                $year,
                $nextNumber
            );
        });
    }
}