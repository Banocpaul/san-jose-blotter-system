<?php

namespace App\Http\Controllers;

use App\Enums\CaseStage;
use App\Enums\CaseStatus;
use App\Enums\RecordStatus;
use App\Models\BlotterCase;
use App\Models\CaseAssignment;
use App\Models\InvestigationNote;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CaseWorkflowController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Assign Case To Councilor
    |--------------------------------------------------------------------------
    */

    public function assign(
        Request $request,
        BlotterCase $blotter
    ) {
        $this->authorize(
            'assign',
            $blotter
        );

        /*
        |--------------------------------------------------------------------------
        | Validate Request
        |--------------------------------------------------------------------------
        */

        $data = $request->validate([
            'assigned_to' => [
                'required',
                'integer',
                'exists:users,id',
            ],

            'assignment_notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Validate Councilor
        |--------------------------------------------------------------------------
        */

        $councilor = User::with('role')
            ->findOrFail(
                $data['assigned_to']
            );

        if (
            ! $councilor->is_active
            ||
            $councilor->role?->slug !== 'councilor'
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'assigned_to' =>
                        'The selected user must be an active Barangay Councilor.',
                ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Prevent Assignment Of Finished / Mediation Cases
        |--------------------------------------------------------------------------
        |
        | A case can only be assigned while the overall record is Open.
        |
        | It must also still be in either:
        |
        | New
        | Under Assessment
        |
        */

        if (
            $blotter->record_status !== RecordStatus::Open
            ||
            in_array(
                $blotter->case_stage,
                [
                    CaseStage::ForMediation,
                    CaseStage::ForPangkatConciliation,
                    CaseStage::ForFurtherActionCfa,
                    CaseStage::SettledResolved,
                    CaseStage::Closed,
                ],
                true
            )
        ) {
            return back()->withErrors([
                'assignment' =>
                    'This case can no longer be assigned to a Councilor in its current stage.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Assign / Reassign Transaction
        |--------------------------------------------------------------------------
        */

        DB::transaction(
            function () use (
                $blotter,
                $councilor,
                $data
            ) {
                /*
                 * Lock case against simultaneous workflow updates.
                 */

                $lockedCase = BlotterCase::whereKey(
                    $blotter->id
                )
                    ->lockForUpdate()
                    ->firstOrFail();

                /*
                 * Check the status again after obtaining the database lock.
                 */

                if (
                    $lockedCase->record_status
                    !== RecordStatus::Open
                    ||
                    in_array(
                        $lockedCase->case_stage,
                        [
                            CaseStage::ForMediation,
                            CaseStage::ForPangkatConciliation,
                            CaseStage::ForFurtherActionCfa,
                            CaseStage::SettledResolved,
                            CaseStage::Closed,
                        ],
                        true
                    )
                ) {
                    abort(
                        409,
                        'The case workflow changed while the assignment was being processed.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Existing Active Assignment
                |--------------------------------------------------------------------------
                */

                $previousAssignment =
                    CaseAssignment::where(
                        'blotter_case_id',
                        $lockedCase->id
                    )
                        ->whereNull(
                            'completed_at'
                        )
                        ->lockForUpdate()
                        ->first();

                $previousCouncilor = null;

                if ($previousAssignment) {
                    $previousCouncilor =
                        User::find(
                            $previousAssignment->assigned_to
                        );

                    $previousAssignment->update([
                        'completed_at' =>
                            now(),
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Create Assignment
                |--------------------------------------------------------------------------
                */

                $newAssignment =
                    CaseAssignment::create([
                        'blotter_case_id' =>
                            $lockedCase->id,

                        'assigned_to' =>
                            $councilor->id,

                        'assigned_by' =>
                            auth()->id(),

                        'assignment_notes' =>
                            $data[
                                'assignment_notes'
                            ] ?? null,

                        'assigned_at' =>
                            now(),

                        'completed_at' =>
                            null,
                    ]);

                /*
                |--------------------------------------------------------------------------
                | Move Case To Under Assessment
                |--------------------------------------------------------------------------
                |
                | Legacy status is retained temporarily for compatibility.
                |
                */

                $lockedCase->update([
                    'status' =>
                        CaseStatus::UnderInvestigation,

                    'case_stage' =>
                        CaseStage::UnderAssessment,

                    'record_status' =>
                        RecordStatus::Open,

                    'closed_at' =>
                        null,
                ]);

                /*
                |--------------------------------------------------------------------------
                | Audit Trail
                |--------------------------------------------------------------------------
                */

                $isReassignment =
                    $previousAssignment !== null;

                $action =
                    $isReassignment
                        ? 'reassigned'
                        : 'assigned';

                $description =
                    $isReassignment
                        ? "Case {$lockedCase->reference_number} was reassigned to {$councilor->name}."
                        : "Case {$lockedCase->reference_number} was assigned to {$councilor->name}.";

                AuditLogService::log(
                    action:
                        $action,

                    module:
                        'Blotter Cases',

                    description:
                        $description,

                    auditable:
                        $lockedCase,

                    oldValues:
                        $isReassignment
                            ? [
                                'assignment_id' =>
                                    $previousAssignment->id,

                                'assigned_to' =>
                                    $previousAssignment->assigned_to,

                                'assigned_to_name' =>
                                    $previousCouncilor?->name,

                                'completed_at' =>
                                    $previousAssignment
                                        ->completed_at
                                        ?->format(
                                            'Y-m-d H:i:s'
                                        ),
                            ]
                            : [],

                    newValues: [
                        'assignment_id' =>
                            $newAssignment->id,

                        'assigned_to' =>
                            $councilor->id,

                        'assigned_to_name' =>
                            $councilor->name,

                        'assigned_by' =>
                            auth()->id(),

                        'assignment_notes' =>
                            $newAssignment
                                ->assignment_notes,

                        'assigned_at' =>
                            $newAssignment
                                ->assigned_at
                                ?->format(
                                    'Y-m-d H:i:s'
                                ),

                        /*
                         * Temporary legacy status.
                         */
                        'case_status' =>
                            CaseStatus::UnderInvestigation
                                ->value,

                        /*
                         * Client workflow.
                         */
                        'case_stage' =>
                            CaseStage::UnderAssessment
                                ->value,

                        'record_status' =>
                            RecordStatus::Open
                                ->value,
                    ]
                );
            }
        );

        return back()->with(
            'success',
            'Case assigned successfully to '
            . $councilor->name
            . '.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Add Investigation Note
    |--------------------------------------------------------------------------
    */

    public function addInvestigationNote(
        Request $request,
        BlotterCase $blotter
    ) {
        $this->authorize(
            'investigate',
            $blotter
        );

        /*
        |--------------------------------------------------------------------------
        | Validate Investigation Note
        |--------------------------------------------------------------------------
        */

        $data = $request->validate([
            'note' => [
                'required',
                'string',
                'max:10000',
            ],

            'action_taken' => [
                'nullable',
                'string',
                'max:10000',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Prevent Notes On Finished Records
        |--------------------------------------------------------------------------
        |
        | Resolved and Closed cases must no longer accept investigation notes.
        |
        */

        if (
            $blotter->record_status
            !== RecordStatus::Open
        ) {
            return back()->withErrors([
                'investigation' =>
                    'Investigation notes cannot be added to a resolved or closed case.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Create Investigation Note
        |--------------------------------------------------------------------------
        */

        DB::transaction(
            function () use (
                $blotter,
                $data
            ) {
                /*
                 * Lock the parent case to prevent a note from being added
                 * while another request closes or resolves the case.
                 */

                $lockedCase =
                    BlotterCase::whereKey(
                        $blotter->id
                    )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    $lockedCase->record_status
                    !== RecordStatus::Open
                ) {
                    abort(
                        409,
                        'This case is no longer open.'
                    );
                }

                $investigationNote =
                    InvestigationNote::create([
                        'blotter_case_id' =>
                            $lockedCase->id,

                        'user_id' =>
                            auth()->id(),

                        'note' =>
                            $data['note'],

                        'action_taken' =>
                            $data[
                                'action_taken'
                            ] ?? null,

                        'noted_at' =>
                            now(),
                    ]);

                /*
                |--------------------------------------------------------------------------
                | Audit Trail
                |--------------------------------------------------------------------------
                */

                AuditLogService::log(
                    action:
                        'investigation_note_added',

                    module:
                        'Blotter Cases',

                    description:
                        "Investigation note was added to case {$lockedCase->reference_number}.",

                    auditable:
                        $investigationNote,

                    newValues: [
                        'blotter_case_id' =>
                            $lockedCase->id,

                        'reference_number' =>
                            $lockedCase
                                ->reference_number,

                        'investigation_note_id' =>
                            $investigationNote->id,

                        'note' =>
                            $investigationNote->note,

                        'action_taken' =>
                            $investigationNote
                                ->action_taken,

                        'user_id' =>
                            $investigationNote
                                ->user_id,

                        'case_stage' =>
                            $lockedCase
                                ->case_stage
                                ?->value,

                        'record_status' =>
                            $lockedCase
                                ->record_status
                                ?->value,

                        'noted_at' =>
                            $investigationNote
                                ->noted_at
                                ?->format(
                                    'Y-m-d H:i:s'
                                ),
                    ]
                );
            }
        );

        return back()->with(
            'success',
            'Investigation note added successfully.'
        );
    }
}