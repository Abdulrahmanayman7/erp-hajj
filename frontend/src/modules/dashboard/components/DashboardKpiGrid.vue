<script setup lang="ts">
import { computed, type Component } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { Boxes, CalendarDays, ClipboardList, FileText, Gavel, IdCard } from 'lucide-vue-next'

import type { DashboardKpiKey, DashboardKpis } from '../types/dashboard'
import {
  getTopKpis,
  effectiveSeverity,
  isSafeAppHref,
  severityValueClass,
} from '../utils/dashboardDisplay'

const props = defineProps<{
  kpis?: DashboardKpis | null
}>()

const { t } = useI18n()
const entries = computed(() => getTopKpis(props.kpis))

const iconFor: Partial<Record<DashboardKpiKey, Component>> = {
  tasks_overdue: ClipboardList,
  decisions_pending_approval: Gavel,
  contracts_expiring_soon: FileText,
  inventory_attention: Boxes,
  custodies_overdue: IdCard,
  meetings_today: CalendarDays,
}

function iconTone(severity: string, value: number): string {
  if (value <= 0) return 'app-kpi-icon--neutral'
  if (severity === 'critical') return 'app-kpi-icon--critical'
  if (severity === 'warning') return 'app-kpi-icon--warning'
  return 'app-kpi-icon--info'
}
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
        class="app-surface-flat group flex min-h-[96px] min-w-0 flex-col p-3 transition md:min-h-[104px] md:p-3.5"
        :class="
          isSafeAppHref(entry.kpi.href)
            ? 'hover:border-brand-primary/25 [@media(hover:hover)]:hover:bg-[var(--surface-subtle)]'
            : ''
        "
      >
        <span
          class="inline-flex h-8 w-8 items-center justify-center rounded-[8px]"
          :class="iconTone(effectiveSeverity(entry.kpi.severity, entry.kpi.value), entry.kpi.value)"
          aria-hidden="true"
        >
          <component
            :is="iconFor[entry.key] ?? ClipboardList"
            class="h-4 w-4"
            :stroke-width="1.85"
          />
        </span>
        <p
          class="app-type-kpi mt-2.5"
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
