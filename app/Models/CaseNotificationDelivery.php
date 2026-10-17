<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseNotificationDelivery extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['recipient_email'];

    protected function casts(): array
    {
        return ['message' => 'encrypted', 'recipient_email' => 'encrypted', 'submitted_at' => 'datetime'];
    }

    public function maskedRecipient(): string
    {
        [$local, $domain] = explode('@', $this->recipient_email, 2);

        return mb_substr($local, 0, 1).'***@'.$domain;
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CaseNotificationContact::class, 'case_notification_contact_id');
    }
}
