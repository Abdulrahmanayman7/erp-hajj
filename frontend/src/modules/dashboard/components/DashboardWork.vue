<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { CalendarDays, ClipboardList, FileText, Gavel } from 'lucide-vue-next'

import type { DashboardKpis, DashboardWork } from '../types/dashboard'
import {
  asSparseRecord,
  effectiveSeverity,
  hasWorkMetrics,
  isSafeAppHref,
  pickKpis,
  severityValueClass,
  WORK_CONTRACT_KPI_KEYS,
  WORK_DECISION_KPI_KEYS,
  WORK_MEETING_KPI_KEYS,
  WORK_TASK_KPI_KEYS,
} from '../utils/dashboardDisplay'
import DashboardSectionCard from './DashboardSectionCard.vue'

const props = defineProps<{
  work?: DashboardWork | null
  kpis?: DashboardKpis | null
}>()

const { t } = useI18n()

const workData = computed(() => asSparseRecord<DashboardWork>(props.work))
const myTasks = computed(() => workData.value?.my_tasks)
const taskEntries = computed(() => pickKpis(props.kpis, WORK_TASK_KPI_KEYS))
const decisionEntries = computed(() => pickKpis(props.kpis, WORK_DECISION_KPI_KEYS))
const meetingEntries = computed(() => pickKpis(props.kpis, WORK_MEETING_KPI_KEYS))
const contractEntries = computed(() => pickKpis(props.kpis, WORK_CONTRACT_KPI_KEYS))

const visible = computed(() => hasWorkMetrics(props.kpis, props.work))

const groups = computed(() =>
  [
    {
      key: 'tasks',
      title: t('dashboard.workTasks'),
      icon: ClipboardList,
      entries: taskEntries.value,
    },
    {
      key: 'decisions',
      title: t('dashboard.workDecisions'),
      icon: Gavel,
      entries: decisionEntries.value,
    },
    {
      key: 'meetings',
      title: t('dashboard.workMeetings'),
      icon: CalendarDays,
      entries: meetingEntries.value,
    },
    {
      key: 'contracts',
      title: t('dashboard.workContracts'),
      icon: FileText,
      entries: contractEntries.value,
    },
  ].filter((group) => group.entries.length > 0),
)
</script>

<template>
  <DashboardSectionCard
    v-if="visible"
    :title="t('dashboard.work')"
  >
    <div class="space-y-4">
      <component
        :is="isSafeAppHref(myTasks.href) ? RouterLink : 'div'"
        v-if="myTasks"
        :to="isSafeAppHref(myTasks.href) ? myTasks.href : undefined"
        class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-brand-border bg-brand-bg/70 p-4 transition"
        :class="
          isSafeAppHref(myTasks.href)
            ? 'hover:border-brand-primary/30 hover:bg-brand-primary-soft/40'
            : ''
        "
      >
        <div>
          <p class="text-sm font-bold text-brand-text">
            {{ t('dashboard.myTasks') }}
          </p>
          <p class="mt-1 text-xs text-brand-text-secondary">
            {{ t('dashboard.myTasksHint') }}
          </p>
        </div>
        <div class="flex gap-4 text-sm">
          <div class="text-center">
            <p class="text-xs text-brand-text-muted">
              {{ t('dashboard.openCount') }}
            </p>
            <p class="mt-0.5 text-lg font-bold tabular-nums text-brand-primary-dark">
              {{ myTasks.open }}
            </p>
          </div>
          <div class="text-center">
            <p class="text-xs text-brand-text-muted">
              {{ t('dashboard.overdueCount') }}
            </p>
            <p
              class="mt-0.5 text-lg font-bold tabular-nums"
              :class="myTasks.overdue > 0 ? 'text-red-800' : 'text-brand-text'"
            >
              {{ myTasks.overdue }}
            </p>
          </div>
        </div>
      </component>

      <div
        v-for="group in groups"
        :key="group.key"
        class="rounded-xl border border-brand-border p-3"
      >
        <h4 class="mb-2 flex items-center gap-2 text-sm font-semibold text-brand-text">
          <component
            :is="group.icon"
            class="h-4 w-4 text-brand-primary"
            aria-hidden="true"
          />
          {{ group.title }}
        </h4>
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
          <component
            :is="isSafeAppHref(entry.kpi.href) ? RouterLink : 'div'"
            v-for="entry in group.entries"
            :key="entry.key"
            :to="isSafeAppHref(entry.kpi.href) ? entry.kpi.href : undefined"
            class="rounded-lg bg-brand-bg px-3 py-2.5 transition"
            :class="
              isSafeAppHref(entry.kpi.href)
                ? 'hover:bg-brand-primary-soft/40'
                : ''
            "
          >
            <p class="text-xs text-brand-text-secondary">
              {{ entry.kpi.label }}
            </p>
            <p
              class="mt-1 text-lg font-bold tabular-nums"
              :class="severityValueClass(effectiveSeverity(entry.kpi.severity, entry.kpi.value))"
            >
              {{ entry.kpi.value }}
            </p>
          </component>
        </div>
      </div>
    </div>
  </DashboardSectionCard>
</template>
