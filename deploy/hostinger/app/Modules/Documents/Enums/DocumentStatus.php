<?php

namespace App\Modules\Documents\Enums;

enum DocumentStatus: string
{
    case Active = 'active';
    case Archived = 'archived';

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
