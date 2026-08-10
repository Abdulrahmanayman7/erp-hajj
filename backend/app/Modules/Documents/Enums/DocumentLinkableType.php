<?php

namespace App\Modules\Documents\Enums;

/**
 * Stable morph aliases for Document.linkable_type (never FQCN).
 */
enum DocumentLinkableType: string
{
    case Contract = 'contract';
    case Meeting = 'meeting';
    case Decision = 'decision';
    case Task = 'task';
    case Employee = 'employee';
    case OrganizationUnit = 'organization_unit';

    /**
     * @return list<string>
     */
    public static function mvpAliases(): array
    {
        return array_map(
            static fn (self $case): string => $case->value,
            self::cases(),
        );
    }

    public static function tryFromAlias(?string $alias): ?self
    {
        if ($alias === null || $alias === '') {
            return null;
        }

        return self::tryFrom($alias);
    }
}
