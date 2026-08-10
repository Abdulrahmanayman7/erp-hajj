<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { ArrowRight, Pencil } from 'lucide-vue-next'
import { useCurrentUserQuery } from '@/modules/auth/queries/useCurrentUserQuery'
import { useDecisionsQuery } from '@/modules/decisions/queries/useDecisionsQuery'
import { useEmployeesQuery } from '@/modules/employees/queries/useEmployeesQuery'
import { useOrganizationUnitsFlatQuery } from '@/modules/organization/queries/useOrganizationUnitsQuery'
import type { AppSelectOption } from '@/shared/components/AppSelect.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'
import TaskFormDrawer from '../components/TaskFormDrawer.vue'
import TaskLifecycleActions from '../components/TaskLifecycleActions.vue'
import TaskTimeline from '../components/TaskTimeline.vue'
import { useUpdateTaskMutation } from '../mutations/useTaskMutations'
import { useTaskQuery } from '../queries/useTasksQuery'
import type { TaskFormState } from '../types/tasks'
import { taskPriorityBadgeClass, taskStatusBadgeClass, validateTaskForm } from '../validation/taskValidation'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { can } = usePermissions()
const toast = useToast()
const { data: currentUser } = useCurrentUserQuery()
const linkedEmployeeId = computed(() => currentUser.value?.employee_id ?? null)

const id = computed(() => Number(route.params.id))
const { data, isLoading, isError, refetch } = useTaskQuery(id)
const task = computed(() => data.value ?? null)

const { data: orgs } = useOrganizationUnitsFlatQuery({ status: 'active' })
const { data: employees } = useEmployeesQuery(computed(() => ({ status: 'active' as const, per_page: 100 })))
const { data: approvedDecisions } = useDecisionsQuery(computed(() => ({ status: 'approved', per_page: 100 })))
const orgOptions = computed<AppSelectOption[]>(() => [{ value: '', label: t('tasks.noOrgUnit') }, ...(orgs.value?.data ?? []).map((x) => ({ value: x.id, label: x.name }))])
const employeeOptions = computed<AppSelectOption[]>(() => [{ value: '', label: t('tasks.noEmployee') }, ...(employees.value?.data ?? []).map((x) => ({ value: x.id, label: x.full_name }))])
const decisionOptions = computed<AppSelectOption[]>(() => (approvedDecisions.value?.data ?? []).map((x) => ({ value: x.id, label: `${x.decision_number} — ${x.title}` })))

const update = useUpdateTaskMutation()
const open = ref(false)
const error = ref('')
const errors = reactive<Record<string, string>>({})
const form = reactive<TaskFormState>({ title: '', description: '', notes: '', priority: 'medium', decision_id: '', organization_unit_id: '', assigned_to_employee_id: '', start_date: '', due_date: '' })

const canEdit = computed(() => !!task.value && ['draft', 'assigned'].includes(task.value.status) && can('tasks.update'))

function edit(): void {
  if (!task.value) return
  Object.assign(form, {
    title: task.value.title, description: task.value.description ?? '', notes: task.value.notes ?? '', priority: task.value.priority,
    decision_id: task.value.decision_id ?? '', organization_unit_id: task.value.organization_unit_id ?? '', assigned_to_employee_id: task.value.assigned_to_employee_id ?? '',
    start_date: task.value.start_date ?? '', due_date: task.value.due_date ?? '',
  })
  open.value = true
}

