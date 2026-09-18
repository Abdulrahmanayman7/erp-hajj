<?php

namespace App\Core\Auth;

/**
 * Visual avatar style group for local static assets.
 * Not biological gender and never inferred from the user's name.
 */
enum AvatarGroup: string
{
    case Male = 'male';
    case Female = 'female';
    case Neutral = 'neutral';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
