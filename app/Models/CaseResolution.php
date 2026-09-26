<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseResolution extends Model
{
    protected $fillable = [
        'blotter_case_id',
        'mediation_outcome_id',
        'resolution_type',
        'status',
        'agreement_details',
        'remarks',
        'finalized_by',
        'finalized_at',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'finalized_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function blotterCase()
    {
        return $this->belongsTo(BlotterCase::class);
    }

    public function mediationOutcome()
    {
        return $this->belongsTo(MediationOutcome::class);
    }

    public function finalizedBy()
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
