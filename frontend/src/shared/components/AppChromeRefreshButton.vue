<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RefreshCw } from 'lucide-vue-next'

import AppTooltip from '@/shared/components/AppTooltip.vue'
import { useAppChromeNavigation } from '@/shared/composables/useAppChromeNavigation'

const { t } = useI18n()
const {
  hardRefreshProgress,
  isHoldingRefresh,
  onRefreshPointerDown,
  onRefreshPointerUp,
  onRefreshPointerLeave,
  onRefreshPointerCancel,
} = useAppChromeNavigation()

const refreshLabel = computed(() =>
  isHoldingRefresh.value
    ? t('shell.hardRefreshProgress', { progress: hardRefreshProgress.value })
    : t('shell.refresh'),
)

const tooltipText = computed(() => `${t('shell.refresh')} — ${t('shell.hardRefreshHint')}`)
</script>

<template>
  <AppTooltip :text="tooltipText" side="bottom">
    <button
      type="button"
      class="app-btn-ghost app-chrome-refresh relative"
      :aria-label="refreshLabel"
      :aria-valuemin="0"
      :aria-valuemax="100"
      :aria-valuenow="hardRefreshProgress"
      :aria-busy="isHoldingRefresh"
      @touchstart.prevent
      @contextmenu.prevent
      @pointerdown="onRefreshPointerDown"
      @pointerup="onRefreshPointerUp"
      @pointerleave="onRefreshPointerLeave"
      @pointercancel="onRefreshPointerCancel"
    >
      <span
        v-if="isHoldingRefresh"
        class="absolute inset-1 rounded-[8px] bg-brand-primary/10"
        :style="{ opacity: Math.max(0.2, hardRefreshProgress / 100) }"
        aria-hidden="true"
      />
      <RefreshCw
        class="relative h-5 w-5"
        :class="{ 'animate-spin': isHoldingRefresh }"
        :stroke-width="2"
        aria-hidden="true"
      />
    </button>
  </AppTooltip>
</template>
