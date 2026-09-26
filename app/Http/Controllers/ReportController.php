<?php

namespace App\Http\Controllers;

use App\Enums\CaseStage;
use App\Enums\CaseStatus;
use App\Models\BlotterCase;
use App\Models\IncidentType;
use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $filtered = $this->filteredQuery($request);

        /*
        |------------------------------------------------------------------
        | Filter-aware KPI summary
        |------------------------------------------------------------------
        |
        | One aggregate query supplies all report KPIs. This avoids running
        | one COUNT query per card every time the report page is opened.
        |
        */
        $summary = (clone $filtered)
            ->selectRaw(
                "COUNT(*) AS total_cases,
                 SUM(CASE WHEN case_stage NOT IN (?, ?) THEN 1 ELSE 0 END) AS active_cases,
                 SUM(CASE WHEN case_stage = ? THEN 1 ELSE 0 END) AS settled_cases,
                 SUM(CASE WHEN case_stage = ? THEN 1 ELSE 0 END) AS cfa_cases",
                [
                    CaseStage::SettledResolved->value,
                    CaseStage::Closed->value,
                    CaseStage::SettledResolved->value,
                    CaseStage::ForFurtherActionCfa->value,
                ]
            )
            ->first();

        $cases = $this->reportRowsQuery($request)
            ->latest('incident_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('reports.index', [
            'cases' => $cases,
            'summary' => $summary,
            'incidentTypes' => IncidentType::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'caseStages' => CaseStage::cases(),
            'caseStatuses' => CaseStatus::cases(),
        ]);
    }

    public function print(Request $request)
    {
        $cases = $this->reportRowsQuery($request)
            ->latest('incident_date')
            ->latest('id')
            ->get();

        AuditLogService::log(
            action: 'printed',
            module: 'Reports & Export',
            description: 'Printed a filtered case report.'
        );

        return view('reports.print', [
            'cases' => $cases,
            'filters' => $this->filterLabels($request),
            'generatedAt' => now(),
        ]);
    }

    public function export(Request $request)
    {
        $cases = $this->reportRowsQuery($request)
            ->latest('incident_date')
            ->latest('id')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Case Report');

        $headers = [
            'Case Number',
            'Incident Date',
            'Incident Type',
            'Complainant(s)',
            'Respondent(s)',
            'Location',
            'Case Stage',
            'Status',
            'Reported At',
        ];

        foreach ($headers as $index => $heading) {
            $column = Coordinate::stringFromColumnIndex($index + 1);

            $sheet->setCellValueExplicit(
                "{$column}1",
                $heading,
                DataType::TYPE_STRING
            );
        }

        $row = 2;

        foreach ($cases as $case) {
            $values = [
                $case->reference_number,
                $case->incident_date?->format('Y-m-d') ?? '',
                $case->incidentType?->name ?? '',
                $this->partyText($case->complainants),
                $this->partyText($case->respondents),
                $case->location ?? '',
                $this->enumValue($case->case_stage),
                $this->enumValue($case->status),
                $case->reported_at?->format('Y-m-d H:i:s') ?? '',
            ];

            foreach ($values as $index => $value) {
                $column = Coordinate::stringFromColumnIndex($index + 1);

                $sheet->setCellValueExplicit(
                    "{$column}{$row}",
                    (string) $value,
                    DataType::TYPE_STRING
                );
            }

            $row++;
        }

        $lastRow = max(1, $row - 1);

        $sheet->getStyle('A1:I1')->getFont()->setBold(true);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:I{$lastRow}");
        $sheet->getStyle("A1:I{$lastRow}")
            ->getAlignment()
            ->setVertical(
                \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP
            );

        if ($lastRow >= 2) {
            $sheet->getStyle("D2:F{$lastRow}")
                ->getAlignment()
                ->setWrapText(true);
        }

        $widths = [
            'A' => 20,
            'B' => 15,
            'C' => 28,
            'D' => 38,
            'E' => 38,
            'F' => 38,
            'G' => 27,
            'H' => 22,
            'I' => 22,
        ];

        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $filterSheet = $spreadsheet->createSheet();
        $filterSheet->setTitle('Report Filters');
        $filterSheet->setCellValue('A1', 'Filter');
        $filterSheet->setCellValue('B1', 'Value');
        $filterSheet->getStyle('A1:B1')->getFont()->setBold(true);

        $filterRow = 2;
        foreach ($this->filterLabels($request) as $label => $value) {
            $filterSheet->setCellValueExplicit(
                "A{$filterRow}",
                (string) $label,
                DataType::TYPE_STRING
            );
            $filterSheet->setCellValueExplicit(
                "B{$filterRow}",
                (string) $value,
                DataType::TYPE_STRING
            );
            $filterRow++;
        }

        $filterSheet->setCellValueExplicit(
            "A{$filterRow}",
            'Records Exported',
            DataType::TYPE_STRING
        );
        $filterSheet->setCellValueExplicit(
            "B{$filterRow}",
            (string) $cases->count(),
            DataType::TYPE_STRING
        );

        $filterSheet->getColumnDimension('A')->setWidth(24);
        $filterSheet->getColumnDimension('B')->setWidth(55);

        AuditLogService::log(
            action: 'exported',
            module: 'Reports & Export',
            description: 'Exported a filtered case report to Excel.'
        );

        $filename = 'barangay-san-jose-case-report-'
            . now()->format('Y-m-d_H-i-s')
            . '.xlsx';

        return response()->streamDownload(
            function () use ($spreadsheet) {
                $writer = new Xlsx($spreadsheet);
                $writer->save('php://output');
                $spreadsheet->disconnectWorksheets();
            },
            $filename,
            [
                'Content-Type' =>
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' =>
                    'max-age=0, no-cache, no-store, must-revalidate',
                'Pragma' => 'public',
            ]
        );
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = BlotterCase::query();

        $query->searchCase(
            $request->string('search')->toString()
        );

        if ($request->filled('incident_type_id')) {
            $query->where(
                'incident_type_id',
                (int) $request->input('incident_type_id')
            );
        }

        if ($request->filled('case_stage')) {
            $stage = $request->string('case_stage')->toString();
            $validStages = array_map(
                fn (CaseStage $caseStage) => $caseStage->value,
                CaseStage::cases()
            );

            if (in_array($stage, $validStages, true)) {
                $query->where('case_stage', $stage);
            }
        }

        if ($request->filled('status')) {
            $status = $request->string('status')->toString();
            $validStatuses = array_map(
                fn (CaseStatus $caseStatus) => $caseStatus->value,
                CaseStatus::cases()
            );

            if (in_array($status, $validStatuses, true)) {
                $query->where('status', $status);
            }
        }

        if ($request->filled('date_from')) {
            $query->where(
                'incident_date',
                '>=',
                $request->input('date_from')
            );
        }

        if ($request->filled('date_to')) {
            $query->where(
                'incident_date',
                '<=',
                $request->input('date_to')
            );
        }

        return $query;
    }

    private function reportRowsQuery(Request $request): Builder
    {
        return $this->filteredQuery($request)
            ->select([
                'id',
                'reference_number',
                'incident_type_id',
                'incident_date',
                'location',
                'status',
                'case_stage',
                'reported_at',
            ])
            ->with([
                'incidentType:id,name',
                'complainants:id,blotter_case_id,first_name,middle_name,last_name,suffix,is_san_jose_resident',
                'respondents:id,blotter_case_id,first_name,middle_name,last_name,suffix,is_san_jose_resident',
            ]);
    }

    private function partyText($parties): string
    {
        return $parties
            ->map(function ($party) {
                $name = collect([
                    $party->first_name,
                    $party->middle_name,
                    $party->last_name,
                    $party->suffix,
                ])->filter()->implode(' ');

                $classification = $party->is_san_jose_resident
                    ? 'Resident'
                    : 'Non-Resident';

                return trim($name) . " ({$classification})";
            })
            ->filter()
            ->implode('; ');
    }

    private function enumValue($value): string
    {
        return $value instanceof \BackedEnum
            ? (string) $value->value
            : (string) ($value ?? '');
    }

    private function filterLabels(Request $request): array
    {
        $incidentType = $request->filled('incident_type_id')
            ? IncidentType::query()
                ->whereKey((int) $request->input('incident_type_id'))
                ->value('name')
            : null;

        return [
            'Search' => $request->input('search') ?: 'Any',
            'Incident Type' => $incidentType ?: 'All Types',
            'Case Stage' => $request->input('case_stage') ?: 'All Stages',
            'Status' => $request->input('status') ?: 'All Statuses',
            'Date From' => $request->input('date_from') ?: 'Any Date',
            'Date To' => $request->input('date_to') ?: 'Any Date',
        ];
    }
}
