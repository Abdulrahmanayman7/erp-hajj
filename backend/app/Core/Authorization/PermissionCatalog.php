<?php

namespace App\Core\Authorization;

/**
 * Sprint 006 permission catalog definitions (implemented modules only).
 *
 * @see docs/09-modules/02-users-and-authorization/PERMISSIONS.md
 */
final class PermissionCatalog
{
    public const MODULE_DISPLAY = [
        'users' => 'المستخدمون',
        'roles' => 'الأدوار',
        'permissions' => 'الصلاحيات',
        'organization_units' => 'الهيكل التنظيمي',
        'employees' => 'الموظفون',
        'positions' => 'المسميات الوظيفية',
        'contracts' => 'العقود',
        'meetings' => 'الاجتماعات',
        'decisions' => 'القرارات',
        'dashboard' => 'لوحة التحكم',
        'tenant_settings' => 'إعدادات المنشأة',
    ];

    /**
     * @return list<array{name: string, display_name: string, module: string, description: string|null}>
     */
    public static function definitions(): array
    {
        return [
            ['name' => 'users.view', 'display_name' => 'عرض المستخدمين', 'module' => 'users', 'description' => 'List and view tenant users'],
            ['name' => 'users.create', 'display_name' => 'إنشاء مستخدم', 'module' => 'users', 'description' => 'Create tenant users'],
            ['name' => 'users.update', 'display_name' => 'تحديث مستخدم', 'module' => 'users', 'description' => 'Update user name/email'],
            ['name' => 'users.disable', 'display_name' => 'تفعيل/تعطيل مستخدم', 'module' => 'users', 'description' => 'Enable or disable users'],
            ['name' => 'users.assign_roles', 'display_name' => 'تعيين أدوار المستخدم', 'module' => 'users', 'description' => 'Replace a user’s role set'],

            ['name' => 'roles.view', 'display_name' => 'عرض الأدوار', 'module' => 'roles', 'description' => 'List and view roles'],
            ['name' => 'roles.create', 'display_name' => 'إنشاء دور', 'module' => 'roles', 'description' => 'Create custom roles'],
            ['name' => 'roles.update', 'display_name' => 'تحديث دور', 'module' => 'roles', 'description' => 'Update role metadata / activate'],
            ['name' => 'roles.delete', 'display_name' => 'حذف دور', 'module' => 'roles', 'description' => 'Hard-delete unused custom roles'],
            ['name' => 'roles.assign_permissions', 'display_name' => 'تعيين صلاحيات الدور', 'module' => 'roles', 'description' => 'Replace a role’s permission set'],

            ['name' => 'permissions.view', 'display_name' => 'عرض كتالوج الصلاحيات', 'module' => 'permissions', 'description' => 'Read global permission catalog'],

            ['name' => 'organization_units.view', 'display_name' => 'عرض الهيكل التنظيمي', 'module' => 'organization_units', 'description' => 'View organization units tree and details'],
            ['name' => 'organization_units.create', 'display_name' => 'إنشاء وحدة تنظيمية', 'module' => 'organization_units', 'description' => 'Create organization units'],
            ['name' => 'organization_units.update', 'display_name' => 'تحديث وحدة تنظيمية', 'module' => 'organization_units', 'description' => 'Update, move, activate/deactivate units and assign unit manager'],
            ['name' => 'organization_units.delete', 'display_name' => 'حذف وحدة تنظيمية', 'module' => 'organization_units', 'description' => 'Hard-delete leaf organization units when allowed'],

            ['name' => 'employees.view', 'display_name' => 'عرض الموظفين', 'module' => 'employees', 'description' => 'List and view employees'],
            ['name' => 'employees.create', 'display_name' => 'إنشاء موظف', 'module' => 'employees', 'description' => 'Create employees'],
            ['name' => 'employees.update', 'display_name' => 'تحديث موظف', 'module' => 'employees', 'description' => 'Update profile, activate, link/unlink user'],
            ['name' => 'employees.deactivate', 'display_name' => 'تعطيل موظف', 'module' => 'employees', 'description' => 'Deactivate employees'],
            ['name' => 'employees.assign_supervisor', 'display_name' => 'تعيين المشرف المباشر', 'module' => 'employees', 'description' => 'Assign or clear employee supervisor'],

            ['name' => 'positions.view', 'display_name' => 'عرض المسميات الوظيفية', 'module' => 'positions', 'description' => 'List and view positions'],
            ['name' => 'positions.create', 'display_name' => 'إنشاء مسمى وظيفي', 'module' => 'positions', 'description' => 'Create positions'],
            ['name' => 'positions.update', 'display_name' => 'تحديث مسمى وظيفي', 'module' => 'positions', 'description' => 'Update / activate / deactivate positions'],
            ['name' => 'positions.delete', 'display_name' => 'حذف مسمى وظيفي', 'module' => 'positions', 'description' => 'Hard-delete unused positions'],

            ['name' => 'contracts.view', 'display_name' => 'عرض العقود', 'module' => 'contracts', 'description' => 'List and view contracts and categories'],
            ['name' => 'contracts.create', 'display_name' => 'إنشاء عقد', 'module' => 'contracts', 'description' => 'Create draft contracts'],
            ['name' => 'contracts.update', 'display_name' => 'تحديث عقد', 'module' => 'contracts', 'description' => 'Update draft contracts and manage categories'],
            ['name' => 'contracts.review', 'display_name' => 'مراجعة عقد', 'module' => 'contracts', 'description' => 'Submit for review and return to draft'],
            ['name' => 'contracts.approve', 'display_name' => 'اعتماد عقد', 'module' => 'contracts', 'description' => 'Approve contracts in review'],
            ['name' => 'contracts.sign', 'display_name' => 'توقيع عقد', 'module' => 'contracts', 'description' => 'Record contract signing attestation'],
            ['name' => 'contracts.execute', 'display_name' => 'تنفيذ عقد', 'module' => 'contracts', 'description' => 'Start contract execution'],
            ['name' => 'contracts.close', 'display_name' => 'إغلاق عقد', 'module' => 'contracts', 'description' => 'Close executing contracts'],
            ['name' => 'contracts.renew', 'display_name' => 'تجديد عقد', 'module' => 'contracts', 'description' => 'Renew executing contracts'],
            ['name' => 'contracts.cancel', 'display_name' => 'إلغاء عقد', 'module' => 'contracts', 'description' => 'Cancel pre-signature contracts'],
            ['name' => 'contracts.delete', 'display_name' => 'حذف عقد', 'module' => 'contracts', 'description' => 'Hard-delete eligible draft contracts'],

            ['name' => 'meetings.view', 'display_name' => 'عرض الاجتماعات', 'module' => 'meetings', 'description' => 'List and view meetings, attendees, agenda, minutes, recommendations'],
            ['name' => 'meetings.create', 'display_name' => 'إنشاء اجتماع', 'module' => 'meetings', 'description' => 'Create draft meetings'],
            ['name' => 'meetings.update', 'display_name' => 'تحديث اجتماع', 'module' => 'meetings', 'description' => 'Update meetings, schedule/start/complete, manage agenda, delete eligible drafts'],
            ['name' => 'meetings.cancel', 'display_name' => 'إلغاء اجتماع', 'module' => 'meetings', 'description' => 'Cancel draft, scheduled, or in-progress meetings'],
            ['name' => 'meetings.manage_attendees', 'display_name' => 'إدارة حضور الاجتماع', 'module' => 'meetings', 'description' => 'Add/remove attendees and update attendance status'],
            ['name' => 'meetings.manage_minutes', 'display_name' => 'إدارة محضر الاجتماع', 'module' => 'meetings', 'description' => 'Update minutes and manage recommendations'],

            ['name' => 'decisions.view', 'display_name' => 'عرض القرارات', 'module' => 'decisions', 'description' => 'List and view decisions and history'],
            ['name' => 'decisions.create', 'display_name' => 'إنشاء قرار', 'module' => 'decisions', 'description' => 'Create standalone or recommendation-sourced decisions'],
            ['name' => 'decisions.update', 'display_name' => 'تحديث قرار', 'module' => 'decisions', 'description' => 'Edit draft decisions; submit and cancel'],
            ['name' => 'decisions.approve', 'display_name' => 'اعتماد قرار', 'module' => 'decisions', 'description' => 'Approve or return decisions to draft'],
            ['name' => 'decisions.close', 'display_name' => 'إغلاق قرار', 'module' => 'decisions', 'description' => 'Close approved decisions'],
            ['name' => 'decisions.delete', 'display_name' => 'حذف قرار', 'module' => 'decisions', 'description' => 'Hard-delete untouched draft decisions'],

            ['name' => 'dashboard.view', 'display_name' => 'عرض لوحة التحكم', 'module' => 'dashboard', 'description' => 'Access authenticated home'],
            ['name' => 'tenant_settings.view', 'display_name' => 'عرض إعدادات المنشأة', 'module' => 'tenant_settings', 'description' => 'View tenant settings'],
            ['name' => 'tenant_settings.update', 'display_name' => 'تحديث إعدادات المنشأة', 'module' => 'tenant_settings', 'description' => 'Update tenant settings'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function allNames(): array
    {
        return array_column(self::definitions(), 'name');
    }

    /**
     * Default system role templates → permission names.
     *
     * @return array<string, array{name: string, description: string|null, permissions: list<string>|'*'}>
     */
    public static function roleTemplates(): array
    {
        $all = self::allNames();

        return [
            'tenant_owner' => [
                'name' => 'مالك المنشأة',
                'description' => 'أعلى صلاحية داخل المنشأة',
                'permissions' => $all,
            ],
            'general_manager' => [
                'name' => 'المدير العام',
                'description' => null,
                'permissions' => [
                    'dashboard.view',
                    'users.view',
                    'roles.view',
                    'permissions.view',
                    'tenant_settings.view',
                    'organization_units.view',
                    'organization_units.create',
                    'organization_units.update',
                    'organization_units.delete',
                    'employees.view',
                    'employees.create',
                    'employees.update',
                    'employees.deactivate',
                    'employees.assign_supervisor',
                    'positions.view',
                    'positions.create',
                    'positions.update',
                    'positions.delete',
                    'contracts.view',
                    'contracts.create',
                    'contracts.update',
                    'contracts.review',
                    'contracts.approve',
                    'contracts.sign',
                    'contracts.execute',
                    'contracts.close',
                    'contracts.renew',
                    'contracts.cancel',
                    'contracts.delete',
                    'meetings.view',
                    'meetings.create',
                    'meetings.update',
                    'meetings.cancel',
                    'meetings.manage_attendees',
                    'meetings.manage_minutes',
                    'decisions.view',
                    'decisions.create',
                    'decisions.update',
                    'decisions.approve',
                    'decisions.close',
                    'decisions.delete',
                ],
            ],
            'department_manager' => [
                'name' => 'مدير الإدارة',
                'description' => null,
                'permissions' => [
                    'dashboard.view',
                    'users.view',
                    'organization_units.view',
                    'employees.view',
                    'employees.update',
                    'employees.assign_supervisor',
                    'positions.view',
                    'contracts.view',
                    'contracts.create',
                    'contracts.update',
                    'contracts.review',
                    'meetings.view',
                    'meetings.create',
                    'meetings.update',
                    'meetings.manage_attendees',
                    'meetings.manage_minutes',
                    'decisions.view',
                    'decisions.create',
                    'decisions.update',
                    'decisions.delete',
                ],
            ],
            'supervisor' => [
                'name' => 'المشرف',
                'description' => null,
                'permissions' => [
                    'dashboard.view',
                    'employees.view',
                ],
            ],
            'employee' => [
                'name' => 'الموظف',
                'description' => null,
                'permissions' => ['dashboard.view'],
            ],
            'auditor' => [
                'name' => 'المراجع / المدقق',
                'description' => null,
                'permissions' => [
                    'dashboard.view',
                    'users.view',
                    'roles.view',
                    'permissions.view',
                    'employees.view',
                    'positions.view',
                    'contracts.view',
                    'meetings.view',
                    'decisions.view',
                ],
            ],
            'read_only' => [
                'name' => 'مستخدم اطلاع فقط',
                'description' => null,
                'permissions' => ['dashboard.view'],
            ],
        ];
    }

    public static function isPlatformPermission(string $name): bool
    {
        return str_starts_with($name, 'platform_tenants.');
    }

    /**
     * High-risk permissions for UI warnings.
     *
     * @return list<string>
     */
    public static function highRiskPermissions(): array
    {
        return [
            'users.assign_roles',
            'roles.assign_permissions',
            'users.disable',
            'tenant_settings.update',
        ];
    }
}
