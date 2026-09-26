<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    |
    | Keep frequently reused person-directory filters in one place. This keeps
    | controllers smaller and avoids slightly different search logic across
    | modules that reuse the People Directory.
    |
    */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $searchQuery) use ($term) {
            $like = "%{$term}%";
            $codePrefix = "{$term}%";

            $searchQuery
                ->where('resident_code', 'like', $codePrefix)
                ->orWhere('first_name', 'like', $like)
                ->orWhere('middle_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('contact_number', 'like', $like)
                ->orWhere('street', 'like', $like)
                ->orWhere('purok', 'like', $like)
                ->orWhere('address_details', 'like', $like);
        });
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
        return collect([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
            $this->suffix,
        ])->filter()->join(' ');
    }
}
