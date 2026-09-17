import { QueryClient } from '@tanstack/vue-query'
import { createRouter, createWebHistory } from 'vue-router'

import { createAuthGuard } from '../guards/authGuard'

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      refetchOnWindowFocus: false,
    },
  },
})

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      redirect: '/app',
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('@/modules/auth/pages/LoginPage.vue'),
      meta: { guestOnly: true, layout: 'guest' },
    },
    {
      path: '/forgot-password',
      name: 'forgot-password',
      component: () => import('@/modules/auth/pages/ForgotPasswordPage.vue'),
      meta: { guestOnly: true, layout: 'guest' },
    },
    {
      path: '/reset-password',
      name: 'reset-password',
      component: () => import('@/modules/auth/pages/ResetPasswordPage.vue'),
      meta: { guestOnly: true, layout: 'guest' },
    },
    {
      path: '/access-blocked',
      name: 'access-blocked',
      component: () => import('@/modules/auth/pages/AccessBlockedPage.vue'),
      meta: { guestOnly: true, layout: 'guest' },
    },
    {
      path: '/setup',
      name: 'platform-setup',
      component: () => import('@/modules/platform/pages/PlatformSetupPage.vue'),
      meta: { guestOnly: true, layout: 'guest' },
    },
    {
      path: '/platform',
      redirect: '/platform/tenants',
    },
    {
      path: '/platform/tenants',
      name: 'platform-tenants',
      component: () => import('@/modules/platform/pages/PlatformTenantsPage.vue'),
      meta: {
        requiresAuth: true,
        layout: 'platform',
        permission: 'platform_tenants.view',
      },
    },
    {
      path: '/platform/tenants/create',
      name: 'platform-tenants-create',
      component: () => import('@/modules/platform/pages/PlatformTenantCreatePage.vue'),
      meta: {
        requiresAuth: true,
        layout: 'platform',
        permission: 'platform_tenants.create',
      },
    },
    {
      path: '/platform/tenants/:id',
      name: 'platform-tenant-details',
      component: () => import('@/modules/platform/pages/PlatformTenantDetailsPage.vue'),
      meta: {
        requiresAuth: true,
        layout: 'platform',
        permission: 'platform_tenants.view',
      },
    },
    {
      path: '/app',
      name: 'app-home',
      component: () => import('@/modules/dashboard/pages/DashboardPage.vue'),
      meta: { requiresAuth: true, layout: 'app', permission: 'dashboard.view' },
    },
    {
      path: '/app/dashboard',
      redirect: '/app',
    },
    {
      path: '/app/403',
      name: 'app-forbidden',
      component: () => import('@/modules/auth/pages/ForbiddenPage.vue'),
      meta: { requiresAuth: true, layout: 'app' },
    },
    {
      path: '/app/users',
      name: 'users',
      component: () => import('@/modules/users/pages/UsersPage.vue'),
      meta: { requiresAuth: true, layout: 'app', permission: 'users.view' },
    },
    {
      path: '/app/roles',
      name: 'roles',
      component: () => import('@/modules/roles/pages/RolesPage.vue'),
      meta: { requiresAuth: true, layout: 'app', permission: 'roles.view' },
    },
    {
      path: '/app/organization',
      name: 'organization',
      component: () => import('@/modules/organization/pages/OrganizationPage.vue'),
      meta: { requiresAuth: true, layout: 'app', permission: 'organization_units.view' },
    },
    {
      path: '/app/organization-tree',
      name: 'organization-tree',
      component: () => import('@/modules/organization/pages/OrganizationTreePage.vue'),
      meta: { requiresAuth: true, layout: 'app', permission: 'organization_units.view' },
    },
    {
      path: '/app/employees',
      name: 'employees',
      component: () => import('@/modules/employees/pages/EmployeesPage.vue'),
      meta: { requiresAuth: true, layout: 'app', permission: 'employees.view' },
    },
    {
      path: '/app/contracts',
      name: 'contracts',
      component: () => import('@/modules/contracts/pages/ContractsPage.vue'),
      meta: { requiresAuth: true, layout: 'app', permission: 'contracts.view' },
    },
    {
      path: '/app/contracts/:id',
      name: 'contract-details',
      component: () => import('@/modules/contracts/pages/ContractDetailsPage.vue'),
      meta: { requiresAuth: true, layout: 'app', permission: 'contracts.view' },
    },
    {
      path: '/app/meetings',
      name: 'meetings',
      component: () => import('@/modules/meetings/pages/MeetingsPage.vue'),
      meta: { requiresAuth: true, layout: 'app', permission: 'meetings.view' },
    },
    {
      path: '/app/meetings/:id',
      name: 'meeting-details',
      component: () => import('@/modules/meetings/pages/MeetingDetailsPage.vue'),
      meta: { requiresAuth: true, layout: 'app', permission: 'meetings.view' },
    },
    {
      path: '/app/decisions',
      name: 'decisions',
      component: () => import('@/modules/decisions/pages/DecisionsPage.vue'),
      meta: { requiresAuth: true, layout: 'app', permission: 'decisions.view' },
    },
    {
      path: '/app/decisions/:id',
      name: 'decision-details',
      component: () => import('@/modules/decisions/pages/DecisionDetailsPage.vue'),
      meta: { requiresAuth: true, layout: 'app', permission: 'decisions.view' },
    },
    {
      path: '/app/tasks',
      name: 'tasks',
      component: () => import('@/modules/tasks/pages/TasksPage.vue'),
      meta: { requiresAuth: true, layout: 'app', permission: 'tasks.view' },
    },
    {
      path: '/app/tasks/:id',
      name: 'task-details',
      component: () => import('@/modules/tasks/pages/TaskDetailsPage.vue'),
      meta: { requiresAuth: true, layout: 'app', permission: 'tasks.view' },
    },
    {
      path: '/app/documents',
      name: 'documents',
      component: () => import('@/modules/documents/pages/DocumentsListPage.vue'),
      meta: { requiresAuth: true, layout: 'app', permission: 'documents.view' },
    },
    {
      path: '/app/documents/:id',
      name: 'document-details',
      component: () => import('@/modules/documents/pages/DocumentDetailsPage.vue'),
      meta: { requiresAuth: true, layout: 'app', permission: 'documents.view' },
    },
    {
      path: '/app/warehouses',
      name: 'warehouses',
      component: () => import('@/modules/inventory/pages/WarehousesPage.vue'),
      meta: { requiresAuth: true, layout: 'app', permission: 'warehouses.view' },
    },
    {
      path: '/app/warehouses/:id',
      name: 'warehouse-details',
      component: () => import('@/modules/inventory/pages/WarehouseDetailsPage.vue'),
      meta: { requiresAuth: true, layout: 'app', permission: 'warehouses.view' },
    },
    {
      path: '/app/inventory',
      name: 'inventory',
      component: () => import('@/modules/inventory/pages/InventoryBalancesPage.vue'),
      meta: { requiresAuth: true, layout: 'app', permission: 'inventory.view' },
    },
    {
      path: '/app/inventory/items',
      name: 'inventory-items',
      component: () => import('@/modules/inventory/pages/InventoryItemsPage.vue'),
      meta: { requiresAuth: true, layout: 'app', permission: 'inventory.view' },
    },
    {
      path: '/app/inventory/items/:id',
      name: 'inventory-item-details',
      component: () => import('@/modules/inventory/pages/InventoryItemDetailsPage.vue'),
      meta: { requiresAuth: true, layout: 'app', permission: 'inventory.view' },
    },
    {
      path: '/app/inventory/movements',
      name: 'inventory-movements',
      component: () => import('@/modules/inventory/pages/InventoryMovementsPage.vue'),
      meta: { requiresAuth: true, layout: 'app', permission: 'inventory.view' },
    },
    {
      path: '/app/assets',
      name: 'assets',
      component: () => import('@/modules/assets/pages/AssetsListPage.vue'),
      meta: { requiresAuth: true, layout: 'app', permission: 'assets.view' },
    },
    {
      path: '/app/assets/:id',
      name: 'asset-details',
      component: () => import('@/modules/assets/pages/AssetDetailsPage.vue'),
      meta: { requiresAuth: true, layout: 'app', permission: 'assets.view' },
    },
    {
      path: '/app/my-custodies',
      name: 'my-custodies',
      component: () => import('@/modules/assets/pages/MyCustodiesPage.vue'),
      meta: { requiresAuth: true, layout: 'app', permission: 'assets.view' },
    },
    {
      path: '/app/notifications',
      name: 'notifications',
      component: () => import('@/modules/notifications/pages/NotificationsPage.vue'),
      meta: { requiresAuth: true, layout: 'app' },
    },
    {
      path: '/app/audit',
      name: 'audit',
      component: () => import('@/modules/audit/pages/AuditListPage.vue'),
      meta: { requiresAuth: true, layout: 'app', permission: 'audit_logs.view' },
    },
    {
      path: '/app/audit/:id',
      name: 'audit-detail',
      component: () => import('@/modules/audit/pages/AuditDetailPage.vue'),
      meta: { requiresAuth: true, layout: 'app', permission: 'audit_logs.view' },
    },
    {
      path: '/app/settings',
      name: 'settings',
      component: () => import('@/modules/settings/pages/SettingsPage.vue'),
      meta: { requiresAuth: true, layout: 'app', permission: 'tenant_settings.view' },
    },
    {
      path: '/app/roles/:id/permissions',
      name: 'role-permissions',
      component: () => import('@/modules/roles/pages/RolePermissionsPage.vue'),
      meta: { requiresAuth: true, layout: 'app', permission: 'roles.view' },
    },
  ],
})

router.beforeEach(createAuthGuard(queryClient))
