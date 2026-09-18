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
  Menu,
  Minimize2,
  RefreshCw,
} from 'lucide-vue-next'

import rafeeaLogo from '@/assets/brand/rafeea-logo.png'
import { useLogoutMutation } from '@/modules/auth/mutations/useLogoutMutation'
import { useCurrentUserQuery } from '@/modules/auth/queries/useCurrentUserQuery'
import NotificationBell from '@/modules/notifications/components/NotificationBell.vue'
import AppTooltip from '@/shared/components/AppTooltip.vue'
import UserAvatar from '@/shared/components/UserAvatar.vue'
import { useAppChromeNavigation } from '@/shared/composables/useAppChromeNavigation'
import { useFullscreen } from '@/shared/composables/useFullscreen'
import { useMobileMore } from '@/shared/composables/useMobileMore'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useSidebarCollapse } from '@/shared/composables/useSidebarCollapse'

const { t } = useI18n()
const route = useRoute()
const { data: user } = useCurrentUserQuery()
const { can } = usePermissions()
const { mutate: logout, isPending: isLoggingOut } = useLogoutMutation()
const { collapsed, toggleCollapsed } = useSidebarCollapse()
const { moreOpen, openMore } = useMobileMore()
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
const tabletMenuRoot = ref<HTMLElement | null>(null)
const desktopMenuRoot = ref<HTMLElement | null>(null)

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
  const target = event.target as Node
  if (tabletMenuRoot.value?.contains(target) || desktopMenuRoot.value?.contains(target)) {
    return
  }
  closeMenu()
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
    <!-- Mobile: hamburger · centered logo · bell -->
    <div class="grid w-full grid-cols-[2.75rem_minmax(0,1fr)_2.75rem] items-center md:hidden">
      <button
        type="button"
        class="app-btn-ghost"
        :aria-label="t('shell.openMenu')"
        :aria-expanded="moreOpen"
        aria-controls="app-mobile-more-sheet"
        @click="openMore"
      >
        <Menu class="h-5 w-5" :stroke-width="1.9" aria-hidden="true" />
      </button>
      <RouterLink to="/app" class="flex items-center justify-center" :aria-label="t('auth.companyName')">
        <img :src="rafeeaLogo" alt="" class="h-8 w-8 object-contain" width="32" height="32" decoding="async" />
      </RouterLink>
      <div class="flex justify-end">
        <NotificationBell />
      </div>
    </div>

    <div class="hidden min-w-0 flex-1 items-center gap-2 md:flex md:gap-3">
      <!-- Tablet: avatar + short name at the start (RTL right) -->
      <div ref="tabletMenuRoot" class="relative hidden md:block xl:hidden">
        <button
          type="button"
          class="inline-flex max-w-[220px] items-center gap-2 rounded-[10px] p-1 transition hover:bg-[var(--surface-muted)] md:px-1.5"
          :aria-expanded="menuOpen"
          aria-haspopup="menu"
          @click.stop="toggleMenu"
        >
          <UserAvatar :user="user" size="lg" :lazy="false" />
          <div class="min-w-0 text-start">
            <p class="truncate text-sm font-semibold leading-tight text-brand-text">{{ displayName }}</p>
            <p class="truncate text-xs leading-tight text-brand-text-muted">{{ roleLabel }}</p>
          </div>
        </button>

        <div
          v-if="menuOpen"
          class="absolute inset-s-0 top-full z-40 mt-2 w-44 overflow-hidden rounded-[14px] border border-brand-border bg-brand-surface p-1"
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

    <div class="hidden items-center gap-0.5 md:flex">
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

      <RouterLink
        to="/app"
        class="ms-1 hidden shrink-0 items-center xl:hidden md:flex"
        :aria-label="t('auth.companyName')"
      >
        <img :src="rafeeaLogo" alt="" class="h-8 w-8 object-contain" width="32" height="32" decoding="async" />
      </RouterLink>

      <div ref="desktopMenuRoot" class="relative hidden xl:block">
        <button
          type="button"
          class="inline-flex max-w-[220px] items-center gap-2 rounded-[10px] p-1 transition hover:bg-[var(--surface-muted)] md:px-1.5"
          :aria-expanded="menuOpen"
          aria-haspopup="menu"
          @click.stop="toggleMenu"
        >
          <UserAvatar :user="user" size="lg" :lazy="false" />
          <div class="min-w-0 text-start">
            <p class="truncate text-sm font-semibold leading-tight text-brand-text">{{ displayName }}</p>
            <p class="truncate text-xs leading-tight text-brand-text-muted">{{ roleLabel }}</p>
          </div>
          <ChevronDown class="h-4 w-4 text-brand-text-muted" :stroke-width="1.75" />
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
