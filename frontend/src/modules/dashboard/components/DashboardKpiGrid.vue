<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'

import type { DashboardKpis } from '../types/dashboard'
import { getTopKpis, effectiveSeverity, isSafeAppHref, severityValueClass } from '../utils/dashboardDisplay'

const props = defineProps<{
  kpis?: DashboardKpis | null
}>()

const { t } = useI18n()
const entries = computed(() => getTopKpis(props.kpis))
</script>

<template>
  <section v-if="entries.length > 0" class="space-y-3">
    <h2 class="app-type-section">{{ t('dashboard.overview') }}</h2>
    <div class="grid grid-cols-2 gap-2.5 md:gap-3 xl:grid-cols-3">
      <component
        :is="isSafeAppHref(entry.kpi.href) ? RouterLink : 'div'"
        v-for="entry in entries"
        :key="entry.key"
        :to="isSafeAppHref(entry.kpi.href) ? entry.kpi.href : undefined"
        class="app-surface-flat group flex min-h-[88px] min-w-0 flex-col p-3 transition md:min-h-[96px] md:p-3.5"
        :class="
          isSafeAppHref(entry.kpi.href)
            ? 'hover:border-brand-primary/25 [@media(hover:hover)]:hover:bg-[var(--surface-subtle)]'
            : ''
        "
      >
        <p
          class="app-type-kpi"
          :class="severityValueClass(effectiveSeverity(entry.kpi.severity, entry.kpi.value))"
        >
          {{ entry.kpi.value }}
        </p>
        <p class="mt-0.5 line-clamp-2 text-[12px] font-medium leading-snug text-brand-text-secondary md:text-[13px]">
          {{ entry.kpi.label }}
        </p>
      </component>
    </div>
  </section>
</template>
