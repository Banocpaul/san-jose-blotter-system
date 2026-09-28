<?php

namespace App\Http\Controllers;

use App\Enums\CaseStage;
use App\Enums\RecordStatus;
use App\Models\BlotterCase;
use App\Models\IncidentType;
use Illuminate\Http\Request;

class CaseManagementController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize(
            'viewAny',
            BlotterCase::class
        );

        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Base Case Visibility
        |--------------------------------------------------------------------------
        |
        | Captain / Secretary / Staff:
        |     See cases allowed by their role.
        |
        | Councilor:
        |     See cases currently assigned to them.
        |
        | Lupon:
        |     See cases linked to their mediation proceedings.
        |
        */

        $baseQuery = BlotterCase::query()
            ->visibleTo($user);

        /*
        |--------------------------------------------------------------------------
        | KPI Stage Counts
        |--------------------------------------------------------------------------
        |
        | One grouped query is used instead of one COUNT query per card.
        |
        */

        $kpiStageValues = collect(
            CaseStage::kpiStages()
        )
            ->map(
                fn (CaseStage $stage) =>
                    $stage->value
            )
            ->all();

        $rawStageCounts = (clone $baseQuery)
            ->whereIn(
                'case_stage',
                $kpiStageValues
            )
            ->selectRaw(
                'case_stage, COUNT(*) AS total'
            )
            ->groupBy(
                'case_stage'
            )
            ->pluck(
                'total',
                'case_stage'
            );

        $stageCounts = collect(
            CaseStage::kpiStages()
        )
            ->mapWithKeys(
                fn (CaseStage $stage) => [
                    $stage->value =>
                        (int) (
                            $rawStageCounts[
                                $stage->value
                            ] ?? 0
                        ),
                ]
            );

        /*
        |--------------------------------------------------------------------------
        | Main Case Query
        |--------------------------------------------------------------------------
        */

        $query = (clone $baseQuery)
            ->select([
                'blotter_cases.id',
                'blotter_cases.reference_number',
                'blotter_cases.incident_type_id',

                /*
                 * Legacy compatibility field.
                 * Kept in the query temporarily because other workflow
                 * modules still use it.
                 */
                'blotter_cases.status',

                /*
                 * New client-requested workflow fields.
                 */
                'blotter_cases.case_stage',
                'blotter_cases.record_status',

                'blotter_cases.updated_at',
            ])
            ->with([
                'incidentType:id,name',

                'complainants:id,blotter_case_id,first_name,middle_name,last_name,suffix',

                'respondents:id,blotter_case_id,first_name,middle_name,last_name,suffix',

                'currentAssignment.assignedOfficer:id,name',
            ])
            ->searchCase(
                $request
                    ->string('search')
                    ->toString()
            );

        /*
        |--------------------------------------------------------------------------
        | Current Stage Filter
        |--------------------------------------------------------------------------
        */

        if ($request->filled('stage')) {
            $stage =
                $request
                    ->string('stage')
                    ->toString();

            $validStages = array_map(
                fn (CaseStage $caseStage) =>
                    $caseStage->value,
                CaseStage::cases()
            );

            if (
                in_array(
                    $stage,
                    $validStages,
                    true
                )
            ) {
                $query->where(
                    'case_stage',
                    $stage
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Record Status Filter
        |--------------------------------------------------------------------------
        */

        if ($request->filled('record_status')) {
            $recordStatus =
                $request
                    ->string('record_status')
                    ->toString();

            $validRecordStatuses =
                array_map(
                    fn (RecordStatus $status) =>
                        $status->value,
                    RecordStatus::cases()
                );

            if (
                in_array(
                    $recordStatus,
                    $validRecordStatuses,
                    true
                )
            ) {
                $query->where(
                    'record_status',
                    $recordStatus
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Incident Type Filter
        |--------------------------------------------------------------------------
        */

        if (
            $request->filled(
                'incident_type_id'
            )
        ) {
            $query->where(
                'incident_type_id',
                (int) $request->input(
                    'incident_type_id'
                )
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Paginated Results
        |--------------------------------------------------------------------------
        */

        $cases = $query
            ->latest(
                'updated_at'
            )
            ->paginate(
                15
            )
            ->withQueryString();

        /*
        |--------------------------------------------------------------------------
        | Incident Types
        |--------------------------------------------------------------------------
        */

        $incidentTypes =
            IncidentType::query()
                ->where(
                    'is_active',
                    true
                )
                ->orderBy(
                    'name'
                )
                ->get([
                    'id',
                    'name',
                ]);

        /*
        |--------------------------------------------------------------------------
        | View
        |--------------------------------------------------------------------------
        */

        return view(
            'cases.index',
            [
                'cases' =>
                    $cases,

                'stageCounts' =>
                    $stageCounts,

                'stages' =>
                    CaseStage::cases(),

                'kpiStages' =>
                    CaseStage::kpiStages(),

                'recordStatuses' =>
                    RecordStatus::cases(),

                'incidentTypes' =>
                    $incidentTypes,
            ]
        );
    }
}