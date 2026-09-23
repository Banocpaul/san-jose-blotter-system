<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogService
{
    /*
    |--------------------------------------------------------------------------
    | Write Audit Log
    |--------------------------------------------------------------------------
    |
    | Example:
    |
    | AuditLogService::log(
    |     action: 'created',
    |     module: 'Blotter',
    |     description: 'Created blotter case.',
    |     auditable: $case,
    |     newValues: $case->toArray()
    | );
    |
    */

    public static function log(
        string $action,
        ?string $module = null,
        ?string $description = null,
        ?Model $auditable = null,
        array $oldValues = [],
        array $newValues = [],
        ?int $userId = null
    ): AuditLog {
        return AuditLog::create([
            'user_id' =>
                $userId ?? auth()->id(),

            'action' =>
                $action,

            'module' =>
                $module,

            'description' =>
                $description,

            'auditable_type' =>
                $auditable
                    ? get_class($auditable)
                    : null,

            'auditable_id' =>
                $auditable?->getKey(),

            'old_values' =>
                self::sanitizeValues(
                    $oldValues
                ),

            'new_values' =>
                self::sanitizeValues(
                    $newValues
                ),

            'ip_address' =>
                request()?->ip(),

            'user_agent' =>
                request()?->userAgent(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Sanitize Sensitive Values
    |--------------------------------------------------------------------------
    |
    | We should never store passwords or authentication secrets
    | inside the audit trail.
    |
    */

    private static function sanitizeValues(
        array $values
    ): array {
        $sensitiveFields = [
            'password',
            'password_confirmation',
            'remember_token',
            'current_password',
            'new_password',
            'new_password_confirmation',
        ];

        foreach ($sensitiveFields as $field) {
            if (
                array_key_exists(
                    $field,
                    $values
                )
            ) {
                $values[$field] =
                    '[REDACTED]';
            }
        }

        return $values;
    }
}