<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { TaskAssignmentHistoryEntry, TaskTransition } from '../types/tasks'
import { taskStatusBadgeClass } from '../validation/taskValidation'
const props = defineProps<{ transitions: TaskTransition[]; assignments: TaskAssignmentHistoryEntry[] }>()
const { t } = useI18n()
const orderedTransitions = computed(() => [...props.transitions].sort((a, b) => Date.parse(a.created_at ?? '') - Date.parse(b.created_at ?? '')))
const orderedAssignments = computed(() => [...props.assignments].sort((a, b) => Date.parse(a.created_at ?? '') - Date.parse(b.created_at ?? '')))
</script>
<template>
  <section class="rounded-2xl border border-brand-border bg-brand-surface p-5">
    <h3 class="font-bold">{{ t('tasks.timeline.statusTitle') }}</h3>
    <p v-if="!orderedTransitions.length" class="mt-3 text-sm text-brand-text-muted">{{ t('tasks.timeline.statusEmpty') }}</p>
    <ol v-else class="mt-4 space-y-4 border-s-2 border-brand-border ps-5">
      <li v-for="item in orderedTransitions" :key="item.id">
        <span class="rounded-full px-2 py-1 text-xs" :class="taskStatusBadgeClass(item.to_status)">{{ t(`tasks.status.${item.to_status}`) }}</span>
        <p class="mt-2 text-sm">{{ item.performed_by?.name ?? t('tasks.systemActor') }}</p>
        <p v-if="item.comment" class="mt-1 text-sm text-brand-text-secondary">{{ item.comment }}</p>
      </li>
    </ol>
    <h3 class="mt-6 font-bold">{{ t('tasks.timeline.assignmentTitle') }}</h3>
    <p v-if="!orderedAssignments.length" class="mt-3 text-sm text-brand-text-muted">{{ t('tasks.timeline.assignmentEmpty') }}</p>
    <ol v-else class="mt-4 space-y-4 border-s-2 border-brand-border ps-5">
      <li v-for="item in orderedAssignments" :key="item.id">
        <p class="text-sm font-semibold">{{ t('tasks.timeline.assignedTo') }}: {{ item.to_employee?.full_name ?? t('tasks.noEmployee') }}</p>
        <p v-if="item.from_employee" class="mt-0.5 text-xs text-brand-text-muted">{{ t('tasks.timeline.assignedFrom') }}: {{ item.from_employee.full_name }}</p>
        <p class="mt-1 text-sm text-brand-text-secondary">{{ item.performed_by?.name ?? t('tasks.systemActor') }}</p>
        <p v-if="item.comment" class="mt-1 text-sm text-brand-text-secondary">{{ item.comment }}</p>
      </li>
    </ol>
  </section>
</template>
