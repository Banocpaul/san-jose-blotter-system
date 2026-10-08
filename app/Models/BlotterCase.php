<?php

namespace App\Models;

use App\Enums\CaseStage;
use App\Enums\CaseStatus;
use App\Enums\RecordStatus;
use App\Services\CaseReferenceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BlotterCase extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'incident_type_id',
        'incident_date',
        'incident_time',
        'location',
        'narrative',
        'initial_action',
        'remarks',
        'status',
        'case_stage',
        'record_status',
        'created_by',
        'reported_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'incident_date' => 'date',
            'reported_at' => 'datetime',
            'closed_at' => 'datetime',
            'stage_entered_at' => 'datetime',
            'tracked_opened_at' => 'datetime',
            'sla_started_at' => 'datetime',
            'sla_extension_days' => 'integer',
            'status' => CaseStatus::class,
            'case_stage' => CaseStage::class,
            'record_status' => RecordStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (BlotterCase $case) {

            /*
            |--------------------------------------------------------------------------
            | Generate Case Reference
            |--------------------------------------------------------------------------
            */

            if (empty($case->reference_number)) {
                $case->reference_number =
                    app(CaseReferenceService::class)->generate();
            }

            /*
            |--------------------------------------------------------------------------
            | Legacy Status
            |--------------------------------------------------------------------------
            |
            | Kept temporarily while the rest of the system is being migrated
            | to case_stage + record_status.
            |
            */

            if (empty($case->status)) {
                $case->status = CaseStatus::Pending;
            }

            /*
            |--------------------------------------------------------------------------
            | Current Case Stage
            |--------------------------------------------------------------------------
            */

            if (empty($case->case_stage)) {
                $case->case_stage = CaseStage::New;
            }

            /*
            |--------------------------------------------------------------------------
            | Record Status
            |--------------------------------------------------------------------------
            */

            if (empty($case->record_status)) {
                $case->record_status = RecordStatus::Open;
            }

            /*
            |--------------------------------------------------------------------------
            | Reported Timestamp
            |--------------------------------------------------------------------------
            */

            if (empty($case->reported_at)) {
                $case->reported_at = now();
            }

            $case->stage_entered_at = now();
            if ($case->record_status === RecordStatus::Open) {
                $case->tracked_opened_at = now();
            }
            $case->sla_started_at = match ($case->case_stage) {
                CaseStage::New => $case->reported_at,
                CaseStage::UnderAssessment => $case->stage_entered_at,
                default => null,
            };
        });

        static::updating(function (BlotterCase $case) {
            $reopened = $case->isDirty('record_status') && $case->record_status === RecordStatus::Open;
            if ($case->isDirty('case_stage') || $reopened) {
                $case->stage_entered_at = now();
                $case->sla_started_at = in_array($case->case_stage, [CaseStage::New, CaseStage::UnderAssessment], true)
                    ? $case->stage_entered_at : null;
                $case->sla_extension_days = 0;
                $case->sla_extension_reason = null;
            }
            if ($reopened && $case->tracked_opened_at === null) {
                $case->tracked_opened_at = now();
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Reusable Query Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeVisibleTo(
        Builder $query,
        User $user
    ): Builder {
        $role = $user->role?->slug;

        /*
        |--------------------------------------------------------------------------
        | Councilor Visibility
        |--------------------------------------------------------------------------
        |
        | Councilors may only see cases currently assigned to them.
        |
        */

        if ($role === 'councilor') {
            return $query->whereHas(
                'assignments',
                fn (Builder $assignment) => $assignment
                    ->where(
                        'assigned_to',
                        $user->id
                    )
                    ->whereNull(
                        'completed_at'
                    )
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Lupon Visibility
        |--------------------------------------------------------------------------
        |
        | Lupon members may only see cases where they have a mediation session.
        |
        */

        if ($role === 'lupon') {
            return $query->whereHas(
                'mediationSessions',
                fn (Builder $session) => $session->where(
                    'lupon_member_id',
                    $user->id
                )
            );
        }

        return $query;
    }

    public function scopeSearchCase(
        Builder $query,
        ?string $search
    ): Builder {
        $search = trim(
            (string) $search
        );

        if ($search === '') {
            return $query;
        }

        $tokens = preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY);
        $matchPerson = function (Builder $person) use ($tokens): void {
            foreach ($tokens as $token) {
                $person->where(function (Builder $part) use ($token): void {
                    $part->where('first_name', 'like', "%{$token}%")
                        ->orWhere('middle_name', 'like', "%{$token}%")
                        ->orWhere('last_name', 'like', "%{$token}%")
                        ->orWhere('suffix', 'like', "%{$token}%");
                });
            }
        };

        return $query->where(function (Builder $caseQuery) use ($search, $matchPerson): void {
            $caseQuery->where('reference_number', 'like', "%{$search}%")
                ->orWhere('location', 'like', "%{$search}%")
                ->orWhereHas('complainants', $matchPerson)
                ->orWhereHas('respondents', $matchPerson);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function incidentType()
    {
        return $this->belongsTo(
            IncidentType::class,
            'incident_type_id'
        );
    }

    public function creator()
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function complainants()
    {
        return $this->hasMany(
            CaseComplainant::class,
            'blotter_case_id'
        );
    }

    public function respondents()
    {
        return $this->hasMany(
            CaseRespondent::class,
            'blotter_case_id'
        );
    }

    public function witnesses()
    {
        return $this->hasMany(
            CaseWitness::class,
            'blotter_case_id'
        );
    }

    public function assignments()
    {
        return $this->hasMany(
            CaseAssignment::class,
            'blotter_case_id'
        );
    }

    public function currentAssignment()
    {
        return $this->hasOne(
            CaseAssignment::class,
            'blotter_case_id'
        )
            ->whereNull(
                'completed_at'
            )
            ->latestOfMany();
    }

    public function investigationNotes()
    {
        return $this->hasMany(
            InvestigationNote::class,
            'blotter_case_id'
        );
    }

    public function mediationSessions()
    {
        return $this->hasMany(
            MediationSession::class,
            'blotter_case_id'
        );
    }

    public function latestMediationSession()
    {
        return $this->hasOne(
            MediationSession::class,
            'blotter_case_id'
        )
            ->latestOfMany();
    }

    public function caseResolution()
    {
        return $this->hasOne(
            CaseResolution::class,
            'blotter_case_id'
        );
    }
}
