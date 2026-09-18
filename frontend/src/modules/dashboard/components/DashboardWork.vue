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

const taskPills = computed(() => {
  const pills: Array<{ key: string; label: string; value: number; tone: string }> = []
  if (myTasks.value) {
    pills.push({
      key: 'open',
      label: t('dashboard.openCount'),
      value: myTasks.value.open,
      tone: 'bg-brand-primary-soft text-brand-primary-dark',
    })
    pills.push({
      key: 'overdue',
      label: t('dashboard.overdueCount'),
      value: myTasks.value.overdue,
      tone:
        myTasks.value.overdue > 0
          ? 'bg-[var(--danger-soft)] text-red-800'
          : 'bg-[var(--surface-muted)] text-brand-text',
    })
    const dueSoon = taskEntries.value.find((entry) => entry.key === 'tasks_due_soon')
    if (dueSoon) {
      pills.push({
        key: 'dueSoon',
        label: t('dashboard.dueSoonCount'),
        value: dueSoon.kpi.value,
        tone: 'bg-[var(--warning-soft)] text-amber-900',
      })
    }
    return pills
  }

  for (const entry of taskEntries.value) {
    pills.push({
      key: entry.key,
      label: entry.kpi.label,
      value: entry.kpi.value,
      tone:
        effectiveSeverity(entry.kpi.severity, entry.kpi.value) === 'critical'
          ? 'bg-[var(--danger-soft)] text-red-800'
          : 'bg-brand-primary-soft text-brand-primary-dark',
    })
  }
  return pills
})

const groups = computed(() =>
  [
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
        :is="myTasks && isSafeAppHref(myTasks.href) ? RouterLink : 'div'"
        v-if="myTasks || taskPills.length > 0"
        :to="myTasks && isSafeAppHref(myTasks.href) ? myTasks.href : undefined"
        class="rounded-xl bg-brand-bg/70 p-4 transition"
        :class="
          myTasks && isSafeAppHref(myTasks.href)
            ? 'hover:bg-brand-primary-soft/40'
            : ''
        "
      >
        <div class="mb-3 flex items-center gap-2">
          <ClipboardList class="h-4 w-4 text-brand-primary" aria-hidden="true" />
          <div>
            <p class="text-sm font-bold text-brand-text">
              {{ t('dashboard.myTasks') }}
            </p>
            <p class="text-xs text-brand-text-secondary">
              {{ t('dashboard.myTasksHint') }}
            </p>
          </div>
        </div>
        <div class="flex flex-wrap gap-2">
          <span
            v-for="pill in taskPills"
            :key="pill.key"
            class="inline-flex min-h-8 items-center gap-1.5 rounded-full px-3 text-xs font-semibold tabular-nums"
            :class="pill.tone"
          >
            {{ pill.label }}
            <span>{{ pill.value }}</span>
          </span>
        </div>
      </component>

      <div
        v-for="group in groups"
        :key="group.key"
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
