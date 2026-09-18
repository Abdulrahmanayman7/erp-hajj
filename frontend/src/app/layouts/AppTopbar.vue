<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'
import {
  ArrowRight,
  ChevronDown,
  ChevronsLeft,
  ChevronsRight,
  FolderTree,
  LogOut,
  Maximize2,
  Minimize2,
  RefreshCw,
} from 'lucide-vue-next'

import { useLogoutMutation } from '@/modules/auth/mutations/useLogoutMutation'
import { useCurrentUserQuery } from '@/modules/auth/queries/useCurrentUserQuery'
import NotificationBell from '@/modules/notifications/components/NotificationBell.vue'
import AppTooltip from '@/shared/components/AppTooltip.vue'
import UserAvatar from '@/shared/components/UserAvatar.vue'
import { useAppChromeNavigation } from '@/shared/composables/useAppChromeNavigation'
import { useFullscreen } from '@/shared/composables/useFullscreen'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useSidebarCollapse } from '@/shared/composables/useSidebarCollapse'

const { t } = useI18n()
const route = useRoute()
const { data: user } = useCurrentUserQuery()
const { can } = usePermissions()
const { mutate: logout, isPending: isLoggingOut } = useLogoutMutation()
const { collapsed, toggleCollapsed } = useSidebarCollapse()
const { isFullscreen, toggleFullscreen } = useFullscreen()
const {
  goBack,
  hardRefreshProgress,
  isHoldingRefresh,
  onRefreshPointerDown,
  onRefreshPointerUp,
  onRefreshPointerCancel,
} = useAppChromeNavigation()

const menuOpen = ref(false)
const menuRoot = ref<HTMLElement | null>(null)

const displayName = computed(() => user.value?.name ?? t('auth.userFallback'))
const roleLabel = computed(() => user.value?.roles?.[0]?.name ?? t('auth.systemManager'))
const tenantName = computed(() => user.value?.tenant?.name ?? null)
const canOpenOrganizationTree = computed(
  () => !!user.value?.tenant && can('organization_units.view'),
)
const sidebarToggleLabel = computed(() =>
  collapsed.value ? t('shell.expandSidebar') : t('shell.collapseSidebar'),
)
const fullscreenToggleLabel = computed(() =>
  isFullscreen.value ? t('shell.exitFullscreen') : t('shell.enterFullscreen'),
)
const refreshLabel = computed(() =>
  isHoldingRefresh.value
    ? t('shell.hardRefreshProgress', { progress: hardRefreshProgress.value })
    : t('shell.refresh'),
)

const pageTitle = computed(() => {
  const crumbs = breadcrumbs.value
  return crumbs[crumbs.length - 1]?.label ?? t('nav.dashboard')
})

const breadcrumbs = computed(() => {
  const path = route.path
  const crumbs = [{ label: t('shell.breadcrumbHome'), to: '/app' as string | null }]

  if (path.startsWith('/app/users')) {
    crumbs.push({ label: t('nav.users'), to: null })
  } else if (path.startsWith('/app/roles')) {
    crumbs.push({ label: t('nav.rolesShort'), to: null })
    if (path.includes('/permissions')) {
      crumbs[crumbs.length - 1] = { label: t('nav.rolesShort'), to: '/app/roles' }
      crumbs.push({ label: t('roles.actions.permissions'), to: null })
    }
  } else if (path.startsWith('/app/organization-tree')) {
    crumbs.push({ label: t('organizationTree.title'), to: null })
  } else if (path.startsWith('/app/organization')) {
    crumbs.push({ label: t('nav.organization'), to: null })
  } else if (path.startsWith('/app/employees')) {
    crumbs.push({ label: t('nav.employees'), to: null })
  } else if (path.startsWith('/app/contracts')) {
    crumbs.push({ label: t('nav.contracts'), to: null })
  } else if (path.startsWith('/app/meetings')) {
    crumbs.push({ label: t('nav.meetings'), to: null })
  } else if (path.startsWith('/app/decisions')) {
    crumbs.push({ label: t('nav.decisions'), to: null })
  } else if (path.startsWith('/app/tasks')) {
    crumbs.push({ label: t('nav.tasks'), to: null })
  } else if (path.startsWith('/app/documents')) {
    crumbs.push({ label: t('nav.documents'), to: null })
  } else if (path.startsWith('/app/warehouses')) {
    crumbs.push({ label: t('nav.warehouses'), to: null })
  } else if (path.startsWith('/app/inventory')) {
    crumbs.push({ label: t('nav.inventory'), to: null })
  } else if (path.startsWith('/app/assets')) {
    crumbs.push({ label: t('nav.assets'), to: null })
  } else if (path.startsWith('/app/my-custodies')) {
    crumbs.push({ label: t('nav.myCustodies'), to: null })
  } else if (path.startsWith('/app/audit')) {
    crumbs.push({ label: t('nav.audit'), to: null })
  } else if (path.startsWith('/app/settings')) {
    crumbs.push({ label: t('nav.settings'), to: null })
  } else if (path.startsWith('/app/notifications')) {
    crumbs.push({ label: t('nav.notifications'), to: null })
  } else if (path === '/app' || path === '/app/') {
    crumbs.push({ label: t('nav.dashboard'), to: null })
  }

  return crumbs
})

