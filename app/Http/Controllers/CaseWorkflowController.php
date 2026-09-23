<?php

namespace App\Http\Controllers;

use App\Enums\CaseStatus;
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
    |
    | Only Barangay Captain and Secretary are authorized
    | to assign or reassign blotter cases.
    |
    */

    public function assign(
        Request $request,
        BlotterCase $blotter
    ) {
        /*
         * Record-level authorization.
         *
         * Policy:
         * Captain   -> allowed
         * Secretary -> allowed
         * Others    -> denied
         */
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
        |
        | exists:users,id is not enough.
        |
        | The selected user must:
        | - exist
        | - be active
        | - have the Councilor role
        |
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
        | Prevent Assignment Of Closed / Mediation Cases
        |--------------------------------------------------------------------------
        */

        $status = $blotter->status instanceof CaseStatus
            ? $blotter->status
            : CaseStatus::from(
                $blotter->status
            );

        if (
            in_array(
                $status,
                [
                    CaseStatus::ForMediation,
                    CaseStatus::Settled,
                    CaseStatus::Resolved,
                    CaseStatus::Referred,
                    CaseStatus::Dismissed,
                ],
                true
            )
        ) {
            return back()->withErrors([
                'assignment' =>
                    'This case can no longer be assigned to a Councilor in its current status.',
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
                 * Lock the case so two users cannot
                 * assign it at exactly the same time.
                 */
                $lockedCase = BlotterCase::whereKey(
                    $blotter->id
                )
                    ->lockForUpdate()
                    ->firstOrFail();

                /*
                 * Find the current active assignment before
                 * closing it so we can distinguish a new
                 * assignment from a reassignment.
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
                 * Create the new active assignment.
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
                 * Once assigned to a Councilor,
                 * the case enters investigation.
                 */
                $lockedCase->update([
                    'status' =>
                        CaseStatus::UnderInvestigation,

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

                $action = $isReassignment
                    ? 'reassigned'
                    : 'assigned';

                $description = $isReassignment
                    ? "Case {$lockedCase->reference_number} was reassigned to {$councilor->name}."
                    : "Case {$lockedCase->reference_number} was assigned to {$councilor->name}.";

                AuditLogService::log(
                    action: $action,
                    module: 'Blotter Cases',
                    description: $description,
                    auditable: $lockedCase,
                    oldValues: $isReassignment
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

                        'case_status' =>
                            CaseStatus::UnderInvestigation
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
    |
    | Captain and Secretary may add notes to cases.
    |
    | Councilors may only add investigation notes to
    | cases that are CURRENTLY assigned to them.
    |
    */

    public function addInvestigationNote(
        Request $request,
        BlotterCase $blotter
    ) {
        /*
         * Critical record-level authorization.
         *
         * A Councilor cannot bypass the UI by changing
         * the blotter ID in the request.
         */
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
        | Prevent Notes On Closed Cases
        |--------------------------------------------------------------------------
        */

        $status = $blotter->status instanceof CaseStatus
            ? $blotter->status
            : CaseStatus::from(
                $blotter->status
            );

        if (
            in_array(
                $status,
                [
                    CaseStatus::Settled,
                    CaseStatus::Resolved,
                    CaseStatus::Referred,
                    CaseStatus::Dismissed,
                ],
                true
            )
        ) {
            return back()->withErrors([
                'investigation' =>
                    'Investigation notes cannot be added to a closed case.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Create Investigation Note + Audit Trail
        |--------------------------------------------------------------------------
        */

        DB::transaction(
            function () use (
                $blotter,
                $data
            ) {
                $investigationNote =
                    InvestigationNote::create([
                        'blotter_case_id' =>
                            $blotter->id,

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

                AuditLogService::log(
                    action:
                        'investigation_note_added',

                    module:
                        'Blotter Cases',

                    description:
                        "Investigation note was added to case {$blotter->reference_number}.",

                    auditable:
                        $investigationNote,

                    newValues: [
                        'blotter_case_id' =>
                            $blotter->id,

                        'reference_number' =>
                            $blotter->reference_number,

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
