<?php

namespace App\Modules\SuppliersAndProcurement\Policies;

use App\Models\User;
use App\Modules\SuppliersAndProcurement\Models\GeoPlace;

class GeoPlacePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('suppliers.view') || $actor->hasPermission('geo_places.view');
    }

    public function view(User $actor, GeoPlace $geoPlace): bool
    {
        return $this->viewAny($actor);
    }
}
