<?php

namespace App\Modules\Transportation\Enums;

/**
 * `none`, `compatible`, `overridden` are stored at assignment time;
 * `mismatch` is only ever computed live (I10).
 */
enum EntitlementStatus: string
{
    case None = 'none';
    case Compatible = 'compatible';
    case Mismatch = 'mismatch';
    case Overridden = 'overridden';
}
