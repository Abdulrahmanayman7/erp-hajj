<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { ArrowRight, Pencil, Plus } from 'lucide-vue-next'
import { useEmployeesQuery } from '@/modules/employees/queries/useEmployeesQuery'
import { useOrganizationUnitsFlatQuery } from '@/modules/organization/queries/useOrganizationUnitsQuery'
import TaskFormDrawer from '@/modules/tasks/components/TaskFormDrawer.vue'
import { useCreateTaskMutation } from '@/modules/tasks/mutations/useTaskMutations'
import { useTasksQuery } from '@/modules/tasks/queries/useTasksQuery'
import type { TaskFormState } from '@/modules/tasks/types/tasks'
import { taskStatusBadgeClass, validateTaskForm } from '@/modules/tasks/validation/taskValidation'
import type { AppSelectOption } from '@/shared/components/AppSelect.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'
import DecisionFormDrawer from '../components/DecisionFormDrawer.vue'
import DecisionLifecycleActions from '../components/DecisionLifecycleActions.vue'
import DecisionTimeline from '../components/DecisionTimeline.vue'
import { useUpdateDecisionMutation } from '../mutations/useDecisionMutations'
import { useDecisionQuery } from '../queries/useDecisionsQuery'
import type { DecisionFormState } from '../types/decisions'
import { decisionStatusBadgeClass, validateDecisionForm } from '../validation/decisionValidation'
const { t } = useI18n(); const route = useRoute(); const router = useRouter(); const { can } = usePermissions(); const toast = useToast(); const id = computed(() => Number(route.params.id)); const { data, isLoading, isError, refetch } = useDecisionQuery(id); const decision = computed(() => data.value ?? null)
const { data: orgs } = useOrganizationUnitsFlatQuery({ status: 'active' }); const { data: employees } = useEmployeesQuery(computed(() => ({ status: 'active' as const, per_page: 100 }))); const orgOptions = computed<AppSelectOption[]>(() => [{ value: '', label: t('decisions.noOrgUnit') }, ...(orgs.value?.data ?? []).map(x => ({ value: x.id, label: x.name }))]); const employeeOptions = computed<AppSelectOption[]>(() => [{ value: '', label: t('decisions.noEmployee') }, ...(employees.value?.data ?? []).map(x => ({ value: x.id, label: x.full_name }))])
const update = useUpdateDecisionMutation(); const open = ref(false); const error = ref(''); const errors = reactive<Record<string, string>>({}); const form = reactive<DecisionFormState>({ title: '', body: '', notes: '', organization_unit_id: '', issued_by_employee_id: '', responsible_employee_id: '', effective_date: '', due_date: '' })
function edit() { if (!decision.value) return; Object.assign(form, { title: decision.value.title, body: decision.value.body, notes: decision.value.notes ?? '', organization_unit_id: decision.value.organization_unit_id ?? '', issued_by_employee_id: decision.value.issued_by_employee_id ?? '', responsible_employee_id: decision.value.responsible_employee_id ?? '', effective_date: decision.value.effective_date ?? '', due_date: decision.value.due_date ?? '' }); open.value = true }; async function save() { Object.keys(errors).forEach(x => delete errors[x]); Object.assign(errors, validateDecisionForm(form)); if (Object.keys(errors).length || !decision.value) return; await update.mutateAsync({ id: decision.value.id, payload: { ...form, organization_unit_id: form.organization_unit_id || null, issued_by_employee_id: form.issued_by_employee_id || null, responsible_employee_id: form.responsible_employee_id || null, effective_date: form.effective_date || null, due_date: form.due_date || null } }); toast.success(t('decisions.toasts.updated')); open.value = false }

