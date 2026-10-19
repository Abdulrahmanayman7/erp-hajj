<?php

use App\Core\Tenancy\TenancyServiceProvider;
use App\Modules\Accommodation\AccommodationServiceProvider;
use App\Modules\SeasonPilgrims\SeasonPilgrimsServiceProvider;
use App\Modules\Transportation\TransportationServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    TenancyServiceProvider::class,
    SeasonPilgrimsServiceProvider::class,
    TransportationServiceProvider::class,
    AccommodationServiceProvider::class,
];
