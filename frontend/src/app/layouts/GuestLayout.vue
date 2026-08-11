<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>

<template>
  <div class="relative min-h-screen overflow-hidden bg-gradient-to-bl from-emerald-950 via-emerald-900 to-emerald-800 text-neutral-900">
    <div
      class="pointer-events-none absolute inset-0 opacity-30"
      style="background-image: radial-gradient(circle at 20% 20%, rgba(212,175,55,0.25), transparent 40%), radial-gradient(circle at 80% 0%, rgba(255,255,255,0.08), transparent 35%);"
    />

    <div class="relative mx-auto flex min-h-screen max-w-lg flex-col justify-center px-4 py-10 sm:px-6">
      <div class="mb-8 text-center text-white">
        <p class="text-3xl font-bold tracking-tight sm:text-4xl">{{ t('app.name') }}</p>
        <p class="mt-2 text-sm text-emerald-100/90 sm:text-base">{{ t('app.tagline') }}</p>
      </div>

      <div class="rounded-2xl border border-white/10 bg-white p-6 shadow-xl sm:p-8">
        <slot />
      </div>
    </div>
  </div>
</template>
