<?php

namespace App\Http\Controllers;

use App\Services\AuditLogService;
use App\Services\SystemBackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class BackupRestoreController extends Controller
{
    private const MAX_BACKUP_KILOBYTES = 51200;

    public function index(
        SystemBackupService $backupService
    ) {
        $this->cleanupStaleFiles();

        return view(
            'admin.backup.index',
            [
                'counts' =>
                    $backupService
                        ->currentCounts(),

                'safetyToken' =>
                    session(
                        'backup_restore_safety_token'
                    ),
            ]
        );
    }

    public function download(
        Request $request,
        SystemBackupService $backupService
    ) {
        $this->cleanupStaleFiles();

        $payload =
            $backupService
                ->createPayload();

        $filename =
            'SanJose_Backup_'
            . now()->format(
                'Y-m-d_His'
            )
            . '.sjbackup';

        $directory =
            storage_path(
                'app/private/generated-backups'
            );

        File::ensureDirectoryExists(
            $directory
        );

        $path =
            $directory
            . DIRECTORY_SEPARATOR
            . Str::uuid()
            . '.sjbackup';

        File::put(
            $path,
            $payload
        );

        AuditLogService::log(
            action:
                'system_backup_downloaded',

            module:
                'Backup & Restore',

            description:
                'Barangay system backup was generated for download.',

            newValues: [
                'filename' =>
                    $filename,

                'record_counts' =>
                    $backupService
                        ->currentCounts(),
            ],

            userId:
                $request
                    ->user()
                    ->id
        );

        return response()
            ->download(
                $path,
                $filename,
                [
                    'Content-Type' =>
                        'application/octet-stream',
                ]
            )
            ->deleteFileAfterSend(
                true
            );
    }

    public function validateUpload(
        Request $request,
        SystemBackupService $backupService
    ) {
        $data =
            $request->validate([
                'backup_file' => [
                    'required',
                    'file',
                    'max:'
                    . self::MAX_BACKUP_KILOBYTES,
                ],
            ]);

        $file =
            $data['backup_file'];

        if (
            strtolower(
                $file
                    ->getClientOriginalExtension()
            )
            !== 'sjbackup'
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'backup_file' =>
                        'Select a valid .sjbackup file.',
                ]);
        }

        try {
            $payload =
                File::get(
                    $file
                        ->getRealPath()
                );

            $document =
                $backupService
                    ->decodePayload(
                        $payload
                    );

            /*
             * Prevent restoring a backup that would remove the currently
             * authenticated Barangay Captain account.
             */
            if (
                ! $backupService
                    ->backupContainsActiveCaptain(
                        $document,
                        $request
                            ->user()
                            ->id
                    )
            ) {
                throw new RuntimeException(
                    'Restore blocked: this backup does not contain your current active Barangay Captain account.'
                );
            }
        } catch (Throwable $exception) {
            return back()
                ->withInput()
                ->withErrors([
                    'backup_file' =>
                        $exception
                            ->getMessage(),
                ]);
        }

        $directory =
            storage_path(
                'app/private/restore-staging'
            );

        File::ensureDirectoryExists(
            $directory
        );

        $token =
            (string) Str::uuid();

        $stagedPath =
            $directory
            . DIRECTORY_SEPARATOR
            . $token
            . '.sjbackup';

        File::put(
            $stagedPath,
            $payload
        );

        session([
            'backup_restore_staging_token' =>
                $token,
        ]);

        return view(
            'admin.backup.confirm',
            [
                'token' =>
                    $token,

                'backup' => [
                    'created_at' =>
                        $document[
                            'created_at'
                        ]
                        ?? null,

                    'application' =>
                        $document[
                            'application'
                        ]
                        ?? null,

                    'version' =>
                        $document[
                            'version'
                        ]
                        ?? null,

                    'counts' =>
                        $document[
                            'counts'
                        ]
                        ?? [],
                ],

                'currentCounts' =>
                    $backupService
                        ->currentCounts(),
            ]
        );
    }

    public function restore(
        Request $request,
        SystemBackupService $backupService
    ) {
        $data =
            $request->validate([
                'token' => [
                    'required',
                    'uuid',
                ],

                'confirmation' => [
                    'required',
                    'string',
                    'in:RESTORE',
                ],

                'current_password' => [
                    'required',
                    'string',
                ],
            ]);

        if (
            ! Hash::check(
                $data['current_password'],
                $request
                    ->user()
                    ->password
            )
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'current_password' =>
                        'The current password is incorrect.',
                ]);
        }

        $sessionToken =
            session(
                'backup_restore_staging_token'
            );

        if (
            ! is_string(
                $sessionToken
            )
            ||
            ! hash_equals(
                $sessionToken,
                $data['token']
            )
        ) {
            abort(403);
        }

        $stagedPath =
            storage_path(
                'app/private/restore-staging/'
                . $data['token']
                . '.sjbackup'
            );

        if (
            ! File::exists(
                $stagedPath
            )
        ) {
            return redirect()
                ->route(
                    'admin.backup.index'
                )
                ->withErrors([
                    'backup_file' =>
                        'The validated backup file has expired. Upload it again.',
                ]);
        }

        try {
            $document =
                $backupService
                    ->decodePayload(
                        File::get(
                            $stagedPath
                        )
                    );

            if (
                ! $backupService
                    ->backupContainsActiveCaptain(
                        $document,
                        $request
                            ->user()
                            ->id
                    )
            ) {
                throw new RuntimeException(
                    'Restore blocked because your active Barangay Captain account is not present in the backup.'
                );
            }
        } catch (Throwable $exception) {
            File::delete(
                $stagedPath
            );

            session()->forget(
                'backup_restore_staging_token'
            );

            return redirect()
                ->route(
                    'admin.backup.index'
                )
                ->withErrors([
                    'backup_file' =>
                        $exception
                            ->getMessage(),
                ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Pre-Restore Safety Backup
        |--------------------------------------------------------------------------
        |
        | Generate a snapshot immediately before modifying the database.
        | It stays on temporary server storage only until the Captain downloads
        | it from the success page.
        |
        */

        $safetyDirectory =
            storage_path(
                'app/private/safety-backups'
            );

        File::ensureDirectoryExists(
            $safetyDirectory
        );

        $safetyToken =
            (string) Str::uuid();

        $safetyPath =
            $safetyDirectory
            . DIRECTORY_SEPARATOR
            . $safetyToken
            . '.sjbackup';

        File::put(
            $safetyPath,
            $backupService
                ->createPayload()
        );

        session([
            'backup_restore_safety_token' =>
                $safetyToken,
        ]);

        $beforeCounts =
            $backupService
                ->currentCounts();

        try {
            $backupService
                ->restore(
                    $document
                );
        } catch (Throwable $exception) {
            return redirect()
                ->route(
                    'admin.backup.index'
                )
                ->withErrors([
                    'restore' =>
                        'Restore failed and the database transaction was rolled back: '
                        . $exception
                            ->getMessage(),
                ])
                ->with(
                    'safety_backup_available',
                    true
                );
        } finally {
            File::delete(
                $stagedPath
            );

            session()->forget(
                'backup_restore_staging_token'
            );
        }

        AuditLogService::log(
            action:
                'system_backup_restored',

            module:
                'Backup & Restore',

            description:
                'Barangay system data was restored from a validated backup.',

            oldValues: [
                'record_counts' =>
                    $beforeCounts,
            ],

            newValues: [
                'backup_created_at' =>
                    $document[
                        'created_at'
                    ]
                    ?? null,

                'record_counts' =>
                    $backupService
                        ->currentCounts(),
            ],

            userId:
                $request
                    ->user()
                    ->id
        );

        return redirect()
            ->route(
                'admin.backup.index'
            )
            ->with(
                'success',
                'Backup restored successfully. Download the pre-restore safety backup before leaving this page.'
            )
            ->with(
                'safety_backup_available',
                true
            );
    }

    public function downloadSafety(
        string $token
    ) {
        $sessionToken =
            session(
                'backup_restore_safety_token'
            );

        if (
            ! is_string(
                $sessionToken
            )
            ||
            ! hash_equals(
                $sessionToken,
                $token
            )
        ) {
            abort(403);
        }

        $path =
            storage_path(
                'app/private/safety-backups/'
                . $token
                . '.sjbackup'
            );

        abort_unless(
            File::exists(
                $path
            ),
            404
        );

        session()->forget(
            'backup_restore_safety_token'
        );

        $filename =
            'SanJose_PreRestore_Safety_'
            . now()->format(
                'Y-m-d_His'
            )
            . '.sjbackup';

        return response()
            ->download(
                $path,
                $filename,
                [
                    'Content-Type' =>
                        'application/octet-stream',
                ]
            )
            ->deleteFileAfterSend(
                true
            );
    }

    private function cleanupStaleFiles(): void
    {
        $directories = [
            storage_path(
                'app/private/generated-backups'
            ),
            storage_path(
                'app/private/restore-staging'
            ),
            storage_path(
                'app/private/safety-backups'
            ),
        ];

        $cutoff =
            now()
                ->subDay()
                ->timestamp;

        foreach ($directories as $directory) {
            if (! File::isDirectory($directory)) {
                continue;
            }

            foreach (
                File::files($directory)
                as $file
            ) {
                if (
                    $file
                        ->getMTime()
                    < $cutoff
                ) {
                    File::delete(
                        $file
                            ->getPathname()
                    );
                }
            }
        }
    }
}
