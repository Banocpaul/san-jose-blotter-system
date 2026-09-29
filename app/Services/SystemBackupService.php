<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class SystemBackupService
{
    public const FORMAT = 'san-jose-blotter-backup';

    public const VERSION = 1;

    /*
    |--------------------------------------------------------------------------
    | Application Data Tables
    |--------------------------------------------------------------------------
    |
    | Framework/runtime tables such as sessions, cache, jobs and password reset
    | tokens are intentionally excluded. The backup contains the persistent
    | records needed to rebuild the Barangay San Jose application data.
    |
    */

    private array $insertOrder = [
        'roles',
        'users',
        'incident_types',
        'case_sequences',
        'resident_sequences',
        'residents',
        'blotter_cases',
        'case_complainants',
        'case_respondents',
        'case_witnesses',
        'case_assignments',
        'investigation_notes',
        'mediation_sessions',
        'mediation_attendees',
        'mediation_summons',
        'mediation_outcomes',
        'case_resolutions',
        'audit_logs',
    ];

    public function tables(): array
    {
        return $this->insertOrder;
    }

    public function currentCounts(): array
    {
        $this->assertRequiredTablesExist();

        $counts = [];

        foreach ($this->insertOrder as $table) {
            $counts[$table] =
                (int) DB::table($table)->count();
        }

        return $counts;
    }

    public function createPayload(): string
    {
        $document =
            $this->createDocument();

        $json = json_encode(
            $document,
            JSON_THROW_ON_ERROR
        );

        $compressed =
            gzencode(
                $json,
                9
            );

        if ($compressed === false) {
            throw new RuntimeException(
                'Unable to compress the backup data.'
            );
        }

        /*
         * Laravel Crypt uses the application's APP_KEY and authenticated
         * encryption. This protects both confidentiality and integrity.
         */
        return Crypt::encryptString(
            base64_encode(
                $compressed
            )
        );
    }

    public function decodePayload(
        string $payload
    ): array {
        try {
            $encoded =
                Crypt::decryptString(
                    trim($payload)
                );

            $compressed =
                base64_decode(
                    $encoded,
                    true
                );

            if ($compressed === false) {
                throw new RuntimeException(
                    'Backup payload encoding is invalid.'
                );
            }

            $json =
                gzdecode(
                    $compressed
                );

            if ($json === false) {
                throw new RuntimeException(
                    'Backup payload compression is invalid.'
                );
            }

            $document =
                json_decode(
                    $json,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'The backup file is invalid, corrupted, or was encrypted with a different application key.',
                0,
                $exception
            );
        }

        if (! is_array($document)) {
            throw new RuntimeException(
                'Backup document structure is invalid.'
            );
        }

        $this->validateDocument(
            $document
        );

        return $document;
    }

    public function restore(
        array $document
    ): void {
        $this->validateDocument(
            $document
        );

        $deleteOrder =
            array_reverse(
                $this->insertOrder
            );

        DB::transaction(
            function () use (
                $document,
                $deleteOrder
            ) {
                /*
                 * Delete children before parents so foreign-key rules remain
                 * active. We intentionally do not disable FK checks.
                 */
                foreach ($deleteOrder as $table) {
                    DB::table($table)
                        ->delete();
                }

                /*
                 * Restore parents before children. Explicit IDs are preserved
                 * so all relationships remain intact.
                 */
                foreach ($this->insertOrder as $table) {
                    $rows =
                        $document['tables'][$table];

                    if (empty($rows)) {
                        continue;
                    }

                    foreach (
                        array_chunk(
                            $rows,
                            250
                        )
                        as $chunk
                    ) {
                        DB::table($table)
                            ->insert(
                                $chunk
                            );
                    }
                }
            },
            3
        );
    }

    public function backupContainsActiveCaptain(
        array $document,
        int $userId
    ): bool {
        $roles =
            collect(
                $document['tables']['roles']
                ?? []
            );

        $captainRole =
            $roles->first(
                fn (array $role) =>
                    ($role['slug'] ?? null)
                    === 'barangay_captain'
            );

        if (! $captainRole) {
            return false;
        }

        $captainRoleId =
            (int) (
                $captainRole['id']
                ?? 0
            );

        return collect(
            $document['tables']['users']
            ?? []
        )->contains(
            function (array $user) use (
                $userId,
                $captainRoleId
            ) {
                return
                    (int) (
                        $user['id']
                        ?? 0
                    ) === $userId
                    &&
                    (int) (
                        $user['role_id']
                        ?? 0
                    ) === $captainRoleId
                    &&
                    (bool) (
                        $user['is_active']
                        ?? false
                    );
            }
        );
    }

    private function createDocument(): array
    {
        $this->assertRequiredTablesExist();

        $schema = [];
        $counts = [];
        $tables = [];

        foreach ($this->insertOrder as $table) {
            $columns =
                Schema::getColumnListing(
                    $table
                );

            sort($columns);

            $schema[$table] =
                $columns;

            $rows =
                DB::table($table)
                    ->orderBy('id')
                    ->get()
                    ->map(
                        fn ($row) =>
                            (array) $row
                    )
                    ->values()
                    ->all();

            $tables[$table] =
                $rows;

            $counts[$table] =
                count($rows);
        }

        return [
            'format' =>
                self::FORMAT,

            'version' =>
                self::VERSION,

            'created_at' =>
                now()->toIso8601String(),

            'application' =>
                config(
                    'app.name',
                    'Barangay San Jose Blotter Management System'
                ),

            'schema' =>
                $schema,

            'counts' =>
                $counts,

            'tables' =>
                $tables,
        ];
    }

    private function validateDocument(
        array $document
    ): void {
        $this->assertRequiredTablesExist();

        if (
            ($document['format'] ?? null)
            !== self::FORMAT
        ) {
            throw new RuntimeException(
                'This file is not a Barangay San Jose system backup.'
            );
        }

        if (
            (int) (
                $document['version']
                ?? 0
            )
            !== self::VERSION
        ) {
            throw new RuntimeException(
                'This backup version is not supported by the current system.'
            );
        }

        if (
            ! isset(
                $document['schema'],
                $document['counts'],
                $document['tables']
            )
            ||
            ! is_array(
                $document['schema']
            )
            ||
            ! is_array(
                $document['counts']
            )
            ||
            ! is_array(
                $document['tables']
            )
        ) {
            throw new RuntimeException(
                'Backup metadata is incomplete.'
            );
        }

        foreach ($this->insertOrder as $table) {
            if (
                ! array_key_exists(
                    $table,
                    $document['tables']
                )
                ||
                ! is_array(
                    $document['tables'][$table]
                )
            ) {
                throw new RuntimeException(
                    "Backup table {$table} is missing or invalid."
                );
            }

            if (
                ! array_key_exists(
                    $table,
                    $document['schema']
                )
                ||
                ! is_array(
                    $document['schema'][$table]
                )
            ) {
                throw new RuntimeException(
                    "Backup schema for {$table} is missing."
                );
            }

            $currentColumns =
                Schema::getColumnListing(
                    $table
                );

            $backupColumns =
                $document['schema'][$table];

            sort($currentColumns);
            sort($backupColumns);

            if (
                $currentColumns
                !== $backupColumns
            ) {
                throw new RuntimeException(
                    "Backup schema mismatch for table {$table}. Run the same application version and migrations before restoring."
                );
            }

            $declaredCount =
                (int) (
                    $document['counts'][$table]
                    ?? -1
                );

            $actualCount =
                count(
                    $document['tables'][$table]
                );

            if (
                $declaredCount
                !== $actualCount
            ) {
                throw new RuntimeException(
                    "Backup record count mismatch for table {$table}."
                );
            }
        }

        /*
         * A restorable backup must contain at least one active Barangay Captain
         * account so the application cannot be restored into an unmanaged state.
         */
        $roles =
            collect(
                $document['tables']['roles']
            );

        $captainRole =
            $roles->first(
                fn (array $role) =>
                    ($role['slug'] ?? null)
                    === 'barangay_captain'
            );

        if (! $captainRole) {
            throw new RuntimeException(
                'Backup does not contain the Barangay Captain role.'
            );
        }

        $captainRoleId =
            (int) (
                $captainRole['id']
                ?? 0
            );

        $hasActiveCaptain =
            collect(
                $document['tables']['users']
            )->contains(
                fn (array $user) =>
                    (int) (
                        $user['role_id']
                        ?? 0
                    ) === $captainRoleId
                    &&
                    (bool) (
                        $user['is_active']
                        ?? false
                    )
            );

        if (! $hasActiveCaptain) {
            throw new RuntimeException(
                'Backup does not contain an active Barangay Captain account.'
            );
        }
    }

    private function assertRequiredTablesExist(): void
    {
        foreach ($this->insertOrder as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException(
                    "Required table {$table} does not exist. Run all migrations first."
                );
            }
        }
    }
}