async function save(): Promise<void> {
  Object.keys(errors).forEach((x) => delete errors[x])
  Object.assign(errors, validateTaskForm(form))
  if (Object.keys(errors).length || !task.value) return
  try {
    await update.mutateAsync({
      id: task.value.id,
      payload: { title: form.title.trim(), description: form.description.trim() || null, notes: form.notes.trim() || null, priority: form.priority, organization_unit_id: form.organization_unit_id || null, start_date: form.start_date || null, due_date: form.due_date || null },
    })
    toast.success(t('tasks.toasts.updated'))
    open.value = false
  } catch {
    error.value = t('tasks.errors.generic')
  }
}
</script>
<template>
  <div class="space-y-6">
    <button class="rounded-lg border px-3 py-2" @click="router.push('/app/tasks')"><ArrowRight class="inline h-4 w-4" /> {{ t('tasks.backToList') }}</button>

    <div v-if="isLoading" class="rounded-2xl border p-10 text-center">{{ t('tasks.loadingDetails') }}</div>
    <div v-else-if="isError || !task" class="rounded-2xl border p-10 text-center">{{ t('tasks.errors.loadDetails') }} <button @click="() => refetch()">{{ t('tasks.retry') }}</button></div>

    <template v-else>
      <div class="flex flex-wrap justify-between gap-4">
        <div>
          <div class="flex flex-wrap items-center gap-2">
            <span class="font-mono">{{ task.task_number }}</span>
            <span class="rounded-full px-2 py-1 text-xs" :class="taskStatusBadgeClass(task.status)">{{ t(`tasks.status.${task.status}`) }}</span>
            <span class="rounded-full px-2 py-1 text-xs" :class="taskPriorityBadgeClass(task.priority)">{{ t(`tasks.priority.${task.priority}`) }}</span>
            <span v-if="task.is_overdue" class="rounded-full bg-red-50 px-2 py-1 text-xs text-red-800 ring-1 ring-red-200/70">{{ t('tasks.overdueBadge') }}</span>
          </div>
          <h2 class="mt-2 text-2xl font-bold">{{ task.title }}</h2>
        </div>
        <PermissionGuard v-if="canEdit" permission="tasks.update"><button class="rounded-xl border px-4 py-2" @click="edit"><Pencil class="inline h-4 w-4" /> {{ t('tasks.actions.edit') }}</button></PermissionGuard>
      </div>

      <section>
        <h3 class="mb-3 font-bold">{{ t('tasks.sections.overview') }}</h3>
        <div class="grid gap-4 md:grid-cols-3">
          <div v-for="item in [
            { label: 'startDate', value: task.start_date },
            { label: 'dueDate', value: task.due_date },
            { label: 'progressPercent', value: `${task.progress_percent}%` },
            { label: 'organizationUnit', value: task.organization_unit?.name },
            { label: 'assignee', value: task.assigned_to_employee?.full_name },
            { label: 'createdBy', value: task.created_by?.name },
          ]" :key="item.label" class="rounded-2xl border border-brand-border bg-brand-surface p-4">
            <p class="text-xs text-brand-text-muted">{{ t(`tasks.fields.${item.label}`) }}</p>
            <p class="mt-1 font-semibold">{{ item.value ?? '—' }}</p>
          </div>
        </div>
        <div class="mt-4 rounded-2xl border p-4">
          <p class="text-xs text-brand-text-muted">{{ t('tasks.fields.description') }}</p>
          <p class="mt-2 whitespace-pre-wrap">{{ task.description || '—' }}</p>
          <p v-if="task.notes" class="mt-3 whitespace-pre-wrap text-sm text-brand-text-secondary">{{ task.notes }}</p>
        </div>
      </section>

      <section class="rounded-2xl border p-5">
        <h3 class="font-bold">{{ t('tasks.sections.source') }}</h3>
        <p v-if="!task.decision" class="mt-2 text-sm">{{ t('tasks.standaloneSource') }}</p>
        <RouterLink v-else :to="`/app/decisions/${task.decision.id}`" class="mt-2 inline-block text-sm text-brand-primary-dark underline">{{ task.decision.decision_number }} — {{ task.decision.title }}</RouterLink>
      </section>

      <section v-if="task.status === 'completed'" class="rounded-2xl border p-5">
        <h3 class="font-bold">{{ t('tasks.sections.outcome') }}</h3>
        <p class="mt-2 text-sm text-brand-text-muted">{{ t('tasks.fields.completedAt') }}: {{ task.completed_at ?? '—' }}</p>
        <p class="mt-2 whitespace-pre-wrap">{{ task.completion_notes }}</p>
      </section>

      <section class="rounded-2xl border p-5">
        <h3 class="mb-3 font-bold">{{ t('tasks.lifecycleTitle') }}</h3>
        <TaskLifecycleActions :task="task" :linked-employee-id="linkedEmployeeId" @refreshed="() => refetch()" />
      </section>

      <TaskTimeline :transitions="task.status_transitions ?? []" :assignments="task.assignment_history ?? []" />
    </template>

    <TaskFormDrawer :open="open" :editing="task" :form="form" :form-error="error" :field-errors="errors" :submitting="update.isPending.value" :employee-options="employeeOptions" :org-unit-options="orgOptions" :decision-options="decisionOptions" @close="open = false" @submit="save" @update:form="Object.assign(form, $event)" />
  </div>
</template>
