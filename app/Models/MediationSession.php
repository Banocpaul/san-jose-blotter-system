<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediationSession extends Model
{
    protected $fillable = [
        'blotter_case_id',
        'hearing_number',
        'proceeding_type',
        'scheduled_date',
        'scheduled_time',
        'venue',
        'lupon_member_id',
        'created_by',
        'status',
        'mediation_notes',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function blotterCase()
    {
        return $this->belongsTo(
            BlotterCase::class,
            'blotter_case_id'
        );
    }

    public function luponMember()
    {
        return $this->belongsTo(
            User::class,
            'lupon_member_id'
        );
    }

    public function creator()
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function attendees()
    {
        return $this->hasMany(
            MediationAttendee::class,
            'mediation_session_id'
        );
    }

    public function summons()
    {
        return $this->hasMany(
            MediationSummons::class,
            'mediation_session_id'
        );
    }

    public function outcome()
    {
        return $this->hasOne(
            MediationOutcome::class,
            'mediation_session_id'
        );
    }
}