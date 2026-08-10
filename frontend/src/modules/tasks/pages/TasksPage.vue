<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { Eye, Pencil, Plus } from 'lucide-vue-next'
import { useDecisionsQuery } from '@/modules/decisions/queries/useDecisionsQuery'
import { useEmployeesQuery } from '@/modules/employees/queries/useEmployeesQuery'
import { useOrganizationUnitsFlatQuery } from '@/modules/organization/queries/useOrganizationUnitsQuery'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'
import TaskFormDrawer from '../components/TaskFormDrawer.vue'
import { useCreateTaskMutation, useUpdateTaskMutation } from '../mutations/useTaskMutations'
import { useTasksQuery } from '../queries/useTasksQuery'
import type { ListTasksParams, Task, TaskFormState, TaskPriority, TaskStatus } from '../types/tasks'
import { TASK_PRIORITIES, TASK_STATUSES, taskPriorityBadgeClass, taskStatusBadgeClass, validateTaskForm } from '../validation/taskValidation'

const { t } = useI18n()
const { can } = usePermissions()
const toast = useToast()

const emptyForm: TaskFormState = { title: '', description: '', notes: '', priority: 'medium', decision_id: '', organization_unit_id: '', assigned_to_employee_id: '', start_date: '', due_date: '' }

const segment = ref<'all' | 'mine'>('all')
const filters = reactive({
  search: '', status: 'all' as TaskStatus | 'all', priority: 'all' as TaskPriority | 'all',
  assigned_to_employee_id: '' as number | '', organization_unit_id: '' as number | '',
  overdue: false, page: 1, per_page: 15, sort: 'due_date',
})
const params = computed<ListTasksParams>(() => ({
  ...filters,
  status: filters.status === 'all' ? '' : filters.status,
  priority: filters.priority === 'all' ? '' : filters.priority,
  overdue: filters.overdue ? true : '',
  assigned_to_me: segment.value === 'mine' ? true : '',
}))
const { data, isLoading, isError, refetch } = useTasksQuery(params)
const tasks = computed(() => data.value?.data ?? [])

const { data: orgs } = useOrganizationUnitsFlatQuery({ status: 'active' })
const { data: employees } = useEmployeesQuery(computed(() => ({ status: 'active' as const, per_page: 100 })))
const { data: approvedDecisions } = useDecisionsQuery(computed(() => ({ status: 'approved', per_page: 100 })))

const orgOptions = computed<AppSelectOption[]>(() => [{ value: '', label: t('tasks.filters.allOrgUnits') }, ...(orgs.value?.data ?? []).map((x) => ({ value: x.id, label: x.name }))])
const employeeOptions = computed<AppSelectOption[]>(() => [{ value: '', label: t('tasks.noEmployee') }, ...(employees.value?.data ?? []).map((x) => ({ value: x.id, label: x.full_name, hint: x.employee_number }))])
const decisionOptions = computed<AppSelectOption[]>(() => (approvedDecisions.value?.data ?? []).map((x) => ({ value: x.id, label: `${x.decision_number} — ${x.title}` })))
const statusOptions = computed<AppSelectOption[]>(() => [{ value: 'all', label: t('tasks.filters.allStatuses') }, ...TASK_STATUSES.map((x) => ({ value: x, label: t(`tasks.status.${x}`) }))])
const priorityOptions = computed<AppSelectOption[]>(() => [{ value: 'all', label: t('tasks.filters.allPriorities') }, ...TASK_PRIORITIES.map((x) => ({ value: x, label: t(`tasks.priority.${x}`) }))])

const create = useCreateTaskMutation()
const update = useUpdateTaskMutation()
const drawerOpen = ref(false)
const editing = ref<Task | null>(null)
const error = ref('')
const fieldErrors = reactive<Record<string, string>>({})
const form = reactive<TaskFormState>({ ...emptyForm })

watch(() => [filters.search, filters.status, filters.priority, filters.assigned_to_employee_id, filters.organization_unit_id, filters.overdue, segment.value], () => { filters.page = 1 })

function assignForm(v: TaskFormState): void { Object.assign(form, v) }