function toggleMenu(): void {
  menuOpen.value = !menuOpen.value
}

function closeMenu(): void {
  menuOpen.value = false
}

function onLogout(): void {
  closeMenu()
  logout()
}

function onDocumentClick(event: MouseEvent): void {
  if (!menuRoot.value) return
  if (!menuRoot.value.contains(event.target as Node)) {
    closeMenu()
  }
}

onMounted(() => {
  document.addEventListener('click', onDocumentClick)
})

onUnmounted(() => {
  document.removeEventListener('click', onDocumentClick)
})
</script>

<template>
  <header
    class="app-topbar flex h-12 shrink-0 items-center justify-between gap-2 px-3 md:h-14 md:gap-3 md:px-4 xl:h-16 xl:px-5"
    style="padding-inline-start: max(12px, env(safe-area-inset-left, 0px)); padding-inline-end: max(12px, env(safe-area-inset-right, 0px)); padding-top: env(safe-area-inset-top, 0px)"
  >
    <div class="flex min-w-0 items-center gap-2 md:gap-3">
      <!-- Desktop chrome tools (≥1280) -->
      <div class="hidden items-center gap-1 xl:flex">
        <AppTooltip :text="t('shell.goBack')" side="bottom">
          <button
            type="button"
            class="app-btn-ghost"
            :aria-label="t('shell.goBack')"
            @click="goBack"
          >
            <ArrowRight class="h-5 w-5" :stroke-width="2" aria-hidden="true" />
          </button>
        </AppTooltip>

        <AppTooltip :text="`${t('shell.refresh')} — ${t('shell.hardRefreshHint')}`" side="bottom">
          <button
            type="button"
            class="app-btn-ghost relative select-none"
            :aria-label="refreshLabel"
            :aria-valuemin="0"
            :aria-valuemax="100"
            :aria-valuenow="hardRefreshProgress"
            :aria-busy="isHoldingRefresh"
            @pointerdown.prevent="onRefreshPointerDown"
            @pointerup="onRefreshPointerUp"
            @pointerleave="onRefreshPointerCancel"
            @pointercancel="onRefreshPointerCancel"
          >
            <span
              v-if="isHoldingRefresh"
              class="absolute inset-1 rounded-[8px] bg-brand-primary/10"
              :style="{ opacity: Math.max(0.2, hardRefreshProgress / 100) }"
              aria-hidden="true"
            />
            <RefreshCw
              class="relative h-5 w-5"
              :class="{ 'animate-spin': isHoldingRefresh }"
              :stroke-width="2"
              aria-hidden="true"
            />
          </button>
        </AppTooltip>

        <AppTooltip :text="sidebarToggleLabel" side="bottom">
          <button
            type="button"
            class="app-btn-ghost text-brand-primary-dark"
            :aria-label="sidebarToggleLabel"
            :aria-expanded="!collapsed"
            aria-controls="app-sidebar"
            @click="toggleCollapsed"
          >
            <ChevronsRight
              v-if="!collapsed"
              class="h-5 w-5"
              :stroke-width="2"
              aria-hidden="true"
            />
            <ChevronsLeft v-else class="h-5 w-5" :stroke-width="2" aria-hidden="true" />
          </button>
        </AppTooltip>

        <div class="mx-1 h-5 w-px shrink-0 bg-brand-border" aria-hidden="true" />
      </div>

      <!-- Mobile: brand mark only -->
      <div class="min-w-0 md:hidden">
        <p class="truncate text-[17px] font-bold leading-none tracking-tight text-brand-primary-dark">
          {{ t('auth.companyName') }}
        </p>
      </div>

      <!-- Tablet: page title -->
      <div class="hidden min-w-0 md:block xl:hidden">
        <p class="truncate text-[15px] font-semibold text-brand-text">{{ pageTitle }}</p>
        <p v-if="tenantName" class="truncate text-xs text-brand-text-muted">{{ tenantName }}</p>
      </div>

      <!-- Desktop breadcrumbs -->
      <nav class="hidden min-w-0 text-sm xl:block" aria-label="مسار التنقل">
        <ol class="flex flex-wrap items-center gap-1.5 text-brand-text-secondary">
          <li
            v-for="(crumb, index) in breadcrumbs"
            :key="`${crumb.label}-${index}`"
            class="flex items-center gap-1.5"
          >
            <span v-if="index > 0" class="text-brand-text-muted" aria-hidden="true">/</span>
            <RouterLink
              v-if="crumb.to"
              :to="crumb.to"
              class="font-medium text-brand-text-secondary transition hover:text-brand-primary"
            >
              {{ crumb.label }}
            </RouterLink>
            <span v-else class="font-semibold text-brand-text">{{ crumb.label }}</span>
          </li>
        </ol>
      </nav>
    </div>

    <div class="flex items-center gap-0.5">
      <div
        v-if="tenantName"
        class="me-1 hidden max-w-[200px] items-center gap-1 rounded-[10px] bg-[var(--surface-subtle)] px-2.5 py-1 xl:flex"
      >
        <p class="min-w-0 truncate text-xs font-medium text-brand-text" :title="tenantName">
          {{ tenantName }}
        </p>
        <AppTooltip v-if="canOpenOrganizationTree" :text="t('organizationTree.openFromShell')" side="bottom">
          <RouterLink
            to="/app/organization-tree"
            class="app-btn-ghost !h-8 !w-8 text-brand-primary"
            :aria-label="t('organizationTree.openFromShell')"
          >
            <FolderTree class="h-4 w-4" :stroke-width="2" aria-hidden="true" />
          </RouterLink>
        </AppTooltip>
      </div>

      <AppTooltip
        v-if="canOpenOrganizationTree"
        :text="t('organizationTree.openFromShell')"
        side="bottom"
      >
        <RouterLink
          to="/app/organization-tree"
          class="app-btn-ghost hidden text-brand-primary md:inline-flex xl:hidden"
          :aria-label="t('organizationTree.openFromShell')"
        >
          <FolderTree class="h-5 w-5" :stroke-width="1.75" aria-hidden="true" />
        </RouterLink>
      </AppTooltip>

      <NotificationBell />

      <AppTooltip :text="fullscreenToggleLabel" side="bottom">
        <button
          type="button"
          class="app-btn-ghost hidden xl:inline-flex"
          :aria-label="fullscreenToggleLabel"
          :aria-pressed="isFullscreen"
          @click="toggleFullscreen"
        >
          <Minimize2
            v-if="isFullscreen"
            class="h-5 w-5"
            :stroke-width="1.75"
            aria-hidden="true"
          />
          <Maximize2
            v-else
            class="h-5 w-5"
            :stroke-width="1.75"
            aria-hidden="true"
          />
        </button>
      </AppTooltip>

      <div ref="menuRoot" class="relative">
        <button
          type="button"
          class="inline-flex max-w-[220px] items-center gap-2 rounded-[10px] p-1 transition hover:bg-[var(--surface-muted)] md:px-1.5"
          :aria-expanded="menuOpen"
          aria-haspopup="menu"
          @click.stop="toggleMenu"
        >
          <UserAvatar :user="user" size="lg" :lazy="false" />
          <div class="hidden min-w-0 text-start xl:block">
            <p class="truncate text-sm font-semibold leading-tight text-brand-text">{{ displayName }}</p>
            <p class="truncate text-xs leading-tight text-brand-text-muted">{{ roleLabel }}</p>
          </div>
          <ChevronDown class="hidden h-4 w-4 text-brand-text-muted xl:block" :stroke-width="1.75" />
        </button>

        <div
          v-if="menuOpen"
          class="absolute inset-e-0 top-full z-40 mt-2 w-44 overflow-hidden rounded-[14px] border border-brand-border bg-brand-surface p-1"
          style="box-shadow: var(--shadow-overlay)"
          role="menu"
        >
          <button
            type="button"
            class="flex w-full items-center gap-2 rounded-[8px] px-2.5 py-2 text-[13px] font-medium text-brand-danger transition hover:bg-[var(--danger-soft)] disabled:opacity-60"
            role="menuitem"
            :disabled="isLoggingOut"
            @click="onLogout"
          >
            <LogOut class="h-3.5 w-3.5 shrink-0" :stroke-width="1.75" />
            <span>{{ t('auth.logout') }}</span>
          </button>
        </div>
      </div>
    </div>
  </header>
</template>
