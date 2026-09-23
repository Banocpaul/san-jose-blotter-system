<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediationOutcome extends Model
{
    protected $fillable = [
        'mediation_session_id',
        'outcome',
        'agreement_details',
        'referral_agency',
        'remarks',
        'recorded_by',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
        ];
    }

    public function mediationSession()
    {
        return $this->belongsTo(
            MediationSession::class
        );
    }

    public function recordedBy()
    {
        return $this->belongsTo(
            User::class,
            'recorded_by'
        );
    }
}