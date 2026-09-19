<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ArrowLeft, MessageSquareText, UserRound } from 'lucide-vue-next'

import type { TaskAssignmentHistoryEntry, TaskTransition } from '../types/tasks'
import { taskStatusBadgeClass } from '../validation/taskValidation'
import { formatIsoTimestampAr } from '@/shared/utils/gregorianDate'

const props = withDefaults(
  defineProps<{
    transitions: TaskTransition[]
    assignments: TaskAssignmentHistoryEntry[]
    timezone?: string
  }>(),
  {
    timezone: 'Asia/Riyadh',
  },
)

const { t } = useI18n()

const orderedTransitions = computed(() =>
  [...props.transitions].sort((a, b) => {
    const aTime = a.created_at ? Date.parse(a.created_at) : 0
    const bTime = b.created_at ? Date.parse(b.created_at) : 0
    return aTime - bTime
  }),
)

const orderedAssignments = computed(() =>
  [...props.assignments].sort((a, b) => {
    const aTime = a.created_at ? Date.parse(a.created_at) : 0
    const bTime = b.created_at ? Date.parse(b.created_at) : 0
    return aTime - bTime
  }),
)

function formatTimestamp(value: string | null): string {
  return formatIsoTimestampAr(value, props.timezone, '—')
}

function statusLabel(status: string | null): string {
  if (!status) return '—'
  return t(`tasks.status.${status}`)
}
</script>

<template>
  <section class="rounded-2xl border border-brand-border bg-brand-surface">
    <header class="border-b border-brand-border px-5 py-4">
      <h3 class="text-base font-bold text-brand-text">{{ t('tasks.timelineTitle') }}</h3>
      <p class="mt-1 text-sm text-brand-text-secondary">{{ t('tasks.timelineSubtitle') }}</p>
    </header>

    <div class="border-b border-brand-border px-5 py-4">
      <h4 class="text-sm font-bold text-brand-text">{{ t('tasks.timeline.statusTitle') }}</h4>
    </div>

    <div
      v-if="orderedTransitions.length === 0"
      class="px-5 py-8 text-center text-sm text-brand-text-muted"
    >
      {{ t('tasks.timeline.statusEmpty') }}
    </div>

    <ol v-else class="relative space-y-0 px-5 py-5">
      <li
        v-for="(transition, index) in orderedTransitions"
        :key="transition.id"
        class="relative flex gap-4 pb-6 last:pb-0"
      >
        <div class="relative flex w-4 shrink-0 flex-col items-center">
          <span
            class="mt-1.5 z-10 h-3 w-3 rounded-full bg-brand-primary ring-[3px] ring-brand-primary/15"
          />
          <span
            v-if="index < orderedTransitions.length - 1"
            class="absolute top-5 bottom-0 w-px bg-brand-border"
            aria-hidden="true"
          />
        </div>

        <div class="min-w-0 flex-1 rounded-xl border border-brand-border/80 bg-brand-bg/40 px-4 py-3">
          <div class="flex flex-wrap items-center gap-2">
            <span
              v-if="transition.from_status"
              class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold"
              :class="taskStatusBadgeClass(transition.from_status)"
            >
              {{ statusLabel(transition.from_status) }}
            </span>
            <ArrowLeft
              v-if="transition.from_status"
              class="h-3.5 w-3.5 shrink-0 text-brand-text-muted"
              :stroke-width="2"
              aria-hidden="true"
            />
            <span
              class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold"
              :class="taskStatusBadgeClass(transition.to_status)"
            >
              {{ statusLabel(transition.to_status) }}
            </span>
          </div>

          <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-brand-text-muted">
            <span class="inline-flex items-center gap-1.5 font-semibold text-brand-text">
              <UserRound class="h-3.5 w-3.5 text-brand-text-secondary" :stroke-width="1.75" />
              {{ transition.performed_by?.name ?? t('tasks.systemActor') }}
            </span>
            <span>{{ formatTimestamp(transition.created_at) }}</span>
          </div>

          <p
            v-if="transition.comment"
            class="mt-3 flex gap-2 rounded-lg border border-brand-border/70 bg-brand-surface px-3 py-2 text-sm text-brand-text-secondary"
          >
            <MessageSquareText
              class="mt-0.5 h-3.5 w-3.5 shrink-0 text-brand-text-muted"
              :stroke-width="1.75"
            />
            <span>{{ transition.comment }}</span>
          </p>
        </div>
      </li>
    </ol>

    <div class="border-y border-brand-border px-5 py-4">
      <h4 class="text-sm font-bold text-brand-text">{{ t('tasks.timeline.assignmentTitle') }}</h4>
    </div>

    <div
      v-if="orderedAssignments.length === 0"
      class="px-5 py-8 text-center text-sm text-brand-text-muted"
    >
      {{ t('tasks.timeline.assignmentEmpty') }}
    </div>

    <ol v-else class="relative space-y-0 px-5 py-5">
      <li
        v-for="(item, index) in orderedAssignments"
        :key="item.id"
        class="relative flex gap-4 pb-6 last:pb-0"
      >
        <div class="relative flex w-4 shrink-0 flex-col items-center">
          <span
            class="mt-1.5 z-10 h-3 w-3 rounded-full bg-brand-primary ring-[3px] ring-brand-primary/15"
          />
          <span
            v-if="index < orderedAssignments.length - 1"
            class="absolute top-5 bottom-0 w-px bg-brand-border"
            aria-hidden="true"
          />
        </div>

        <div class="min-w-0 flex-1 rounded-xl border border-brand-border/80 bg-brand-bg/40 px-4 py-3">
          <p class="text-sm font-semibold text-brand-text">
            {{ t('tasks.timeline.assignedTo') }}:
            {{ item.to_employee?.full_name ?? t('tasks.noEmployee') }}
          </p>
          <p
            v-if="item.from_employee"
            class="mt-1 text-xs text-brand-text-secondary"
          >
            {{ t('tasks.timeline.assignedFrom') }}: {{ item.from_employee.full_name }}
          </p>

          <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-brand-text-muted">
            <span class="inline-flex items-center gap-1.5 font-semibold text-brand-text">
              <UserRound class="h-3.5 w-3.5 text-brand-text-secondary" :stroke-width="1.75" />
              {{ item.performed_by?.name ?? t('tasks.systemActor') }}
            </span>
            <span>{{ formatTimestamp(item.created_at) }}</span>
          </div>

          <p
            v-if="item.comment"
            class="mt-3 flex gap-2 rounded-lg border border-brand-border/70 bg-brand-surface px-3 py-2 text-sm text-brand-text-secondary"
          >
            <MessageSquareText
              class="mt-0.5 h-3.5 w-3.5 shrink-0 text-brand-text-muted"
              :stroke-width="1.75"
            />
            <span>{{ item.comment }}</span>
          </p>
        </div>
      </li>
    </ol>
  </section>
</template>
