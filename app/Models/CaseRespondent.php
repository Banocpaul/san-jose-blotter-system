<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseRespondent extends Model
{
    protected $fillable = [
        'blotter_case_id',
        'resident_id',

        'is_san_jose_resident',
        'house_number',
        'sitio',

        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'contact_number',
        'address',
    ];

    protected $casts = [
        'is_san_jose_resident' => 'boolean',
    ];

    public function blotterCase()
    {
        return $this->belongsTo(
            BlotterCase::class
        );
    }

    public function resident()
    {
        return $this->belongsTo(
            Resident::class
        );
    }
}