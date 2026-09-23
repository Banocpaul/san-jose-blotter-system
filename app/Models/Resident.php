<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Resident extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'resident_code',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'sex',
        'birth_date',
        'civil_status',
        'contact_number',
        'email',
        'house_number',
        'street',
        'purok',
        'address_details',
        'is_registered_voter',
        'classification',
        'vulnerable_sector',
        'emergency_contact_name',
        'emergency_contact_number',
        'government_id_type',
        'government_id_number',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'is_registered_voter' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function complaints()
    {
        return $this->hasMany(CaseComplainant::class);
    }

    public function responses()
    {
        return $this->hasMany(CaseRespondent::class);
    }

    public function witnessRecords()
    {
        return $this->hasMany(CaseWitness::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim(
            $this->first_name . ' ' .
            ($this->middle_name ?? '') . ' ' .
            $this->last_name . ' ' .
            ($this->suffix ?? '')
        );
    }
}