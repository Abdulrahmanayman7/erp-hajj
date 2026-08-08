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
      path: '/app/roles/:id/permissions',
      name: 'role-permissions',
      component: () => import('@/modules/roles/pages/RolePermissionsPage.vue'),
      meta: { requiresAuth: true, layout: 'app', permission: 'roles.view' },
    },
  ],
})

router.beforeEach(createAuthGuard(queryClient))
