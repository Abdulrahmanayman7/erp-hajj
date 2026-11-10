<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'

import type { OperationalKpiCard } from '@/modules/operational-kpis/types/operationalKpis'
import {
  canNavigateKpiDrilldown,
  formatKpiDisplay,
} from '@/modules/operational-kpis/utils/operationalKpisDisplay'

const props = defineProps<{
  kpis: OperationalKpiCard[]
}>()

const { t } = useI18n()
const unavailable = computed(() => t('operationalKpis.unavailable'))
</script>

<template>
  <section
    v-if="kpis.length > 0"
    class="app-surface-flat space-y-3 p-4"
    aria-label="project progress panel"
    dir="rtl"
  >
    <div class="space-y-1">
      <h2 class="text-sm font-bold text-brand-text">
        {{ t('executiveDashboard.panels.projectsTitle') }}
      </h2>
      <p class="text-xs text-brand-text-secondary">
        {{ t('executiveDashboard.panels.projectsHint') }}
      </p>
    </div>
    <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
      <component
        :is="canNavigateKpiDrilldown(kpi) ? RouterLink : 'div'"
        v-for="kpi in kpis"
        :key="kpi.id"
        :to="canNavigateKpiDrilldown(kpi) ? kpi.href! : undefined"
        class="rounded-lg border border-brand-border/80 bg-[var(--surface-subtle)] p-3"
        :aria-label="`${kpi.label}: ${formatKpiDisplay(kpi, unavailable)}`"
      >
        <div class="flex items-start justify-between gap-2">
          <span class="font-mono text-[11px] font-semibold text-brand-text-muted">{{ kpi.id }}</span>
          <span
            v-if="kpi.state === 'error'"
            class="rounded bg-red-50 px-1.5 py-0.5 text-[10px] font-bold text-red-700"
            >{{ t('operationalKpis.errorCard') }}</span
          >
          <span
            v-else-if="kpi.state === 'undefined'"
            class="rounded bg-[var(--surface-subtle)] px-1.5 py-0.5 text-[10px] font-bold text-brand-text-muted"
            >{{ t('executiveDashboard.panels.undefinedBadge') }}</span
          >
        </div>
        <p
          class="mt-1 text-2xl font-bold tabular-nums"
          :class="kpi.state === 'ok' ? 'text-brand-text' : 'text-brand-text-muted'"
        >
          {{ formatKpiDisplay(kpi, unavailable) }}
        </p>
        <p class="mt-0.5 text-[12px] font-medium text-brand-text-secondary">{{ kpi.label }}</p>
      </component>
    </div>
  </section>
</template>
