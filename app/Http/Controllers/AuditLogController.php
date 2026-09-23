<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AuditLogController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Audit Trail List
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $query = $this->filteredQuery(
            $request
        );

        /*
        |--------------------------------------------------------------------------
        | Paginated Results
        |--------------------------------------------------------------------------
        */

        $logs = $query
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        /*
        |--------------------------------------------------------------------------
        | Filter Options
        |--------------------------------------------------------------------------
        */

        $users = User::query()
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'username',
            ]);

        $actions = AuditLog::query()
            ->whereNotNull('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        $modules = AuditLog::query()
            ->whereNotNull('module')
            ->distinct()
            ->orderBy('module')
            ->pluck('module');

        return view(
            'admin.audit.index',
            [
                'logs' =>
                    $logs,

                'users' =>
                    $users,

                'actions' =>
                    $actions,

                'modules' =>
                    $modules,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Export Filtered Audit Trail To Excel
    |--------------------------------------------------------------------------
    |
    | The export uses the exact same filtering method as the Audit Trail page.
    | Therefore, Search / User / Action / Module / Date From / Date To filters
    | are automatically respected in the exported workbook.
    |
    */

    public function export(
        Request $request
    ) {
        $logs = $this
            ->filteredQuery(
                $request
            )
            ->latest('created_at')
            ->get();

        $spreadsheet =
            new Spreadsheet();

        $sheet =
            $spreadsheet
                ->getActiveSheet();

        $sheet->setTitle(
            'Audit Trail'
        );

        /*
        |--------------------------------------------------------------------------
        | Header Row
        |--------------------------------------------------------------------------
        */

        $headers = [
            'Date / Time',
            'User',
            'Username',
            'Role',
            'Action',
            'Module',
            'Description',
            'IP Address',
            'Related Record',
            'Old Values',
            'New Values',
            'Browser / Device',
        ];

        foreach (
            $headers
            as $index => $heading
        ) {
            $column =
                $this->excelColumn(
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
        | Data Rows
        |--------------------------------------------------------------------------
        */

        $row = 2;

        foreach ($logs as $log) {
            $relatedRecord = null;

            if (
                $log->auditable_type
                ||
                $log->auditable_id
            ) {
                $relatedRecord =
                    trim(
                        class_basename(
                            $log->auditable_type
                            ?? ''
                        )
                        . (
                            $log->auditable_id
                                ? ' #'
                                    . $log->auditable_id
                                : ''
                        )
                    );
            }

            $values = [
                $log->created_at
                    ?->format(
                        'Y-m-d h:i:s A'
                    )
                    ?? '',

                $log->user?->name
                    ?? 'System / Deleted User',

                $log->user?->username
                    ?? '',

                $log->user?->role?->name
                    ?? '',

                $log->action
                    ?? '',

                $log->module
                    ?? '',

                $log->description
                    ?? '',

                $log->ip_address
                    ?? '',

                $relatedRecord
                    ?? '',

                $this->jsonForExcel(
                    $log->old_values
                ),

                $this->jsonForExcel(
                    $log->new_values
                ),

                $log->user_agent
                    ?? '',
            ];

            foreach (
                $values
                as $index => $value
            ) {
                $column =
                    $this->excelColumn(
                        $index + 1
                    );

                /*
                 * Explicit string typing prevents audit text beginning
                 * with "=" / "+" / "-" / "@" from becoming an Excel
                 * formula when the workbook is opened.
                 */
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
        | Workbook Formatting
        |--------------------------------------------------------------------------
        */

        $lastRow =
            max(
                1,
                $row - 1
            );

        $sheet
            ->getStyle(
                'A1:L1'
            )
            ->getFont()
            ->setBold(
                true
            );

        $sheet->freezePane(
            'A2'
        );

        $sheet->setAutoFilter(
            "A1:L{$lastRow}"
        );

        $sheet
            ->getStyle(
                "A1:L{$lastRow}"
            )
            ->getAlignment()
            ->setVertical(
                \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP
            );

        if ($lastRow >= 2) {
            $sheet
                ->getStyle(
                    "G2:L{$lastRow}"
                )
                ->getAlignment()
                ->setWrapText(
                    true
                );
        }

        /*
         * Fixed widths are used instead of auto-size for long JSON and
         * user-agent fields so large audit logs export efficiently.
         */
        $widths = [
            'A' => 22,
            'B' => 24,
            'C' => 20,
            'D' => 22,
            'E' => 28,
            'F' => 22,
            'G' => 45,
            'H' => 18,
            'I' => 24,
            'J' => 50,
            'K' => 50,
            'L' => 55,
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
        | Filter Summary Sheet
        |--------------------------------------------------------------------------
        */

        $filterSheet =
            $spreadsheet
                ->createSheet();

        $filterSheet->setTitle(
            'Export Filters'
        );

        $filters = [
            'Search' =>
                $request->input(
                    'search'
                ),

            'User' =>
                $request->filled(
                    'user_id'
                )
                    ? User::find(
                        $request->user_id
                    )?->name
                    : 'All Users',

            'Action' =>
                $request->input(
                    'action'
                )
                ?: 'All Actions',

            'Module' =>
                $request->input(
                    'module'
                )
                ?: 'All Modules',

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

            'Records Exported' =>
                (string) $logs->count(),

            'Exported At' =>
                now()->format(
                    'Y-m-d h:i:s A'
                ),
        ];

        $filterSheet->setCellValueExplicit(
            'A1',
            'Filter',
            DataType::TYPE_STRING
        );

        $filterSheet->setCellValueExplicit(
            'B1',
            'Value',
            DataType::TYPE_STRING
        );

        $filterSheet
            ->getStyle(
                'A1:B1'
            )
            ->getFont()
            ->setBold(
                true
            );

        $filterRow = 2;

        foreach (
            $filters
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
                    (string) (
                        $value
                        ?? ''
                    ),
                    DataType::TYPE_STRING
                );

            $filterRow++;
        }

        $filterSheet
            ->getColumnDimension(
                'A'
            )
            ->setWidth(
                24
            );

        $filterSheet
            ->getColumnDimension(
                'B'
            )
            ->setWidth(
                55
            );

        /*
        |--------------------------------------------------------------------------
        | Download
        |--------------------------------------------------------------------------
        */

        $filename =
            'audit-trail-'
            . now()->format(
                'Y-m-d_H-i-s'
            )
            . '.xlsx';

        return response()->streamDownload(
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
    | Shared Audit Filter Query
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    | Both index() and export() call this method. Any future filter added
    | here automatically applies to both the browser table and Excel export.
    |
    */

    private function filteredQuery(
        Request $request
    ): Builder {
        $query =
            AuditLog::with([
                'user.role',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */

        if ($request->filled('search')) {
            $search =
                trim(
                    $request->search
                );

            $query->where(
                function (
                    Builder $q
                ) use ($search) {
                    $q->where(
                        'action',
                        'like',
                        "%{$search}%"
                    )
                        ->orWhere(
                            'module',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'description',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhereHas(
                            'user',
                            function (
                                Builder $userQuery
                            ) use ($search) {
                                $userQuery
                                    ->where(
                                        'name',
                                        'like',
                                        "%{$search}%"
                                    )
                                    ->orWhere(
                                        'username',
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
        | User Filter
        |--------------------------------------------------------------------------
        */

        if ($request->filled('user_id')) {
            $query->where(
                'user_id',
                $request->user_id
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Action Filter
        |--------------------------------------------------------------------------
        */

        if ($request->filled('action')) {
            $query->where(
                'action',
                $request->action
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Module Filter
        |--------------------------------------------------------------------------
        */

        if ($request->filled('module')) {
            $query->where(
                'module',
                $request->module
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Date From
        |--------------------------------------------------------------------------
        */

        if ($request->filled('date_from')) {
            $query->whereDate(
                'created_at',
                '>=',
                $request->date_from
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Date To
        |--------------------------------------------------------------------------
        */

        if ($request->filled('date_to')) {
            $query->whereDate(
                'created_at',
                '<=',
                $request->date_to
            );
        }

        return $query;
    }

    /*
    |--------------------------------------------------------------------------
    | JSON Export Helper
    |--------------------------------------------------------------------------
    */

    private function jsonForExcel(
        mixed $value
    ): string {
        if (
            $value === null
            ||
            $value === []
        ) {
            return '';
        }

        return json_encode(
            $value,
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        ) ?: '';
    }

    /*
    |--------------------------------------------------------------------------
    | Excel Column Helper
    |--------------------------------------------------------------------------
    */

    private function excelColumn(
        int $number
    ): string {
        $column = '';

        while ($number > 0) {
            $number--;

            $column =
                chr(
                    65
                    + (
                        $number
                        % 26
                    )
                )
                . $column;

            $number =
                intdiv(
                    $number,
                    26
                );
        }

        return $column;
    }
}
