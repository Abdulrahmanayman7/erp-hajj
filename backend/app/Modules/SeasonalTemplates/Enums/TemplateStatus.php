<?php

namespace App\Modules\SeasonalTemplates\Enums;

enum TemplateStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Archived = 'archived';

    public function canApprove(): bool
    {
        return $this === self::Draft;
    }

    public function canArchive(): bool
    {
        return $this === self::Draft || $this === self::Approved;
    }

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }
}
