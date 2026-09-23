<?php

namespace App\Http\Controllers;

use App\Enums\CaseStatus;
use App\Http\Requests\BlotterCaseRequest;
use App\Models\BlotterCase;
use App\Models\IncidentType;
use App\Models\Resident;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\ResidentReferenceService;
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

        $role = $user->role?->slug;

        $query = BlotterCase::with([
            'incidentType',
            'complainants',
            'respondents',
            'currentAssignment.assignedOfficer',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Role-Based Record Filtering
        |--------------------------------------------------------------------------
        */

        if ($role === 'councilor') {
            $query->whereHas(
                'assignments',
                function ($assignment) use ($user) {
                    $assignment
                        ->where(
                            'assigned_to',
                            $user->id
                        )
                        ->whereNull(
                            'completed_at'
                        );
                }
            );
        }

        if ($role === 'lupon') {
            $query->whereHas(
                'mediationSessions',
                function ($session) use ($user) {
                    $session->where(
                        'lupon_member_id',
                        $user->id
                    );
                }
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */

        if ($request->filled('search')) {
            $search = trim(
                $request->search
            );

            $query->where(
                function ($q) use ($search) {
                    $q->where(
                        'reference_number',
                        'like',
                        "%{$search}%"
                    )
                        ->orWhere(
                            'location',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhereHas(
                            'complainants',
                            function ($person) use ($search) {
                                $person
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
                                    );
                            }
                        )
                        ->orWhereHas(
                            'respondents',
                            function ($person) use ($search) {
                                $person
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
                                    );
                            }
                        );
                }
            );
        }

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
            ->get();

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
            ->get();

        $residents = Resident::where(
            'is_active',
            true
        )
            ->whereHas(
                'complaints'
            )
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view(
            'blotter.create',
            compact(
                'incidentTypes',
                'residents'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Store Blotter Case
    |--------------------------------------------------------------------------
    */

    public function store(
        BlotterCaseRequest $request
    ) {
        $this->authorize(
            'create',
            BlotterCase::class
        );

        $case = DB::transaction(
            function () use ($request) {
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

                    'created_by' =>
                        auth()->id(),

                    'reported_at' =>
                        now(),
                ]);

                /*
                |--------------------------------------------------------------------------
                | Complainant
                |--------------------------------------------------------------------------
                */

                $this->createComplainant(
                    $case,
                    $data
                );

                /*
                |--------------------------------------------------------------------------
                | Respondent
                |--------------------------------------------------------------------------
                */

                $this->createRespondent(
                    $case,
                    $data
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

        /*
        |--------------------------------------------------------------------------
        | Active Residents
        |--------------------------------------------------------------------------
        |
        | Used by the Add Witness form.
        |
        */

        $residents = Resident::where(
            'is_active',
            true
        )
            ->whereHas(
                'complaints'
            )
            ->orderBy('last_name')
            ->orderBy('first_name')
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

                'residents' =>
                    $residents,
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
            ->get();

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
    | Create Complainant
    |--------------------------------------------------------------------------
    */

    private function createComplainant(
        BlotterCase $case,
        array $data
    ): void {
        $resident = null;

        /*
        |--------------------------------------------------------------------------
        | Existing Complainant Record
        |--------------------------------------------------------------------------
        */

        if (
            ! empty(
                $data[
                    'complainant_resident_id'
                ]
            )
        ) {
            $resident = Resident::where(
                'is_active',
                true
            )
                ->whereHas(
                    'complaints'
                )
                ->findOrFail(
                    $data[
                        'complainant_resident_id'
                    ]
                );
        }

        /*
        |--------------------------------------------------------------------------
        | New Complainant Information
        |--------------------------------------------------------------------------
        */

        if (! $resident) {
            $isSanJoseResident = ! (
                $data[
                    'complainant_not_san_jose'
                ]
                ?? false
            );

            $houseNumber =
                $isSanJoseResident
                    ? (
                        $data[
                            'complainant_house_number'
                        ]
                        ?? null
                    )
                    : null;

            $sitio =
                $isSanJoseResident
                    ? (
                        $data[
                            'complainant_sitio'
                        ]
                        ?? null
                    )
                    : null;

            $address =
                $isSanJoseResident
                    ? $this->sanJoseAddress(
                        $houseNumber,
                        $sitio
                    )
                    : (
                        $data[
                            'complainant_address'
                        ]
                        ?? null
                    );

            $firstName = trim(
                $data[
                    'complainant_first_name'
                ]
            );

            $middleName = trim(
                (string) (
                    $data[
                        'complainant_middle_name'
                    ]
                    ?? ''
                )
            );

            $lastName = trim(
                $data[
                    'complainant_last_name'
                ]
            );

            $suffix = trim(
                (string) (
                    $data[
                        'complainant_suffix'
                    ]
                    ?? ''
                )
            );

            $contactNumber = trim(
                (string) (
                    $data[
                        'complainant_contact_number'
                    ]
                    ?? ''
                )
            );

            /*
             * Reuse a matching active complainant record when the
             * same name and contact number already exist. We only
             * auto-match when a contact number is available to avoid
             * merging two different people who happen to share a name.
             */
            if ($contactNumber !== '') {
                $resident =
                    Resident::where(
                        'is_active',
                        true
                    )
                        ->whereHas(
                            'complaints'
                        )
                        ->where(
                            'first_name',
                            $firstName
                        )
                        ->where(
                            'last_name',
                            $lastName
                        )
                        ->where(
                            'contact_number',
                            $contactNumber
                        )
                        ->when(
                            $middleName !== '',
                            fn ($query) =>
                                $query->where(
                                    'middle_name',
                                    $middleName
                                ),
                            fn ($query) =>
                                $query->whereNull(
                                    'middle_name'
                                )
                        )
                        ->when(
                            $suffix !== '',
                            fn ($query) =>
                                $query->where(
                                    'suffix',
                                    $suffix
                                ),
                            fn ($query) =>
                                $query->whereNull(
                                    'suffix'
                                )
                        )
                        ->first();
            }

            /*
            |--------------------------------------------------------------------------
            | Automatically Save New Complainant Record
            |--------------------------------------------------------------------------
            */

            if (! $resident) {
                $referenceService =
                    app(
                        ResidentReferenceService::class
                    );

                $resident = Resident::create([
                    'resident_code' =>
                        $referenceService->generate(),

                    'first_name' =>
                        $firstName,

                    'middle_name' =>
                        $middleName !== ''
                            ? $middleName
                            : null,

                    'last_name' =>
                        $lastName,

                    'suffix' =>
                        $suffix !== ''
                            ? $suffix
                            : null,

                    'contact_number' =>
                        $contactNumber !== ''
                            ? $contactNumber
                            : null,

                    /*
                     * We keep the existing residents table internally.
                     * For Barangay San Jose complainants, purok stores
                     * the Sitio value so the current schema can be reused.
                     */
                    'house_number' =>
                        $houseNumber,

                    'purok' =>
                        $sitio,

                    /*
                     * Outside-San-Jose complainants keep their full
                     * address in address_details.
                     */
                    'address_details' =>
                        $isSanJoseResident
                            ? null
                            : $address,

                    'is_active' =>
                        true,

                    'created_by' =>
                        auth()->id(),
                ]);

                AuditLogService::log(
                    action:
                        'complainant_record_created',

                    module:
                        'Complainant Records',

                    description:
                        "Complainant record {$resident->resident_code} was created automatically from blotter case {$case->reference_number}.",

                    auditable:
                        $resident,

                    newValues: [
                        'resident_code' =>
                            $resident->resident_code,

                        'first_name' =>
                            $resident->first_name,

                        'middle_name' =>
                            $resident->middle_name,

                        'last_name' =>
                            $resident->last_name,

                        'suffix' =>
                            $resident->suffix,

                        'contact_number' =>
                            $resident->contact_number,

                        'house_number' =>
                            $resident->house_number,

                        'sitio' =>
                            $resident->purok,

                        'address_details' =>
                            $resident->address_details,
                    ]
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Build Case Snapshot From Saved Complainant Record
        |--------------------------------------------------------------------------
        */

        $isSanJoseResident =
            $this->residentIsFromSanJose(
                $resident
            );

        $houseNumber =
            $isSanJoseResident
                ? $resident->house_number
                : null;

        $sitio =
            $isSanJoseResident
                ? (
                    in_array(
                        $resident->purok,
                        [
                            'Sitio 1',
                            'Sitio 2',
                            'Sitio 3',
                            'Sitio 4',
                        ],
                        true
                    )
                        ? $resident->purok
                        : null
                )
                : null;

        $address =
            $isSanJoseResident
                ? $this->sanJoseAddress(
                    $houseNumber,
                    $sitio
                )
                : $this->residentAddress(
                    $resident
                );

        $case
            ->complainants()
            ->create([
                'resident_id' =>
                    $resident->id,

                'is_san_jose_resident' =>
                    $isSanJoseResident,

                'house_number' =>
                    $houseNumber,

                'sitio' =>
                    $sitio,

                'first_name' =>
                    $resident->first_name,

                'middle_name' =>
                    $resident->middle_name,

                'last_name' =>
                    $resident->last_name,

                'suffix' =>
                    $resident->suffix,

                'contact_number' =>
                    $resident->contact_number,

                'address' =>
                    $address,
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Create Respondent
    |--------------------------------------------------------------------------
    */

    private function createRespondent(
        BlotterCase $case,
        array $data
    ): void {
        $resident = null;

        if (
            ! empty(
                $data[
                    'respondent_resident_id'
                ]
            )
        ) {
            $resident = Resident::where(
                'is_active',
                true
            )
                ->whereHas(
                    'complaints'
                )
                ->findOrFail(
                    $data[
                        'respondent_resident_id'
                    ]
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Determine Address Type
        |--------------------------------------------------------------------------
        */

        if ($resident) {
            /*
             * A respondent may already exist in Complainant Records
             * because they previously filed a different complaint.
             * Reuse that profile without creating a new master record.
             */
            $isSanJoseResident =
                $this->residentIsFromSanJose(
                    $resident
                );

            $houseNumber =
                $isSanJoseResident
                    ? $resident->house_number
                    : null;

            $sitio =
                $isSanJoseResident
                    && in_array(
                        $resident->purok,
                        [
                            'Sitio 1',
                            'Sitio 2',
                            'Sitio 3',
                            'Sitio 4',
                        ],
                        true
                    )
                        ? $resident->purok
                        : null;

            $address =
                $isSanJoseResident
                    ? $this->sanJoseAddress(
                        $houseNumber,
                        $sitio
                    )
                    : $this->residentAddress(
                        $resident
                    );
        } else {
            $isSanJoseResident = ! (
                $data[
                    'respondent_not_san_jose'
                ]
                ?? false
            );

            if ($isSanJoseResident) {
                $houseNumber =
                    $data[
                        'respondent_house_number'
                    ]
                    ?? null;

                $sitio =
                    $data[
                        'respondent_sitio'
                    ]
                    ?? null;

                $address =
                    $this->sanJoseAddress(
                        $houseNumber,
                        $sitio
                    );
            } else {
                $houseNumber = null;
                $sitio = null;

                $address =
                    $data[
                        'respondent_address'
                    ]
                    ?? null;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Create Respondent Snapshot
        |--------------------------------------------------------------------------
        */

        $case
            ->respondents()
            ->create([
                'resident_id' =>
                    $resident?->id,

                'is_san_jose_resident' =>
                    $isSanJoseResident,

                'house_number' =>
                    $houseNumber,

                'sitio' =>
                    $sitio,

                'first_name' =>
                    $resident?->first_name
                    ?? $data[
                        'respondent_first_name'
                    ],

                'middle_name' =>
                    $resident?->middle_name
                    ?? (
                        $data[
                            'respondent_middle_name'
                        ]
                        ?? null
                    ),

                'last_name' =>
                    $resident?->last_name
                    ?? $data[
                        'respondent_last_name'
                    ],

                'suffix' =>
                    $resident?->suffix
                    ?? (
                        $data[
                            'respondent_suffix'
                        ]
                        ?? null
                    ),

                'contact_number' =>
                    $resident?->contact_number
                    ?? (
                        $data[
                            'respondent_contact_number'
                        ]
                        ?? null
                    ),

                'address' =>
                    $address,
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Barangay San Jose Address Helper
    |--------------------------------------------------------------------------
    */

    private function sanJoseAddress(
        ?string $houseNumber,
        ?string $sitio
    ): string {
        return collect([
            $houseNumber
                ? 'House No. ' . $houseNumber
                : null,

            $sitio,

            'Barangay San Jose',
        ])
            ->filter()
            ->join(', ');
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

    /*
    |--------------------------------------------------------------------------
    | Determine Whether Saved Complainant Is From Barangay San Jose
    |--------------------------------------------------------------------------
    */

    private function residentIsFromSanJose(
        Resident $resident
    ): bool {
        if (
            in_array(
                $resident->purok,
                [
                    'Sitio 1',
                    'Sitio 2',
                    'Sitio 3',
                    'Sitio 4',
                ],
                true
            )
        ) {
            return true;
        }

        $address =
            strtolower(
                $this->residentAddress(
                    $resident
                )
            );

        return str_contains(
            $address,
            'barangay san jose'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Resident Address Helper
    |--------------------------------------------------------------------------
    */

    private function residentAddress(
        Resident $resident
    ): string {
        return collect([
            $resident->house_number,
            $resident->street,
            $resident->purok,
            $resident->address_details,
        ])
            ->filter()
            ->join(', ');
    }
}