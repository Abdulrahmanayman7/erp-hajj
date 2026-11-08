<?php

namespace App\Modules\SeasonalTemplates\Enums;

/**
 * draft → active ⇄ paused → archived (archived is terminal, S08-D12).
 */
enum SeasonReminderRuleStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
    case Archived = 'archived';

    public function canActivate(): bool
    {
        return $this === self::Draft || $this === self::Paused;
    }

    public function canPause(): bool
    {
        return $this === self::Active;
    }

    public function canArchive(): bool
    {
        return $this === self::Active || $this === self::Paused;
    }

    /**
     * Content (title, body, trigger, recipient permission, severity) is editable
     * only while the rule is not delivering and not terminal.
     */
    public function isEditable(): bool
    {
        return $this === self::Draft || $this === self::Paused;
    }
}
