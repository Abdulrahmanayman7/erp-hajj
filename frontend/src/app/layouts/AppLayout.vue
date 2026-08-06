<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>

<template>
  <div class="flex min-h-screen bg-neutral-50 text-neutral-900">
    <!-- Right sidebar (first element renders on the right in RTL) -->
    <aside class="flex w-64 shrink-0 flex-col border-e border-neutral-200 bg-emerald-900 text-white">
      <div class="border-b border-emerald-800 px-6 py-5">
        <span class="text-xl font-bold">{{ t('app.name') }}</span>
      </div>
      <nav class="flex-1 space-y-1 p-4">
        <RouterLink
          to="/"
          class="block rounded-md px-4 py-2 text-sm font-medium text-emerald-100 hover:bg-emerald-800"
          active-class="bg-emerald-800 text-white"
        >
          {{ t('nav.systemStatus') }}
        </RouterLink>
      </nav>
      <div class="border-t border-emerald-800 p-4 text-xs text-emerald-200">
        {{ t('app.foundationPhase') }}
      </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
      <header class="flex h-16 items-center border-b border-neutral-200 bg-white px-6 shadow-sm">
        <h1 class="text-lg font-semibold">{{ t('app.name') }}</h1>
      </header>

      <main class="flex-1 p-6">
        <slot />
      </main>
    </div>
  </div>
</template>
