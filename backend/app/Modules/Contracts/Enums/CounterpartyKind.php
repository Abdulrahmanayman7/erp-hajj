<?php

namespace App\Modules\Contracts\Enums;

enum CounterpartyKind: string
{
    case Person = 'person';
    case Organization = 'organization';
    case Other = 'other';
}
