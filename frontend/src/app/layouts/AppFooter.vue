<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const year = computed(() => new Date().getFullYear())
</script>

<template>
  <footer
    class="shrink-0 rounded-[16px] border border-brand-border bg-brand-surface px-5 py-4 sm:px-6 lg:px-8"
  >
    <div
      class="mx-auto flex w-full max-w-[1440px] flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
    >
      <div class="min-w-0">
        <p class="text-sm font-semibold text-brand-text">
          <span class="text-brand-primary-dark">{{ t('auth.companyName') }}</span>
          <span class="mx-2 text-brand-gold">·</span>
          <span dir="ltr">{{ t('app.name') }}</span>
        </p>
        <p class="mt-0.5 text-xs text-brand-text-secondary">
          {{ t('app.tagline') }}
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-brand-text-muted">
        <span>{{ t('shell.footerRights', { year }) }}</span>
        <span class="hidden h-3 w-px bg-brand-border sm:inline-block" aria-hidden="true" />
        <span>{{ t('shell.footerSecure') }}</span>
      </div>
    </div>
  </footer>
</template>
