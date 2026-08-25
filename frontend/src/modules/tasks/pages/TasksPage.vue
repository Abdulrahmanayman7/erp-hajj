<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRouter } from 'vue-router'
import { ChevronLeft, ChevronRight, Eye, Pencil, Plus, Search } from 'lucide-vue-next'
import { listEmployees } from '@/modules/employees/api/employeesApi'
import { useOrganizationUnitsFlatQuery } from '@/modules/organization/queries/useOrganizationUnitsQuery'
import AppMobileFilters from '@/shared/components/AppMobileFilters.vue'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import AppTooltip from '@/shared/components/AppTooltip.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { employeeSelectOption, toSelectId } from '@/shared/lookups/selectOptions'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'
import { useDebouncedRef } from '@/shared/composables/useDebouncedRef'
import TaskFormDrawer from '../components/TaskFormDrawer.vue'
import { useCreateTaskMutation, useUpdateTaskMutation } from '../mutations/useTaskMutations'
import { useTasksQuery } from '../queries/useTasksQuery'
import type { ListTasksParams, Task, TaskFormState, TaskPriority, TaskStatus } from '../types/tasks'
import { TASK_PRIORITIES, TASK_STATUSES, resolveTasksListState, taskPriorityBadgeClass, taskStatusBadgeClass, taskStatusDotClass, validateTaskForm } from '../validation/taskValidation'

const { t } = useI18n()
const router = useRouter()
const { can } = usePermissions()
const toast = useToast()
const focusedRowIndex = ref(-1)

const emptyForm: TaskFormState = { title: '', description: '', notes: '', priority: 'medium', decision_id: '', organization_unit_id: '', assigned_to_employee_id: '', start_date: '', due_date: '' }

const segment = ref<'all' | 'mine'>('all')
const filters = reactive({
  search: '', status: 'all' as TaskStatus | 'all', priority: 'all' as TaskPriority | 'all',
  assigned_to_employee_id: '' as number | '', organization_unit_id: '' as number | '',
  overdue: false, page: 1, per_page: 15, sort: 'due_date',
})
const committedSearch = useDebouncedRef(() => filters.search)
const params = computed<ListTasksParams>(() => ({
  ...filters,
  search: committedSearch.value,
  status: filters.status === 'all' ? '' : filters.status,
  priority: filters.priority === 'all' ? '' : filters.priority,
  overdue: filters.overdue ? true : '',
  assigned_to_me: segment.value === 'mine' ? true : '',
}))
const { data, isLoading, isError, refetch, isFetching } = useTasksQuery(params)
const tasks = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta)
const listState = computed(() => resolveTasksListState({ isLoading: isLoading.value, isError: isError.value, count: tasks.value.length }))

const { data: orgs } = useOrganizationUnitsFlatQuery({ status: 'active' })
const fetchActiveEmployees = (params: { search?: string; page: number; per_page: number }) =>
  listEmployees({ ...params, status: 'active' })

const orgOptions = computed<AppSelectOption[]>(() => [{ value: '', label: t('tasks.filters.allOrgUnits') }, ...(orgs.value?.data ?? []).map((x) => ({ value: x.id, label: x.name }))])
const emptyEmployeeFilter = computed<AppSelectOption>(() => ({ value: '', label: t('tasks.noEmployee') }))
const statusOptions = computed<AppSelectOption[]>(() => [{ value: 'all', label: t('tasks.filters.allStatuses') }, ...TASK_STATUSES.map((x) => ({ value: x, label: t(`tasks.status.${x}`) }))])
const priorityOptions = computed<AppSelectOption[]>(() => [{ value: 'all', label: t('tasks.filters.allPriorities') }, ...TASK_PRIORITIES.map((x) => ({ value: x, label: t(`tasks.priority.${x}`) }))])

