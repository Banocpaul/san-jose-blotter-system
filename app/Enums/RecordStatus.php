<?php

namespace App\Enums;

enum RecordStatus: string
{
    case Open = 'Open';
    case Resolved = 'Resolved';
    case Closed = 'Closed';

    public function badgeClass(): string
    {
        return match ($this) {
            self::Open => 'text-bg-primary',
            self::Resolved => 'text-bg-success',
            self::Closed => 'text-bg-secondary',
        };
    }
}