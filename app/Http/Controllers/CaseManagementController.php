<?php

namespace App\Http\Controllers;

use App\Enums\CaseStage;
use App\Enums\CaseStatus;
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
        |------------------------------------------------------------------
        | Base access scope
        |------------------------------------------------------------------
        |
        | The same role restrictions are reused for both KPI counts and the
        | paginated case list. This prevents KPI cards from exposing counts
        | for records the current user cannot access.
        |
        */
        $baseQuery = BlotterCase::query()
            ->visibleTo($user);

        /*
        |------------------------------------------------------------------
        | KPI counts - one grouped query
        |------------------------------------------------------------------
        |
        | Do not execute one COUNT query per card. One GROUP BY query returns
        | all five client-requested KPI values.
        |
        */
        $kpiStageValues = collect(CaseStage::kpiStages())
            ->map(fn (CaseStage $stage) => $stage->value)
            ->all();

        $rawStageCounts = (clone $baseQuery)
            ->whereIn('case_stage', $kpiStageValues)
            ->selectRaw('case_stage, COUNT(*) AS total')
            ->groupBy('case_stage')
            ->pluck('total', 'case_stage');

        $stageCounts = collect(CaseStage::kpiStages())
            ->mapWithKeys(
                fn (CaseStage $stage) => [
                    $stage->value => (int) ($rawStageCounts[$stage->value] ?? 0),
                ]
            );

        /*
        |------------------------------------------------------------------
        | Case list
        |------------------------------------------------------------------
        */
        $query = (clone $baseQuery)
            ->select([
                'blotter_cases.id',
                'blotter_cases.reference_number',
                'blotter_cases.incident_type_id',
                'blotter_cases.status',
                'blotter_cases.case_stage',
                'blotter_cases.updated_at',
            ])
            ->with([
                'incidentType:id,name',
                'complainants:id,blotter_case_id,first_name,middle_name,last_name,suffix',
                'respondents:id,blotter_case_id,first_name,middle_name,last_name,suffix',
                'currentAssignment.assignedOfficer:id,name',
            ])
            ->searchCase(
                $request->string('search')->toString()
            );

        if ($request->filled('stage')) {
            $query->where(
                'case_stage',
                $request->string('stage')->toString()
            );
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->string('status')->toString()
            );
        }

        if ($request->filled('incident_type_id')) {
            $query->where(
                'incident_type_id',
                (int) $request->input('incident_type_id')
            );
        }

        $cases = $query
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        $incidentTypes = IncidentType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('cases.index', [
            'cases' => $cases,
            'stageCounts' => $stageCounts,
            'stages' => CaseStage::cases(),
            'kpiStages' => CaseStage::kpiStages(),
            'statuses' => CaseStatus::cases(),
            'incidentTypes' => $incidentTypes,
        ]);
    }
}
