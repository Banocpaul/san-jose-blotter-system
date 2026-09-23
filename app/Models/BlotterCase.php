<?php

namespace App\Models;

use App\Enums\CaseStatus;
use App\Services\CaseReferenceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BlotterCase extends Model
{
    use SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | Mass Assignable Fields
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'incident_type_id',
        'incident_date',
        'incident_time',
        'location',
        'narrative',
        'initial_action',
        'remarks',
        'status',
        'created_by',
        'reported_at',
        'closed_at',
    ];

    /*
    |--------------------------------------------------------------------------
    | Attribute Casting
    |--------------------------------------------------------------------------
    */

    protected function casts(): array
    {
        return [
            'incident_date' => 'date',
            'reported_at' => 'datetime',
            'closed_at' => 'datetime',
            'status' => CaseStatus::class,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Model Events
    |--------------------------------------------------------------------------
    |
    | Automatically generate the reference number and default values whenever
    | a new blotter case is created.
    |
    */

    protected static function booted(): void
    {
        static::creating(function (BlotterCase $case) {

            if (empty($case->reference_number)) {
                $case->reference_number =
                    app(CaseReferenceService::class)->generate();
            }

            if (empty($case->status)) {
                $case->status = CaseStatus::Pending;
            }

            if (empty($case->reported_at)) {
                $case->reported_at = now();
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Incident Type
    |--------------------------------------------------------------------------
    */

    public function incidentType()
    {
        return $this->belongsTo(
            IncidentType::class,
            'incident_type_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | User Who Created The Case
    |--------------------------------------------------------------------------
    */

    public function creator()
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Complainants
    |--------------------------------------------------------------------------
    */

    public function complainants()
    {
        return $this->hasMany(
            CaseComplainant::class,
            'blotter_case_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Respondents
    |--------------------------------------------------------------------------
    */

    public function respondents()
    {
        return $this->hasMany(
            CaseRespondent::class,
            'blotter_case_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Witnesses
    |--------------------------------------------------------------------------
    */

    public function witnesses()
    {
        return $this->hasMany(
            CaseWitness::class,
            'blotter_case_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Assignment History
    |--------------------------------------------------------------------------
    |
    | A case may be reassigned later, so we keep all assignment records.
    |
    */

    public function assignments()
    {
        return $this->hasMany(
            CaseAssignment::class,
            'blotter_case_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Current Assignment
    |--------------------------------------------------------------------------
    |
    | Returns the active assignment where completed_at is still NULL.
    |
    */

    public function currentAssignment()
    {
        return $this->hasOne(
            CaseAssignment::class,
            'blotter_case_id'
        )
            ->whereNull('completed_at')
            ->latestOfMany();
    }

    /*
    |--------------------------------------------------------------------------
    | Investigation Notes
    |--------------------------------------------------------------------------
    */

    public function investigationNotes()
    {
        return $this->hasMany(
            InvestigationNote::class,
            'blotter_case_id'
        );
    } public function mediationSessions()
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
    )->latestOfMany();
}
}