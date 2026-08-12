<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'
import { ChevronDown, CircleHelp, LogOut } from 'lucide-vue-next'

import { useLogoutMutation } from '@/modules/auth/mutations/useLogoutMutation'
import { useCurrentUserQuery } from '@/modules/auth/queries/useCurrentUserQuery'
import NotificationBell from '@/modules/notifications/components/NotificationBell.vue'
import UserAvatar from '@/shared/components/UserAvatar.vue'

const { t } = useI18n()
const route = useRoute()
const { data: user } = useCurrentUserQuery()
const { mutate: logout, isPending: isLoggingOut } = useLogoutMutation()

const menuOpen = ref(false)
const menuRoot = ref<HTMLElement | null>(null)

const displayName = computed(() => user.value?.name ?? t('auth.userFallback'))
const roleLabel = computed(() => user.value?.roles?.[0]?.name ?? t('auth.systemManager'))

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
    class="flex h-[72px] shrink-0 items-center justify-between gap-4 rounded-[1.5rem] border border-brand-border bg-brand-surface px-5 shadow-[0_10px_28px_-24px_rgba(23,32,29,0.28)] sm:px-6 lg:px-8"
  >
    <nav class="min-w-0 text-sm" aria-label="مسار التنقل">
      <ol class="flex flex-wrap items-center gap-1.5 text-brand-text-secondary">
        <li v-for="(crumb, index) in breadcrumbs" :key="`${crumb.label}-${index}`" class="flex items-center gap-1.5">
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

    <div class="flex items-center gap-1 sm:gap-2">
      <NotificationBell />

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
          class="absolute inset-e-0 top-full z-40 mt-2 w-56 overflow-hidden rounded-xl border border-brand-border bg-brand-surface py-1 shadow-[0_12px_32px_-18px_rgba(23,32,29,0.35)]"
          role="menu"
        >
          <div class="border-b border-brand-border px-3 py-2.5 sm:hidden">
            <p class="truncate text-sm font-semibold text-brand-text">{{ displayName }}</p>
            <p class="truncate text-xs text-brand-text-secondary">{{ roleLabel }}</p>
          </div>
          <button
            type="button"
            class="flex w-full items-center gap-2.5 px-3 py-2.5 text-sm text-brand-danger transition hover:bg-brand-bg disabled:opacity-60"
            role="menuitem"
            :disabled="isLoggingOut"
            @click="onLogout"
          >
            <LogOut class="h-4 w-4" :stroke-width="1.75" />
            <span>{{ t('auth.logout') }}</span>
          </button>
        </div>
      </div>
    </div>
  </header>
</template>
