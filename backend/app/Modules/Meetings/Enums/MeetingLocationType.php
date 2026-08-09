<?php

namespace App\Modules\Meetings\Enums;

enum MeetingLocationType: string
{
    case Physical = 'physical';
    case Remote = 'remote';
    case Hybrid = 'hybrid';
}
