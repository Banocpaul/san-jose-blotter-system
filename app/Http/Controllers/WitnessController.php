<?php

namespace App\Http\Controllers;

use App\Enums\CaseStatus;
use App\Models\BlotterCase;
use App\Models\CaseWitness;
use App\Models\Resident;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WitnessController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Store Witness
    |--------------------------------------------------------------------------
    */

    public function store(
        Request $request,
        BlotterCase $blotter
    ) {
        $this->authorize(
            'manageWitnesses',
            $blotter
        );

        /*
         * Prevent witness modification
         * once the case has been closed.
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
                'witness' =>
                    'Witnesses cannot be added to a closed case.',
            ]);
        }

        $data = $request->validate([
            'resident_id' => [
                'nullable',
                'integer',
                'exists:residents,id',
            ],

            'first_name' => [
                'required_without:resident_id',
                'nullable',
                'string',
                'max:100',
            ],

            'middle_name' => [
                'nullable',
                'string',
                'max:100',
            ],

            'last_name' => [
                'required_without:resident_id',
                'nullable',
                'string',
                'max:100',
            ],

            'suffix' => [
                'nullable',
                'string',
                'max:30',
            ],

            'contact_number' => [
                'nullable',
                'string',
                'max:30',
            ],

            'address' => [
                'nullable',
                'string',
                'max:500',
            ],

            'statement' => [
                'nullable',
                'string',
                'max:10000',
            ],
        ]);

        $resident = null;

        if (
            ! empty(
                $data['resident_id']
            )
        ) {
            $resident = Resident::whereKey(
                $data['resident_id']
            )
                ->where(
                    'is_active',
                    true
                )
                ->first();

            if (! $resident) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'resident_id' =>
                            'The selected resident is not active.',
                    ]);
            }
        }

        DB::transaction(
            function () use (
                $blotter,
                $resident,
                $data
            ) {
                $witness = CaseWitness::create([
                    'blotter_case_id' =>
                        $blotter->id,

                    'resident_id' =>
                        $resident?->id,

                    'first_name' =>
                        $resident?->first_name
                        ?? $data['first_name'],

                    'middle_name' =>
                        $resident?->middle_name
                        ?? (
                            $data['middle_name']
                            ?? null
                        ),

                    'last_name' =>
                        $resident?->last_name
                        ?? $data['last_name'],

                    'suffix' =>
                        $resident?->suffix
                        ?? (
                            $data['suffix']
                            ?? null
                        ),

                    'contact_number' =>
                        $resident?->contact_number
                        ?? (
                            $data['contact_number']
                            ?? null
                        ),

                    'address' =>
                        $resident
                            ? $this->residentAddress(
                                $resident
                            )
                            : (
                                $data['address']
                                ?? null
                            ),

                    'statement' =>
                        $data['statement']
                        ?? null,
                ]);

                AuditLogService::log(
                    action:
                        'witness_created',

                    module:
                        'Blotter Cases',

                    description:
                        "Witness {$witness->full_name} was added to case {$blotter->reference_number}.",

                    auditable:
                        $witness,

                    newValues:
                        $this->witnessAuditSnapshot(
                            $witness,
                            $blotter
                        )
                );
            }
        );

        return back()->with(
            'success',
            'Witness added successfully.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update Witness
    |--------------------------------------------------------------------------
    */

    public function update(
        Request $request,
        CaseWitness $witness
    ) {
        $witness->load(
            'blotterCase'
        );

        $case =
            $witness->blotterCase;

        $this->authorize(
            'manageWitnesses',
            $case
        );

        $status = $case->status instanceof CaseStatus
            ? $case->status
            : CaseStatus::from(
                $case->status
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
                'witness' =>
                    'Witness information cannot be changed because this case is already closed.',
            ]);
        }

        $data = $request->validate([
            'first_name' => [
                'required',
                'string',
                'max:100',
            ],

            'middle_name' => [
                'nullable',
                'string',
                'max:100',
            ],

            'last_name' => [
                'required',
                'string',
                'max:100',
            ],

            'suffix' => [
                'nullable',
                'string',
                'max:30',
            ],

            'contact_number' => [
                'nullable',
                'string',
                'max:30',
            ],

            'address' => [
                'nullable',
                'string',
                'max:500',
            ],

            'statement' => [
                'nullable',
                'string',
                'max:10000',
            ],
        ]);

        DB::transaction(
            function () use (
                $witness,
                $case,
                $data
            ) {
                $oldValues =
                    $this->witnessAuditSnapshot(
                        $witness,
                        $case
                    );

                $oldName =
                    $witness->full_name;

                $witness->update([
                    'first_name' =>
                        $data['first_name'],

                    'middle_name' =>
                        $data['middle_name']
                        ?? null,

                    'last_name' =>
                        $data['last_name'],

                    'suffix' =>
                        $data['suffix']
                        ?? null,

                    'contact_number' =>
                        $data['contact_number']
                        ?? null,

                    'address' =>
                        $data['address']
                        ?? null,

                    'statement' =>
                        $data['statement']
                        ?? null,
                ]);

                $witness->refresh();

                AuditLogService::log(
                    action:
                        'witness_updated',

                    module:
                        'Blotter Cases',

                    description:
                        "Witness {$oldName} was updated in case {$case->reference_number}.",

                    auditable:
                        $witness,

                    oldValues:
                        $oldValues,

                    newValues:
                        $this->witnessAuditSnapshot(
                            $witness,
                            $case
                        )
                );
            }
        );

        return back()->with(
            'success',
            'Witness information updated successfully.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Remove Witness
    |--------------------------------------------------------------------------
    */

    public function destroy(
        CaseWitness $witness
    ) {
        $witness->load(
            'blotterCase'
        );

        $case =
            $witness->blotterCase;

        $this->authorize(
            'manageWitnesses',
            $case
        );

        $status = $case->status instanceof CaseStatus
            ? $case->status
            : CaseStatus::from(
                $case->status
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
                'witness' =>
                    'Witnesses cannot be removed from a closed case.',
            ]);
        }

        DB::transaction(
            function () use (
                $witness,
                $case
            ) {
                $oldValues =
                    $this->witnessAuditSnapshot(
                        $witness,
                        $case
                    );

                $witnessName =
                    $witness->full_name;

                $witness->delete();

                AuditLogService::log(
                    action:
                        'witness_deleted',

                    module:
                        'Blotter Cases',

                    description:
                        "Witness {$witnessName} was removed from case {$case->reference_number}.",

                    auditable:
                        $witness,

                    oldValues:
                        $oldValues,

                    newValues: [
                        'deleted' =>
                            true,
                    ]
                );
            }
        );

        return back()->with(
            'success',
            'Witness removed successfully.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Witness Audit Snapshot
    |--------------------------------------------------------------------------
    */

    private function witnessAuditSnapshot(
        CaseWitness $witness,
        BlotterCase $case
    ): array {
        return [
            'witness_id' =>
                $witness->id,

            'blotter_case_id' =>
                $case->id,

            'reference_number' =>
                $case->reference_number,

            'resident_id' =>
                $witness->resident_id,

            'first_name' =>
                $witness->first_name,

            'middle_name' =>
                $witness->middle_name,

            'last_name' =>
                $witness->last_name,

            'suffix' =>
                $witness->suffix,

            'full_name' =>
                $witness->full_name,

            'contact_number' =>
                $witness->contact_number,

            'address' =>
                $witness->address,

            'statement' =>
                $witness->statement,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Build Resident Address
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
