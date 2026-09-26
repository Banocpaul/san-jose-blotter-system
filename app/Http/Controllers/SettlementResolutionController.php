<?php

namespace App\Http\Controllers;

use App\Enums\CaseStage;
use App\Enums\CaseStatus;
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
                fn (Builder $session) => $session
                    ->where('lupon_member_id', $user->id)
            );
        }

        $kpiRow = (clone $baseQuery)
            ->selectRaw(
                "SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending_count"
            )
            ->selectRaw(
                "SUM(CASE WHEN status = 'Finalized' THEN 1 ELSE 0 END) AS finalized_count"
            )
            ->selectRaw(
                "SUM(CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END) AS resolved_count"
            )
            ->first();

        $kpis = [
            'pending' => (int) ($kpiRow?->pending_count ?? 0),
            'finalized' => (int) ($kpiRow?->finalized_count ?? 0),
            'resolved' => (int) ($kpiRow?->resolved_count ?? 0),
        ];

        $resolutions = (clone $baseQuery)
            ->select([
                'case_resolutions.id',
                'case_resolutions.blotter_case_id',
                'case_resolutions.mediation_outcome_id',
                'case_resolutions.resolution_type',
                'case_resolutions.status',
                'case_resolutions.agreement_details',
                'case_resolutions.finalized_by',
                'case_resolutions.finalized_at',
                'case_resolutions.resolved_by',
                'case_resolutions.resolved_at',
                'case_resolutions.updated_at',
            ])
            ->with([
                'blotterCase:id,reference_number,incident_type_id,status,case_stage,closed_at',
                'blotterCase.incidentType:id,name',
                'blotterCase.complainants:id,blotter_case_id,first_name,middle_name,last_name,suffix',
                'blotterCase.respondents:id,blotter_case_id,first_name,middle_name,last_name,suffix',
                'mediationOutcome:id,mediation_session_id,outcome,agreement_details,remarks,recorded_at',
                'mediationOutcome.mediationSession:id,hearing_number,proceeding_type,scheduled_date',
                'finalizedBy:id,name',
                'resolvedBy:id,name',
            ])
            ->when(
                $request->filled('search'),
                function (Builder $query) use ($request) {
                    $search = trim($request->string('search')->toString());

                    $query->where(function (Builder $inner) use ($search) {
                        $inner
                            ->where('resolution_type', 'like', "%{$search}%")
                            ->orWhereHas(
                                'blotterCase',
                                fn (Builder $case) => $case
                                    ->where('reference_number', 'like', "%{$search}%")
                            )
                            ->orWhereHas(
                                'blotterCase.complainants',
                                fn (Builder $person) => $person
                                    ->where('first_name', 'like', "%{$search}%")
                                    ->orWhere('middle_name', 'like', "%{$search}%")
                                    ->orWhere('last_name', 'like', "%{$search}%")
                            )
                            ->orWhereHas(
                                'blotterCase.respondents',
                                fn (Builder $person) => $person
                                    ->where('first_name', 'like', "%{$search}%")
                                    ->orWhere('middle_name', 'like', "%{$search}%")
                                    ->orWhere('last_name', 'like', "%{$search}%")
                            );
                    });
                }
            );

        $status = $request->string('status')->toString();

        if (in_array($status, ['Pending', 'Finalized', 'Resolved'], true)) {
            $resolutions->where('status', $status);
        }

        $resolutions = $resolutions
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('settlements.index', [
            'resolutions' => $resolutions,
            'kpis' => $kpis,
            'roleSlug' => $role,
        ]);
    }

    public function finalize(Request $request, CaseResolution $resolution)
    {
        $this->ensureManager($request);

        if ($resolution->status !== 'Pending') {
            return back()->withErrors([
                'resolution' => 'Only pending settlement records may be finalized.',
            ]);
        }

        $data = $request->validate([
            'agreement_details' => ['nullable', 'string', 'max:10000'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($resolution, $data, $request) {
            $locked = CaseResolution::whereKey($resolution->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'Pending') {
                abort(409, 'This settlement record has already been processed.');
            }

            $old = $locked->only([
                'status',
                'agreement_details',
                'remarks',
            ]);

            $locked->update([
                'status' => 'Finalized',
                'agreement_details' => $data['agreement_details']
                    ?? $locked->agreement_details,
                'remarks' => $data['remarks']
                    ?? $locked->remarks,
                'finalized_by' => $request->user()->id,
                'finalized_at' => now(),
            ]);

            AuditLogService::log(
                action: 'settlement_finalized',
                module: 'Settlement & Resolutions',
                description: 'Finalized settlement for case '
                    . $locked->blotterCase()->value('reference_number')
                    . '.',
                auditable: $locked,
                oldValues: $old,
                newValues: $locked->fresh()->toArray()
            );
        });

        return back()->with('success', 'Settlement finalized successfully.');
    }

    public function resolve(Request $request, CaseResolution $resolution)
    {
        $this->ensureManager($request);

        if ($resolution->status !== 'Finalized') {
            return back()->withErrors([
                'resolution' => 'Finalize the settlement before marking the case resolved.',
            ]);
        }

        DB::transaction(function () use ($resolution, $request) {
            $locked = CaseResolution::whereKey($resolution->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'Finalized') {
                abort(409, 'This settlement record is no longer awaiting resolution.');
            }

            $case = BlotterCase::whereKey($locked->blotter_case_id)
                ->lockForUpdate()
                ->firstOrFail();

            $oldResolutionStatus = $locked->status;
            $oldCaseStatus = $case->status?->value ?? (string) $case->status;

            $locked->update([
                'status' => 'Resolved',
                'resolved_by' => $request->user()->id,
                'resolved_at' => now(),
            ]);

            $case->update([
                'status' => CaseStatus::Resolved,
                'case_stage' => CaseStage::SettledResolved,
                'closed_at' => now(),
            ]);

            AuditLogService::log(
                action: 'case_resolution_completed',
                module: 'Settlement & Resolutions',
                description: 'Marked case '
                    . $case->reference_number
                    . ' as resolved.',
                auditable: $locked,
                oldValues: [
                    'resolution_status' => $oldResolutionStatus,
                    'case_status' => $oldCaseStatus,
                ],
                newValues: [
                    'resolution_status' => 'Resolved',
                    'case_status' => CaseStatus::Resolved->value,
                    'resolved_at' => $locked->resolved_at,
                ]
            );
        });

        return back()->with('success', 'Case marked as resolved.');
    }

    private function ensureManager(Request $request): void
    {
        $role = $request->user()?->role?->slug;

        if (! in_array($role, ['barangay_captain', 'secretary'], true)) {
            abort(403);
        }
    }
}