const activeFilterCount = computed(() => {
  let count = 0
  if (filters.status !== 'all') count += 1
  if (filters.priority !== 'all') count += 1
  if (filters.assigned_to_employee_id !== '') count += 1
  if (filters.organization_unit_id !== '') count += 1
  if (filters.overdue) count += 1
  return count
})

function resetFilters(): void {
  filters.status = 'all'
  filters.priority = 'all'
  filters.assigned_to_employee_id = ''
  filters.organization_unit_id = ''
  filters.overdue = false
}

const create = useCreateTaskMutation()
const update = useUpdateTaskMutation()
const drawerOpen = ref(false)
const editing = ref<Task | null>(null)
const error = ref('')
const fieldErrors = reactive<Record<string, string>>({})
const form = reactive<TaskFormState>({ ...emptyForm })

watch(() => [committedSearch.value, filters.status, filters.priority, filters.assigned_to_employee_id, filters.organization_unit_id, filters.overdue, segment.value], () => { filters.page = 1 })
watch(tasks, (rows) => {
  if (!rows.length) focusedRowIndex.value = -1
  else if (focusedRowIndex.value >= rows.length) focusedRowIndex.value = rows.length - 1
})

function assignForm(v: TaskFormState): void { Object.assign(form, v) }
function rowToneClass(index: number): string { return focusedRowIndex.value === index ? 'bg-[#EDF6F1]' : index % 2 === 1 ? 'bg-[#FAFBFA]' : 'bg-brand-surface' }
function rowAccentClass(index: number): string { return focusedRowIndex.value === index ? 'border-s-brand-primary' : 'border-s-transparent' }
function focusRow(index: number): void { if (tasks.value.length) focusedRowIndex.value = Math.min(Math.max(index, 0), tasks.value.length - 1) }
function onTableKeydown(event: KeyboardEvent): void {
  if (drawerOpen.value || !tasks.value.length) return
  const target = event.target as HTMLElement | null
  if (target && ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName)) return
  if (event.key === 'ArrowDown') { event.preventDefault(); focusRow(focusedRowIndex.value < 0 ? 0 : focusedRowIndex.value + 1) }
  else if (event.key === 'ArrowUp') { event.preventDefault(); focusRow(focusedRowIndex.value < 0 ? 0 : focusedRowIndex.value - 1) }
  else if (event.key === 'Home') { event.preventDefault(); focusRow(0) }
  else if (event.key === 'End') { event.preventDefault(); focusRow(tasks.value.length - 1) }
  else if (event.key === 'Enter' && focusedRowIndex.value >= 0) { event.preventDefault(); void router.push(`/app/tasks/${tasks.value[focusedRowIndex.value]?.id}`) }
}

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
    <AppPageHeader
      :title="t('tasks.title')"
      :subtitle="t('tasks.subtitle')"
      :meta="meta ? t('tasks.total', { count: meta.total }) : undefined"
    >
      <template #actions>
        <PermissionGuard permission="tasks.create">
          <button
            type="button"
            class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white transition hover:bg-brand-primary sm:w-auto"
            @click="openCreate"
          >
            <Plus class="h-4 w-4" :stroke-width="2.25" />
            {{ t('tasks.add') }}
          </button>
        </PermissionGuard>
      </template>
    </AppPageHeader>

    <div class="flex w-full gap-2 rounded-xl border border-brand-border bg-brand-surface p-1 sm:w-fit">
      <button
        type="button"
        class="min-h-11 flex-1 rounded-lg px-4 py-2 text-sm font-semibold transition sm:flex-none"
        :class="segment === 'all' ? 'bg-brand-primary-dark text-white' : 'text-brand-text-secondary'"
        @click="segment = 'all'"
      >
        {{ t('tasks.segments.all') }}
      </button>
      <button
        type="button"
        class="min-h-11 flex-1 rounded-lg px-4 py-2 text-sm font-semibold transition sm:flex-none"
        :class="segment === 'mine' ? 'bg-brand-primary-dark text-white' : 'text-brand-text-secondary'"
        @click="segment = 'mine'"
      >
        {{ t('tasks.segments.mine') }}
      </button>
    </div>

    <AppMobileFilters
      v-model:search="filters.search"
      :search-placeholder="t('tasks.searchPlaceholder')"
      :active-count="activeFilterCount"
      @reset="resetFilters"
    >
      <template #desktop>
        <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-brand-border bg-brand-surface p-4 shadow-[0_1px_2px_rgba(23,32,29,0.03)]">
          <div class="relative min-w-48 flex-1">
            <Search class="pointer-events-none absolute inset-s-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-text-muted" :stroke-width="1.75" />
            <input
              v-model="filters.search"
              type="search"
              class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface pe-3 ps-10 text-sm text-brand-text outline-none transition placeholder:text-brand-text-muted focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
              :placeholder="t('tasks.searchPlaceholder')"
            />
          </div>
          <AppSelect v-model="filters.status" :options="statusOptions" />
          <AppSelect v-model="filters.priority" :options="priorityOptions" />
          <AppRemoteSelect
            :model-value="filters.assigned_to_employee_id"
            query-key="employees-active"
            :fetcher="fetchActiveEmployees"
            :map-option="employeeSelectOption"
            :empty-option="emptyEmployeeFilter"
            @update:model-value="filters.assigned_to_employee_id = toSelectId($event)"
          />
          <AppSelect v-model="filters.organization_unit_id" :options="orgOptions" searchable />
          <label class="flex h-11 items-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-3 text-sm font-semibold text-brand-text">
            <input v-model="filters.overdue" type="checkbox" class="h-4 w-4" />{{ t('tasks.filters.overdue') }}
          </label>
        </div>
      </template>
      <template #filters>
        <div class="space-y-3">
          <AppSelect v-model="filters.status" :options="statusOptions" />
          <AppSelect v-model="filters.priority" :options="priorityOptions" />
          <AppRemoteSelect
            :model-value="filters.assigned_to_employee_id"
            query-key="employees-active"
            :fetcher="fetchActiveEmployees"
            :map-option="employeeSelectOption"
            :empty-option="emptyEmployeeFilter"
            @update:model-value="filters.assigned_to_employee_id = toSelectId($event)"
          />
          <AppSelect v-model="filters.organization_unit_id" :options="orgOptions" searchable />
          <label class="flex h-11 w-full items-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-3 text-sm font-semibold text-brand-text">
            <input v-model="filters.overdue" type="checkbox" class="h-4 w-4" />{{ t('tasks.filters.overdue') }}
          </label>
        </div>
      </template>
    </AppMobileFilters>

    <div v-if="listState === 'loading'" class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted">{{ t('tasks.loading') }}</div>
    <div v-else-if="listState === 'error'" class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center"><p class="text-sm text-red-700">{{ t('tasks.errors.load') }}</p><button class="mt-3 text-sm font-semibold text-brand-primary-dark underline" @click="() => refetch()">{{ t('tasks.retry') }}</button></div>
    <div v-else-if="listState === 'empty'" class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center">
      <p v-if="segment === 'mine'">{{ t('tasks.noLinkedEmployee') }}</p>
      <p v-else>{{ t('tasks.empty') }}</p>
    </div>
    <template v-else>
      <div class="hidden overflow-hidden rounded-2xl border border-brand-border bg-brand-surface shadow-[0_1px_2px_rgba(23,32,29,0.03)] outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/25 md:block" tabindex="0" role="grid" :aria-rowcount="tasks.length" :aria-label="t('tasks.title')" @keydown="onTableKeydown">
        <div class="overflow-x-auto"><table class="min-w-full border-separate border-spacing-0 text-sm">
          <thead><tr class="bg-[#F4F6F5]"><th v-for="(key, columnIndex) in ['number','title','source','assignee','dueDate','priority','progress','status','actions']" :key="key" class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text" :class="columnIndex === 0 ? 'border-s-[3px] border-s-transparent' : ''">{{ t(`tasks.columns.${key}`) }}</th></tr></thead>
          <tbody>
            <tr v-for="(task, index) in tasks" :key="task.id" class="group" :class="rowToneClass(index)" role="row" :aria-selected="focusedRowIndex === index" @mouseenter="focusedRowIndex = index">
              <td class="whitespace-nowrap border-b border-brand-border/80 border-s-[3px] px-5 py-3.5 text-center transition-colors duration-150 group-hover:border-s-brand-primary group-hover:bg-[#EDF6F1]" :class="rowAccentClass(index)">
                <RouterLink :to="`/app/tasks/${task.id}`" class="inline-flex items-center rounded-lg border border-brand-border bg-brand-bg px-2.5 py-1 font-mono text-[12px] font-bold tracking-wide text-brand-text shadow-[0_1px_0_rgba(23,32,29,0.04)] transition group-hover:border-brand-primary/30 group-hover:bg-brand-surface hover:border-brand-primary/35 hover:bg-brand-primary-soft hover:text-brand-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/25" dir="ltr">{{ task.task_number }}</RouterLink>
              </td>
              <td class="border-b border-brand-border/80 px-5 py-3.5 text-center font-semibold text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]">
                <RouterLink :to="`/app/tasks/${task.id}`" class="text-brand-text transition hover:text-brand-primary-dark hover:underline hover:underline-offset-2">{{ task.title }}</RouterLink>
              </td>
              <td class="border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]">{{ task.decision?.title ?? t('tasks.standaloneSource') }}</td>
              <td class="border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]">{{ task.assigned_to_employee?.full_name ?? '—' }}</td>
              <td class="border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]" dir="ltr">{{ task.due_date ?? '—' }}</td>
              <td class="border-b border-brand-border/80 px-5 py-3.5 text-center transition-colors duration-150 group-hover:bg-[#EDF6F1]"><span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold text-brand-text" :class="taskPriorityBadgeClass(task.priority)">{{ t(`tasks.priority.${task.priority}`) }}</span></td>
              <td class="border-b border-brand-border/80 px-5 py-3.5 text-center font-semibold text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]">{{ task.progress_percent }}%</td>
              <td class="border-b border-brand-border/80 px-5 py-3.5 text-center transition-colors duration-150 group-hover:bg-[#EDF6F1]">
                <div class="inline-flex flex-col items-center justify-center gap-1.5">
                  <span class="inline-flex min-w-[7.25rem] items-center justify-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-bold tracking-wide text-brand-text shadow-sm" :class="taskStatusBadgeClass(task.status)">
                    <span class="h-1.5 w-1.5 shrink-0 rounded-full" :class="taskStatusDotClass(task.status)" aria-hidden="true" />
                    {{ t(`tasks.status.${task.status}`) }}
                  </span>
                  <span
                    v-if="task.is_overdue"
                    class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-[11px] font-bold text-brand-text ring-1 ring-inset ring-red-300/80"
                  >
                    {{ t('tasks.overdueBadge') }}
                  </span>
                </div>
              </td>
              <td class="border-b border-brand-border/80 px-5 py-3.5 text-center transition-colors duration-150 group-hover:bg-[#EDF6F1]"><div class="inline-flex items-center justify-center gap-0.5 opacity-70 transition group-hover:opacity-100">
                <AppTooltip :text="t('tasks.actions.view')"><RouterLink :to="`/app/tasks/${task.id}`" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-brand-text transition hover:bg-brand-surface hover:text-brand-primary-dark"><Eye class="h-4 w-4" :stroke-width="2" /></RouterLink></AppTooltip>
                <PermissionGuard v-if="['draft', 'assigned'].includes(task.status)" permission="tasks.update"><AppTooltip :text="t('tasks.actions.edit')"><button class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-brand-text transition hover:bg-brand-surface" @click="openEdit(task)"><Pencil class="h-4 w-4" :stroke-width="2" /></button></AppTooltip></PermissionGuard>
              </div>
              </td>
            </tr>
          </tbody>
        </table></div>
      </div>

      <div class="space-y-3 md:hidden">
        <RouterLink v-for="task in tasks" :key="task.id" :to="`/app/tasks/${task.id}`" class="block rounded-2xl border border-brand-border bg-brand-surface p-4 shadow-[0_1px_2px_rgba(23,32,29,0.03)] transition active:bg-brand-bg">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <span class="font-mono text-sm font-bold text-brand-text">{{ task.task_number }}</span>
            <div class="flex flex-col items-end gap-1">
              <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold text-brand-text" :class="taskStatusBadgeClass(task.status)">
                <span class="h-1.5 w-1.5 rounded-full" :class="taskStatusDotClass(task.status)" />
                {{ t(`tasks.status.${task.status}`) }}
              </span>
              <span
                v-if="task.is_overdue"
                class="inline-flex rounded-full bg-red-100 px-2.5 py-0.5 text-[11px] font-bold text-brand-text ring-1 ring-inset ring-red-300/80"
              >
                {{ t('tasks.overdueBadge') }}
              </span>
            </div>
          </div>
          <p class="mt-2 font-semibold text-brand-text">{{ task.title }}</p>
          <dl class="mt-3 grid grid-cols-2 gap-2 text-xs text-brand-text-secondary">
            <div>
              <dt>{{ t('tasks.columns.assignee') }}</dt>
              <dd class="font-medium text-brand-text">{{ task.assigned_to_employee?.full_name ?? '—' }}</dd>
            </div>
            <div>
              <dt>{{ t('tasks.columns.dueDate') }}</dt>
              <dd class="font-medium text-brand-text" dir="ltr">{{ task.due_date ?? '—' }}</dd>
            </div>
            <div>
              <dt>{{ t('tasks.columns.priority') }}</dt>
              <dd>
                <span class="rounded-full px-2 py-0.5 text-[11px] font-bold text-brand-text" :class="taskPriorityBadgeClass(task.priority)">{{ t(`tasks.priority.${task.priority}`) }}</span>
              </dd>
            </div>
            <div>
              <dt>{{ t('tasks.columns.progress') }}</dt>
              <dd class="font-medium text-brand-text">{{ task.progress_percent }}%</dd>
            </div>
          </dl>
        </RouterLink>
      </div>

      <div
        v-if="meta && meta.last_page > 1"
        class="flex items-center justify-between gap-3"
      >
        <button
          type="button"
          class="inline-flex h-11 items-center gap-1 rounded-xl border border-brand-border bg-brand-surface px-3 text-sm font-semibold text-brand-text transition hover:bg-brand-bg disabled:cursor-not-allowed disabled:opacity-40"
          :disabled="filters.page <= 1 || isFetching"
          @click="filters.page -= 1"
        >
          <ChevronRight class="h-4 w-4" />{{ t('tasks.prev') }}
        </button>
        <span class="text-xs font-semibold text-brand-text">{{ filters.page }} / {{ meta.last_page }}</span>
        <button
          type="button"
          class="inline-flex h-11 items-center gap-1 rounded-xl border border-brand-border bg-brand-surface px-3 text-sm font-semibold text-brand-text transition hover:bg-brand-bg disabled:cursor-not-allowed disabled:opacity-40"
          :disabled="filters.page >= meta.last_page || isFetching"
          @click="filters.page += 1"
        >
          {{ t('tasks.next') }}<ChevronLeft class="h-4 w-4" />
        </button>
      </div>
    </template>

    <TaskFormDrawer :open="drawerOpen" :editing="editing" :form="form" :form-error="error" :field-errors="fieldErrors" :submitting="create.isPending.value || update.isPending.value" :org-unit-options="orgOptions" @close="drawerOpen = false" @submit="save" @update:form="assignForm" />
  </div>
</template>
