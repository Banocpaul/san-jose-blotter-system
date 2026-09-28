<?php

namespace App\Http\Controllers;

use App\Enums\CaseStage;
use App\Enums\RecordStatus;
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
    /*
    |--------------------------------------------------------------------------
    | Reports Index
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $filtered =
            $this->filteredQuery(
                $request
            );

        /*
        |--------------------------------------------------------------------------
        | Filter-Aware KPI Summary
        |--------------------------------------------------------------------------
        |
        | Overall record state is now based on:
        |
        | Open
        | Resolved
        | Closed
        |
        */

        $summary = (clone $filtered)
            ->selectRaw(
                "COUNT(*) AS total_cases,
                 SUM(CASE WHEN record_status = ? THEN 1 ELSE 0 END) AS open_cases,
                 SUM(CASE WHEN record_status = ? THEN 1 ELSE 0 END) AS resolved_cases,
                 SUM(CASE WHEN record_status = ? THEN 1 ELSE 0 END) AS closed_cases",
                [
                    RecordStatus::Open->value,
                    RecordStatus::Resolved->value,
                    RecordStatus::Closed->value,
                ]
            )
            ->first();

        /*
        |--------------------------------------------------------------------------
        | Paginated Report Rows
        |--------------------------------------------------------------------------
        */

        $cases =
            $this->reportRowsQuery(
                $request
            )
                ->latest(
                    'incident_date'
                )
                ->latest('id')
                ->paginate(20)
                ->withQueryString();

        return view(
            'reports.index',
            [
                'cases' =>
                    $cases,

                'summary' =>
                    $summary,

                'incidentTypes' =>
                    IncidentType::query()
                        ->orderBy('name')
                        ->get([
                            'id',
                            'name',
                        ]),

                'caseStages' =>
                    CaseStage::cases(),

                'recordStatuses' =>
                    RecordStatus::cases(),
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Printable Report
    |--------------------------------------------------------------------------
    */

    public function print(Request $request)
    {
        $cases =
            $this->reportRowsQuery(
                $request
            )
                ->latest(
                    'incident_date'
                )
                ->latest('id')
                ->get();

        AuditLogService::log(
            action:
                'printed',

            module:
                'Reports & Export',

            description:
                'Printed a filtered case report.'
        );

        return view(
            'reports.print',
            [
                'cases' =>
                    $cases,

                'filters' =>
                    $this->filterLabels(
                        $request
                    ),

                'generatedAt' =>
                    now(),
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Excel Export
    |--------------------------------------------------------------------------
    */

    public function export(Request $request)
    {
        $cases =
            $this->reportRowsQuery(
                $request
            )
                ->latest(
                    'incident_date'
                )
                ->latest('id')
                ->get();

        $spreadsheet =
            new Spreadsheet();

        $sheet =
            $spreadsheet
                ->getActiveSheet();

        $sheet->setTitle(
            'Case Report'
        );

        /*
        |--------------------------------------------------------------------------
        | Headers
        |--------------------------------------------------------------------------
        */

        $headers = [
            'Case Number',
            'Incident Date',
            'Incident Type',
            'Complainant(s)',
            'Respondent(s)',
            'Location',
            'Current Stage',
            'Record Status',
            'Reported At',
        ];

        foreach (
            $headers
            as $index => $heading
        ) {
            $column =
                Coordinate::stringFromColumnIndex(
                    $index + 1
                );

            $sheet->setCellValueExplicit(
                "{$column}1",
                $heading,
                DataType::TYPE_STRING
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Report Rows
        |--------------------------------------------------------------------------
        */

        $row = 2;

        foreach (
            $cases
            as $case
        ) {
            $values = [
                $case->reference_number,

                $case->incident_date
                    ?->format('Y-m-d')
                    ?? '',

                $case->incidentType
                    ?->name
                    ?? '',

                $this->partyText(
                    $case->complainants
                ),

                $this->partyText(
                    $case->respondents
                ),

                $case->location
                    ?? '',

                $this->enumValue(
                    $case->case_stage
                ),

                $this->enumValue(
                    $case->record_status
                ),

                $case->reported_at
                    ?->format(
                        'Y-m-d H:i:s'
                    )
                    ?? '',
            ];

            foreach (
                $values
                as $index => $value
            ) {
                $column =
                    Coordinate::stringFromColumnIndex(
                        $index + 1
                    );

                $sheet->setCellValueExplicit(
                    "{$column}{$row}",
                    (string) $value,
                    DataType::TYPE_STRING
                );
            }

            $row++;
        }

        /*
        |--------------------------------------------------------------------------
        | Excel Formatting
        |--------------------------------------------------------------------------
        */

        $lastRow =
            max(
                1,
                $row - 1
            );

        $sheet
            ->getStyle('A1:I1')
            ->getFont()
            ->setBold(true);

        $sheet->freezePane(
            'A2'
        );

        $sheet->setAutoFilter(
            "A1:I{$lastRow}"
        );

        $sheet
            ->getStyle(
                "A1:I{$lastRow}"
            )
            ->getAlignment()
            ->setVertical(
                \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP
            );

        if ($lastRow >= 2) {
            $sheet
                ->getStyle(
                    "D2:F{$lastRow}"
                )
                ->getAlignment()
                ->setWrapText(
                    true
                );
        }

        $widths = [
            'A' => 20,
            'B' => 15,
            'C' => 28,
            'D' => 38,
            'E' => 38,
            'F' => 38,
            'G' => 28,
            'H' => 20,
            'I' => 22,
        ];

        foreach (
            $widths
            as $column => $width
        ) {
            $sheet
                ->getColumnDimension(
                    $column
                )
                ->setWidth(
                    $width
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Filter Information Sheet
        |--------------------------------------------------------------------------
        */

        $filterSheet =
            $spreadsheet
                ->createSheet();

        $filterSheet->setTitle(
            'Report Filters'
        );

        $filterSheet->setCellValue(
            'A1',
            'Filter'
        );

        $filterSheet->setCellValue(
            'B1',
            'Value'
        );

        $filterSheet
            ->getStyle('A1:B1')
            ->getFont()
            ->setBold(true);

        $filterRow = 2;

        foreach (
            $this->filterLabels(
                $request
            )
            as $label => $value
        ) {
            $filterSheet
                ->setCellValueExplicit(
                    "A{$filterRow}",
                    (string) $label,
                    DataType::TYPE_STRING
                );

            $filterSheet
                ->setCellValueExplicit(
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

        $filterSheet
            ->getColumnDimension('A')
            ->setWidth(24);

        $filterSheet
            ->getColumnDimension('B')
            ->setWidth(55);

        /*
        |--------------------------------------------------------------------------
        | Audit
        |--------------------------------------------------------------------------
        */

        AuditLogService::log(
            action:
                'exported',

            module:
                'Reports & Export',

            description:
                'Exported a filtered case report to Excel.'
        );

        $filename =
            'barangay-san-jose-case-report-'
            . now()->format(
                'Y-m-d_H-i-s'
            )
            . '.xlsx';

        return response()
            ->streamDownload(
                function () use (
                    $spreadsheet
                ) {
                    $writer =
                        new Xlsx(
                            $spreadsheet
                        );

                    $writer->save(
                        'php://output'
                    );

                    $spreadsheet
                        ->disconnectWorksheets();
                },

                $filename,

                [
                    'Content-Type' =>
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',

                    'Cache-Control' =>
                        'max-age=0, no-cache, no-store, must-revalidate',

                    'Pragma' =>
                        'public',
                ]
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Shared Report Filter Query
    |--------------------------------------------------------------------------
    */

    private function filteredQuery(
        Request $request
    ): Builder {
        $query =
            BlotterCase::query();

        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */

        $query->searchCase(
            $request
                ->string('search')
                ->toString()
        );

        /*
        |--------------------------------------------------------------------------
        | Incident Type
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
        | Current Stage
        |--------------------------------------------------------------------------
        */

        if (
            $request->filled(
                'case_stage'
            )
        ) {
            $stage =
                $request
                    ->string('case_stage')
                    ->toString();

            $validStages =
                array_map(
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
        | Record Status
        |--------------------------------------------------------------------------
        */

        if (
            $request->filled(
                'record_status'
            )
        ) {
            $recordStatus =
                $request
                    ->string(
                        'record_status'
                    )
                    ->toString();

            $validStatuses =
                array_map(
                    fn (RecordStatus $status) =>
                        $status->value,
                    RecordStatus::cases()
                );

            if (
                in_array(
                    $recordStatus,
                    $validStatuses,
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
        | Date Range
        |--------------------------------------------------------------------------
        */

        if (
            $request->filled(
                'date_from'
            )
        ) {
            $query->where(
                'incident_date',
                '>=',
                $request->input(
                    'date_from'
                )
            );
        }

        if (
            $request->filled(
                'date_to'
            )
        ) {
            $query->where(
                'incident_date',
                '<=',
                $request->input(
                    'date_to'
                )
            );
        }

        return $query;
    }

    /*
    |--------------------------------------------------------------------------
    | Report Row Query
    |--------------------------------------------------------------------------
    */

    private function reportRowsQuery(
        Request $request
    ): Builder {
        return $this
            ->filteredQuery(
                $request
            )
            ->select([
                'id',
                'reference_number',
                'incident_type_id',
                'incident_date',
                'location',
                'case_stage',
                'record_status',
                'reported_at',
            ])
            ->with([
                'incidentType:id,name',

                'complainants:id,blotter_case_id,first_name,middle_name,last_name,suffix,is_san_jose_resident',

                'respondents:id,blotter_case_id,first_name,middle_name,last_name,suffix,is_san_jose_resident',
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Party Text Helper
    |--------------------------------------------------------------------------
    */

    private function partyText(
        $parties
    ): string {
        return $parties
            ->map(
                function ($party) {
                    $name =
                        collect([
                            $party->first_name,
                            $party->middle_name,
                            $party->last_name,
                            $party->suffix,
                        ])
                            ->filter()
                            ->implode(' ');

                    $classification =
                        $party
                            ->is_san_jose_resident
                            ? 'Resident'
                            : 'Non-Resident';

                    return trim($name)
                        . " ({$classification})";
                }
            )
            ->filter()
            ->implode('; ');
    }

    /*
    |--------------------------------------------------------------------------
    | Enum Value Helper
    |--------------------------------------------------------------------------
    */

    private function enumValue(
        $value
    ): string {
        return $value instanceof \BackedEnum
            ? (string) $value->value
            : (string) (
                $value ?? ''
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Human-Readable Filter Labels
    |--------------------------------------------------------------------------
    */

    private function filterLabels(
        Request $request
    ): array {
        $incidentType =
            $request->filled(
                'incident_type_id'
            )
                ? IncidentType::query()
                    ->whereKey(
                        (int) $request->input(
                            'incident_type_id'
                        )
                    )
                    ->value('name')
                : null;

        return [
            'Search' =>
                $request->input(
                    'search'
                )
                ?: 'Any',

            'Incident Type' =>
                $incidentType
                ?: 'All Types',

            'Current Stage' =>
                $request->input(
                    'case_stage'
                )
                ?: 'All Stages',

            'Record Status' =>
                $request->input(
                    'record_status'
                )
                ?: 'All Record Statuses',

            'Date From' =>
                $request->input(
                    'date_from'
                )
                ?: 'Any Date',

            'Date To' =>
                $request->input(
                    'date_to'
                )
                ?: 'Any Date',
        ];
    }
}