<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CaseNotificationContact extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['email'];

    protected function casts(): array
    {
        return ['email' => 'encrypted', 'enabled' => 'boolean', 'consent_recorded_at' => 'datetime'];
    }

    public function blotterCase(): BelongsTo
    {
        return $this->belongsTo(BlotterCase::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(CaseNotificationDelivery::class);
    }

    public function party(): ?Model
    {
        $relation = match ($this->party_type) {
            'complainant' => 'complainants',
            'respondent' => 'respondents',
            'witness' => 'witnesses',
            default => null,
        };

        return $relation ? $this->blotterCase?->{$relation}()->find($this->party_id) : null;
    }

    public function maskedEmail(): string
    {
        [$local, $domain] = explode('@', $this->email, 2);

        return mb_substr($local, 0, 1).'***@'.$domain;
    }
}
