<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

import { useLogoutMutation } from '@/modules/auth/mutations/useLogoutMutation'
import { useCurrentUserQuery } from '@/modules/auth/queries/useCurrentUserQuery'

const { t } = useI18n()
const { data: user } = useCurrentUserQuery()
const { mutate: logout, isPending: isLoggingOut } = useLogoutMutation()

const displayName = computed(() => user.value?.name ?? '')
const tenantName = computed(() => user.value?.tenant?.name ?? null)

function onLogout(): void {
  logout()
}
</script>

<template>
  <div class="flex min-h-screen bg-neutral-50 text-neutral-900">
    <aside class="flex w-64 shrink-0 flex-col border-e border-emerald-950/20 bg-emerald-900 text-white">
      <div class="border-b border-emerald-800 px-6 py-5">
        <span class="text-xl font-bold">{{ t('app.name') }}</span>
        <p class="mt-1 text-xs text-emerald-200">{{ t('app.tagline') }}</p>
      </div>
      <nav class="flex-1 space-y-1 p-4">
        <RouterLink
          to="/app"
          class="block rounded-md px-4 py-2 text-sm font-medium text-emerald-100 hover:bg-emerald-800"
          active-class="bg-emerald-800 text-white"
        >
          {{ t('nav.home') }}
        </RouterLink>
      </nav>
      <div class="border-t border-emerald-800 p-4 text-xs text-emerald-200">
        <p v-if="tenantName">{{ tenantName }}</p>
        <p v-else-if="user?.is_platform_user">{{ t('auth.platformUser') }}</p>
      </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
      <header class="flex h-16 items-center justify-between border-b border-neutral-200 bg-white px-6 shadow-sm">
        <h1 class="text-lg font-semibold">{{ t('app.name') }}</h1>
        <div class="flex items-center gap-3">
          <span class="text-sm text-neutral-600">{{ displayName }}</span>
          <button
            type="button"
            class="rounded-md border border-neutral-300 px-3 py-1.5 text-sm text-neutral-700 hover:bg-neutral-50 disabled:opacity-60"
            :disabled="isLoggingOut"
            @click="onLogout"
          >
            {{ t('auth.logout') }}
          </button>
        </div>
      </header>

      <main class="flex-1 p-6">
        <slot />
      </main>
    </div>
  </div>
</template>
