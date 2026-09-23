<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediationAttendee extends Model
{
    protected $fillable = [
        'mediation_session_id',
        'resident_id',
        'participant_type',
        'name',
        'is_present',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'is_present' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Mediation Session
    |--------------------------------------------------------------------------
    |
    | Keep the original mediationSession() relationship for compatibility
    | with any existing code, and expose session() because the controller
    | uses $attendee->session.
    |
    */

    public function mediationSession()
    {
        return $this->belongsTo(
            MediationSession::class,
            'mediation_session_id'
        );
    }

    public function session()
    {
        return $this->belongsTo(
            MediationSession::class,
            'mediation_session_id'
        );
    }

    public function resident()
    {
        return $this->belongsTo(
            Resident::class
        );
    }
}
