<?php

namespace App\Modules\SeasonalTemplates\Support;

use App\Core\Authorization\PermissionCatalog;
use App\Modules\SeasonalTemplates\Exceptions\SeasonalTemplatesDomainException;

/**
 * Deny-by-default allowlist of permissions a reminder rule may target (S08-D14).
 *
 * Only view-scoped, low-risk permissions that exist in the tenant
 * {@see PermissionCatalog} are approved. Anything administrative
 * (users.*, roles.*, tenant_settings.*, platform_*, approve / delete / manage actions)
 * is rejected, so a reminder can never be used to enumerate privileged users.
 * Adding a permission here requires an Owner decision and a MATRIX_VERSION bump.
 */
final class SeasonReminderRecipientPermissions
{
    /**
     * @return list<string>
     */
    public static function approved(): array
    {
        $approved = [
            'seasons.view',
            'season_templates.view',
            'season_projects.view',
            'tasks.view',
            'contracts.view',
            'meetings.view',
            'decisions.view',
            'season_copy.history',
            'season_reminders.view',
        ];

        $catalog = array_column(PermissionCatalog::definitions(), 'name');

        return array_values(array_filter(
            $approved,
            static fn (string $permission): bool => in_array($permission, $catalog, true),
        ));
    }

    public static function isApproved(string $permission): bool
    {
        return in_array($permission, self::approved(), true);
    }

    public static function assertApproved(string $permission): void
    {
        if (! self::isApproved($permission)) {
            throw SeasonalTemplatesDomainException::reminderRecipientPermissionDenied();
        }
    }
}
