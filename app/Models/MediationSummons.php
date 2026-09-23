<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediationSummons extends Model
{
    protected $fillable = [
        'mediation_session_id',
        'resident_id',
        'recipient_type',
        'recipient_name',
        'status',
        'prepared_at',
        'served_at',
        'received_at',
        'delivery_notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'prepared_at' => 'datetime',
            'served_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Mediation Session
    |--------------------------------------------------------------------------
    |
    | Keep the original mediationSession() relationship for compatibility
    | with any existing code, and expose session() because the controller
    | uses $summons->session.
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

    public function creator()
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }
}
