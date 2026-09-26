<?php

namespace App\Http\Controllers;

use App\Enums\CaseStage;
use App\Enums\CaseStatus;
use App\Models\BlotterCase;
use App\Models\CaseAssignment;
use App\Models\CaseResolution;
use App\Models\MediationAttendee;
use App\Models\MediationOutcome;
use App\Models\MediationSession;
use App\Models\MediationSummons;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MediationController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Refer Case To Mediation
    |--------------------------------------------------------------------------
    */

    public function refer(
        BlotterCase $blotter
    ) {
        /*
         * Record-level authorization.
         *
         * Captain / Secretary:
         *     May refer cases.
         *
         * Councilor:
         *     May only refer the case currently assigned to them.
         */
        $this->authorize(
            'referToMediation',
            $blotter
        );

        $status = $blotter->status instanceof CaseStatus
            ? $blotter->status
            : CaseStatus::from(
                $blotter->status
            );

        /*
         * Only active investigation cases
         * may enter mediation.
         */
        if (
            ! in_array(
                $status,
                [
                    CaseStatus::Pending,
                    CaseStatus::UnderInvestigation,
                ],
                true
            )
        ) {
            return back()->withErrors([
                'mediation' =>
                    'Only pending or under-investigation cases may be referred to mediation.',
            ]);
        }

        DB::transaction(
            function () use ($blotter) {

                /*
                 * Lock case during workflow transition.
                 */
                $lockedCase = BlotterCase::whereKey(
                    $blotter->id
                )
                    ->lockForUpdate()
                    ->firstOrFail();

                $previousStatus =
                    $lockedCase->status instanceof CaseStatus
                        ? $lockedCase->status->value
                        : $lockedCase->status;

                $previousStage =
                    $lockedCase->case_stage instanceof CaseStage
                        ? $lockedCase->case_stage->value
                        : $lockedCase->case_stage;

                /*
                 * Close any current Councilor assignment.
                 */
                CaseAssignment::where(
                    'blotter_case_id',
                    $lockedCase->id
                )
                    ->whereNull(
                        'completed_at'
                    )
                    ->update([
                        'completed_at' =>
                            now(),

                        'updated_at' =>
                            now(),
                    ]);

                /*
                 * Move case to mediation.
                 */
                $lockedCase->update([
                    'status' =>
                        CaseStatus::ForMediation,

                    'case_stage' =>
                        CaseStage::ForMediation,

                    'closed_at' =>
                        null,
                ]);

                $lockedCase->refresh();

                /*
                |--------------------------------------------------------------------------
                | Audit Trail
                |--------------------------------------------------------------------------
                */

                AuditLogService::log(
                    action:
                        'referred_to_mediation',

                    module:
                        'Blotter Cases',

                    description:
                        "Case {$lockedCase->reference_number} was referred to mediation.",

                    auditable:
                        $lockedCase,

                    oldValues: [
                        'status' =>
                            $previousStatus,

                        'case_stage' =>
                            $previousStage,
                    ],

                    newValues: [
                        'status' =>
                            CaseStatus::ForMediation->value,

                        'case_stage' =>
                            CaseStage::ForMediation->value,

                        'closed_at' =>
                            null,
                    ]
                );
            }
        );

        return back()->with(
            'success',
            'Case referred to mediation successfully.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Schedule Mediation Hearing
    |--------------------------------------------------------------------------
    |
    | Route-level RBAC should restrict this action
    | to Barangay Captain and Secretary.
    |
    */

    public function schedule(
        Request $request,
        BlotterCase $blotter
    ) {
        /*
         * Defense-in-depth role check.
         */
        $role = auth()->user()?->role?->slug;

        if (
            ! in_array(
                $role,
                [
                    'barangay_captain',
                    'secretary',
                ],
                true
            )
        ) {
            abort(403);
        }

        $data = $request->validate([
            'scheduled_date' => [
                'required',
                'date',
                'after_or_equal:today',
            ],

            'scheduled_time' => [
                'required',
                'date_format:H:i',
            ],

            'venue' => [
                'required',
                'string',
                'max:255',
            ],

            'lupon_member_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],

            'mediation_notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ]);

        $status = $blotter->status instanceof CaseStatus
            ? $blotter->status
            : CaseStatus::from(
                $blotter->status
            );

        /*
         * Case must already be in mediation.
         */
        if (
            $status !==
            CaseStatus::ForMediation
        ) {
            return back()->withErrors([
                'mediation' =>
                    'The case must first be referred to mediation.',
            ]);
        }

        /*
         * Prevent multiple simultaneous
         * scheduled hearings.
         */
        $hasScheduledHearing =
            MediationSession::where(
                'blotter_case_id',
                $blotter->id
            )
                ->where(
                    'status',
                    'Scheduled'
                )
                ->exists();

        if ($hasScheduledHearing) {
            return back()->withErrors([
                'mediation' =>
                    'This case already has an active scheduled hearing.',
            ]);
        }

        /*
         * Validate selected Lupon member.
         */
        $lupon = User::with('role')
            ->findOrFail(
                $data['lupon_member_id']
            );

        if (
            ! $lupon->is_active
            ||
            $lupon->role?->slug !== 'lupon'
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'lupon_member_id' =>
                        'The selected user must be an active Lupon Member.',
                ]);
        }

        $session = DB::transaction(
            function () use (
                $blotter,
                $data,
                $lupon
            ) {
                /*
                 * Lock case before determining
                 * the next hearing number.
                 */
                $lockedCase =
                    BlotterCase::whereKey(
                        $blotter->id
                    )
                        ->lockForUpdate()
                        ->firstOrFail();

                $lastHearingNumber =
                    MediationSession::where(
                        'blotter_case_id',
                        $lockedCase->id
                    )
                        ->max(
                            'hearing_number'
                        )
                    ?? 0;

                $lockedStage =
                    $lockedCase->case_stage instanceof CaseStage
                        ? $lockedCase->case_stage
                        : CaseStage::tryFrom(
                            (string) $lockedCase->case_stage
                        );

                $proceedingType =
                    $lockedStage === CaseStage::ForPangkatConciliation
                        ? 'Pangkat Conciliation'
                        : 'Mediation';

                /*
                 * Create hearing / proceeding.
                 */
                $session =
                    MediationSession::create([
                        'blotter_case_id' =>
                            $lockedCase->id,

                        'hearing_number' =>
                            $lastHearingNumber + 1,

                        'proceeding_type' =>
                            $proceedingType,

                        'scheduled_date' =>
                            $data['scheduled_date'],

                        'scheduled_time' =>
                            $data['scheduled_time'],

                        'venue' =>
                            $data['venue'],

                        'lupon_member_id' =>
                            $data['lupon_member_id'],

                        'created_by' =>
                            auth()->id(),

                        'status' =>
                            'Scheduled',

                        'mediation_notes' =>
                            $data[
                                'mediation_notes'
                            ] ?? null,
                    ]);

                /*
                 * Create attendance and summons
                 * records for complainants.
                 */
                foreach (
                    $lockedCase->complainants
                    as $complainant
                ) {
                    $name = trim(
                        $complainant->first_name
                        . ' '
                        . (
                            $complainant->middle_name
                            ?? ''
                        )
                        . ' '
                        . $complainant->last_name
                        . ' '
                        . (
                            $complainant->suffix
                            ?? ''
                        )
                    );

                    MediationAttendee::create([
                        'mediation_session_id' =>
                            $session->id,

                        'resident_id' =>
                            $complainant->resident_id,

                        'participant_type' =>
                            'Complainant',

                        'name' =>
                            $name,

                        'is_present' =>
                            false,

                        'remarks' =>
                            null,
                    ]);

                    MediationSummons::create([
                        'mediation_session_id' =>
                            $session->id,

                        'resident_id' =>
                            $complainant->resident_id,

                        'recipient_type' =>
                            'Complainant',

                        'recipient_name' =>
                            $name,

                        'status' =>
                            'Not Prepared',

                        'created_by' =>
                            auth()->id(),
                    ]);
                }

                /*
                 * Create attendance and summons
                 * records for respondents.
                 */
                foreach (
                    $lockedCase->respondents
                    as $respondent
                ) {
                    $name = trim(
                        $respondent->first_name
                        . ' '
                        . (
                            $respondent->middle_name
                            ?? ''
                        )
                        . ' '
                        . $respondent->last_name
                        . ' '
                        . (
                            $respondent->suffix
                            ?? ''
                        )
                    );

                    MediationAttendee::create([
                        'mediation_session_id' =>
                            $session->id,

                        'resident_id' =>
                            $respondent->resident_id,

                        'participant_type' =>
                            'Respondent',

                        'name' =>
                            $name,

                        'is_present' =>
                            false,

                        'remarks' =>
                            null,
                    ]);

                    MediationSummons::create([
                        'mediation_session_id' =>
                            $session->id,

                        'resident_id' =>
                            $respondent->resident_id,

                        'recipient_type' =>
                            'Respondent',

                        'recipient_name' =>
                            $name,

                        'status' =>
                            'Not Prepared',

                        'created_by' =>
                            auth()->id(),
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Audit Trail
                |--------------------------------------------------------------------------
                */

                AuditLogService::log(
                    action:
                        'proceeding_scheduled',

                    module:
                        'Lupon & Mediation',

                    description:
                        "{$session->proceeding_type} hearing #{$session->hearing_number} was scheduled for case {$lockedCase->reference_number}.",

                    auditable:
                        $session,

                    newValues: [
                        'blotter_case_id' =>
                            $lockedCase->id,

                        'reference_number' =>
                            $lockedCase->reference_number,

                        'mediation_session_id' =>
                            $session->id,

                        'hearing_number' =>
                            $session->hearing_number,

                        'proceeding_type' =>
                            $session->proceeding_type,

                        'scheduled_date' =>
                            $session->scheduled_date,

                        'scheduled_time' =>
                            $session->scheduled_time,

                        'venue' =>
                            $session->venue,

                        'lupon_member_id' =>
                            $session->lupon_member_id,

                        'lupon_member_name' =>
                            $lupon->name,

                        'status' =>
                            $session->status,

                        'mediation_notes' =>
                            $session->mediation_notes,
                    ]
                );

                return $session;
            }
        );

        return back()->with(
            'success',
            $session->proceeding_type
            . ' hearing #'
            . $session->hearing_number
            . ' scheduled successfully.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update Summons
    |--------------------------------------------------------------------------
    |
    | Captain / Secretary:
    |     May manage any hearing.
    |
    | Lupon:
    |     May manage summons only for hearings
    |     assigned specifically to them.
    |
    */

    public function updateSummons(
        Request $request,
        MediationSummons $summons
    ) {
        /*
         * Load parent hearing, case, and existing outcome.
         */
        $summons->load([
            'session.outcome',
            'session.blotterCase',
        ]);

        /*
         * Record-level hearing authorization.
         */
        $this->authorize(
            'manage',
            $summons->session
        );

        /*
         * Do not modify summons after
         * hearing outcome has been finalized.
         */
        if ($summons->session?->outcome) {
            return back()->withErrors([
                'summons' =>
                    'Summons information can no longer be changed because this hearing already has an outcome.',
            ]);
        }

        if (
            $summons->session?->status
            !== 'Scheduled'
        ) {
            return back()->withErrors([
                'summons' =>
                    'Summons information can only be changed for a scheduled hearing.',
            ]);
        }

        $data = $request->validate([
            'status' => [
                'required',

                Rule::in([
                    'Not Prepared',
                    'Prepared',
                    'Served',
                    'Received',
                    'Failed Delivery',
                ]),
            ],

            'delivery_notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $updates = [
            'status' =>
                $data['status'],

            'delivery_notes' =>
                $data[
                    'delivery_notes'
                ] ?? null,
        ];

        /*
         * Add timestamps as summons progresses.
         */
        if (
            $data['status']
            === 'Prepared'
        ) {
            $updates['prepared_at'] =
                $summons->prepared_at
                ?? now();
        }

        if (
            $data['status']
            === 'Served'
        ) {
            $updates['prepared_at'] =
                $summons->prepared_at
                ?? now();

            $updates['served_at'] =
                $summons->served_at
                ?? now();
        }

        if (
            $data['status']
            === 'Received'
        ) {
            $updates['prepared_at'] =
                $summons->prepared_at
                ?? now();

            $updates['served_at'] =
                $summons->served_at
                ?? now();

            $updates['received_at'] =
                $summons->received_at
                ?? now();
        }

        DB::transaction(
            function () use (
                $summons,
                $updates
            ) {
                $oldValues = [
                    'status' =>
                        $summons->status,

                    'delivery_notes' =>
                        $summons->delivery_notes,

                    'prepared_at' =>
                        $summons->prepared_at,

                    'served_at' =>
                        $summons->served_at,

                    'received_at' =>
                        $summons->received_at,
                ];

                $summons->update(
                    $updates
                );

                $summons->refresh();

                $session =
                    $summons->session;

                $case =
                    $session?->blotterCase;

                AuditLogService::log(
                    action:
                        'mediation_summons_updated',

                    module:
                        'Blotter Cases',

                    description:
                        'Summons for '
                        . $summons->recipient_name
                        . ' was updated for hearing #'
                        . ($session?->hearing_number ?? '?')
                        . ' of case '
                        . ($case?->reference_number ?? 'Unknown')
                        . '.',

                    auditable:
                        $summons,

                    oldValues:
                        $oldValues,

                    newValues: [
                        'blotter_case_id' =>
                            $case?->id,

                        'reference_number' =>
                            $case?->reference_number,

                        'mediation_session_id' =>
                            $session?->id,

                        'hearing_number' =>
                            $session?->hearing_number,

                        'summons_id' =>
                            $summons->id,

                        'recipient_type' =>
                            $summons->recipient_type,

                        'recipient_name' =>
                            $summons->recipient_name,

                        'status' =>
                            $summons->status,

                        'delivery_notes' =>
                            $summons->delivery_notes,

                        'prepared_at' =>
                            $summons->prepared_at,

                        'served_at' =>
                            $summons->served_at,

                        'received_at' =>
                            $summons->received_at,
                    ]
                );
            }
        );

        return back()->with(
            'success',
            'Summons status updated successfully.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update Attendance
    |--------------------------------------------------------------------------
    */

    public function updateAttendance(
        Request $request,
        MediationAttendee $attendee
    ) {
        /*
         * Load parent hearing, case, and existing outcome.
         */
        $attendee->load([
            'session.outcome',
            'session.blotterCase',
        ]);

        /*
         * Lupon may only update attendance
         * for their own assigned hearing.
         */
        $this->authorize(
            'manage',
            $attendee->session
        );

        /*
         * Attendance is locked once the hearing
         * has a recorded outcome.
         */
        if ($attendee->session?->outcome) {
            return back()->withErrors([
                'attendance' =>
                    'Attendance can no longer be changed because this hearing already has an outcome.',
            ]);
        }

        if (
            $attendee->session?->status
            !== 'Scheduled'
        ) {
            return back()->withErrors([
                'attendance' =>
                    'Attendance can only be updated for a scheduled hearing.',
            ]);
        }

        $data = $request->validate([
            'is_present' => [
                'required',
                'boolean',
            ],

            'remarks' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        DB::transaction(
            function () use (
                $attendee,
                $data
            ) {
                $oldValues = [
                    'is_present' =>
                        (bool) $attendee->is_present,

                    'remarks' =>
                        $attendee->remarks,
                ];

                $attendee->update([
                    'is_present' =>
                        (bool) $data['is_present'],

                    'remarks' =>
                        $data['remarks']
                        ?? null,
                ]);

                $attendee->refresh();

                $session =
                    $attendee->session;

                $case =
                    $session?->blotterCase;

                AuditLogService::log(
                    action:
                        'mediation_attendance_updated',

                    module:
                        'Blotter Cases',

                    description:
                        'Attendance for '
                        . $attendee->name
                        . ' was updated for hearing #'
                        . ($session?->hearing_number ?? '?')
                        . ' of case '
                        . ($case?->reference_number ?? 'Unknown')
                        . '.',

                    auditable:
                        $attendee,

                    oldValues:
                        $oldValues,

                    newValues: [
                        'blotter_case_id' =>
                            $case?->id,

                        'reference_number' =>
                            $case?->reference_number,

                        'mediation_session_id' =>
                            $session?->id,

                        'hearing_number' =>
                            $session?->hearing_number,

                        'attendee_id' =>
                            $attendee->id,

                        'participant_type' =>
                            $attendee->participant_type,

                        'name' =>
                            $attendee->name,

                        'is_present' =>
                            (bool) $attendee->is_present,

                        'remarks' =>
                            $attendee->remarks,
                    ]
                );
            }
        );

        return back()->with(
            'success',
            'Attendance updated successfully.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Record Mediation Outcome
    |--------------------------------------------------------------------------
    */

    public function recordOutcome(
        Request $request,
        MediationSession $session
    ) {
        /*
         * Record-level authorization.
         *
         * Lupon can only finalize hearings
         * specifically assigned to their account.
         */
        $this->authorize(
            'manage',
            $session
        );

        $session->load([
            'blotterCase',
            'outcome',
            'attendees',
        ]);

        /*
         * Hearing must still be active.
         */
        if (
            $session->status
            !== 'Scheduled'
        ) {
            return back()->withErrors([
                'outcome' =>
                    'Only a scheduled hearing can have a new outcome recorded.',
            ]);
        }

        /*
         * Prevent duplicate outcomes.
         */
        if ($session->outcome) {
            return back()->withErrors([
                'outcome' =>
                    'An outcome has already been recorded for this hearing.',
            ]);
        }

        $data = $request->validate([
            'outcome' => [
                'required',

                Rule::in([
                    'Settled',
                    'Referred',
                    'Rescheduled',
                    'No Agreement',
                    'Dismissed',
                ]),
            ],

            'agreement_details' => [
                'nullable',
                'string',
                'max:10000',
            ],

            'referral_agency' => [
                'nullable',
                'string',
                'max:255',
            ],

            'remarks' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Outcome-Specific Validation
        |--------------------------------------------------------------------------
        */

        if (
            $data['outcome']
            === 'Settled'
            &&
            blank(
                $data[
                    'agreement_details'
                ] ?? null
            )
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'agreement_details' =>
                        'Settlement agreement details are required when the outcome is Settled.',
                ]);
        }

        if (
            $data['outcome'] === 'Referred'
            && blank(
                $data[
                    'referral_agency'
                ] ?? null
            )
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'referral_agency' =>
                        'Referral agency is required when the outcome is Referred.',
                ]);
        }

        DB::transaction(
            function () use (
                $session,
                $data
            ) {
                /*
                 * Lock the hearing before finalization.
                 */
                $lockedSession =
                    MediationSession::whereKey(
                        $session->id
                    )
                        ->lockForUpdate()
                        ->firstOrFail();

                /*
                 * Re-check for duplicate outcome
                 * inside the database transaction.
                 */
                $alreadyHasOutcome =
                    MediationOutcome::where(
                        'mediation_session_id',
                        $lockedSession->id
                    )->exists();

                if ($alreadyHasOutcome) {
                    abort(
                        409,
                        'A mediation outcome has already been recorded.'
                    );
                }

                $case =
                    BlotterCase::whereKey(
                        $lockedSession
                            ->blotter_case_id
                    )
                        ->lockForUpdate()
                        ->firstOrFail();

                $oldCaseStatus =
                    $case->status instanceof CaseStatus
                        ? $case->status->value
                        : $case->status;

                $oldCaseStage =
                    $case->case_stage instanceof CaseStage
                        ? $case->case_stage->value
                        : $case->case_stage;

                $oldSessionStatus =
                    $lockedSession->status;

                /*
                |--------------------------------------------------------------------------
                | Save Outcome
                |--------------------------------------------------------------------------
                */

                $outcome =
                    MediationOutcome::create([
                        'mediation_session_id' =>
                            $lockedSession->id,

                        'outcome' =>
                            $data['outcome'],

                        'agreement_details' =>
                            $data[
                                'agreement_details'
                            ] ?? null,

                        'referral_agency' =>
                            $data[
                                'referral_agency'
                            ] ?? null,

                        'remarks' =>
                            $data['remarks']
                            ?? null,

                        'recorded_by' =>
                            auth()->id(),

                        'recorded_at' =>
                            now(),
                    ]);

                /*
                |--------------------------------------------------------------------------
                | Apply Workflow Result
                |--------------------------------------------------------------------------
                */

                if (
                    $data['outcome']
                    === 'Settled'
                ) {
                    $lockedSession->update([
                        'status' =>
                            'Completed',

                        'completed_at' =>
                            now(),
                    ]);

                    $case->update([
                        'status' =>
                            CaseStatus::Settled,

                        'case_stage' =>
                            CaseStage::SettledResolved,

                        'closed_at' =>
                            now(),
                    ]);

                    /*
                     * Create the linked Settlement & Resolution record once.
                     * The proceeding decides that the parties settled; the
                     * settlement module handles document finalization and the
                     * final Resolved state without duplicating case data.
                     */
                    CaseResolution::updateOrCreate(
                        [
                            'blotter_case_id' =>
                                $case->id,
                        ],
                        [
                            'mediation_outcome_id' =>
                                $outcome->id,

                            'resolution_type' =>
                                ($lockedSession->proceeding_type
                                    === 'Pangkat Conciliation')
                                    ? 'Pangkat Settlement'
                                    : 'Amicable Settlement',

                            'status' =>
                                'Pending',

                            'agreement_details' =>
                                $outcome->agreement_details,

                            'remarks' =>
                                $outcome->remarks,
                        ]
                    );
                } elseif (
                    $data['outcome']
                    === 'Referred'
                ) {
                    $lockedSession->update([
                        'status' =>
                            'Completed',

                        'completed_at' =>
                            now(),
                    ]);

                    $case->update([
                        'status' =>
                            CaseStatus::Referred,

                        'case_stage' =>
                            CaseStage::ForFurtherActionCfa,

                        'closed_at' =>
                            now(),
                    ]);
                } elseif (
                    $data['outcome']
                    === 'No Agreement'
                ) {
                    $lockedSession->update([
                        'status' =>
                            'Completed',

                        'completed_at' =>
                            now(),
                    ]);

                    $proceedingType =
                        $lockedSession->proceeding_type
                        ?: 'Mediation';

                    if (
                        $proceedingType
                        === 'Pangkat Conciliation'
                    ) {
                        /*
                         * Pangkat conciliation ended without agreement:
                         * move the case to the next/further action stage.
                         */
                        $case->update([
                            'status' =>
                                CaseStatus::Referred,

                            'case_stage' =>
                                CaseStage::ForFurtherActionCfa,

                            'closed_at' =>
                                now(),
                        ]);
                    } else {
                        /*
                         * Mediation ended without agreement:
                         * automatically advance to Pangkat/Conciliation.
                         */
                        $case->update([
                            'status' =>
                                CaseStatus::ForMediation,

                            'case_stage' =>
                                CaseStage::ForPangkatConciliation,

                            'closed_at' =>
                                null,
                        ]);
                    }
                } elseif (
                    $data['outcome']
                    === 'Dismissed'
                ) {
                    $lockedSession->update([
                        'status' =>
                            'Completed',

                        'completed_at' =>
                            now(),
                    ]);

                    $case->update([
                        'status' =>
                            CaseStatus::Dismissed,

                        'case_stage' =>
                            CaseStage::Closed,

                        'closed_at' =>
                            now(),
                    ]);
                } elseif (
                    $data['outcome']
                    === 'Rescheduled'
                ) {
                    $lockedSession->update([
                        'status' =>
                            'Rescheduled',

                        'completed_at' =>
                            now(),
                    ]);

                    $proceedingType =
                        $lockedSession->proceeding_type
                        ?: 'Mediation';

                    $case->update([
                        'status' =>
                            CaseStatus::ForMediation,

                        'case_stage' =>
                            $proceedingType === 'Pangkat Conciliation'
                                ? CaseStage::ForPangkatConciliation
                                : CaseStage::ForMediation,

                        'closed_at' =>
                            null,
                    ]);
                }

                $lockedSession->refresh();
                $case->refresh();

                $newCaseStatus =
                    $case->status instanceof CaseStatus
                        ? $case->status->value
                        : $case->status;

                $newCaseStage =
                    $case->case_stage instanceof CaseStage
                        ? $case->case_stage->value
                        : $case->case_stage;

                /*
                |--------------------------------------------------------------------------
                | Audit Trail
                |--------------------------------------------------------------------------
                */

                AuditLogService::log(
                    action:
                        'mediation_outcome_recorded',

                    module:
                        'Lupon & Mediation',

                    description:
                        ($lockedSession->proceeding_type ?: 'Mediation')
                        . ' outcome '
                        . $data['outcome']
                        . ' was recorded for hearing #'
                        . $lockedSession->hearing_number
                        . ' of case '
                        . $case->reference_number
                        . '.',

                    auditable:
                        $outcome,

                    oldValues: [
                        'case_status' =>
                            $oldCaseStatus,

                        'case_stage' =>
                            $oldCaseStage,

                        'session_status' =>
                            $oldSessionStatus,
                    ],

                    newValues: [
                        'blotter_case_id' =>
                            $case->id,

                        'reference_number' =>
                            $case->reference_number,

                        'mediation_session_id' =>
                            $lockedSession->id,

                        'hearing_number' =>
                            $lockedSession->hearing_number,

                        'proceeding_type' =>
                            $lockedSession->proceeding_type
                            ?: 'Mediation',

                        'outcome_id' =>
                            $outcome->id,

                        'outcome' =>
                            $outcome->outcome,

                        'agreement_details' =>
                            $outcome->agreement_details,

                        'referral_agency' =>
                            $outcome->referral_agency,

                        'remarks' =>
                            $outcome->remarks,

                        'session_status' =>
                            $lockedSession->status,

                        'case_status' =>
                            $newCaseStatus,

                        'case_stage' =>
                            $newCaseStage,

                        'completed_at' =>
                            $lockedSession->completed_at,

                        'case_closed_at' =>
                            $case->closed_at,
                    ]
                );
            }
        );

        return back()->with(
            'success',
            'Mediation outcome recorded successfully.'
        );
    }
}