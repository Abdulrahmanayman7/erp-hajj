<?php

namespace App\Modules\SuppliersAndProcurement\Enums;

enum GeoPlaceKind: string
{
    case Country = 'country';
    case Region = 'region';
    case City = 'city';
    case HolySite = 'holy_site';
}
