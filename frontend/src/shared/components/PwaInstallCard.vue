<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Download, Share, X } from 'lucide-vue-next'

import { usePwaInstall } from '@/shared/pwa/usePwaInstall'

const { t } = useI18n()
const { canInstall, isInstalled, showIosHint, promptInstall, dismissIosHint } = usePwaInstall()

const visible = computed(() => !isInstalled.value && (canInstall.value || showIosHint.value))

async function onInstall(): Promise<void> {
  await promptInstall()
}
</script>

<template>
  <section
    v-if="visible"
    class="rounded-2xl border border-brand-border bg-brand-bg/70 p-3"
    data-testid="pwa-install-card"
  >
    <div class="flex items-start gap-2">
      <div class="min-w-0 flex-1">
        <p class="text-sm font-bold text-brand-text">{{ t('pwa.installTitle') }}</p>
        <p class="mt-1 text-xs leading-5 text-brand-text-secondary">
          {{ canInstall ? t('pwa.installBody') : t('pwa.iosHint') }}
        </p>
      </div>
      <button
        v-if="showIosHint && !canInstall"
        type="button"
        class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-brand-text-muted transition hover:bg-brand-surface"
        :aria-label="t('shell.close')"
        @click="dismissIosHint"
      >
        <X class="h-4 w-4" />
      </button>
    </div>

    <button
      v-if="canInstall"
      type="button"
      class="mt-3 inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl bg-brand-primary px-3 text-sm font-semibold text-white"
      @click="onInstall"
    >
      <Download class="h-4 w-4" :stroke-width="2" />
      {{ t('pwa.installCta') }}
    </button>

    <p
      v-else-if="showIosHint"
      class="mt-2 inline-flex items-center gap-1.5 text-xs font-medium text-brand-primary-dark"
    >
      <Share class="h-3.5 w-3.5" :stroke-width="2" />
      {{ t('pwa.iosSteps') }}
    </p>
  </section>
</template>
