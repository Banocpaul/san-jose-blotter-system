<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResidentRequest;
use App\Models\Resident;
use App\Services\AuditLogService;
use App\Services\ResidentReferenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResidentController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Resident List
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $status = $request->get(
            'status',
            'active'
        );

        if ($status === 'archived') {
            $query =
                Resident::onlyTrashed()
                    ->whereHas(
                        'complaints'
                    );
        } else {
            $query =
                Resident::query()
                    ->whereHas(
                        'complaints'
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
                        'resident_code',
                        'like',
                        "%{$search}%"
                    )
                        ->orWhere(
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
                        ->orWhere(
                            'contact_number',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'street',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'purok',
                            'like',
                            "%{$search}%"
                        );
                }
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Results
        |--------------------------------------------------------------------------
        */

        $residents = $query
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view(
            'residents.index',
            compact(
                'residents',
                'status'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Create Resident Form
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        return view(
            'residents.create'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Store Resident
    |--------------------------------------------------------------------------
    */

    public function store(
        ResidentRequest $request,
        ResidentReferenceService $referenceService
    ) {
        $resident = DB::transaction(
            function () use (
                $request,
                $referenceService
            ) {
                $data =
                    $request->validated();

                $data['resident_code'] =
                    $referenceService->generate();

                $data['created_by'] =
                    auth()->id();

                $resident =
                    Resident::create(
                        $data
                    );

                /*
                |--------------------------------------------------------------------------
                | Audit Trail
                |--------------------------------------------------------------------------
                */

                AuditLogService::log(
                    action: 'created',
                    module: 'Complainant Records',
                    description:
                        "Complainant record {$resident->resident_code} was registered.",
                    auditable: $resident,
                    newValues:
                        $this->residentAuditSnapshot(
                            $resident
                        )
                );

                return $resident;
            }
        );

        return redirect()
            ->route(
                'residents.show',
                $resident
            )
            ->with(
                'success',
                'Complainant record registered successfully.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Show Resident
    |--------------------------------------------------------------------------
    */

    public function show(
        Resident $resident
    ) {
        $resident->load([
            'complaints.blotterCase',
            'responses.blotterCase',
            'witnessRecords.blotterCase',
        ]);

        return view(
            'residents.show',
            compact('resident')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Edit Resident
    |--------------------------------------------------------------------------
    */

    public function edit(
        Resident $resident
    ) {
        return view(
            'residents.edit',
            compact('resident')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update Resident
    |--------------------------------------------------------------------------
    */

    public function update(
        ResidentRequest $request,
        Resident $resident
    ) {
        DB::transaction(
            function () use (
                $request,
                $resident
            ) {
                $oldValues =
                    $this->residentAuditSnapshot(
                        $resident
                    );

                $resident->update(
                    $request->validated()
                );

                $resident->refresh();

                $newValues =
                    $this->residentAuditSnapshot(
                        $resident
                    );

                AuditLogService::log(
                    action: 'updated',
                    module: 'Complainant Records',
                    description:
                        "Complainant record {$resident->resident_code} was updated.",
                    auditable: $resident,
                    oldValues:
                        $oldValues,
                    newValues:
                        $newValues
                );
            }
        );

        return redirect()
            ->route(
                'residents.show',
                $resident
            )
            ->with(
                'success',
                'Complainant record updated successfully.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Archive Resident
    |--------------------------------------------------------------------------
    */

    public function destroy(
        Resident $resident
    ) {
        DB::transaction(
            function () use ($resident) {
                $oldValues =
                    $this->residentAuditSnapshot(
                        $resident
                    );

                $residentCode =
                    $resident->resident_code;

                $resident->delete();

                AuditLogService::log(
                    action: 'archived',
                    module: 'Complainant Records',
                    description:
                        "Complainant record {$residentCode} was archived.",
                    auditable: $resident,
                    oldValues:
                        $oldValues,
                    newValues: [
                        'archived' =>
                            true,

                        'archived_at' =>
                            now()->toDateTimeString(),
                    ]
                );
            }
        );

        return redirect()
            ->route(
                'residents.index'
            )
            ->with(
                'success',
                'Complainant record archived successfully.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Restore Resident
    |--------------------------------------------------------------------------
    */

    public function restore(
        int $id
    ) {
        $resident =
            Resident::onlyTrashed()
                ->findOrFail($id);

        DB::transaction(
            function () use ($resident) {
                $oldValues =
                    $this->residentAuditSnapshot(
                        $resident
                    );

                $resident->restore();

                $resident->refresh();

                AuditLogService::log(
                    action: 'restored',
                    module: 'Complainant Records',
                    description:
                        "Complainant record {$resident->resident_code} was restored.",
                    auditable: $resident,
                    oldValues:
                        $oldValues,
                    newValues:
                        $this->residentAuditSnapshot(
                            $resident
                        )
                );
            }
        );

        return redirect()
            ->route(
                'residents.index'
            )
            ->with(
                'success',
                'Complainant record restored successfully.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Resident Audit Snapshot
    |--------------------------------------------------------------------------
    |
    | This creates a stable snapshot of the resident record so that the
    | Audit Trail can display the values before and after an operation.
    |
    */

    private function residentAuditSnapshot(
        Resident $resident
    ): array {
        $attributes =
            $resident->getAttributes();

        /*
        |--------------------------------------------------------------------------
        | Remove Framework Timestamps
        |--------------------------------------------------------------------------
        |
        | updated_at changes automatically and would add noise to every audit
        | entry. deleted_at remains useful because it identifies archive state.
        |
        */

        unset(
            $attributes['created_at'],
            $attributes['updated_at']
        );

        return $attributes;
    }
}