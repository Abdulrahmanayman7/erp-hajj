<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'

import type { DashboardToday, TodayListItem } from '../types/dashboard'
import {
  formatListItemDate,
  formatListItemTime,
  isSafeAppHref,
} from '../utils/dashboardDisplay'
import DashboardSectionCard from './DashboardSectionCard.vue'

const props = defineProps<{
  today?: DashboardToday | null
  timezone?: string | null
}>()

const { t } = useI18n()

const hasToday = computed(() => props.today != null)

const subsections = computed(() => {
  const today = props.today
  if (!today) return []

  const blocks: Array<{
    key: keyof DashboardToday
    title: string
    empty: string
    items: TodayListItem[] | undefined
    present: boolean
  }> = [
    {
      key: 'meetings_today',
      title: t('dashboard.meetingsToday'),
      empty: t('dashboard.emptyMeetingsToday'),
      items: today.meetings_today,
      present: Array.isArray(today.meetings_today),
    },
    {
      key: 'tasks_due_today',
      title: t('dashboard.tasksDueToday'),
      empty: t('dashboard.emptyTasksToday'),
      items: today.tasks_due_today,
      present: Array.isArray(today.tasks_due_today),
    },
    {
      key: 'contracts_expiring',
      title: t('dashboard.contractsExpiring'),
      empty: t('dashboard.emptyContracts'),
      items: today.contracts_expiring,
      present: Array.isArray(today.contracts_expiring),
    },
    {
      key: 'meetings_upcoming_7d',
      title: t('dashboard.meetingsUpcoming'),
      empty: t('dashboard.emptyUpcoming'),
      items: today.meetings_upcoming_7d,
      present: Array.isArray(today.meetings_upcoming_7d),
    },
  ]

  return blocks.filter((b) => b.present)
})

function itemMeta(item: TodayListItem): string {
  if (item.scheduled_at) {
    return formatListItemTime(item.scheduled_at, props.timezone)
  }
  if (item.due_date) {
    return formatListItemDate(item.due_date)
  }
  if (item.end_date) {
    return formatListItemDate(item.end_date)
  }
  return ''
}
</script>

<template>
  <DashboardSectionCard
    v-if="hasToday"
    :title="t('dashboard.todaySoon')"
  >
    <div
      v-if="subsections.length === 0"
      class="text-sm text-brand-text-muted"
    >
      {{ t('dashboard.emptyUpcoming') }}
    </div>
    <div
      v-else
      class="space-y-5"
    >
      <div
        v-for="block in subsections"
        :key="block.key"
      >
        <h4 class="mb-2 text-sm font-semibold text-brand-text">
          {{ block.title }}
        </h4>
        <p
          v-if="!block.items || block.items.length === 0"
          class="text-sm text-brand-text-muted"
        >
          {{ block.empty }}
        </p>
        <ul
          v-else
          class="space-y-1"
          role="list"
        >
          <li
            v-for="item in block.items"
            :key="`${block.key}-${item.id}`"
          >
            <component
              :is="isSafeAppHref(item.href) ? RouterLink : 'div'"
              :to="isSafeAppHref(item.href) ? item.href : undefined"
              class="flex items-center justify-between gap-3 rounded-xl px-2 py-2 text-sm transition"
              :class="
                isSafeAppHref(item.href)
                  ? 'hover:bg-brand-primary-soft/40'
                  : ''
              "
            >
              <span class="min-w-0 truncate font-medium text-brand-text">
                <span
                  v-if="item.number"
                  class="me-2 font-mono text-xs text-brand-text-muted"
                >{{ item.number }}</span>
                {{ item.title }}
              </span>
              <span
                v-if="itemMeta(item)"
                class="shrink-0 text-xs text-brand-text-secondary"
              >
                {{ itemMeta(item) }}
              </span>
            </component>
          </li>
        </ul>
      </div>
    </div>
  </DashboardSectionCard>
</template>
