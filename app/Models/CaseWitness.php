<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseWitness extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Mass Assignable Fields
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'blotter_case_id',
        'resident_id',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'contact_number',
        'address',
        'statement',
    ];

    /*
    |--------------------------------------------------------------------------
    | Blotter Case Relationship
    |--------------------------------------------------------------------------
    |
    | The witness belongs to one blotter case.
    |
    */

    public function blotterCase(): BelongsTo
    {
        return $this->belongsTo(
            BlotterCase::class,
            'blotter_case_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Resident Relationship
    |--------------------------------------------------------------------------
    |
    | A witness may optionally be linked to a registered resident.
    |
    */

    public function resident(): BelongsTo
    {
        return $this->belongsTo(
            Resident::class,
            'resident_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Full Name Helper
    |--------------------------------------------------------------------------
    |
    | Allows us to use:
    |
    | $witness->full_name
    |
    | inside Blade views.
    |
    */

    public function getFullNameAttribute(): string
    {
        return trim(
            collect([
                $this->first_name,
                $this->middle_name,
                $this->last_name,
                $this->suffix,
            ])
                ->filter()
                ->implode(' ')
        );
    }
}