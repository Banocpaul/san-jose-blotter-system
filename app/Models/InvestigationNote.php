<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvestigationNote extends Model
{
    protected $fillable = [
        'blotter_case_id',
        'user_id',
        'note',
        'action_taken',
        'noted_at',
    ];

    protected function casts(): array
    {
        return [
            'noted_at' => 'datetime',
        ];
    }

    public function blotterCase()
    {
        return $this->belongsTo(BlotterCase::class);
    }

    public function author()
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        );
    }
}