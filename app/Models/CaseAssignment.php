<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseAssignment extends Model
{
    protected $fillable = [
        'blotter_case_id',
        'assigned_to',
        'assigned_by',
        'assignment_notes',
        'assigned_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function blotterCase()
    {
        return $this->belongsTo(BlotterCase::class);
    }

    public function assignedOfficer()
    {
        return $this->belongsTo(
            User::class,
            'assigned_to'
        );
    }

    public function assignedBy()
    {
        return $this->belongsTo(
            User::class,
            'assigned_by'
        );
    }
}