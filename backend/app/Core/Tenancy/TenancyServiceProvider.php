<?php

namespace App\Core\Tenancy;

use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped: fresh instance per request and per queued job — state can
        // never leak across units of work even in long-running workers.
        $this->app->scoped(TenantContext::class);
        $this->app->scoped(PlatformContext::class);

        $this->app->bind(TenantResolver::class, AuthenticatedUserTenantResolver::class);
    }
}
