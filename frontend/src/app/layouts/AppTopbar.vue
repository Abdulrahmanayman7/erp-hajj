<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'
import { ChevronDown, ChevronsLeft, ChevronsRight, CircleHelp, LogOut, Maximize2, Minimize2 } from 'lucide-vue-next'

import { useLogoutMutation } from '@/modules/auth/mutations/useLogoutMutation'
import { useCurrentUserQuery } from '@/modules/auth/queries/useCurrentUserQuery'
import NotificationBell from '@/modules/notifications/components/NotificationBell.vue'
import AppTooltip from '@/shared/components/AppTooltip.vue'
import UserAvatar from '@/shared/components/UserAvatar.vue'
import { useFullscreen } from '@/shared/composables/useFullscreen'
import { useSidebarCollapse } from '@/shared/composables/useSidebarCollapse'

const { t } = useI18n()
const route = useRoute()
const { data: user } = useCurrentUserQuery()
const { mutate: logout, isPending: isLoggingOut } = useLogoutMutation()
const { collapsed, toggleCollapsed } = useSidebarCollapse()
const { isFullscreen, toggleFullscreen } = useFullscreen()

const menuOpen = ref(false)
const menuRoot = ref<HTMLElement | null>(null)

const displayName = computed(() => user.value?.name ?? t('auth.userFallback'))
const roleLabel = computed(() => user.value?.roles?.[0]?.name ?? t('auth.systemManager'))
const sidebarToggleLabel = computed(() =>
  collapsed.value ? t('shell.expandSidebar') : t('shell.collapseSidebar'),
)
const fullscreenToggleLabel = computed(() =>
  isFullscreen.value ? t('shell.exitFullscreen') : t('shell.enterFullscreen'),
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
  } else if (path.startsWith('/app/organization')) {
    crumbs.push({ label: t('nav.organization'), to: null })
  } else if (path.startsWith('/app/employees')) {
    crumbs.push({ label: t('nav.employees'), to: null })
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
    class="flex h-[72px] shrink-0 items-center justify-between gap-4 rounded-[1.5rem] border border-brand-border bg-brand-surface px-4 shadow-[0_10px_28px_-24px_rgba(23,32,29,0.28)] sm:px-6 lg:px-8"
  >
    <div class="flex min-w-0 items-center gap-3">
      <AppTooltip :text="sidebarToggleLabel" side="bottom">
        <button
          type="button"
          class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-brand-primary-dark transition duration-200 hover:bg-brand-bg hover:text-brand-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/25 active:scale-[0.96] motion-reduce:transition-none motion-reduce:active:scale-100"
          :aria-label="sidebarToggleLabel"
          :aria-expanded="!collapsed"
          aria-controls="app-sidebar"
          @click="toggleCollapsed"
        >
          <!-- Sidebar is on the right (RTL): >> collapses, << expands -->
          <ChevronsRight
            v-if="!collapsed"
            class="h-5 w-5"
            :stroke-width="2.25"
            aria-hidden="true"
          />
          <ChevronsLeft
            v-else
            class="h-5 w-5"
            :stroke-width="2.25"
            aria-hidden="true"
          />
        </button>
      </AppTooltip>

      <div class="hidden h-7 w-px shrink-0 bg-brand-border/90 sm:block" aria-hidden="true" />

      <nav class="min-w-0 text-sm" aria-label="مسار التنقل">
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

    <div class="flex items-center gap-1 sm:gap-2">
      <NotificationBell />

      <AppTooltip :text="fullscreenToggleLabel" side="bottom">
        <button
          type="button"
          class="inline-flex h-10 w-10 items-center justify-center rounded-xl text-brand-text-secondary transition hover:bg-brand-bg hover:text-brand-primary"
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

      <button
        type="button"
        class="inline-flex h-10 w-10 items-center justify-center rounded-xl text-brand-text-secondary transition hover:bg-brand-bg hover:text-brand-primary"
        :aria-label="t('nav.help')"
        :title="t('shell.comingSoon')"
      >
        <CircleHelp class="h-5 w-5" :stroke-width="1.75" />
      </button>

      <div ref="menuRoot" class="relative ms-1">
        <button
          type="button"
          class="inline-flex max-w-[240px] items-center gap-2.5 rounded-xl px-2 py-1.5 transition hover:bg-brand-bg"
          :aria-expanded="menuOpen"
          aria-haspopup="menu"
          @click.stop="toggleMenu"
        >
          <UserAvatar :user="user" size="lg" :lazy="false" />
          <div class="hidden min-w-0 text-start sm:block">
            <p class="truncate text-sm font-semibold text-brand-text">{{ displayName }}</p>
            <p class="truncate text-xs text-brand-text-secondary">{{ roleLabel }}</p>
          </div>
          <ChevronDown class="hidden h-4 w-4 text-brand-text-muted sm:block" :stroke-width="1.75" />
        </button>

        <div
          v-if="menuOpen"
          class="absolute inset-e-0 top-full z-40 mt-3 w-44 overflow-hidden rounded-xl border border-brand-border bg-brand-surface p-1 shadow-[0_14px_32px_-18px_rgba(23,32,29,0.38)]"
          role="menu"
        >
          <button
            type="button"
            class="flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 text-[13px] font-medium text-brand-danger transition hover:bg-red-50/90 disabled:opacity-60"
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
