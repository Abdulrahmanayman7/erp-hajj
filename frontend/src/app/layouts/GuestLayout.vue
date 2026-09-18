<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'

const { t } = useI18n()
const route = useRoute()

/** Login owns a full-bleed branded composition; other guest pages keep a compact shell. */
const isFullBleedGuestPage = computed(() => route.name === 'login')
</script>

<template>
  <div v-if="isFullBleedGuestPage" class="h-full min-h-0 flex-1 overflow-hidden">
    <slot />
  </div>

  <div
    v-else
    class="relative h-full min-h-0 flex-1 overflow-auto bg-gradient-to-bl from-[#033D30] via-[#064E3B] to-[#0A5C46] text-neutral-900"
  >
    <div
      class="pointer-events-none absolute inset-0 opacity-40"
      aria-hidden="true"
      style="
        background-image:
          radial-gradient(circle at 20% 20%, rgba(198, 161, 91, 0.22), transparent 40%),
          radial-gradient(circle at 80% 0%, rgba(255, 255, 255, 0.08), transparent 35%);
      "
    />

    <div class="relative mx-auto flex min-h-full max-w-lg flex-col justify-center px-4 py-10 sm:px-6">
      <div class="mb-8 text-center text-white">
        <p class="text-3xl font-bold tracking-tight sm:text-4xl">{{ t('app.name') }}</p>
        <p class="mt-2 text-sm text-emerald-50/90 sm:text-base">{{ t('app.tagline') }}</p>
      </div>

      <div
        class="rounded-2xl border border-[#C6A15B]/20 bg-white p-6 shadow-[0_20px_50px_-24px_rgba(3,61,48,0.45)] sm:p-8"
      >
        <slot />
      </div>
    </div>
  </div>
</template>
