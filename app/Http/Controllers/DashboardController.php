<?php

namespace App\Http\Controllers;

use App\Enums\CaseStage;
use App\Enums\RecordStatus;
use App\Models\BlotterCase;
use App\Models\MediationSession;
use App\Models\Resident;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $role = $user->role?->slug;

        /*
        |--------------------------------------------------------------------------
        | Default Dashboard Data
        |--------------------------------------------------------------------------
        */

        $data = [
            'role' => $role,

            'totalResidents' => 0,
            'totalCases' => 0,

            /*
             * Overall Record Status
             */
            'openCases' => 0,
            'resolvedRecordCases' => 0,
            'closedRecordCases' => 0,

            /*
             * Detailed Current Stage
             */
            'newCases' => 0,
            'underAssessmentCases' => 0,
            'forMediationCases' => 0,
            'forPangkatCases' => 0,
            'settledResolvedCases' => 0,
            'cfaCases' => 0,
            'closedStageCases' => 0,

            'assignedCases' => 0,
            'scheduledHearings' => 0,

            'recentCases' => collect(),
            'upcomingHearings' => collect(),
        ];

        /*
        |--------------------------------------------------------------------------
        | Barangay Captain / Secretary
        |--------------------------------------------------------------------------
        */

        if (
            in_array(
                $role,
                [
                    'barangay_captain',
                    'secretary',
                ],
                true
            )
        ) {
            $data['totalResidents'] =
                Resident::active()->count();

            /*
            |--------------------------------------------------------------------------
            | Current Stage Counts
            |--------------------------------------------------------------------------
            */

            $stageCounts =
                BlotterCase::query()
                    ->select('case_stage')
                    ->selectRaw(
                        'COUNT(*) AS total'
                    )
                    ->groupBy('case_stage')
                    ->pluck(
                        'total',
                        'case_stage'
                    );

            /*
            |--------------------------------------------------------------------------
            | Record Status Counts
            |--------------------------------------------------------------------------
            */

            $recordStatusCounts =
                BlotterCase::query()
                    ->select('record_status')
                    ->selectRaw(
                        'COUNT(*) AS total'
                    )
                    ->groupBy('record_status')
                    ->pluck(
                        'total',
                        'record_status'
                    );

            $data['totalCases'] =
                (int) $recordStatusCounts->sum();

            /*
             * Overall Record Status
             */

            $data['openCases'] =
                (int) (
                    $recordStatusCounts[
                        RecordStatus::Open->value
                    ] ?? 0
                );

            $data['resolvedRecordCases'] =
                (int) (
                    $recordStatusCounts[
                        RecordStatus::Resolved->value
                    ] ?? 0
                );

            $data['closedRecordCases'] =
                (int) (
                    $recordStatusCounts[
                        RecordStatus::Closed->value
                    ] ?? 0
                );

            /*
             * Current Stage
             */

            $data['newCases'] =
                (int) (
                    $stageCounts[
                        CaseStage::New->value
                    ] ?? 0
                );

            $data['underAssessmentCases'] =
                (int) (
                    $stageCounts[
                        CaseStage::UnderAssessment->value
                    ] ?? 0
                );

            $data['forMediationCases'] =
                (int) (
                    $stageCounts[
                        CaseStage::ForMediation->value
                    ] ?? 0
                );

            $data['forPangkatCases'] =
                (int) (
                    $stageCounts[
                        CaseStage::ForPangkatConciliation->value
                    ] ?? 0
                );

            $data['settledResolvedCases'] =
                (int) (
                    $stageCounts[
                        CaseStage::SettledResolved->value
                    ] ?? 0
                );

            $data['cfaCases'] =
                (int) (
                    $stageCounts[
                        CaseStage::ForFurtherActionCfa->value
                    ] ?? 0
                );

            $data['closedStageCases'] =
                (int) (
                    $stageCounts[
                        CaseStage::Closed->value
                    ] ?? 0
                );

            /*
            |--------------------------------------------------------------------------
            | Scheduled Hearings
            |--------------------------------------------------------------------------
            */

            $data['scheduledHearings'] =
                MediationSession::where(
                    'status',
                    'Scheduled'
                )->count();

            /*
            |--------------------------------------------------------------------------
            | Recent Cases
            |--------------------------------------------------------------------------
            */

            $data['recentCases'] =
                BlotterCase::query()
                    ->select([
                        'id',
                        'reference_number',
                        'incident_type_id',
                        'case_stage',
                        'record_status',
                        'reported_at',
                    ])
                    ->with([
                        'incidentType:id,name',
                        'complainants:id,blotter_case_id,first_name,last_name',
                        'respondents:id,blotter_case_id,first_name,last_name',
                    ])
                    ->latest('reported_at')
                    ->limit(5)
                    ->get();

            /*
            |--------------------------------------------------------------------------
            | Upcoming Hearings
            |--------------------------------------------------------------------------
            */

            $data['upcomingHearings'] =
                MediationSession::query()
                    ->select([
                        'id',
                        'blotter_case_id',
                        'hearing_number',
                        'scheduled_date',
                        'scheduled_time',
                        'venue',
                        'lupon_member_id',
                        'status',
                    ])
                    ->with([
                        'blotterCase:id,reference_number',
                        'luponMember:id,name',
                    ])
                    ->where(
                        'status',
                        'Scheduled'
                    )
                    ->where(
                        'scheduled_date',
                        '>=',
                        today()->toDateString()
                    )
                    ->orderBy(
                        'scheduled_date'
                    )
                    ->orderBy(
                        'scheduled_time'
                    )
                    ->limit(5)
                    ->get();

            return view(
                'dashboard',
                $data
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Staff Dashboard
        |--------------------------------------------------------------------------
        */

        if ($role === 'staff') {
            $data['totalResidents'] =
                Resident::active()->count();

            $stageCounts =
                BlotterCase::query()
                    ->select('case_stage')
                    ->selectRaw(
                        'COUNT(*) AS total'
                    )
                    ->groupBy('case_stage')
                    ->pluck(
                        'total',
                        'case_stage'
                    );

            $recordStatusCounts =
                BlotterCase::query()
                    ->select('record_status')
                    ->selectRaw(
                        'COUNT(*) AS total'
                    )
                    ->groupBy('record_status')
                    ->pluck(
                        'total',
                        'record_status'
                    );

            $data['totalCases'] =
                (int) $recordStatusCounts->sum();

            $data['openCases'] =
                (int) (
                    $recordStatusCounts[
                        RecordStatus::Open->value
                    ] ?? 0
                );

            $data['resolvedRecordCases'] =
                (int) (
                    $recordStatusCounts[
                        RecordStatus::Resolved->value
                    ] ?? 0
                );

            $data['closedRecordCases'] =
                (int) (
                    $recordStatusCounts[
                        RecordStatus::Closed->value
                    ] ?? 0
                );

            $data['newCases'] =
                (int) (
                    $stageCounts[
                        CaseStage::New->value
                    ] ?? 0
                );

            $data['underAssessmentCases'] =
                (int) (
                    $stageCounts[
                        CaseStage::UnderAssessment->value
                    ] ?? 0
                );

            $data['recentCases'] =
                BlotterCase::query()
                    ->select([
                        'id',
                        'reference_number',
                        'incident_type_id',
                        'case_stage',
                        'record_status',
                        'reported_at',
                    ])
                    ->with([
                        'incidentType:id,name',
                        'complainants:id,blotter_case_id,first_name,last_name',
                        'respondents:id,blotter_case_id,first_name,last_name',
                    ])
                    ->latest('reported_at')
                    ->limit(5)
                    ->get();

            return view(
                'dashboard',
                $data
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Councilor Dashboard
        |--------------------------------------------------------------------------
        */

        if ($role === 'councilor') {
            $assignedQuery =
                BlotterCase::query()
                    ->whereHas(
                        'assignments',
                        function ($query) use ($user) {
                            $query
                                ->where(
                                    'assigned_to',
                                    $user->id
                                )
                                ->whereNull(
                                    'completed_at'
                                );
                        }
                    );

            $assignedStageCounts =
                (clone $assignedQuery)
                    ->select(
                        'blotter_cases.case_stage'
                    )
                    ->selectRaw(
                        'COUNT(*) AS total'
                    )
                    ->groupBy(
                        'blotter_cases.case_stage'
                    )
                    ->pluck(
                        'total',
                        'blotter_cases.case_stage'
                    );

            $assignedRecordCounts =
                (clone $assignedQuery)
                    ->select(
                        'blotter_cases.record_status'
                    )
                    ->selectRaw(
                        'COUNT(*) AS total'
                    )
                    ->groupBy(
                        'blotter_cases.record_status'
                    )
                    ->pluck(
                        'total',
                        'blotter_cases.record_status'
                    );

            $data['assignedCases'] =
                (int) $assignedRecordCounts->sum();

            $data['openCases'] =
                (int) (
                    $assignedRecordCounts[
                        RecordStatus::Open->value
                    ] ?? 0
                );

            $data['newCases'] =
                (int) (
                    $assignedStageCounts[
                        CaseStage::New->value
                    ] ?? 0
                );

            $data['underAssessmentCases'] =
                (int) (
                    $assignedStageCounts[
                        CaseStage::UnderAssessment->value
                    ] ?? 0
                );

            $data['recentCases'] =
                (clone $assignedQuery)
                    ->select([
                        'blotter_cases.id',
                        'blotter_cases.reference_number',
                        'blotter_cases.incident_type_id',
                        'blotter_cases.case_stage',
                        'blotter_cases.record_status',
                        'blotter_cases.reported_at',
                    ])
                    ->with([
                        'incidentType:id,name',
                        'complainants:id,blotter_case_id,first_name,last_name',
                        'respondents:id,blotter_case_id,first_name,last_name',
                    ])
                    ->latest(
                        'reported_at'
                    )
                    ->limit(5)
                    ->get();

            return view(
                'dashboard',
                $data
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Lupon Dashboard
        |--------------------------------------------------------------------------
        */

        if ($role === 'lupon') {
            $mediationCaseQuery =
                BlotterCase::query()
                    ->whereHas(
                        'mediationSessions',
                        function ($query) use ($user) {
                            $query->where(
                                'lupon_member_id',
                                $user->id
                            );
                        }
                    );

            $mediationStageCounts =
                (clone $mediationCaseQuery)
                    ->select(
                        'blotter_cases.case_stage'
                    )
                    ->selectRaw(
                        'COUNT(*) AS total'
                    )
                    ->groupBy(
                        'blotter_cases.case_stage'
                    )
                    ->pluck(
                        'total',
                        'blotter_cases.case_stage'
                    );

            $mediationRecordCounts =
                (clone $mediationCaseQuery)
                    ->select(
                        'blotter_cases.record_status'
                    )
                    ->selectRaw(
                        'COUNT(*) AS total'
                    )
                    ->groupBy(
                        'blotter_cases.record_status'
                    )
                    ->pluck(
                        'total',
                        'blotter_cases.record_status'
                    );

            $data['assignedCases'] =
                (int) $mediationRecordCounts->sum();

            $data['openCases'] =
                (int) (
                    $mediationRecordCounts[
                        RecordStatus::Open->value
                    ] ?? 0
                );

            $data['forMediationCases'] =
                (int) (
                    $mediationStageCounts[
                        CaseStage::ForMediation->value
                    ] ?? 0
                );

            $data['forPangkatCases'] =
                (int) (
                    $mediationStageCounts[
                        CaseStage::ForPangkatConciliation->value
                    ] ?? 0
                );

            $data['scheduledHearings'] =
                MediationSession::where(
                    'lupon_member_id',
                    $user->id
                )
                    ->where(
                        'status',
                        'Scheduled'
                    )
                    ->count();

            $data['recentCases'] =
                (clone $mediationCaseQuery)
                    ->select([
                        'blotter_cases.id',
                        'blotter_cases.reference_number',
                        'blotter_cases.incident_type_id',
                        'blotter_cases.case_stage',
                        'blotter_cases.record_status',
                        'blotter_cases.reported_at',
                    ])
                    ->with([
                        'incidentType:id,name',
                        'complainants:id,blotter_case_id,first_name,last_name',
                        'respondents:id,blotter_case_id,first_name,last_name',
                    ])
                    ->latest(
                        'reported_at'
                    )
                    ->limit(5)
                    ->get();

            $data['upcomingHearings'] =
                MediationSession::query()
                    ->select([
                        'id',
                        'blotter_case_id',
                        'hearing_number',
                        'scheduled_date',
                        'scheduled_time',
                        'venue',
                        'lupon_member_id',
                        'status',
                    ])
                    ->with([
                        'blotterCase:id,reference_number',
                        'luponMember:id,name',
                    ])
                    ->where(
                        'lupon_member_id',
                        $user->id
                    )
                    ->where(
                        'status',
                        'Scheduled'
                    )
                    ->where(
                        'scheduled_date',
                        '>=',
                        today()->toDateString()
                    )
                    ->orderBy(
                        'scheduled_date'
                    )
                    ->orderBy(
                        'scheduled_time'
                    )
                    ->limit(5)
                    ->get();

            return view(
                'dashboard',
                $data
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Fallback
        |--------------------------------------------------------------------------
        */

        return view(
            'dashboard',
            $data
        );
    }
}