// Linked tasks (Sprint 012 governance chain: Decisions -> Tasks)
const tasksParams = computed(() => ({ decision_id: id.value, per_page: 50 }))
const { data: linkedTasksData } = useTasksQuery(tasksParams)
const linkedTasks = computed(() => linkedTasksData.value?.data ?? [])
const tasksSummary = computed(() => decision.value?.tasks_summary ?? { total: 0, open: 0, completed: 0, cancelled: 0 })
const canCreateTask = computed(() => decision.value?.status === 'approved' && can('tasks.create'))
const taskDecisionOptions = computed<AppSelectOption[]>(() => decision.value ? [{ value: decision.value.id, label: `${decision.value.decision_number} — ${decision.value.title}` }] : [])
const taskEmployeeOptions = computed<AppSelectOption[]>(() => [{ value: '', label: t('tasks.noEmployee') }, ...(employees.value?.data ?? []).map(x => ({ value: x.id, label: x.full_name }))])
const createTask = useCreateTaskMutation(); const taskDrawerOpen = ref(false); const taskError = ref(''); const taskFieldErrors = reactive<Record<string, string>>({})
const emptyTaskForm: TaskFormState = { title: '', description: '', notes: '', priority: 'medium', decision_id: '', organization_unit_id: '', assigned_to_employee_id: '', start_date: '', due_date: '' }
const taskForm = reactive<TaskFormState>({ ...emptyTaskForm })
function openCreateTask() { if (!decision.value) return; Object.assign(taskForm, { ...emptyTaskForm, decision_id: decision.value.id, organization_unit_id: decision.value.organization_unit_id ?? '' }); taskError.value = ''; taskDrawerOpen.value = true }
async function saveTask() { Object.keys(taskFieldErrors).forEach(x => delete taskFieldErrors[x]); Object.assign(taskFieldErrors, validateTaskForm(taskForm)); if (Object.keys(taskFieldErrors).length || !decision.value) return; try { const created = await createTask.mutateAsync({ title: taskForm.title.trim(), description: taskForm.description.trim() || null, notes: taskForm.notes.trim() || null, priority: taskForm.priority, decision_id: decision.value.id, organization_unit_id: taskForm.organization_unit_id || null, assigned_to_employee_id: taskForm.assigned_to_employee_id || null, start_date: taskForm.start_date || null, due_date: taskForm.due_date || null }); taskDrawerOpen.value = false; await router.push(`/app/tasks/${created.id}`) } catch { taskError.value = t('decisions.errors.generic') } }
</script>
<template><div class="space-y-6"><button class="rounded-lg border px-3 py-2" @click="router.push('/app/decisions')"><ArrowRight class="inline h-4 w-4" /> {{ t('decisions.backToList') }}</button><div v-if="isLoading" class="rounded-2xl border p-10 text-center">{{ t('decisions.loadingDetails') }}</div><div v-else-if="isError || !decision" class="rounded-2xl border p-10 text-center">{{ t('decisions.errors.loadDetails') }} <button @click="() => refetch()">{{ t('decisions.retry') }}</button></div><template v-else><div class="flex flex-wrap justify-between gap-4"><div><div class="flex gap-2"><span class="font-mono">{{ decision.decision_number }}</span><span class="rounded-full px-2 py-1 text-xs" :class="decisionStatusBadgeClass(decision.status)">{{ t(`decisions.status.${decision.status}`) }}</span></div><h2 class="mt-2 text-2xl font-bold">{{ decision.title }}</h2></div><PermissionGuard v-if="decision.status === 'draft' && can('decisions.update')" permission="decisions.update"><button class="rounded-xl border px-4 py-2" @click="edit"><Pencil class="inline h-4 w-4" /> {{ t('decisions.actions.edit') }}</button></PermissionGuard></div><section><h3 class="mb-3 font-bold">{{ t('decisions.sections.overview') }}</h3><div class="grid gap-4 md:grid-cols-3"><div v-for="item in [{ label: 'effectiveDate', value: decision.effective_date }, { label: 'dueDate', value: decision.due_date }, { label: 'organizationUnit', value: decision.organization_unit?.name }, { label: 'issuedBy', value: decision.issued_by_employee?.full_name }, { label: 'responsible', value: decision.responsible_employee?.full_name }]" :key="item.label" class="rounded-2xl border border-brand-border bg-brand-surface p-4"><p class="text-xs text-brand-text-muted">{{ t(`decisions.fields.${item.label}`) }}</p><p class="mt-1 font-semibold">{{ item.value ?? '—' }}</p></div></div><div class="mt-4 rounded-2xl border p-4"><p class="text-xs text-brand-text-muted">{{ t('decisions.fields.body') }}</p><p class="mt-2 whitespace-pre-wrap">{{ decision.body }}</p><p v-if="decision.notes" class="mt-3 whitespace-pre-wrap text-sm text-brand-text-secondary">{{ decision.notes }}</p></div></section><section class="rounded-2xl border p-5"><h3 class="font-bold">{{ t('decisions.sections.source') }}</h3><p v-if="!decision.source_recommendation" class="mt-2 text-sm">{{ t('decisions.standaloneSource') }}</p><template v-else><p class="mt-2">{{ decision.source_recommendation.title }}</p><RouterLink v-if="decision.source_meeting" :to="`/app/meetings/${decision.source_meeting.id}`" class="mt-2 inline-block text-sm text-brand-primary-dark underline">{{ decision.source_meeting.meeting_number }} — {{ decision.source_meeting.title }}</RouterLink></template></section><section class="rounded-2xl border p-5"><h3 class="mb-3 font-bold">{{ t('decisions.lifecycleTitle') }}</h3><DecisionLifecycleActions :decision="decision" @refreshed="() => refetch()" /></section><DecisionTimeline :transitions="decision.status_transitions ?? []" />

