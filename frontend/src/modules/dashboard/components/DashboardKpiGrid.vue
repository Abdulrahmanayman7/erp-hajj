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
  severityCardClass,
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
</script>

<template>
  <div
    v-if="entries.length > 0"
    class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3"
  >
    <component
      :is="isSafeAppHref(entry.kpi.href) ? RouterLink : 'div'"
      v-for="entry in entries"
      :key="entry.key"
      :to="isSafeAppHref(entry.kpi.href) ? entry.kpi.href : undefined"
      class="group relative min-h-[128px] overflow-hidden rounded-2xl border p-4 transition sm:p-5"
      :class="[
        severityCardClass(effectiveSeverity(entry.kpi.severity, entry.kpi.value)),
        isSafeAppHref(entry.kpi.href)
          ? 'hover:border-brand-primary/30 hover:shadow-[0_10px_28px_-20px_rgba(6,78,59,0.35)]'
          : '',
      ]"
    >
      <div class="flex items-start justify-between gap-3">
        <span
          class="inline-flex h-9 w-9 items-center justify-center rounded-xl"
          :class="entry.kpi.value > 0 ? 'bg-white/70 text-brand-primary-dark' : 'bg-brand-bg text-brand-text-muted'"
          aria-hidden="true"
        >
          <component :is="iconFor[entry.key] ?? ClipboardList" class="h-[18px] w-[18px]" :stroke-width="1.9" />
        </span>
        <span class="text-xs font-semibold text-brand-text-muted">
          {{ entry.kpi.value > 0 ? t('dashboard.requiresFollowUp') : t('dashboard.allClear') }}
        </span>
      </div>
      <p class="mt-4 text-sm font-semibold text-brand-text-secondary">{{ entry.kpi.label }}</p>
      <p
        class="mt-1 text-2xl font-bold tabular-nums tracking-tight"
        :class="severityValueClass(effectiveSeverity(entry.kpi.severity, entry.kpi.value))"
      >{{ entry.kpi.value }}</p>
    </component>
  </div>
</template>
