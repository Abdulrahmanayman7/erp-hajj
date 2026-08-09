<?php

namespace App\Modules\OrganizationStructure\Support;

use App\Core\Auth\UserStatus;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\OrganizationStructure\Enums\OrganizationUnitStatus;
use App\Modules\OrganizationStructure\Exceptions\OrganizationDomainException;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;

/**
 * Shared validation for parent / manager / sibling name used by Actions.
 */
final class OrganizationUnitValidator
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function resolveActiveParent(?int $parentId): ?OrganizationUnit
    {
        if ($parentId === null) {
            return null;
        }

        $parent = OrganizationUnit::query()->whereKey($parentId)->first();
        if ($parent === null) {
            throw OrganizationDomainException::parentInvalid();
        }

        if ($parent->status !== OrganizationUnitStatus::Active) {
            throw OrganizationDomainException::parentInactive();
        }

        return $parent;
    }

    public function resolveAssignableManager(?int $managerUserId): ?User
    {
        if ($managerUserId === null) {
            return null;
        }

        $tenant = $this->tenantContext->require();
        $user = User::query()
            ->whereKey($managerUserId)
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($user === null) {
            throw OrganizationDomainException::managerInvalid();
        }

        if ($user->status !== UserStatus::Active) {
            throw OrganizationDomainException::managerInvalid();
        }

        return $user;
    }

    public function assertSiblingNameAvailable(string $name, ?int $parentId, ?int $ignoreId = null): void
    {
        $query = OrganizationUnit::query()
            ->where('name', $name)
            ->where(function ($q) use ($parentId): void {
                if ($parentId === null) {
                    $q->whereNull('parent_id');
                } else {
                    $q->where('parent_id', $parentId);
                }
            });

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        if ($query->exists()) {
            throw OrganizationDomainException::nameTaken();
        }
    }
}
