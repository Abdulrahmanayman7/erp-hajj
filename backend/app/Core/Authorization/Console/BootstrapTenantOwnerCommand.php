<?php

namespace App\Core\Authorization\Console;

use App\Core\Authorization\Actions\BootstrapTenantOwner;
use App\Core\Authorization\Exceptions\TenantOwnerBootstrapException;
use App\Core\Authorization\TenantOwnerGuard;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Operational CLI: bootstrap the first (or additional confirmed) Tenant Owner.
 * Never accepts passwords as argv flags (avoids shell history leakage).
 */
class BootstrapTenantOwnerCommand extends Command
{
    protected $signature = 'tenant:bootstrap-owner
                            {--tenant= : Tenant code (defaults to interactive prompt; example default rafee)}
                            {--name= : Owner display name}
                            {--email= : Owner email}
                            {--force : Confirm adding another owner when one already exists}
                            {--assign-existing : Confirm assigning tenant_owner to an existing same-tenant user}';

    protected $description = 'Securely bootstrap an active Tenant Owner for a tenant (operational CLI only)';

    public function handle(
        BootstrapTenantOwner $action,
        TenantOwnerGuard $ownerGuard,
        TenantContext $tenantContext,
    ): int {
        $tenantCode = (string) ($this->option('tenant') ?: $this->ask('Tenant code', 'rafee'));
        $tenantCode = mb_strtolower(trim($tenantCode));

        $tenant = Tenant::query()->where('tenant_code', $tenantCode)->first();
        if ($tenant === null) {
            $this->error("Tenant [{$tenantCode}] was not found.");

            return self::FAILURE;
        }

        $activeOwners = $tenantContext->runAsTenant(
            $tenant,
            fn (): int => $ownerGuard->countActiveOwners((int) $tenant->id),
        );

        $confirmExistingOwners = (bool) $this->option('force');
        if ($activeOwners > 0 && ! $confirmExistingOwners) {
            $this->warn("Tenant [{$tenantCode}] already has {$activeOwners} active Tenant Owner(s).");
            if (! $this->confirm('Continue and assign/create another Tenant Owner?', false)) {
                $this->info('Aborted. No changes were made.');

                return self::SUCCESS;
            }
            $confirmExistingOwners = true;
        }

        $name = (string) ($this->option('name') ?: $this->ask('Name'));
        $email = (string) ($this->option('email') ?: $this->ask('Email'));

        // Password is never accepted via CLI options (shell history safety).
        $password = (string) $this->secret('Password');
        $confirmation = (string) $this->secret('Confirm password');

        if ($password === '' || $password !== $confirmation) {
            $this->error('Password confirmation does not match.');

            return self::FAILURE;
        }

        $confirmAssignExisting = (bool) $this->option('assign-existing');

        try {
            $result = $action->execute(
                $tenantCode,
                $name,
                $email,
                $password,
                $confirmExistingOwners,
                $confirmAssignExisting,
            );
        } catch (TenantOwnerBootstrapException $e) {
            if ($e->errorCode === 'EXISTING_USER_NEEDS_CONFIRMATION') {
                $this->warn($e->getMessage());
                if (! $this->confirm('Assign Tenant Owner to the existing user without changing their password?', false)) {
                    $this->info('Aborted. No changes were made.');

                    return self::SUCCESS;
                }

                try {
                    $result = $action->execute(
                        $tenantCode,
                        $name,
                        $email,
                        $password,
                        $confirmExistingOwners,
                        true,
                    );
                } catch (TenantOwnerBootstrapException $retry) {
                    $this->error($retry->getMessage());

                    return self::FAILURE;
                }
            } else {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
        }

        if ($result['already_owner'] && ! $result['assigned_owner'] && ! $result['created_user']) {
            $this->info("Tenant Owner already provisioned for tenant: {$result['tenant_code']} ({$result['email']})");

            return self::SUCCESS;
        }

        $this->info("Tenant Owner bootstrap completed successfully for tenant: {$result['tenant_code']}");
        $this->line("User email: {$result['email']}");

        return self::SUCCESS;
    }
}
