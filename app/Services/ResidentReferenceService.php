<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ResidentReferenceService
{
    public function generate(): string
    {
        $year = (int) now()->format('Y');

        return DB::transaction(function () use ($year) {

            DB::statement(
                'INSERT INTO resident_sequences
                (`year`, `current_number`, `created_at`, `updated_at`)
                VALUES (?, 0, NOW(), NOW())
                ON DUPLICATE KEY UPDATE `year` = `year`',
                [$year]
            );

            $sequence = DB::table('resident_sequences')
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            $nextNumber = $sequence->current_number + 1;

            DB::table('resident_sequences')
                ->where('year', $year)
                ->update([
                    'current_number' => $nextNumber,
                    'updated_at' => now(),
                ]);

            return sprintf(
                'BSJ-RES-%d-%06d',
                $year,
                $nextNumber
            );
        });
    }
}