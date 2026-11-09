<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'

import type { OperationalKpiCard } from '../types/operationalKpis'
import {
  canNavigateKpiDrilldown,
  formatKpiDisplay,
  groupKpisByDomain,
} from '../utils/operationalKpisDisplay'

const props = defineProps<{
  kpis: OperationalKpiCard[]
}>()

const { t } = useI18n()
const groups = computed(() => groupKpisByDomain(props.kpis))
const hasAnyNavigable = computed(() => props.kpis.some((kpi) => canNavigateKpiDrilldown(kpi)))
</script>

<template>
  <div class="space-y-8">
    <p
      v-if="hasAnyNavigable"
      id="operational-kpis-drilldown-disclaimer"
      class="rounded-lg border border-brand-border/80 bg-[var(--surface-subtle)] px-3 py-2 text-[12px] leading-relaxed text-brand-text-secondary"
      role="note"
    >
      {{ t('operationalKpis.drilldownDisclaimer') }}
    </p>

    <section v-for="entry in groups" :key="entry.group" class="space-y-3">
      <h2 class="app-type-section">
        {{ t(`operationalKpis.domains.${entry.group}`) }}
      </h2>
      <div class="grid grid-cols-2 gap-2.5 md:gap-3 xl:grid-cols-3">
        <component
          :is="canNavigateKpiDrilldown(kpi) ? RouterLink : 'div'"
          v-for="kpi in entry.items"
          :key="kpi.id"
          :to="canNavigateKpiDrilldown(kpi) ? kpi.href! : undefined"
          class="app-surface-flat group flex min-h-[96px] min-w-0 flex-col p-3 transition md:p-3.5"
          :class="
            canNavigateKpiDrilldown(kpi)
              ? 'hover:border-brand-primary/25 [@media(hover:hover)]:hover:bg-[var(--surface-subtle)]'
              : ''
          "
          :aria-label="`${kpi.label}: ${formatKpiDisplay(kpi, t('operationalKpis.unavailable'))}`"
          :aria-describedby="
            canNavigateKpiDrilldown(kpi) ? 'operational-kpis-drilldown-disclaimer' : undefined
          "
        >
          <div class="flex items-start justify-between gap-2">
            <span class="font-mono text-[11px] font-semibold tracking-wide text-brand-text-muted">{{
              kpi.id
            }}</span>
            <span
              v-if="kpi.state === 'error'"
              class="rounded bg-red-50 px-1.5 py-0.5 text-[10px] font-bold text-red-700"
              >{{ t('operationalKpis.errorCard') }}</span
            >
          </div>
          <p
            class="app-type-kpi mt-1"
            :class="kpi.state === 'ok' ? 'text-brand-text' : 'text-brand-text-muted'"
          >
            {{ formatKpiDisplay(kpi, t('operationalKpis.unavailable')) }}
          </p>
          <p
            class="mt-0.5 line-clamp-2 text-[12px] font-medium leading-snug text-brand-text-secondary md:text-[13px]"
          >
            {{ kpi.label }}
          </p>
          <p
            v-if="canNavigateKpiDrilldown(kpi)"
            class="mt-auto pt-2 text-[10px] leading-snug text-brand-text-muted"
          >
            {{ t('operationalKpis.drilldownDisclaimer') }}
          </p>
        </component>
      </div>
    </section>
  </div>
</template>
