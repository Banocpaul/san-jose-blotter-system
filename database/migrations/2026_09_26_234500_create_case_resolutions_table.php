<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_resolutions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('blotter_case_id')
                ->unique()
                ->constrained('blotter_cases')
                ->cascadeOnDelete();

            $table->foreignId('mediation_outcome_id')
                ->nullable()
                ->unique()
                ->constrained('mediation_outcomes')
                ->nullOnDelete();

            $table->string('resolution_type', 100)
                ->default('Amicable Settlement');

            $table->string('status', 30)
                ->default('Pending');

            $table->text('agreement_details')->nullable();
            $table->text('remarks')->nullable();

            $table->foreignId('finalized_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('finalized_at')->nullable();

            $table->foreignId('resolved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index(
                ['status', 'updated_at'],
                'idx_case_resolutions_status_updated'
            );
        });

        // Preserve already-settled cases created before this module existed.
        $settled = DB::table('mediation_outcomes as outcomes')
            ->join(
                'mediation_sessions as sessions',
                'sessions.id',
                '=',
                'outcomes.mediation_session_id'
            )
            ->where('outcomes.outcome', 'Settled')
            ->select([
                'outcomes.id as outcome_id',
                'outcomes.agreement_details',
                'outcomes.remarks',
                'outcomes.created_at',
                'outcomes.updated_at',
                'sessions.blotter_case_id',
                'sessions.proceeding_type',
            ])
            ->orderBy('outcomes.id')
            ->get();

        foreach ($settled as $record) {
            DB::table('case_resolutions')->updateOrInsert(
                ['blotter_case_id' => $record->blotter_case_id],
                [
                    'mediation_outcome_id' => $record->outcome_id,
                    'resolution_type' => $record->proceeding_type === 'Pangkat Conciliation'
                        ? 'Pangkat Settlement'
                        : 'Amicable Settlement',
                    'status' => 'Pending',
                    'agreement_details' => $record->agreement_details,
                    'remarks' => $record->remarks,
                    'created_at' => $record->created_at ?? now(),
                    'updated_at' => $record->updated_at ?? now(),
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('case_resolutions');
    }
};
