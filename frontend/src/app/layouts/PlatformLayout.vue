<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'
import { Building2, LogOut } from 'lucide-vue-next'

import rafeeaLogo from '@/assets/brand/rafeea-logo.png'
import { useLogoutMutation } from '@/modules/auth/mutations/useLogoutMutation'
import { useCurrentUserQuery } from '@/modules/auth/queries/useCurrentUserQuery'
import AppConfirmDialog from '@/shared/components/AppConfirmDialog.vue'
import AppToastHost from '@/shared/components/AppToastHost.vue'
import UserAvatar from '@/shared/components/UserAvatar.vue'

const { t } = useI18n()
const route = useRoute()
const { data: user } = useCurrentUserQuery()
const { mutate: logout, isPending: isLoggingOut } = useLogoutMutation()

const displayName = computed(() => user.value?.name ?? t('auth.userFallback'))
const roleLabel = computed(() => user.value?.roles?.[0]?.name ?? t('auth.platformUser'))

const navItems = computed(() => [
  {
    to: '/platform/tenants',
    label: t('nav.platformTenants'),
    icon: Building2,
    active:
      route.path === '/platform/tenants' ||
      route.path.startsWith('/platform/tenants/'),
  },
])
</script>

<template>
  <div class="app-shell flex h-dvh overflow-hidden bg-brand-bg text-brand-text md:gap-3 md:p-3 lg:gap-4 lg:p-4">
    <div class="hidden h-full w-[17.5rem] shrink-0 overflow-visible md:block">
      <aside
        class="dashboard-sidebar sidebar-glass flex h-full w-full min-h-0 flex-col text-white"
        :aria-label="t('nav.platformArea')"
      >
        <div class="sidebar-brand shrink-0 px-3.5 pb-3 pt-3.5">
          <div class="flex flex-col items-center gap-3 text-center">
            <div
              class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-full bg-gradient-to-br from-brand-gold to-[#8a6a2e] p-0.5 shadow-lg shadow-brand-gold/25 ring-2 ring-white/10"
            >
              <div
                class="sidebar-logo-frame flex h-full w-full items-center justify-center overflow-hidden rounded-full bg-[#05291f]"
              >
                <img
                  :src="rafeeaLogo"
                  :alt="t('auth.companyNameEn')"
                  class="sidebar-logo"
                  width="80"
                  height="80"
                  decoding="async"
                />
              </div>
            </div>
            <div class="min-w-0 space-y-1">
              <div class="text-[15px] font-bold leading-tight tracking-tight">
                {{ t('nav.platformArea') }}
              </div>
              <div class="text-[11px] font-medium text-white/45">
                {{ t('app.name') }}
              </div>
            </div>
          </div>
        </div>

        <nav class="min-h-0 flex-1 space-y-1 overflow-y-auto px-2.5 py-2">
          <RouterLink
            v-for="item in navItems"
            :key="item.to"
            :to="item.to"
            class="sidebar-nav-link"
            :class="item.active ? 'sidebar-nav-link--active' : ''"
          >
            <component :is="item.icon" class="h-4 w-4 shrink-0 opacity-90" />
            <span>{{ item.label }}</span>
          </RouterLink>
        </nav>

        <div class="shrink-0 border-t border-white/10 p-3">
          <div class="mb-3 flex items-center gap-2.5 px-1">
            <UserAvatar :user="user" size="sm" />
            <div class="min-w-0">
              <p class="truncate text-sm font-semibold">{{ displayName }}</p>
              <p class="truncate text-[11px] text-white/50">{{ roleLabel }}</p>
            </div>
          </div>
          <button
            type="button"
            class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl bg-white/10 text-sm font-semibold text-white transition hover:bg-white/15 disabled:opacity-60"
            :disabled="isLoggingOut"
            @click="logout()"
          >
            <LogOut class="h-4 w-4" />
            {{ t('auth.logout') }}
          </button>
        </div>
      </aside>
    </div>

    <div class="flex min-h-0 min-w-0 flex-1 flex-col md:gap-3 lg:gap-4">
      <header
        class="flex shrink-0 items-center justify-between gap-3 rounded-[1.5rem] border border-brand-border bg-brand-surface px-4 py-3 shadow-[0_10px_28px_-24px_rgba(23,32,29,0.22)] sm:px-5"
      >
        <div class="min-w-0">
          <p class="text-xs font-semibold text-brand-text-muted">{{ t('nav.platformArea') }}</p>
          <h1 class="truncate text-base font-bold text-brand-text sm:text-lg">
            {{ t('nav.platformTenants') }}
          </h1>
        </div>
        <div class="flex items-center gap-2 md:hidden">
          <UserAvatar :user="user" size="sm" />
          <button
            type="button"
            class="inline-flex h-10 items-center gap-1.5 rounded-xl border border-brand-border px-3 text-sm font-semibold"
            :disabled="isLoggingOut"
            @click="logout()"
          >
            <LogOut class="h-4 w-4" />
            {{ t('auth.logout') }}
          </button>
        </div>
      </header>

      <!-- Mobile nav -->
      <nav
        class="flex shrink-0 gap-2 overflow-x-auto px-1 md:hidden"
        :aria-label="t('nav.platformArea')"
      >
        <RouterLink
          v-for="item in navItems"
          :key="item.to"
          :to="item.to"
          class="inline-flex h-10 shrink-0 items-center gap-2 rounded-xl border px-3 text-sm font-semibold"
          :class="
            item.active
              ? 'border-brand-primary bg-brand-primary-soft text-brand-primary-dark'
              : 'border-brand-border bg-brand-surface text-brand-text'
          "
        >
          <component :is="item.icon" class="h-4 w-4" />
          {{ item.label }}
        </RouterLink>
      </nav>

      <main class="app-shell-main min-h-0 flex-1 overflow-auto">
        <div class="app-page-container mx-auto w-full max-w-[1440px]">
          <slot />
        </div>
      </main>
    </div>

    <AppToastHost />
    <AppConfirmDialog />
  </div>
</template>
