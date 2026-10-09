<?php

namespace App\Http\Controllers;

use App\Enums\CaseStage;
use App\Enums\CaseStatus;
use App\Enums\RecordStatus;
use App\Models\BlotterCase;
use App\Models\CaseResolution;
use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettlementResolutionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $role = $user?->role?->slug;

        if (! in_array($role, [
            'barangay_captain',
            'secretary',
            'lupon',
        ], true)) {
            abort(403);
        }

        $baseQuery = CaseResolution::query();

        if ($role === 'lupon') {
            $baseQuery->whereHas(
                'blotterCase.mediationSessions',
                fn (Builder $session) => $session->where(
                    'lupon_member_id',
                    $user->id
                )
            );
        }

        $kpiRow = (clone $baseQuery)
            ->selectRaw(
                "SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) AS active_count"
            )
            ->selectRaw(
                "SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed_count"
            )
            ->selectRaw(
                'COUNT(*) AS total_count'
            )
            ->first();

        $kpis = [
            'active' => (int) ($kpiRow?->active_count ?? 0),
            'completed' => (int) ($kpiRow?->completed_count ?? 0),
            'total' => (int) ($kpiRow?->total_count ?? 0),
        ];

        $resolutions = (clone $baseQuery)
            ->select([
                'case_resolutions.id',
                'case_resolutions.blotter_case_id',
                'case_resolutions.mediation_outcome_id',
                'case_resolutions.resolution_type',
                'case_resolutions.status',
                'case_resolutions.agreement_details',
                'case_resolutions.remarks',
                'case_resolutions.resolved_by',
                'case_resolutions.resolved_at',
                'case_resolutions.updated_at',
            ])
            ->with([
                'blotterCase:id,reference_number,incident_type_id,status,case_stage,record_status,closed_at',
                'blotterCase.incidentType:id,name',
                'blotterCase.complainants:id,blotter_case_id,first_name,middle_name,last_name,suffix',
                'blotterCase.respondents:id,blotter_case_id,first_name,middle_name,last_name,suffix',
                'mediationOutcome:id,mediation_session_id,outcome,agreement_details,remarks,recorded_at',
                'mediationOutcome.mediationSession:id,hearing_number,proceeding_type,scheduled_date',
                'resolvedBy:id,name',
            ])
            ->when(
                $request->filled('search'),
                function (Builder $query) use ($request) {
                    $search = trim(
                        $request
                            ->string('search')
                            ->toString()
                    );

                    $query->where(
                        function (Builder $inner) use ($search) {
                            $inner
                                ->where(
                                    'resolution_type',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhereHas(
                                    'blotterCase',
                                    fn (Builder $case) => $case->where(
                                        'reference_number',
                                        'like',
                                        "%{$search}%"
                                    )
                                )
                                ->orWhereHas(
                                    'blotterCase.complainants',
                                    fn (Builder $person) => $person
                                        ->where(
                                            'first_name',
                                            'like',
                                            "%{$search}%"
                                        )
                                        ->orWhere(
                                            'middle_name',
                                            'like',
                                            "%{$search}%"
                                        )
                                        ->orWhere(
                                            'last_name',
                                            'like',
                                            "%{$search}%"
                                        )
                                )
                                ->orWhereHas(
                                    'blotterCase.respondents',
                                    fn (Builder $person) => $person
                                        ->where(
                                            'first_name',
                                            'like',
                                            "%{$search}%"
                                        )
                                        ->orWhere(
                                            'middle_name',
                                            'like',
                                            "%{$search}%"
                                        )
                                        ->orWhere(
                                            'last_name',
                                            'like',
                                            "%{$search}%"
                                        )
                                );
                        }
                    );
                }
            );

        $status = $request
            ->string('status')
            ->toString();

        if (
            in_array(
                $status,
                [
                    'Active',
                    'Completed',
                ],
                true
            )
        ) {
            $resolutions->where(
                'status',
                $status
            );
        }

        $resolutions = $resolutions
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view(
            'settlements.index',
            [
                'resolutions' => $resolutions,
                'kpis' => $kpis,
                'roleSlug' => $role,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Complete Settlement
    |--------------------------------------------------------------------------
    |
    | Settlement status is intentionally separate from the main case status.
    |
    | Settlement:
    |     Active -> Completed
    |
    | Main Case Status:
    |     Open / Resolved / Closed
    |
    | The existing resolved_by / resolved_at columns are retained as legacy
    | storage for the settlement completion actor and timestamp.
    |
    */

    public function complete(
        Request $request,
        CaseResolution $resolution
    ) {
        $this->ensureManager($request);

        if ($resolution->status !== 'Active') {
            return back()->withErrors([
                'resolution' => 'Only active settlement records may be completed.',
            ]);
        }

        DB::transaction(
            function () use (
                $resolution,
                $request
            ) {
                $locked =
                    CaseResolution::whereKey(
                        $resolution->id
                    )
                        ->lockForUpdate()
                        ->firstOrFail();

                if ($locked->status !== 'Active') {
                    abort(
                        409,
                        'This settlement record has already been completed.'
                    );
                }

                $case =
                    BlotterCase::whereKey(
                        $locked->blotter_case_id
                    )
                        ->lockForUpdate()
                        ->firstOrFail();

                $oldResolutionStatus =
                    $locked->status;

                $oldCaseStatus =
                    $case->status instanceof CaseStatus
                        ? $case->status->value
                        : (string) $case->status;

                $oldCaseStage =
                    $case->case_stage instanceof CaseStage
                        ? $case->case_stage->value
                        : (string) $case->case_stage;

                $oldRecordStatus =
                    $case->record_status instanceof RecordStatus
                        ? $case->record_status->value
                        : (string) $case->record_status;

                /*
                |--------------------------------------------------------------------------
                | Complete Settlement Record
                |--------------------------------------------------------------------------
                */

                $locked->update([
                    'resolution_type' => $locked->resolution_type,

                    'status' => 'Completed',

                    'resolved_by' => $request
                        ->user()
                        ->id,

                    'resolved_at' => now(),
                ]);

                /*
                |--------------------------------------------------------------------------
                | Keep Parent Case Synchronized
                |--------------------------------------------------------------------------
                |
                | The mediation outcome already resolves the case. This update
                | keeps the legacy case status aligned while record_status
                | remains the authoritative Open / Resolved / Closed value.
                |
                */

                $case->update([
                    'status' => CaseStatus::Resolved,

                    'case_stage' => CaseStage::SettledResolved,

                    'record_status' => RecordStatus::Resolved,

                    'closed_at' => $case->closed_at
                        ?? now(),
                ]);

                $locked->refresh();
                $case->refresh();

                AuditLogService::log(
                    action: 'settlement_completed',

                    module: 'Settlement & Resolutions',

                    description: 'Completed the settlement record for case '
                        .$case->reference_number
                        .'.',

                    auditable: $locked,

                    oldValues: [
                        'settlement_status' => $oldResolutionStatus,

                        'case_status' => $oldCaseStatus,

                        'case_stage' => $oldCaseStage,

                        'record_status' => $oldRecordStatus,
                    ],

                    newValues: [
                        'settlement_status' => 'Completed',

                        'resolution_type' => $locked->resolution_type,

                        'case_status' => CaseStatus::Resolved
                            ->value,

                        'case_stage' => CaseStage::SettledResolved
                            ->value,

                        'record_status' => RecordStatus::Resolved
                            ->value,

                        'completed_at' => $locked->resolved_at,

                        'case_closed_at' => $case->closed_at,
                    ]
                );
            }
        );

        return back()->with(
            'success',
            'Settlement record completed successfully.'
        );
    }

    private function ensureManager(
        Request $request
    ): void {
        $role =
            $request
                ->user()
                ?->role
                ?->slug;

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
    }
}