function openCreate(): void {
  editing.value = null
  assignForm({ ...emptyForm })
  error.value = ''
  drawerOpen.value = true
}

function openEdit(task: Task): void {
  if (!['draft', 'assigned'].includes(task.status) || !can('tasks.update')) return
  editing.value = task
  assignForm({
    title: task.title, description: task.description ?? '', notes: task.notes ?? '', priority: task.priority,
    decision_id: task.decision_id ?? '', organization_unit_id: task.organization_unit_id ?? '', assigned_to_employee_id: task.assigned_to_employee_id ?? '',
    start_date: task.start_date ?? '', due_date: task.due_date ?? '',
  })
  drawerOpen.value = true
}

async function save(): Promise<void> {
  Object.keys(fieldErrors).forEach((x) => delete fieldErrors[x])
  Object.assign(fieldErrors, validateTaskForm(form))
  if (Object.keys(fieldErrors).length) return

  try {
    if (editing.value) {
      await update.mutateAsync({
        id: editing.value.id,
        payload: { title: form.title.trim(), description: form.description.trim() || null, notes: form.notes.trim() || null, priority: form.priority, organization_unit_id: form.organization_unit_id || null, start_date: form.start_date || null, due_date: form.due_date || null },
      })
      toast.success(t('tasks.toasts.updated'))
    } else {
      await create.mutateAsync({
        title: form.title.trim(), description: form.description.trim() || null, notes: form.notes.trim() || null, priority: form.priority,
        decision_id: form.decision_id || null, organization_unit_id: form.organization_unit_id || null, assigned_to_employee_id: form.assigned_to_employee_id || null,
        start_date: form.start_date || null, due_date: form.due_date || null,
      })
      toast.success(t('tasks.toasts.created'))
    }
    drawerOpen.value = false
  } catch {
    error.value = t('tasks.errors.generic')
  }
}
</script>
<template>
  <div class="space-y-6">
    <div class="flex flex-wrap justify-between gap-4">
      <div><h2 class="text-2xl font-bold">{{ t('tasks.title') }}</h2><p class="text-sm text-brand-text-secondary">{{ t('tasks.subtitle') }}</p></div>
      <PermissionGuard permission="tasks.create"><button class="rounded-xl bg-brand-primary-dark px-4 py-2 text-white" @click="openCreate"><Plus class="inline h-4 w-4" /> {{ t('tasks.add') }}</button></PermissionGuard>
    </div>

    <div class="flex gap-2 rounded-xl border border-brand-border bg-brand-surface p-1 w-fit">
      <button class="rounded-lg px-4 py-2 text-sm font-semibold transition" :class="segment === 'all' ? 'bg-brand-primary-dark text-white' : 'text-brand-text-secondary'" @click="segment = 'all'">{{ t('tasks.segments.all') }}</button>
      <button class="rounded-lg px-4 py-2 text-sm font-semibold transition" :class="segment === 'mine' ? 'bg-brand-primary-dark text-white' : 'text-brand-text-secondary'" @click="segment = 'mine'">{{ t('tasks.segments.mine') }}</button>
    </div>

    <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-brand-border bg-brand-surface p-4">
      <input v-model="filters.search" class="h-11 min-w-48 flex-1 rounded-xl border border-brand-border px-3" :placeholder="t('tasks.searchPlaceholder')" />
      <AppSelect v-model="filters.status" :options="statusOptions" />
      <AppSelect v-model="filters.priority" :options="priorityOptions" />
      <AppSelect v-model="filters.assigned_to_employee_id" :options="employeeOptions" searchable />
      <AppSelect v-model="filters.organization_unit_id" :options="orgOptions" searchable />
      <label class="flex h-11 items-center gap-2 rounded-xl border border-brand-border px-3 text-sm font-semibold text-brand-text-secondary">
        <input v-model="filters.overdue" type="checkbox" class="h-4 w-4" />{{ t('tasks.filters.overdue') }}
      </label>
    </div>

    <div v-if="isLoading" class="rounded-2xl border p-10 text-center">{{ t('tasks.loading') }}</div>
    <div v-else-if="isError" class="rounded-2xl border p-10 text-center"><p>{{ t('tasks.errors.load') }}</p><button @click="() => refetch()">{{ t('tasks.retry') }}</button></div>
    <div v-else-if="!tasks.length" class="rounded-2xl border p-10 text-center">
      <p v-if="segment === 'mine'">{{ t('tasks.noLinkedEmployee') }}</p>
      <p v-else>{{ t('tasks.empty') }}</p>
    </div>
    <template v-else>
      <div class="hidden overflow-x-auto rounded-2xl border border-brand-border bg-brand-surface md:block">
        <table class="min-w-full text-sm">
          <thead><tr class="bg-brand-bg"><th v-for="key in ['number','title','source','assignee','dueDate','priority','progress','status','actions']" :key="key" class="px-4 py-3 text-start">{{ t(`tasks.columns.${key}`) }}</th></tr></thead>
          <tbody>
            <tr v-for="task in tasks" :key="task.id" class="border-t">
              <td class="px-4 py-3 font-mono"><RouterLink :to="`/app/tasks/${task.id}`">{{ task.task_number }}</RouterLink></td>
              <td class="px-4 py-3">{{ task.title }}</td>
              <td class="px-4 py-3">{{ task.decision?.title ?? t('tasks.standaloneSource') }}</td>
              <td class="px-4 py-3">{{ task.assigned_to_employee?.full_name ?? '—' }}</td>
              <td class="px-4 py-3">{{ task.due_date ?? '—' }}</td>
              <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs" :class="taskPriorityBadgeClass(task.priority)">{{ t(`tasks.priority.${task.priority}`) }}</span></td>
              <td class="px-4 py-3">{{ task.progress_percent }}%</td>
              <td class="px-4 py-3">
                <span class="rounded-full px-2 py-1 text-xs" :class="taskStatusBadgeClass(task.status)">{{ t(`tasks.status.${task.status}`) }}</span>
                <span v-if="task.is_overdue" class="ms-1 rounded-full bg-red-50 px-2 py-1 text-xs text-red-800 ring-1 ring-red-200/70">{{ t('tasks.overdueBadge') }}</span>
              </td>
              <td class="px-4 py-3">
                <RouterLink :to="`/app/tasks/${task.id}`"><Eye class="inline h-4 w-4" /></RouterLink>
                <button v-if="['draft', 'assigned'].includes(task.status) && can('tasks.update')" class="ms-2" @click="openEdit(task)"><Pencil class="inline h-4 w-4" /></button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="space-y-3 md:hidden">
        <RouterLink v-for="task in tasks" :key="task.id" :to="`/app/tasks/${task.id}`" class="block rounded-2xl border border-brand-border bg-brand-surface p-4">
          <div class="flex items-center justify-between gap-2"><span class="font-mono text-sm">{{ task.task_number }}</span><span class="rounded-full px-2 py-1 text-xs" :class="taskStatusBadgeClass(task.status)">{{ t(`tasks.status.${task.status}`) }}</span></div>
          <p class="mt-2 font-semibold">{{ task.title }}</p>
          <p class="mt-1 text-sm text-brand-text-secondary">{{ task.assigned_to_employee?.full_name ?? '—' }} · {{ task.due_date ?? '—' }}</p>
          <div class="mt-2 flex items-center gap-2">
            <span class="rounded-full px-2 py-1 text-xs" :class="taskPriorityBadgeClass(task.priority)">{{ t(`tasks.priority.${task.priority}`) }}</span>
            <span v-if="task.is_overdue" class="rounded-full bg-red-50 px-2 py-1 text-xs text-red-800 ring-1 ring-red-200/70">{{ t('tasks.overdueBadge') }}</span>
            <span class="text-xs text-brand-text-muted">{{ task.progress_percent }}%</span>
          </div>
        </RouterLink>
      </div>
    </template>

    <TaskFormDrawer :open="drawerOpen" :editing="editing" :form="form" :form-error="error" :field-errors="fieldErrors" :submitting="create.isPending.value || update.isPending.value" :employee-options="employeeOptions" :org-unit-options="orgOptions" :decision-options="decisionOptions" @close="drawerOpen = false" @submit="save" @update:form="assignForm" />
  </div>
</template>
