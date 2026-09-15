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
        'tasks' => 'المهام',
        'documents' => 'الوثائق',
        'warehouses' => 'المستودعات',
        'inventory' => 'المخزون',
        'assets' => 'الأصول',
        'audit_logs' => 'سجل التدقيق',
        'dashboard' => 'لوحة التحكم',
        'tenant_settings' => 'إعدادات المنشأة',
    ];

    /**
     * @return list<array{name: string, display_name: string, module: string, description: string|null}>
     */
    public static function definitions(): array
    {
        return [
            ['name' => 'users.view', 'display_name' => 'عرض المستخدمين', 'module' => 'users', 'description' => 'عرض قائمة مستخدمي المنشأة وتفاصيلهم'],
            ['name' => 'users.create', 'display_name' => 'إنشاء مستخدم', 'module' => 'users', 'description' => 'إنشاء مستخدمين داخل المنشأة'],
            ['name' => 'users.update', 'display_name' => 'تحديث مستخدم', 'module' => 'users', 'description' => 'تحديث اسم أو بريد المستخدم'],
            ['name' => 'users.disable', 'display_name' => 'تفعيل/تعطيل مستخدم', 'module' => 'users', 'description' => 'تفعيل المستخدمين أو تعطيلهم'],
            ['name' => 'users.assign_roles', 'display_name' => 'تعيين أدوار المستخدم', 'module' => 'users', 'description' => 'استبدال مجموعة أدوار المستخدم'],

            ['name' => 'roles.view', 'display_name' => 'عرض الأدوار', 'module' => 'roles', 'description' => 'عرض قائمة الأدوار وتفاصيلها'],
            ['name' => 'roles.create', 'display_name' => 'إنشاء دور', 'module' => 'roles', 'description' => 'إنشاء أدوار مخصصة'],
            ['name' => 'roles.update', 'display_name' => 'تحديث دور', 'module' => 'roles', 'description' => 'تحديث بيانات الدور أو تفعيله'],
            ['name' => 'roles.delete', 'display_name' => 'حذف دور', 'module' => 'roles', 'description' => 'حذف نهائي للأدوار المخصصة غير المستخدمة'],
            ['name' => 'roles.assign_permissions', 'display_name' => 'تعيين صلاحيات الدور', 'module' => 'roles', 'description' => 'استبدال مجموعة صلاحيات الدور'],

            ['name' => 'permissions.view', 'display_name' => 'عرض كتالوج الصلاحيات', 'module' => 'permissions', 'description' => 'قراءة كتالوج الصلاحيات العام'],

            ['name' => 'organization_units.view', 'display_name' => 'عرض الهيكل التنظيمي', 'module' => 'organization_units', 'description' => 'عرض شجرة الوحدات التنظيمية وتفاصيلها'],
            ['name' => 'organization_units.create', 'display_name' => 'إنشاء وحدة تنظيمية', 'module' => 'organization_units', 'description' => 'إنشاء وحدات تنظيمية'],
            ['name' => 'organization_units.update', 'display_name' => 'تحديث وحدة تنظيمية', 'module' => 'organization_units', 'description' => 'تحديث الوحدات أو نقلها أو تفعيلها/تعطيلها وتعيين مدير الوحدة'],
            ['name' => 'organization_units.delete', 'display_name' => 'حذف وحدة تنظيمية', 'module' => 'organization_units', 'description' => 'حذف نهائي لوحدات تنظيمية طرفية عند السماح'],

            ['name' => 'employees.view', 'display_name' => 'عرض الموظفين', 'module' => 'employees', 'description' => 'عرض قائمة الموظفين وتفاصيلهم'],
            ['name' => 'employees.create', 'display_name' => 'إنشاء موظف', 'module' => 'employees', 'description' => 'إنشاء سجلات موظفين'],
            ['name' => 'employees.update', 'display_name' => 'تحديث موظف', 'module' => 'employees', 'description' => 'تحديث الملف الشخصي أو التفعيل أو ربط/فك ربط مستخدم'],
            ['name' => 'employees.deactivate', 'display_name' => 'تعطيل موظف', 'module' => 'employees', 'description' => 'تعطيل الموظفين'],
            ['name' => 'employees.assign_supervisor', 'display_name' => 'تعيين المشرف المباشر', 'module' => 'employees', 'description' => 'تعيين المشرف المباشر للموظف أو إزالته'],

            ['name' => 'positions.view', 'display_name' => 'عرض المسميات الوظيفية', 'module' => 'positions', 'description' => 'عرض قائمة المسميات الوظيفية وتفاصيلها'],
            ['name' => 'positions.create', 'display_name' => 'إنشاء مسمى وظيفي', 'module' => 'positions', 'description' => 'إنشاء مسميات وظيفية'],
            ['name' => 'positions.update', 'display_name' => 'تحديث مسمى وظيفي', 'module' => 'positions', 'description' => 'تحديث المسميات أو تفعيلها أو تعطيلها'],
            ['name' => 'positions.delete', 'display_name' => 'حذف مسمى وظيفي', 'module' => 'positions', 'description' => 'حذف نهائي للمسميات غير المستخدمة'],

            ['name' => 'contracts.view', 'display_name' => 'عرض العقود', 'module' => 'contracts', 'description' => 'عرض العقود وتصنيفاتها'],
            ['name' => 'contracts.create', 'display_name' => 'إنشاء عقد', 'module' => 'contracts', 'description' => 'إنشاء عقود في حالة المسودة'],
            ['name' => 'contracts.update', 'display_name' => 'تحديث عقد', 'module' => 'contracts', 'description' => 'تحديث عقود المسودة وإدارة التصنيفات'],
            ['name' => 'contracts.review', 'display_name' => 'مراجعة عقد', 'module' => 'contracts', 'description' => 'إرسال العقد للمراجعة أو إعادته للمسودة'],
            ['name' => 'contracts.approve', 'display_name' => 'اعتماد عقد', 'module' => 'contracts', 'description' => 'اعتماد العقود قيد المراجعة'],
            ['name' => 'contracts.sign', 'display_name' => 'توقيع عقد', 'module' => 'contracts', 'description' => 'تسجيل شهادة توقيع العقد'],
            ['name' => 'contracts.execute', 'display_name' => 'تنفيذ عقد', 'module' => 'contracts', 'description' => 'بدء تنفيذ العقد'],
            ['name' => 'contracts.close', 'display_name' => 'إغلاق عقد', 'module' => 'contracts', 'description' => 'إغلاق العقود قيد التنفيذ'],
            ['name' => 'contracts.renew', 'display_name' => 'تجديد عقد', 'module' => 'contracts', 'description' => 'تجديد العقود قيد التنفيذ'],
            ['name' => 'contracts.cancel', 'display_name' => 'إلغاء عقد', 'module' => 'contracts', 'description' => 'إلغاء العقود قبل التوقيع'],
            ['name' => 'contracts.delete', 'display_name' => 'حذف عقد', 'module' => 'contracts', 'description' => 'حذف نهائي لعقود المسودة المؤهلة'],

            ['name' => 'meetings.view', 'display_name' => 'عرض الاجتماعات', 'module' => 'meetings', 'description' => 'عرض الاجتماعات والحضور وجدول الأعمال والمحضر والتوصيات'],
            ['name' => 'meetings.create', 'display_name' => 'إنشاء اجتماع', 'module' => 'meetings', 'description' => 'إنشاء اجتماعات في حالة المسودة'],
            ['name' => 'meetings.update', 'display_name' => 'تحديث اجتماع', 'module' => 'meetings', 'description' => 'تحديث الاجتماع أو جدولته/بدؤه/إكماله وإدارة جدول الأعمال وحذف المسودات المؤهلة'],
            ['name' => 'meetings.cancel', 'display_name' => 'إلغاء اجتماع', 'module' => 'meetings', 'description' => 'إلغاء الاجتماعات في المسودة أو المجدولة أو الجارية'],
            ['name' => 'meetings.manage_attendees', 'display_name' => 'إدارة حضور الاجتماع', 'module' => 'meetings', 'description' => 'إضافة الحضور أو إزالتهم وتحديث حالة الحضور'],
            ['name' => 'meetings.manage_minutes', 'display_name' => 'إدارة محضر الاجتماع', 'module' => 'meetings', 'description' => 'تحديث المحضر وإدارة التوصيات'],

            ['name' => 'decisions.view', 'display_name' => 'عرض القرارات', 'module' => 'decisions', 'description' => 'عرض القرارات وسجلها'],
            ['name' => 'decisions.create', 'display_name' => 'إنشاء قرار', 'module' => 'decisions', 'description' => 'إنشاء قرارات مستقلة أو مبنية على توصية'],
            ['name' => 'decisions.update', 'display_name' => 'تحديث قرار', 'module' => 'decisions', 'description' => 'تعديل قرارات المسودة وإرسالها أو إلغاؤها'],
            ['name' => 'decisions.approve', 'display_name' => 'اعتماد قرار', 'module' => 'decisions', 'description' => 'اعتماد القرارات أو إعادتها للمسودة'],
            ['name' => 'decisions.close', 'display_name' => 'إغلاق قرار', 'module' => 'decisions', 'description' => 'إغلاق القرارات المعتمدة'],
            ['name' => 'decisions.delete', 'display_name' => 'حذف قرار', 'module' => 'decisions', 'description' => 'حذف نهائي لقرارات المسودة غير المستخدمة'],

            ['name' => 'tasks.view', 'display_name' => 'عرض المهام', 'module' => 'tasks', 'description' => 'عرض المهام وسجلها'],
            ['name' => 'tasks.create', 'display_name' => 'إنشاء مهمة', 'module' => 'tasks', 'description' => 'إنشاء مهام مستقلة أو مرتبطة بقرار'],
            ['name' => 'tasks.update', 'display_name' => 'تحديث مهمة', 'module' => 'tasks', 'description' => 'تعديل محتوى المهمة في المسودة أو المعيَّنة'],
            ['name' => 'tasks.assign', 'display_name' => 'تعيين مهمة', 'module' => 'tasks', 'description' => 'تعيين منفّذ المهمة أو إعادة تعيينه'],
            ['name' => 'tasks.change_status', 'display_name' => 'تغيير حالة المهمة', 'module' => 'tasks', 'description' => 'بدء المهمة أو إلغاؤها وتحديث تقدمها'],
            ['name' => 'tasks.complete', 'display_name' => 'إكمال مهمة', 'module' => 'tasks', 'description' => 'إكمال المهام مع ملاحظات الإكمال'],
            ['name' => 'tasks.delete', 'display_name' => 'حذف مهمة', 'module' => 'tasks', 'description' => 'حذف نهائي لمهام المسودة غير المستخدمة'],

            ['name' => 'documents.view', 'display_name' => 'عرض الوثائق', 'module' => 'documents', 'description' => 'عرض بيانات الوثائق الوصفية'],
            ['name' => 'documents.upload', 'display_name' => 'رفع مستند', 'module' => 'documents', 'description' => 'رفع الوثائق وربط اختياري أولي'],
            ['name' => 'documents.download', 'display_name' => 'تنزيل مستند', 'module' => 'documents', 'description' => 'تنزيل ملف المستند'],
            ['name' => 'documents.update', 'display_name' => 'تحديث مستند', 'module' => 'documents', 'description' => 'تحديث البيانات الوصفية والربط'],
            ['name' => 'documents.archive', 'display_name' => 'أرشفة/استعادة مستند', 'module' => 'documents', 'description' => 'أرشفة الوثائق واستعادتها'],
            ['name' => 'documents.delete', 'display_name' => 'حذف مستند', 'module' => 'documents', 'description' => 'حذف نهائي لبيانات المستند وملفه'],
            ['name' => 'documents.manage_categories', 'display_name' => 'إدارة تصنيفات الوثائق', 'module' => 'documents', 'description' => 'إنشاء وتحديث وحذف تصنيفات الوثائق'],

            ['name' => 'warehouses.view', 'display_name' => 'عرض المستودعات', 'module' => 'warehouses', 'description' => 'عرض قائمة المستودعات وتفاصيلها'],
            ['name' => 'warehouses.create', 'display_name' => 'إنشاء مستودع', 'module' => 'warehouses', 'description' => 'إنشاء مستودعات'],
            ['name' => 'warehouses.update', 'display_name' => 'تحديث مستودع', 'module' => 'warehouses', 'description' => 'تحديث المستودعات وتفعيلها أو تعطيلها'],
            ['name' => 'warehouses.delete', 'display_name' => 'حذف مستودع', 'module' => 'warehouses', 'description' => 'حذف نهائي للمستودعات غير المستخدمة'],

            ['name' => 'inventory.view', 'display_name' => 'عرض المخزون', 'module' => 'inventory', 'description' => 'عرض الأصناف والتصنيفات والأرصدة والحركات'],
            ['name' => 'inventory.manage_items', 'display_name' => 'إدارة أصناف المخزون', 'module' => 'inventory', 'description' => 'إدارة الأصناف والتصنيفات وتفعيلها أو تعطيلها'],
            ['name' => 'inventory.add', 'display_name' => 'إضافة مخزون', 'module' => 'inventory', 'description' => 'رصيد افتتاحي واستلام مخزون'],
            ['name' => 'inventory.issue', 'display_name' => 'صرف مخزون', 'module' => 'inventory', 'description' => 'صرف مخزون من المستودعات'],
            ['name' => 'inventory.return', 'display_name' => 'إرجاع مخزون', 'module' => 'inventory', 'description' => 'إرجاع مخزون إلى المستودعات'],
            ['name' => 'inventory.transfer', 'display_name' => 'تحويل مخزون', 'module' => 'inventory', 'description' => 'تحويل المخزون بين المستودعات'],
            ['name' => 'inventory.adjust', 'display_name' => 'تسوية مخزون', 'module' => 'inventory', 'description' => 'تسويات مخزون حساسة'],

            ['name' => 'assets.view', 'display_name' => 'عرض الأصول والعهد', 'module' => 'assets', 'description' => 'عرض الأصول والتصنيفات والعهد'],
            ['name' => 'assets.create', 'display_name' => 'تسجيل أصل', 'module' => 'assets', 'description' => 'تسجيل أصول جديدة'],
            ['name' => 'assets.update', 'display_name' => 'تحديث أصل', 'module' => 'assets', 'description' => 'تحديث بيانات الأصل أو التصنيفات أو الصيانة/الاستعادة'],
            ['name' => 'assets.delete', 'display_name' => 'حذف أصل', 'module' => 'assets', 'description' => 'حذف نهائي للأصول غير المستخدمة'],
            ['name' => 'assets.assign', 'display_name' => 'تسليم عهدة', 'module' => 'assets', 'description' => 'تسليم عهدة أصل'],
            ['name' => 'assets.return', 'display_name' => 'استلام عهدة', 'module' => 'assets', 'description' => 'استلام عهدة أصل'],
            ['name' => 'assets.retire', 'display_name' => 'استبعاد/فقد أصل', 'module' => 'assets', 'description' => 'استبعاد الأصل أو الإبلاغ عن فقده'],

            ['name' => 'audit_logs.view', 'display_name' => 'عرض سجل التدقيق', 'module' => 'audit_logs', 'description' => 'عرض سجلات تدقيق المنشأة'],

            ['name' => 'dashboard.view', 'display_name' => 'عرض لوحة التحكم', 'module' => 'dashboard', 'description' => 'الوصول إلى الصفحة الرئيسية بعد تسجيل الدخول'],
            ['name' => 'tenant_settings.view', 'display_name' => 'عرض إعدادات المنشأة', 'module' => 'tenant_settings', 'description' => 'عرض إعدادات المنشأة'],
            ['name' => 'tenant_settings.update', 'display_name' => 'تحديث إعدادات المنشأة', 'module' => 'tenant_settings', 'description' => 'تحديث إعدادات المنشأة'],
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
                    'audit_logs.view',
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
                    'tasks.view',
                    'tasks.create',
                    'tasks.update',
                    'tasks.assign',
                    'tasks.change_status',
                    'tasks.complete',
                    'tasks.delete',
                    'documents.view',
                    'documents.upload',
                    'documents.download',
                    'documents.update',
                    'documents.archive',
                    'documents.delete',
                    'documents.manage_categories',
                    'warehouses.view',
                    'warehouses.create',
                    'warehouses.update',
                    'warehouses.delete',
                    'inventory.view',
                    'inventory.manage_items',
                    'inventory.add',
                    'inventory.issue',
                    'inventory.return',
                    'inventory.transfer',
                    'inventory.adjust',
                    'assets.view',
                    'assets.create',
                    'assets.update',
                    'assets.delete',
                    'assets.assign',
                    'assets.return',
                    'assets.retire',
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
                    'tasks.view',
                    'tasks.create',
                    'tasks.update',
                    'tasks.assign',
                    'tasks.change_status',
                    'tasks.complete',
                    'tasks.delete',
                    'documents.view',
                    'documents.upload',
                    'documents.download',
                    'documents.update',
                    'documents.archive',
                    'documents.manage_categories',
                    'warehouses.view',
                    'warehouses.create',
                    'warehouses.update',
                    'inventory.view',
                    'inventory.manage_items',
                    'inventory.add',
                    'inventory.issue',
                    'inventory.return',
                    'inventory.transfer',
                    'assets.view',
                    'assets.create',
                    'assets.update',
                    'assets.assign',
                    'assets.return',
                ],
            ],
            'supervisor' => [
                'name' => 'المشرف',
                'description' => null,
                'permissions' => [
                    'dashboard.view',
                    'employees.view',
                    'tasks.view',
                    'documents.view',
                    'documents.download',
                    'warehouses.view',
                    'inventory.view',
                    'assets.view',
                ],
            ],
            'employee' => [
                'name' => 'الموظف',
                'description' => null,
                'permissions' => [
                    'dashboard.view',
                    'tasks.view',
                    'documents.view',
                    'documents.download',
                    'assets.view',
                ],
            ],
            'auditor' => [
                'name' => 'المراجع / المدقق',
                'description' => null,
                'permissions' => [
                    'dashboard.view',
                    'audit_logs.view',
                    'users.view',
                    'roles.view',
                    'permissions.view',
                    'employees.view',
                    'positions.view',
                    'contracts.view',
                    'meetings.view',
                    'decisions.view',
                    'tasks.view',
                    'documents.view',
                    'documents.download',
                    'warehouses.view',
                    'inventory.view',
                    'assets.view',
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
            'inventory.adjust',
            'assets.retire',
        ];
    }
}
