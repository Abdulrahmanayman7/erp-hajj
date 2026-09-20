<script setup lang="ts">
import { computed, toRef, type Ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ArrowDown, LoaderCircle, RefreshCw } from 'lucide-vue-next'

import { usePullToRefresh } from '@/shared/composables/usePullToRefresh'
import type { PullToRefreshPhase } from '@/shared/utils/pullToRefresh'

const props = withDefaults(
  defineProps<{
    scroller: HTMLElement | null
    /** When soft query refresh fails, fall back to a full page reload. */
    hardReloadFallback?: boolean
  }>(),
  {
    hardReloadFallback: false,
  },
)

const { t } = useI18n()
const scrollerRef = toRef(props, 'scroller') as Ref<HTMLElement | null>

const { enabled, phase, pullPx, progress, refreshing, visible, maxPullPx } = usePullToRefresh(scrollerRef, {
  hardReloadFallback: props.hardReloadFallback,
})

const label = computed(() => {
  switch (phase.value as PullToRefreshPhase) {
    case 'ready':
      return t('shell.pullToRefresh.release')
    case 'refreshing':
      return t('shell.pullToRefresh.refreshing')
    case 'pulling':
    default:
      return t('shell.pullToRefresh.pull')
  }
})

const shellStyle = computed(() => ({
  height: `${Math.min(maxPullPx, Math.max(0, pullPx.value))}px`,
  opacity: visible.value ? Math.min(1, 0.25 + progress.value * 0.75) : 0,
  transition: refreshing.value || pullPx.value === 0 ? 'height 200ms ease, opacity 180ms ease' : 'none',
}))
</script>

<template>
  <div
    v-if="enabled"
    class="pointer-events-none absolute inset-x-0 top-0 z-30 flex justify-center overflow-hidden"
    aria-live="polite"
    :aria-busy="refreshing"
  >
    <div class="flex w-full items-end justify-center" :style="shellStyle" role="status">
      <div
        v-show="visible"
        class="mb-1 inline-flex items-center gap-2 rounded-full border border-brand-border/80 bg-brand-surface/95 px-3 py-1.5 text-xs font-semibold text-brand-text shadow-[0_8px_20px_-14px_rgba(23,32,29,0.45)] backdrop-blur-sm"
      >
        <LoaderCircle
          v-if="phase === 'refreshing'"
          class="h-4 w-4 animate-spin text-brand-primary"
          :stroke-width="2"
          aria-hidden="true"
        />
        <RefreshCw
          v-else-if="phase === 'ready'"
          class="h-4 w-4 text-brand-primary"
          :stroke-width="2"
          aria-hidden="true"
        />
        <ArrowDown
          v-else
          class="h-4 w-4 text-brand-text-secondary transition-transform duration-150"
          :class="progress > 0.85 ? 'translate-y-0.5' : ''"
          :stroke-width="2"
          aria-hidden="true"
        />
        <span>{{ label }}</span>
      </div>
    </div>
  </div>
</template>