<section class="rounded-2xl border p-5">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <h3 class="font-bold">{{ t('decisions.tasksSection.title') }}</h3>
    <PermissionGuard v-if="canCreateTask" permission="tasks.create"><button class="rounded-xl bg-brand-primary-dark px-4 py-2 text-sm text-white" @click="openCreateTask"><Plus class="inline h-4 w-4" /> {{ t('decisions.tasksSection.createTask') }}</button></PermissionGuard>
  </div>
  <div class="mt-3 flex flex-wrap gap-2 text-sm">
    <span class="rounded-full bg-brand-bg px-3 py-1">{{ t('decisions.tasksSection.total', { count: tasksSummary.total }) }}</span>
    <span class="rounded-full bg-sky-50 px-3 py-1 text-sky-900 ring-1 ring-sky-200/70">{{ t('decisions.tasksSection.open', { count: tasksSummary.open }) }}</span>
    <span class="rounded-full bg-emerald-50 px-3 py-1 text-emerald-800 ring-1 ring-emerald-200/70">{{ t('decisions.tasksSection.completed', { count: tasksSummary.completed }) }}</span>
    <span class="rounded-full bg-red-50 px-3 py-1 text-red-800 ring-1 ring-red-200/70">{{ t('decisions.tasksSection.cancelled', { count: tasksSummary.cancelled }) }}</span>
  </div>
  <p v-if="!linkedTasks.length" class="mt-4 text-sm text-brand-text-muted">{{ t('decisions.tasksSection.empty') }}</p>
  <div v-else class="mt-4 overflow-x-auto rounded-xl border border-brand-border">
    <table class="min-w-full text-sm">
      <thead><tr class="bg-brand-bg"><th class="px-3 py-2 text-start">{{ t('tasks.columns.number') }}</th><th class="px-3 py-2 text-start">{{ t('tasks.columns.title') }}</th><th class="px-3 py-2 text-start">{{ t('tasks.columns.assignee') }}</th><th class="px-3 py-2 text-start">{{ t('tasks.columns.status') }}</th><th class="px-3 py-2 text-start">{{ t('tasks.columns.dueDate') }}</th></tr></thead>
      <tbody>
        <tr v-for="taskItem in linkedTasks" :key="taskItem.id" class="border-t">
          <td class="px-3 py-2 font-mono"><RouterLink :to="`/app/tasks/${taskItem.id}`">{{ taskItem.task_number }}</RouterLink></td>
          <td class="px-3 py-2">{{ taskItem.title }}</td>
          <td class="px-3 py-2">{{ taskItem.assigned_to_employee?.full_name ?? '—' }}</td>
          <td class="px-3 py-2"><span class="rounded-full px-2 py-1 text-xs" :class="taskStatusBadgeClass(taskItem.status)">{{ t(`tasks.status.${taskItem.status}`) }}</span><span v-if="taskItem.is_overdue" class="ms-1 rounded-full bg-red-50 px-2 py-1 text-xs text-red-800 ring-1 ring-red-200/70">{{ t('tasks.overdueBadge') }}</span></td>
          <td class="px-3 py-2">{{ taskItem.due_date ?? '—' }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</section>
</template><DecisionFormDrawer :open="open" :editing="decision" :form="form" :form-error="error" :field-errors="errors" :submitting="update.isPending.value" :employee-options="employeeOptions" :org-unit-options="orgOptions" @close="open = false" @submit="save" @update:form="Object.assign(form, $event)" /><TaskFormDrawer :open="taskDrawerOpen" :editing="null" :form="taskForm" :form-error="taskError" :field-errors="taskFieldErrors" :submitting="createTask.isPending.value" :employee-options="taskEmployeeOptions" :org-unit-options="orgOptions" :decision-options="taskDecisionOptions" :locked-decision-id="decision?.id ?? null" @close="taskDrawerOpen = false" @submit="saveTask" @update:form="Object.assign(taskForm, $event)" /></div></template>
