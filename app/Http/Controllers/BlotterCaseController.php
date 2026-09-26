<?php

namespace App\Http\Controllers;

use App\Enums\CaseStage;
use App\Enums\CaseStatus;
use App\Http\Requests\BlotterCaseRequest;
use App\Models\BlotterCase;
use App\Models\IncidentType;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\CasePartyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BlotterCaseController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Blotter Case List
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $this->authorize(
            'viewAny',
            BlotterCase::class
        );

        $user = auth()->user();

        $query = BlotterCase::query()
            ->visibleTo($user)
            ->select([
                'blotter_cases.id',
                'blotter_cases.reference_number',
                'blotter_cases.incident_type_id',
                'blotter_cases.incident_date',
                'blotter_cases.status',
                'blotter_cases.reported_at',
            ])
            ->with([
                'incidentType:id,name',
                'complainants:id,blotter_case_id,first_name,middle_name,last_name,suffix',
                'respondents:id,blotter_case_id,first_name,middle_name,last_name,suffix',
            ])
            ->searchCase(
                $request->string('search')->toString()
            );

        /*
        |--------------------------------------------------------------------------
        | Status Filter
        |--------------------------------------------------------------------------
        */

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->status
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Incident Type Filter
        |--------------------------------------------------------------------------
        */

        if ($request->filled('incident_type_id')) {
            $query->where(
                'incident_type_id',
                $request->incident_type_id
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Results
        |--------------------------------------------------------------------------
        */

        $cases = $query
            ->latest('reported_at')
            ->paginate(15)
            ->withQueryString();

        $incidentTypes = IncidentType::where(
            'is_active',
            true
        )
            ->orderBy('name')
            ->get(['id', 'name']);

        $statuses = CaseStatus::cases();

        return view(
            'blotter.index',
            compact(
                'cases',
                'incidentTypes',
                'statuses'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Create Blotter Form
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        $this->authorize(
            'create',
            BlotterCase::class
        );

        $incidentTypes = IncidentType::where(
            'is_active',
            true
        )
            ->orderBy('name')
            ->get(['id', 'name']);

        return view(
            'blotter.create',
            compact('incidentTypes')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Store Blotter Case
    |--------------------------------------------------------------------------
    */

    public function store(
        BlotterCaseRequest $request,
        CasePartyService $casePartyService
    ) {
        $this->authorize(
            'create',
            BlotterCase::class
        );

        $case = DB::transaction(
            function () use ($request, $casePartyService) {
                $data = $request->validated();

                /*
                |--------------------------------------------------------------------------
                | Create Main Case
                |--------------------------------------------------------------------------
                */

                $case = BlotterCase::create([
                    'incident_type_id' =>
                        $data['incident_type_id'],

                    'incident_date' =>
                        $data['incident_date'],

                    'incident_time' =>
                        $data['incident_time'] ?? null,

                    'location' =>
                        $data['location'],

                    'narrative' =>
                        $data['narrative'],

                    'initial_action' =>
                        $data['initial_action'] ?? null,

                    'remarks' =>
                        $data['remarks'] ?? null,

                    'status' =>
                        CaseStatus::Pending,

                    'case_stage' =>
                        CaseStage::New,

                    'created_by' =>
                        auth()->id(),

                    'reported_at' =>
                        now(),
                ]);

                /*
                |--------------------------------------------------------------------------
                | Parties From People Directory
                |--------------------------------------------------------------------------
                |
                | Both people are loaded in one query and copied into immutable
                | case-party snapshots. No duplicate person record is created here.
                |
                */

                $casePartyService->attachFromDirectory(
                    $case,
                    (int) $data['complainant_resident_id'],
                    (int) $data['respondent_resident_id']
                );

                /*
                |--------------------------------------------------------------------------
                | Audit Trail
                |--------------------------------------------------------------------------
                */

                AuditLogService::log(
                    action: 'created',
                    module: 'Blotter Cases',
                    description:
                        "Blotter case {$case->reference_number} was created.",
                    auditable: $case,
                    newValues:
                        $this->caseAuditSnapshot(
                            $case
                        )
                );

                return $case;
            }
        );

        return redirect()
            ->route(
                'blotter.show',
                $case
            )
            ->with(
                'success',
                'Blotter case created successfully.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Show Blotter Case
    |--------------------------------------------------------------------------
    */

    public function show(
        BlotterCase $blotter
    ) {
        /*
         * Record-level authorization.
         */
        $this->authorize(
            'view',
            $blotter
        );

        /*
        |--------------------------------------------------------------------------
        | Load Case Data
        |--------------------------------------------------------------------------
        */

        $blotter->load([
            'incidentType',
            'creator.role',

            'complainants.resident',
            'respondents.resident',
            'witnesses.resident',

            'currentAssignment.assignedOfficer.role',
            'currentAssignment.assignedBy.role',

            'assignments.assignedOfficer.role',
            'assignments.assignedBy.role',

            'investigationNotes.author.role',

            'mediationSessions.luponMember.role',
            'mediationSessions.creator.role',

            'mediationSessions.attendees.resident',

            'mediationSessions.summons.resident',
            'mediationSessions.summons.creator',

            'mediationSessions.outcome.recordedBy',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Active Councilors
        |--------------------------------------------------------------------------
        */

        $councilors = User::where(
            'is_active',
            true
        )
            ->whereHas(
                'role',
                function ($query) {
                    $query->where(
                        'slug',
                        'councilor'
                    );
                }
            )
            ->orderBy('name')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Active Lupon Members
        |--------------------------------------------------------------------------
        */

        $luponMembers = User::where(
            'is_active',
            true
        )
            ->whereHas(
                'role',
                function ($query) {
                    $query->where(
                        'slug',
                        'lupon'
                    );
                }
            )
            ->orderBy('name')
            ->get();

        return view(
            'blotter.show',
            [
                'case' =>
                    $blotter,

                'councilors' =>
                    $councilors,

                'luponMembers' =>
                    $luponMembers,

            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Edit Blotter Case
    |--------------------------------------------------------------------------
    */

    public function edit(
        BlotterCase $blotter
    ) {
        $this->authorize(
            'update',
            $blotter
        );

        $incidentTypes = IncidentType::where(
            'is_active',
            true
        )
            ->orderBy('name')
            ->get(['id', 'name']);

        $statuses = CaseStatus::cases();

        return view(
            'blotter.edit',
            [
                'case' =>
                    $blotter,

                'incidentTypes' =>
                    $incidentTypes,

                'statuses' =>
                    $statuses,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update Blotter Case
    |--------------------------------------------------------------------------
    */

    public function update(
        BlotterCaseRequest $request,
        BlotterCase $blotter
    ) {
        $this->authorize(
            'update',
            $blotter
        );

        $data = $request->validated();

        /*
        |--------------------------------------------------------------------------
        | Determine Status
        |--------------------------------------------------------------------------
        */

        $newStatus = isset(
            $data['status']
        )
            ? CaseStatus::from(
                $data['status']
            )
            : $blotter->status;

        /*
        |--------------------------------------------------------------------------
        | Closed Statuses
        |--------------------------------------------------------------------------
        */

        $closedStatuses = [
            CaseStatus::Settled,
            CaseStatus::Resolved,
            CaseStatus::Referred,
            CaseStatus::Dismissed,
        ];

        /*
        |--------------------------------------------------------------------------
        | Determine closed_at
        |--------------------------------------------------------------------------
        */

        $closedAt = in_array(
            $newStatus,
            $closedStatuses,
            true
        )
            ? (
                $blotter->closed_at
                ?? now()
            )
            : null;

        $newCaseStage = CaseStage::fromStatus(
            $newStatus,
            $blotter->case_stage
        );

        /*
        |--------------------------------------------------------------------------
        | Update Case + Audit Trail
        |--------------------------------------------------------------------------
        */

        DB::transaction(
            function () use (
                $blotter,
                $data,
                $newStatus,
                $newCaseStage,
                $closedAt
            ) {
                $oldValues =
                    $this->caseAuditSnapshot(
                        $blotter
                    );

                $blotter->update([
                    'incident_type_id' =>
                        $data['incident_type_id'],

                    'incident_date' =>
                        $data['incident_date'],

                    'incident_time' =>
                        $data['incident_time'] ?? null,

                    'location' =>
                        $data['location'],

                    'narrative' =>
                        $data['narrative'],

                    'initial_action' =>
                        $data['initial_action'] ?? null,

                    'remarks' =>
                        $data['remarks'] ?? null,

                    'status' =>
                        $newStatus,

                    'case_stage' =>
                        $newCaseStage,

                    'closed_at' =>
                        $closedAt,
                ]);

                $blotter->refresh();

                AuditLogService::log(
                    action: 'updated',
                    module: 'Blotter Cases',
                    description:
                        "Blotter case {$blotter->reference_number} was updated.",
                    auditable: $blotter,
                    oldValues:
                        $oldValues,
                    newValues:
                        $this->caseAuditSnapshot(
                            $blotter
                        )
                );
            }
        );

        return redirect()
            ->route(
                'blotter.show',
                $blotter
            )
            ->with(
                'success',
                'Blotter case updated successfully.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Archive Blotter Case
    |--------------------------------------------------------------------------
    */

    public function destroy(
        BlotterCase $blotter
    ) {
        $this->authorize(
            'delete',
            $blotter
        );

        DB::transaction(
            function () use ($blotter) {
                $oldValues =
                    $this->caseAuditSnapshot(
                        $blotter
                    );

                $blotter->delete();

                AuditLogService::log(
                    action: 'archived',
                    module: 'Blotter Cases',
                    description:
                        "Blotter case {$blotter->reference_number} was archived.",
                    auditable: $blotter,
                    oldValues:
                        $oldValues,
                    newValues: [
                        'archived' => true,
                        'archived_at' =>
                            now()->toDateTimeString(),
                    ]
                );
            }
        );

        return redirect()
            ->route(
                'blotter.index'
            )
            ->with(
                'success',
                'Blotter case archived successfully.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Audit Snapshot Helper
    |--------------------------------------------------------------------------
    */

    private function caseAuditSnapshot(
        BlotterCase $case
    ): array {
        return [
            'reference_number' =>
                $this->auditAttribute(
                    $case,
                    'reference_number'
                ),

            'incident_type_id' =>
                $this->auditAttribute(
                    $case,
                    'incident_type_id'
                ),

            'incident_date' =>
                $this->auditAttribute(
                    $case,
                    'incident_date'
                ),

            'incident_time' =>
                $this->auditAttribute(
                    $case,
                    'incident_time'
                ),

            'location' =>
                $this->auditAttribute(
                    $case,
                    'location'
                ),

            'narrative' =>
                $this->auditAttribute(
                    $case,
                    'narrative'
                ),

            'initial_action' =>
                $this->auditAttribute(
                    $case,
                    'initial_action'
                ),

            'remarks' =>
                $this->auditAttribute(
                    $case,
                    'remarks'
                ),

            'status' =>
                $this->auditAttribute(
                    $case,
                    'status'
                ),

            'case_stage' =>
                $this->auditAttribute(
                    $case,
                    'case_stage'
                ),

            'created_by' =>
                $this->auditAttribute(
                    $case,
                    'created_by'
                ),

            'reported_at' =>
                $this->auditAttribute(
                    $case,
                    'reported_at'
                ),

            'closed_at' =>
                $this->auditAttribute(
                    $case,
                    'closed_at'
                ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Audit Attribute Helper
    |--------------------------------------------------------------------------
    */

    private function auditAttribute(
        BlotterCase $case,
        string $attribute
    ): mixed {
        $rawValue =
            $case->getRawOriginal(
                $attribute
            );

        if ($rawValue !== null) {
            return $rawValue;
        }

        $value =
            $case->getAttribute(
                $attribute
            );

        if (
            $value instanceof \BackedEnum
        ) {
            return $value->value;
        }

        if (
            $value instanceof \DateTimeInterface
        ) {
            return $value->format(
                'Y-m-d H:i:s'
            );
        }

        return $value;
    }
}
