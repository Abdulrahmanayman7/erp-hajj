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
                ],
            ],
            'department_manager' => [
                'name' => 'مدير الإدارة',
                'description' => null,
                'permissions' => ['dashboard.view', 'users.view'],
            ],
            'supervisor' => [
                'name' => 'المشرف',
                'description' => null,
                'permissions' => ['dashboard.view'],
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
