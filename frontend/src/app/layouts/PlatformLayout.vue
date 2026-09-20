<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'
import { Building2, LogOut } from 'lucide-vue-next'

import rafeeaLogo from '@/assets/brand/rafeea-logo.png'
import { useLogoutMutation } from '@/modules/auth/mutations/useLogoutMutation'
import { useCurrentUserQuery } from '@/modules/auth/queries/useCurrentUserQuery'
import AppConfirmDialog from '@/shared/components/AppConfirmDialog.vue'
import AppPullToRefresh from '@/shared/components/AppPullToRefresh.vue'
import AppToastHost from '@/shared/components/AppToastHost.vue'
import PwaInstallCard from '@/shared/components/PwaInstallCard.vue'
import UserAvatar from '@/shared/components/UserAvatar.vue'

const { t } = useI18n()
const route = useRoute()
const { data: user } = useCurrentUserQuery()
const { mutate: logout, isPending: isLoggingOut } = useLogoutMutation()
const mainEl = ref<HTMLElement | null>(null)

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
  <div class="app-shell flex h-full min-h-0 overflow-hidden bg-brand-bg text-brand-text md:gap-3 md:p-3 xl:gap-4 xl:p-4">
    <!-- Desktop ≥1280: full platform sidebar -->
    <div class="hidden h-full min-h-0 w-[17.5rem] shrink-0 self-stretch overflow-visible xl:block">
      <aside
        class="platform-sidebar flex h-full w-full min-h-0 flex-col text-white"
        :aria-label="t('nav.platformArea')"
      >
        <div class="shrink-0 px-3.5 pb-3 pt-3.5">
          <div class="flex flex-col items-center gap-3 text-center">
            <div
              class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-full bg-gradient-to-br from-brand-gold to-[#8a6a2e] p-0.5 shadow-lg shadow-brand-gold/25 ring-2 ring-white/10"
            >
              <div class="flex h-full w-full items-center justify-center overflow-hidden rounded-full bg-[#05291f]">
                <img
                  :src="rafeeaLogo"
                  :alt="t('auth.companyNameEn')"
                  class="h-12 w-12 object-contain"
                  width="48"
                  height="48"
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
            class="flex min-h-11 items-center gap-2.5 rounded-xl px-3 text-sm font-semibold transition"
            :class="
              item.active
                ? 'bg-white/14 text-white'
                : 'text-white/78 hover:bg-white/10 hover:text-white'
            "
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
            class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-white/10 text-sm font-semibold text-white transition hover:bg-white/15 disabled:opacity-60"
            :disabled="isLoggingOut"
            @click="logout()"
          >
            <LogOut class="h-4 w-4" />
            {{ t('auth.logout') }}
          </button>
        </div>
      </aside>
    </div>

    <!-- Tablet 768–1279: compact rail -->
    <aside
      class="platform-sidebar hidden h-full min-h-0 w-[var(--app-nav-rail-width)] shrink-0 flex-col self-stretch md:flex xl:hidden"
      :aria-label="t('nav.platformArea')"
    >
      <div class="flex flex-col items-center gap-2 px-2 pb-3 pt-4">
        <RouterLink
          to="/platform/tenants"
          class="app-nav-rail__logo"
          :aria-label="t('nav.platformArea')"
        >
          <span class="app-brand-mark app-brand-mark--md">
            <img
              :src="rafeeaLogo"
              alt=""
              class="app-brand-mark__img"
              width="48"
              height="48"
              decoding="async"
            />
          </span>
        </RouterLink>
      </div>
      <nav class="flex min-h-0 flex-1 flex-col items-stretch gap-1 overflow-y-auto px-2 py-1">
        <RouterLink
          v-for="item in navItems"
          :key="item.to"
          :to="item.to"
          class="app-nav-rail__item"
          :class="{ 'app-nav-rail__item--active': item.active }"
          :aria-current="item.active ? 'page' : undefined"
          :title="item.label"
        >
          <component :is="item.icon" class="h-5 w-5 shrink-0" :stroke-width="1.75" />
          <span class="app-nav-rail__label">{{ item.label }}</span>
        </RouterLink>
      </nav>
      <div class="flex flex-col items-stretch gap-2 border-t border-white/10 px-2 py-3">
        <button
          type="button"
          class="app-nav-rail__item"
          :aria-label="t('auth.logout')"
          :disabled="isLoggingOut"
          @click="logout()"
        >
          <LogOut class="h-5 w-5 shrink-0" :stroke-width="1.75" />
          <span class="app-nav-rail__label">{{ t('auth.logout') }}</span>
        </button>
      </div>
    </aside>

    <div class="flex h-full min-h-0 min-w-0 flex-1 flex-col self-stretch md:gap-3 xl:gap-4">
      <header
        class="flex h-12 shrink-0 items-center justify-between gap-3 border-b border-brand-border bg-brand-surface px-3 md:h-14 md:rounded-[16px] md:border md:px-4 xl:h-16 xl:px-5"
        style="padding-inline-start: max(12px, env(safe-area-inset-left, 0px)); padding-inline-end: max(12px, env(safe-area-inset-right, 0px)); padding-top: env(safe-area-inset-top, 0px)"
      >
        <div class="flex min-w-0 items-center gap-2.5">
          <RouterLink
            to="/platform/tenants"
            class="flex shrink-0 items-center gap-1.5 md:hidden"
            :aria-label="t('nav.platformArea')"
          >
            <span class="app-brand-mark app-brand-mark--sm">
              <img :src="rafeeaLogo" alt="" class="app-brand-mark__img" width="40" height="40" decoding="async" />
            </span>
          </RouterLink>
          <div class="min-w-0">
            <p class="truncate text-xs font-semibold text-brand-text-muted">{{ t('nav.platformArea') }}</p>
            <p class="hidden truncate text-sm font-semibold text-brand-text md:block xl:hidden">
              {{ displayName }}
            </p>
          </div>
        </div>

        <div class="flex items-center gap-1.5">
          <div class="hidden items-center gap-2 xl:flex">
            <UserAvatar :user="user" size="sm" />
            <div class="min-w-0">
              <p class="truncate text-sm font-semibold leading-tight text-brand-text">{{ displayName }}</p>
              <p class="truncate text-xs leading-tight text-brand-text-muted">{{ roleLabel }}</p>
            </div>
          </div>
          <UserAvatar :user="user" size="sm" class="xl:hidden" />
          <button
            type="button"
            class="inline-flex h-11 min-w-11 items-center justify-center gap-1.5 rounded-[10px] px-2 text-sm font-semibold text-brand-text-secondary transition hover:bg-[var(--surface-muted)] disabled:opacity-60 md:hidden"
            :disabled="isLoggingOut"
            :aria-label="t('auth.logout')"
            @click="logout()"
          >
            <LogOut class="h-4 w-4" />
          </button>
        </div>
      </header>

      <nav
        class="flex shrink-0 gap-2 overflow-x-auto px-3 md:hidden"
        :aria-label="t('nav.platformArea')"
        style="padding-inline-start: max(12px, env(safe-area-inset-left, 0px)); padding-inline-end: max(12px, env(safe-area-inset-right, 0px))"
      >
        <RouterLink
          v-for="item in navItems"
          :key="item.to"
          :to="item.to"
          class="inline-flex h-11 shrink-0 items-center gap-2 rounded-[10px] border px-3 text-sm font-semibold"
          :class="
            item.active
              ? 'border-brand-primary/35 bg-brand-primary-soft text-brand-primary-dark'
              : 'border-brand-border bg-brand-surface text-brand-text'
          "
        >
          <component :is="item.icon" class="h-4 w-4" />
          {{ item.label }}
        </RouterLink>
      </nav>

      <main
        ref="mainEl"
        class="app-shell-main min-h-0 flex-1 overflow-x-hidden overflow-y-auto overscroll-y-contain"
      >
        <AppPullToRefresh :scroller="mainEl" />
        <div class="app-page-container mx-auto w-full max-w-[1440px] space-y-4">
          <div class="md:hidden">
            <PwaInstallCard />
          </div>
          <slot />
        </div>
      </main>
    </div>

    <AppToastHost />
    <AppConfirmDialog />
  </div>
</template>

<style scoped>
.platform-sidebar {
  border-radius: var(--radius-card-lg);
  background: linear-gradient(180deg, #0a5c45 0%, #064e3b 55%, #04382b 100%);
  color: #fff;
  box-shadow: 0 12px 28px -20px rgb(6 78 59 / 0.5);
}
</style>
