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
      path: '/app',
      name: 'app-home',
      component: () => import('@/modules/auth/pages/AppHomePage.vue'),
      meta: { requiresAuth: true, layout: 'app' },
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
      path: '/app/roles/:id/permissions',
      name: 'role-permissions',
      component: () => import('@/modules/roles/pages/RolePermissionsPage.vue'),
      meta: { requiresAuth: true, layout: 'app', permission: 'roles.view' },
    },
  ],
})

router.beforeEach(createAuthGuard(queryClient))
