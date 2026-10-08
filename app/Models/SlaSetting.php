<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlaSetting extends Model
{
    protected function casts(): array
    {
        return ['targets' => 'array', 'non_working_dates' => 'array', 'near_percent' => 'integer', 'revision' => 'integer'];
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
