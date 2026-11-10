<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'

import { useAccommodationSeasonStore } from '@/modules/accommodation/stores/seasonContextStore'
import { listEmployees } from '@/modules/employees/api/employeesApi'
import type { Employee } from '@/modules/employees/types/employees'
import { listPurchaseOrders } from '@/modules/purchase-orders/api/purchaseOrdersApi'
import type { PurchaseOrder } from '@/modules/purchase-orders/types/purchaseOrder'
import SeasonWorkspaceBar from '@/modules/season-pilgrims/components/SeasonWorkspaceBar.vue'
import { useSeasonsQuery } from '@/modules/season-pilgrims/queries/useSeasonsQuery'
import {
  useCancelTaskMutation,
  useCompleteTaskMutation,
  useCreateTaskMutation,
} from '@/modules/tasks/mutations/useTaskMutations'
import { useTasksQuery } from '@/modules/tasks/queries/useTasksQuery'
import type { Task, TaskPriority } from '@/modules/tasks/types/tasks'
import { ApiError } from '@/shared/api/http'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'
import { employeeSelectOption } from '@/shared/lookups/selectOptions'

import SeasonProjectProgressBaselinePanel from '../components/SeasonProjectProgressBaselinePanel.vue'
import {
  useActivateSeasonProjectMutation,
  useCancelSeasonProjectMutation,
  useCompleteSeasonProjectMutation,
  useCreateSeasonProjectPhaseMutation,
  useDeleteSeasonProjectMutation,
  useDeleteSeasonProjectPhaseMutation,
  useLinkSeasonProjectPurchaseOrderMutation,
  useReassignSeasonProjectOwnerMutation,
  useUnlinkSeasonProjectPurchaseOrderMutation,
  useUpdateSeasonProjectBudgetMutation,
  useUpdateSeasonProjectMutation,
  useUpdateSeasonProjectPhaseMutation,
} from '../mutations/useSeasonProjectsMutations'
import {
  useSeasonProjectBudgetQuery,
  useSeasonProjectPhasesQuery,
  useSeasonProjectQuery,
} from '../queries/useSeasonProjectsQuery'
import type { SeasonProjectPhase, SeasonProjectPhaseStatus } from '../types/seasonProjects'
import { SEASON_PROJECT_PHASE_STATUSES } from '../types/seasonProjects'
import {
  canActivateProject,
  canCancelProject,
  canCompleteProject,
  canEditProjectFields,
  canLinkPoLifecycle,
  canManageBudgetLifecycle,
  canReassignOwner,
  canSoftDeleteProject,
  emptyPhaseForm,
  emptyProjectForm,
  formatMoneyDisplay,
  isProjectTerminal,
  isTaskMutationFrozenForCompletedProject,
  mapSeasonProjectErrorCode,
  validatePhaseForm,
  validateProjectForm,
} from '../validation/seasonProjectsValidation'

const { t } = useI18n()
const toast = useToast()
const { confirm } = useConfirm()
const { can } = usePermissions()
const route = useRoute()
const router = useRouter()
const store = useAccommodationSeasonStore()
const { selectedSeasonId } = storeToRefs(store)
const seasonsQuery = useSeasonsQuery({ per_page: 100 })
const selectedSeason = computed(
  () => (seasonsQuery.data.value?.data ?? []).find((s) => s.id === selectedSeasonId.value) ?? null,
)
const seasonWritable = computed(() => selectedSeason.value?.status !== 'closed')

const projectId = computed(() => {
  const n = Number(route.params.id)
  return Number.isFinite(n) && n > 0 ? n : null
})

const {
  data: projectData,
  isLoading,
  isError,
  refetch,
  error: projectError,
} = useSeasonProjectQuery(
  projectId,
  selectedSeasonId,
  () => projectId.value != null && selectedSeasonId.value != null,
)
const project = computed(() => projectData.value ?? null)

const { data: phasesData, refetch: refetchPhases } = useSeasonProjectPhasesQuery(
  projectId,
  selectedSeasonId,
  () => project.value != null,
)
const phases = computed(() => phasesData.value ?? [])

const { data: budgetData, refetch: refetchBudget } = useSeasonProjectBudgetQuery(
  projectId,
  selectedSeasonId,
  () => project.value != null && can('season_projects.view'),
)
const budget = computed(() => budgetData.value ?? null)

const tasksParams = computed(() => ({
  project_id: projectId.value ?? undefined,
  page: 1,
  per_page: 50,
}))
const { data: tasksData, refetch: refetchTasks } = useTasksQuery(
  tasksParams,
  () => project.value != null && can('tasks.view'),
)
const tasks = computed(() => tasksData.value?.data ?? [])
const openTasksCount = computed(
  () =>
    tasks.value.filter((task) => !['completed', 'cancelled'].includes(task.status)).length,
)

const fetchActiveEmployees = (params: { search?: string; page: number; per_page: number }) =>
  listEmployees({ ...params, status: 'active' })
async function fetchPurchaseOrders(params: {
  search?: string
  page: number
  per_page: number
}) {
  const result = await listPurchaseOrders({ q: params.search, page: params.page })
  const meta = result.meta ?? {}
  return {
    data: result.data,
    meta: {
      current_page: Number(meta.current_page ?? params.page),
      last_page: Number(meta.last_page ?? 1),
      per_page: Number(meta.per_page ?? params.per_page),
      total: Number(meta.total ?? result.data.length),
    },
  }
}

function mapPoOption(row: PurchaseOrder): AppSelectOption {
  return {
    value: row.id,
    label: row.po_number,
    hint: `${row.status} · ${row.currency ?? ''}`,
  }
}

function apiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) return t('seasonProjects.errors.generic')
  const mapped = mapSeasonProjectErrorCode(error.code)
  if (mapped !== 'generic') return t(`seasonProjects.errors.${mapped}`)
  return error.message || t('seasonProjects.errors.generic')
}

const updateMutation = useUpdateSeasonProjectMutation()
const deleteMutation = useDeleteSeasonProjectMutation()
const activateMutation = useActivateSeasonProjectMutation()
const completeMutation = useCompleteSeasonProjectMutation()
const cancelMutation = useCancelSeasonProjectMutation()
const reassignMutation = useReassignSeasonProjectOwnerMutation()
const createPhaseMutation = useCreateSeasonProjectPhaseMutation()
const updatePhaseMutation = useUpdateSeasonProjectPhaseMutation()
const deletePhaseMutation = useDeleteSeasonProjectPhaseMutation()
const updateBudgetMutation = useUpdateSeasonProjectBudgetMutation()
const linkPoMutation = useLinkSeasonProjectPurchaseOrderMutation()
const unlinkPoMutation = useUnlinkSeasonProjectPurchaseOrderMutation()
const createTaskMutation = useCreateTaskMutation()
const completeTaskMutation = useCompleteTaskMutation()
const cancelTaskMutation = useCancelTaskMutation()

const activatePending = computed(() => activateMutation.isPending.value)
const completePending = computed(() => completeMutation.isPending.value)
const cancelPending = computed(() => cancelMutation.isPending.value)
const reassignPending = computed(() => reassignMutation.isPending.value)
const budgetPending = computed(() => updateBudgetMutation.isPending.value)
const linkPoPending = computed(() => linkPoMutation.isPending.value)

const editOpen = ref(false)
const editForm = reactive(emptyProjectForm())
const editError = ref('')
const editFieldErrors = reactive<Record<string, string>>({})

function openEdit(): void {
  if (!project.value) return
  Object.assign(editForm, {
    title: project.value.title,
    description: project.value.description ?? '',
    owner_employee_id: project.value.owner_employee_id ?? '',
  })
  editError.value = ''
  Object.keys(editFieldErrors).forEach((k) => delete editFieldErrors[k])
  editOpen.value = true
}

async function submitEdit(): Promise<void> {
  if (!project.value) return
  Object.keys(editFieldErrors).forEach((k) => delete editFieldErrors[k])
  const local = validateProjectForm(editForm)
  if (Object.keys(local).length) {
    Object.assign(
      editFieldErrors,
      Object.fromEntries(Object.entries(local).map(([k, v]) => [k, t(`seasonProjects.errors.${v}`)])),
    )
    return
  }
  try {
    await updateMutation.mutateAsync({
      id: project.value.id,
      payload: {
        title: editForm.title.trim(),
        description: editForm.description.trim() || null,
        owner_employee_id:
          editForm.owner_employee_id === '' ? null : Number(editForm.owner_employee_id),
      },
      seasonId: selectedSeasonId.value,
    })
    toast.success(t('seasonProjects.toasts.updated'))
    editOpen.value = false
  } catch (error) {
    editError.value = apiMessage(error)
  }
}

async function onActivate(): Promise<void> {
  if (!project.value) return
  const ok = await confirm({
    title: t('seasonProjects.lifecycle.activateConfirmTitle'),
    message: t('seasonProjects.lifecycle.activateConfirmBody'),
    confirmLabel: t('seasonProjects.lifecycle.activate'),
    cancelLabel: t('seasonProjects.cancel'),
  })
  if (!ok) return
  try {
    await activateMutation.mutateAsync({ id: project.value.id, seasonId: selectedSeasonId.value })
    toast.success(t('seasonProjects.toasts.activated'))
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

async function onComplete(): Promise<void> {
  if (!project.value) return
  const ok = await confirm({
    title: t('seasonProjects.lifecycle.completeConfirmTitle'),
    message: t('seasonProjects.lifecycle.completeConfirmBody'),
    confirmLabel: t('seasonProjects.lifecycle.complete'),
    cancelLabel: t('seasonProjects.cancel'),
  })
  if (!ok) return
  try {
    await completeMutation.mutateAsync({ id: project.value.id, seasonId: selectedSeasonId.value })
    toast.success(t('seasonProjects.toasts.completed'))
    await refetchTasks()
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

async function onCancelProject(): Promise<void> {
  if (!project.value) return
  const ok = await confirm({
    title: t('seasonProjects.lifecycle.cancelConfirmTitle'),
    message: t('seasonProjects.lifecycle.cancelConfirmBody'),
    confirmLabel: t('seasonProjects.lifecycle.cancel'),
    cancelLabel: t('seasonProjects.close'),
  })
  if (!ok) return
  try {
    await cancelMutation.mutateAsync({ id: project.value.id, seasonId: selectedSeasonId.value })
    toast.success(t('seasonProjects.toasts.cancelled'))
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

async function onDeleteDraft(): Promise<void> {
  if (!project.value) return
  const ok = await confirm({
    title: t('seasonProjects.lifecycle.deleteConfirmTitle'),
    message: t('seasonProjects.lifecycle.deleteConfirmBody'),
    confirmLabel: t('seasonProjects.delete'),
    cancelLabel: t('seasonProjects.cancel'),
  })
  if (!ok) return
  try {
    await deleteMutation.mutateAsync({ id: project.value.id, seasonId: selectedSeasonId.value })
    toast.success(t('seasonProjects.toasts.deleted'))
    await router.push('/app/season-projects')
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

const reassignId = ref<number | ''>('')
async function onReassign(): Promise<void> {
  if (!project.value || reassignId.value === '') return
  try {
    await reassignMutation.mutateAsync({
      id: project.value.id,
      ownerEmployeeId: Number(reassignId.value),
      seasonId: selectedSeasonId.value,
    })
    toast.success(t('seasonProjects.toasts.reassigned'))
    reassignId.value = ''
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

const phaseForm = reactive(emptyPhaseForm())
const phaseEditing = ref<SeasonProjectPhase | null>(null)
const phaseError = ref('')
const phaseFieldErrors = reactive<Record<string, string>>({})
const phaseDrawer = ref(false)

const phaseStatusOptions = computed<AppSelectOption[]>(() =>
  SEASON_PROJECT_PHASE_STATUSES.map((s) => ({
    value: s,
    label: t(`seasonProjects.phaseStatus.${s}`),
  })),
)

function openPhaseCreate(): void {
  phaseEditing.value = null
  Object.assign(phaseForm, emptyPhaseForm())
  phaseError.value = ''
  Object.keys(phaseFieldErrors).forEach((k) => delete phaseFieldErrors[k])
  phaseDrawer.value = true
}

function openPhaseEdit(row: SeasonProjectPhase): void {
  phaseEditing.value = row
  Object.assign(phaseForm, {
    title: row.title,
    sort_order: row.sort_order,
    starts_on: row.starts_on ?? '',
    ends_on: row.ends_on ?? '',
    status: row.status,
  })
  phaseError.value = ''
  Object.keys(phaseFieldErrors).forEach((k) => delete phaseFieldErrors[k])
  phaseDrawer.value = true
}

async function submitPhase(): Promise<void> {
  if (!project.value) return
  Object.keys(phaseFieldErrors).forEach((k) => delete phaseFieldErrors[k])
  const local = validatePhaseForm(phaseForm)
  if (Object.keys(local).length) {
    Object.assign(
      phaseFieldErrors,
      Object.fromEntries(Object.entries(local).map(([k, v]) => [k, t(`seasonProjects.errors.${v}`)])),
    )
    return
  }
  try {
    if (phaseEditing.value) {
      await updatePhaseMutation.mutateAsync({
        projectId: project.value.id,
        phaseId: phaseEditing.value.id,
        payload: { ...phaseForm },
        seasonId: selectedSeasonId.value,
      })
    } else {
      await createPhaseMutation.mutateAsync({
        projectId: project.value.id,
        payload: { ...phaseForm, title: phaseForm.title.trim() },
        seasonId: selectedSeasonId.value,
      })
    }
    toast.success(t('seasonProjects.toasts.phaseSaved'))
    phaseDrawer.value = false
    await Promise.all([refetchPhases(), refetchTasks()])
  } catch (error) {
    phaseError.value = apiMessage(error)
  }
}

async function onDeletePhase(row: SeasonProjectPhase): Promise<void> {
  if (!project.value) return
  const ok = await confirm({
    title: t('seasonProjects.phases.deleteConfirmTitle'),
    message: t('seasonProjects.phases.deleteConfirmBody'),
    confirmLabel: t('seasonProjects.delete'),
    cancelLabel: t('seasonProjects.cancel'),
  })
  if (!ok) return
  try {
    await deletePhaseMutation.mutateAsync({
      projectId: project.value.id,
      phaseId: row.id,
      seasonId: selectedSeasonId.value,
    })
    toast.success(t('seasonProjects.toasts.phaseDeleted'))
    await Promise.all([refetchPhases(), refetchTasks()])
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

const budgetAmount = ref('')
const budgetCurrency = ref('SAR')
async function submitBudget(): Promise<void> {
  if (!project.value) return
  try {
    await updateBudgetMutation.mutateAsync({
      projectId: project.value.id,
      payload: {
        budget_amount: budgetAmount.value === '' ? null : budgetAmount.value,
        currency_code: budgetCurrency.value || undefined,
      },
      seasonId: selectedSeasonId.value,
    })
    toast.success(t('seasonProjects.toasts.budgetUpdated'))
    await refetchBudget()
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

const linkPoId = ref<number | ''>('')
const linkNotes = ref('')
async function submitLinkPo(): Promise<void> {
  if (!project.value || linkPoId.value === '') return
  try {
    await linkPoMutation.mutateAsync({
      projectId: project.value.id,
      purchaseOrderId: Number(linkPoId.value),
      notes: linkNotes.value.trim() || null,
      seasonId: selectedSeasonId.value,
    })
    toast.success(t('seasonProjects.toasts.poLinked'))
    linkPoId.value = ''
    linkNotes.value = ''
    await refetchBudget()
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

async function onUnlinkPo(linkId: number): Promise<void> {
  if (!project.value) return
  try {
    await unlinkPoMutation.mutateAsync({
      projectId: project.value.id,
      linkId,
      seasonId: selectedSeasonId.value,
    })
    toast.success(t('seasonProjects.toasts.poUnlinked'))
    await refetchBudget()
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

const taskTitle = ref('')
const taskPhaseId = ref<number | ''>('')
const taskPriority = ref<TaskPriority>('medium')
const taskDrawer = ref(false)

const phaseOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: '—' },
  ...phases.value.map((p) => ({ value: p.id, label: p.title })),
])

async function submitTask(): Promise<void> {
  if (!project.value || !taskTitle.value.trim()) return
  if (isTaskMutationFrozenForCompletedProject(project.value.status, 'create')) {
    toast.error(t('seasonProjects.errors.taskCreateNotAllowed'))
    return
  }
  try {
    await createTaskMutation.mutateAsync({
      title: taskTitle.value.trim(),
      priority: taskPriority.value,
      project_id: project.value.id,
      season_id: project.value.season_id,
      phase_id: taskPhaseId.value === '' ? null : Number(taskPhaseId.value),
    })
    toast.success(t('seasonProjects.toasts.taskCreated'))
    taskDrawer.value = false
    taskTitle.value = ''
    taskPhaseId.value = ''
    await refetchTasks()
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

async function onCompleteTask(task: Task): Promise<void> {
  const notes = window.prompt(t('seasonProjects.tasks.completionNotes'))
  if (!notes || !notes.trim()) return
  try {
    await completeTaskMutation.mutateAsync({ id: task.id, completionNotes: notes.trim() })
    toast.success(t('seasonProjects.toasts.taskCompleted'))
    await refetchTasks()
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

async function onCancelTask(task: Task): Promise<void> {
  const comment = window.prompt(t('seasonProjects.tasks.cancelComment'))
  if (!comment || !comment.trim()) return
  try {
    await cancelTaskMutation.mutateAsync({ id: task.id, comment: comment.trim() })
    toast.success(t('seasonProjects.toasts.taskCancelled'))
    await refetchTasks()
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

const notFound = computed(
  () =>
    projectError.value instanceof ApiError &&
    (projectError.value.status === 404 || projectError.value.status === 403),
)

function syncBudgetForm(): void {
  if (!budget.value) return
  budgetAmount.value = budget.value.budget_amount ?? ''
  budgetCurrency.value = budget.value.currency_code || 'SAR'
}

watch(
  budget,
  (value) => {
    if (value) syncBudgetForm()
  },
  { immediate: true },
)
</script>

<template>
  <div class="space-y-4">
    <AppPageHeader
      :title="project?.title ?? t('seasonProjects.title')"
      :subtitle="project ? project.number : t('seasonProjects.subtitle')"
    >
      <template #actions>
        <button
          type="button"
          class="rounded-lg border px-3 py-2 text-sm"
          @click="router.push('/app/season-projects')"
        >
          {{ t('seasonProjects.backToList') }}
        </button>
      </template>
    </AppPageHeader>

    <SeasonWorkspaceBar />

    <section
      v-if="selectedSeasonId == null"
      class="rounded-xl border border-dashed border-brand-border px-4 py-10 text-center text-brand-muted"
    >
      {{ t('seasonProjects.needSeason') }}
    </section>
    <section
      v-else-if="isLoading"
      class="rounded-xl border border-brand-border px-4 py-10 text-center"
    >
      {{ t('seasonProjects.loading') }}
    </section>
    <section
      v-else-if="notFound"
      class="rounded-xl border border-brand-border px-4 py-10 text-center text-brand-muted"
    >
      404
    </section>
    <section
      v-else-if="isError"
      class="rounded-xl border border-red-200 bg-red-50 px-4 py-10 text-center"
    >
      <p class="mb-3">{{ t('seasonProjects.errors.generic') }}</p>
      <button type="button" class="rounded-lg border px-3 py-1.5 text-sm" @click="() => refetch()">
        {{ t('seasonProjects.retry') }}
      </button>
    </section>

    <template v-else-if="project">
      <div
        v-if="project.status === 'completed'"
        class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm"
      >
        {{ t('seasonProjects.lifecycle.completedBanner') }}
      </div>
      <div
        v-else-if="project.status === 'cancelled'"
        class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm"
      >
        {{ t('seasonProjects.lifecycle.cancelledBanner') }}
      </div>
      <p class="text-xs text-brand-muted">{{ t('seasonProjects.lifecycle.noRestore') }}</p>

      <section class="rounded-xl border border-brand-border bg-brand-surface p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="space-y-1 text-sm">
            <p>
              <span class="text-brand-muted">{{ t('seasonProjects.columns.status') }}:</span>
              {{ t(`seasonProjects.status.${project.status}`) }}
            </p>
            <p>
              <span class="text-brand-muted">{{ t('seasonProjects.columns.owner') }}:</span>
              {{ project.owner_employee?.full_name ?? '—' }}
            </p>
            <p>
              <span class="text-brand-muted">{{ t('seasonProjects.columns.progress') }}:</span>
              {{ project.progress_percent }}%
            </p>
            <p>
              <span class="text-brand-muted">{{ t('seasonProjects.delay.summary') }}:</span>
              {{ project.delay_summary || t('seasonProjects.delay.none') }}
            </p>
            <p v-if="project.description" class="max-w-3xl text-brand-muted">{{ project.description }}</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <PermissionGuard permission="season_projects.update">
              <button
                v-if="canEditProjectFields(project.status) && seasonWritable"
                type="button"
                class="rounded-lg border px-3 py-1.5 text-sm"
                @click="openEdit"
              >
                {{ t('seasonProjects.edit') }}
              </button>
            </PermissionGuard>
            <PermissionGuard permission="season_projects.activate">
              <button
                v-if="canActivateProject(project.status) && seasonWritable"
                type="button"
                class="rounded-lg bg-brand-primary px-3 py-1.5 text-sm font-semibold text-white disabled:opacity-50"
                :disabled="!project.owner_employee_id || activatePending"
                @click="onActivate"
              >
                {{ t('seasonProjects.lifecycle.activate') }}
              </button>
            </PermissionGuard>
            <PermissionGuard permission="season_projects.complete">
              <button
                v-if="canCompleteProject(project.status) && seasonWritable"
                type="button"
                class="rounded-lg border border-brand-primary px-3 py-1.5 text-sm text-brand-primary"
                :disabled="completePending"
                @click="onComplete"
              >
                {{ t('seasonProjects.lifecycle.complete') }}
              </button>
            </PermissionGuard>
            <PermissionGuard permission="season_projects.cancel">
              <button
                v-if="canCancelProject(project.status) && seasonWritable"
                type="button"
                class="rounded-lg border border-red-300 px-3 py-1.5 text-sm text-red-700"
                :disabled="cancelPending"
                @click="onCancelProject"
              >
                {{ t('seasonProjects.lifecycle.cancel') }}
              </button>
            </PermissionGuard>
            <PermissionGuard permission="season_projects.update">
              <button
                v-if="canSoftDeleteProject(project.status) && seasonWritable"
                type="button"
                class="rounded-lg border border-red-200 px-3 py-1.5 text-sm text-red-600"
                @click="onDeleteDraft"
              >
                {{ t('seasonProjects.delete') }}
              </button>
            </PermissionGuard>
          </div>
        </div>

        <PermissionGuard permission="season_projects.reassign_owner">
          <div
            v-if="canReassignOwner(project.status) && seasonWritable"
            class="mt-4 flex flex-wrap items-end gap-2 border-t border-brand-border pt-3"
          >
            <label class="block min-w-[220px] flex-1 text-sm">
              <span class="mb-1 block">{{ t('seasonProjects.lifecycle.reassign') }}</span>
              <AppRemoteSelect
                v-model="reassignId"
                :query-key="['season-project-reassign-owner']"
                :fetcher="fetchActiveEmployees"
                :map-option="(row: Employee) => employeeSelectOption(row)"
              />
            </label>
            <button
              type="button"
              class="rounded-lg bg-brand-primary px-3 py-2 text-sm text-white disabled:opacity-50"
              :disabled="reassignId === '' || reassignPending"
              @click="onReassign"
            >
              {{ t('seasonProjects.lifecycle.reassign') }}
            </button>
          </div>
        </PermissionGuard>
      </section>

      <PermissionGuard permission="season_projects.view">
        <SeasonProjectProgressBaselinePanel
          :project="project"
          :season-id="selectedSeasonId"
          :season-writable="seasonWritable"
        />
      </PermissionGuard>

      <!-- Phases -->
      <section class="rounded-xl border border-brand-border bg-brand-surface p-4">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
          <div>
            <h2 class="text-base font-semibold">{{ t('seasonProjects.phases.title') }}</h2>
            <p class="text-sm text-brand-muted">{{ t('seasonProjects.phases.subtitle') }}</p>
          </div>
          <PermissionGuard permission="season_projects.manage_phases">
            <button
              v-if="!isProjectTerminal(project.status) && seasonWritable"
              type="button"
              class="rounded-lg border px-3 py-1.5 text-sm"
              @click="openPhaseCreate"
            >
              {{ t('seasonProjects.phases.add') }}
            </button>
          </PermissionGuard>
        </div>
        <p class="mb-2 text-xs text-brand-muted">{{ t('seasonProjects.phases.deleteHint') }}</p>
        <p v-if="!phases.length" class="text-sm text-brand-muted">{{ t('seasonProjects.phases.empty') }}</p>
        <ul v-else class="space-y-2">
          <li
            v-for="phase in phases"
            :key="phase.id"
            class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-brand-border/70 px-3 py-2 text-sm"
          >
            <div>
              <p class="font-medium">{{ phase.title }}</p>
              <p class="text-xs text-brand-muted">
                {{ t(`seasonProjects.phaseStatus.${phase.status as SeasonProjectPhaseStatus}`) }}
                · #{{ phase.sort_order }}
                <span v-if="phase.starts_on"> · {{ phase.starts_on }}</span>
                <span v-if="phase.ends_on"> → {{ phase.ends_on }}</span>
              </p>
            </div>
            <PermissionGuard permission="season_projects.manage_phases">
              <div v-if="!isProjectTerminal(project.status) && seasonWritable" class="flex gap-2">
                <button type="button" class="underline" @click="openPhaseEdit(phase)">
                  {{ t('seasonProjects.edit') }}
                </button>
                <button type="button" class="text-red-600 underline" @click="onDeletePhase(phase)">
                  {{ t('seasonProjects.delete') }}
                </button>
              </div>
            </PermissionGuard>
          </li>
        </ul>
      </section>

      <!-- Tasks -->
      <section class="rounded-xl border border-brand-border bg-brand-surface p-4">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
          <div>
            <h2 class="text-base font-semibold">{{ t('seasonProjects.tasks.title') }}</h2>
            <p class="text-sm text-brand-muted">
              {{ t('seasonProjects.tasks.subtitle') }}
              · {{ t('seasonProjects.tasks.openTasks', { count: openTasksCount }) }}
            </p>
          </div>
          <PermissionGuard permission="tasks.create">
            <button
              v-if="project.status === 'active' && seasonWritable"
              type="button"
              class="rounded-lg border px-3 py-1.5 text-sm"
              @click="taskDrawer = true"
            >
              {{ t('seasonProjects.tasks.add') }}
            </button>
          </PermissionGuard>
        </div>
        <p
          v-if="project.status === 'completed'"
          class="mb-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900"
        >
          {{ t('seasonProjects.tasks.frozenHint') }}
        </p>
        <p v-if="!tasks.length" class="text-sm text-brand-muted">{{ t('seasonProjects.tasks.empty') }}</p>
        <ul v-else class="space-y-2">
          <li
            v-for="task in tasks"
            :key="task.id"
            class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-brand-border/70 px-3 py-2 text-sm"
          >
            <div>
              <p class="font-medium">
                {{ task.task_number }} — {{ task.title }}
                <span
                  v-if="task.delay_state === 'delayed'"
                  class="ms-2 rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-700"
                >
                  {{ t('seasonProjects.tasks.delayBadge') }}
                </span>
              </p>
              <p class="text-xs text-brand-muted">{{ task.status }} · {{ task.priority }}</p>
            </div>
            <div class="flex gap-2">
              <PermissionGuard permission="tasks.complete">
                <button
                  v-if="!['completed', 'cancelled'].includes(task.status)"
                  type="button"
                  class="underline"
                  @click="onCompleteTask(task)"
                >
                  {{ t('seasonProjects.tasks.complete') }}
                </button>
              </PermissionGuard>
              <PermissionGuard permission="tasks.cancel">
                <button
                  v-if="!['completed', 'cancelled'].includes(task.status)"
                  type="button"
                  class="text-red-600 underline"
                  @click="onCancelTask(task)"
                >
                  {{ t('seasonProjects.tasks.cancelTask') }}
                </button>
              </PermissionGuard>
            </div>
          </li>
        </ul>
      </section>

      <!-- Budget -->
      <section class="rounded-xl border border-brand-border bg-brand-surface p-4">
        <h2 class="text-base font-semibold">{{ t('seasonProjects.budget.title') }}</h2>
        <p class="mb-3 text-sm text-brand-muted">{{ t('seasonProjects.budget.subtitle') }}</p>

        <template v-if="budget">
          <div
            v-if="!budget.financials.available"
            class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm"
          >
            <p class="font-medium">{{ t('seasonProjects.budget.unavailable') }}</p>
            <p v-if="budget.financials.unavailable_reason" class="text-xs">
              {{
                t('seasonProjects.budget.unavailableReason', {
                  reason: budget.financials.unavailable_reason,
                })
              }}
            </p>
          </div>
          <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 text-sm">
            <div>
              <p class="text-brand-muted">{{ t('seasonProjects.columns.budget') }}</p>
              <p class="font-semibold">
                {{ formatMoneyDisplay(budget.budget_amount) }} {{ budget.currency_code }}
              </p>
            </div>
            <div>
              <p class="text-brand-muted">{{ t('seasonProjects.budget.outstanding') }}</p>
              <p class="font-semibold">
                {{
                  formatMoneyDisplay(
                    budget.financials.outstanding_commitment,
                    budget.financials.available,
                  )
                }}
              </p>
            </div>
            <div>
              <p class="text-brand-muted">{{ t('seasonProjects.budget.actual') }}</p>
              <p class="font-semibold">
                {{
                  formatMoneyDisplay(budget.financials.actual_spending, budget.financials.available)
                }}
              </p>
            </div>
            <div>
              <p class="text-brand-muted">{{ t('seasonProjects.budget.remaining') }}</p>
              <p class="font-semibold">
                {{ formatMoneyDisplay(budget.financials.remaining, budget.financials.available) }}
              </p>
            </div>
          </div>
          <p class="mt-2 text-xs text-brand-muted">
            {{ t('seasonProjects.budget.valuationBasis') }}: {{ budget.financials.valuation_basis }}
          </p>

          <PermissionGuard permission="season_projects.manage_budget">
            <div
              v-if="canManageBudgetLifecycle(project.status) && seasonWritable"
              class="mt-4 flex flex-wrap items-end gap-2 border-t border-brand-border pt-3"
            >
              <label class="text-sm">
                <span class="mb-1 block">{{ t('seasonProjects.fields.budgetAmount') }}</span>
                <input
                  v-model="budgetAmount"
                  class="rounded-lg border border-brand-border px-3 py-2"
                >
              </label>
              <label class="text-sm">
                <span class="mb-1 block">{{ t('seasonProjects.fields.currencyCode') }}</span>
                <input
                  v-model="budgetCurrency"
                  class="w-24 rounded-lg border border-brand-border px-3 py-2"
                >
              </label>
              <button
                type="button"
                class="rounded-lg bg-brand-primary px-3 py-2 text-sm text-white disabled:opacity-50"
                :disabled="budgetPending"
                @click="submitBudget"
              >
                {{ t('seasonProjects.budget.edit') }}
              </button>
            </div>
          </PermissionGuard>

          <div class="mt-4 border-t border-brand-border pt-3">
            <h3 class="mb-1 font-medium">{{ t('seasonProjects.budget.poLinks') }}</h3>
            <p class="mb-2 text-xs text-brand-muted">{{ t('seasonProjects.budget.crossSeasonHint') }}</p>
            <PermissionGuard permission="season_projects.link_po">
              <div
                v-if="canLinkPoLifecycle(project.status) && seasonWritable"
                class="mb-3 flex flex-wrap items-end gap-2"
              >
                <label class="min-w-[220px] flex-1 text-sm">
                  <span class="mb-1 block">{{ t('seasonProjects.fields.purchaseOrder') }}</span>
                  <AppRemoteSelect
                    v-model="linkPoId"
                    :query-key="['season-project-link-po']"
                    :fetcher="fetchPurchaseOrders"
                    :map-option="mapPoOption"
                  />
                </label>
                <label class="min-w-[180px] flex-1 text-sm">
                  <span class="mb-1 block">{{ t('seasonProjects.fields.linkNotes') }}</span>
                  <input
                    v-model="linkNotes"
                    class="w-full rounded-lg border border-brand-border px-3 py-2"
                  >
                </label>
                <button
                  type="button"
                  class="rounded-lg bg-brand-primary px-3 py-2 text-sm text-white disabled:opacity-50"
                  :disabled="linkPoId === '' || linkPoPending"
                  @click="submitLinkPo"
                >
                  {{ t('seasonProjects.budget.linkPo') }}
                </button>
              </div>
            </PermissionGuard>

            <p v-if="!budget.po_links.length" class="text-sm text-brand-muted">
              {{ t('seasonProjects.budget.emptyLinks') }}
            </p>
            <ul v-else class="space-y-2">
              <li
                v-for="link in budget.po_links"
                :key="link.id"
                class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-brand-border/70 px-3 py-2 text-sm"
              >
                <div>
                  <p class="font-medium">{{ link.po_number }} · {{ link.po_status }}</p>
                  <p class="text-xs text-brand-muted">
                    {{ t('seasonProjects.budget.poSeason') }}: {{ link.po_season_id ?? '—' }}
                    · {{ t('seasonProjects.budget.ordered') }}:
                    {{ formatMoneyDisplay(link.ordered_amount, budget.financials.available) }}
                    {{ link.currency_code }}
                  </p>
                </div>
                <PermissionGuard permission="season_projects.link_po">
                  <button
                    v-if="canLinkPoLifecycle(project.status) && seasonWritable"
                    type="button"
                    class="text-red-600 underline"
                    @click="onUnlinkPo(link.id)"
                  >
                    {{ t('seasonProjects.budget.unlink') }}
                  </button>
                </PermissionGuard>
              </li>
            </ul>
          </div>
        </template>
      </section>
    </template>

    <!-- Edit drawer -->
    <div
      v-if="editOpen"
      class="fixed inset-0 z-40 flex justify-end bg-black/30"
      role="dialog"
      aria-modal="true"
      @click.self="editOpen = false"
    >
      <div class="flex h-full w-full max-w-md flex-col bg-white shadow-xl">
        <header class="border-b px-4 py-3">
          <h2 class="text-lg font-semibold">{{ t('seasonProjects.editTitle') }}</h2>
        </header>
        <form class="flex flex-1 flex-col gap-3 overflow-y-auto p-4" @submit.prevent="submitEdit">
          <label class="block text-sm">
            <span class="mb-1 block">{{ t('seasonProjects.fields.title') }}</span>
            <input v-model="editForm.title" class="w-full rounded-lg border px-3 py-2">
            <span v-if="editFieldErrors.title" class="text-xs text-red-600">{{ editFieldErrors.title }}</span>
          </label>
          <label class="block text-sm">
            <span class="mb-1 block">{{ t('seasonProjects.fields.description') }}</span>
            <textarea v-model="editForm.description" rows="3" class="w-full rounded-lg border px-3 py-2" />
          </label>
          <label class="block text-sm">
            <span class="mb-1 block">{{ t('seasonProjects.fields.owner') }}</span>
            <AppRemoteSelect
              v-model="editForm.owner_employee_id"
              :query-key="['season-project-edit-owner']"
              :fetcher="fetchActiveEmployees"
              :map-option="(row: Employee) => employeeSelectOption(row)"
              :selected-option="
                project?.owner_employee ? employeeSelectOption(project.owner_employee) : null
              "
              clearable
            />
          </label>
          <p v-if="editError" class="rounded bg-red-50 px-3 py-2 text-sm text-red-700">{{ editError }}</p>
          <div class="mt-auto flex gap-2 border-t pt-3">
            <button type="button" class="rounded-lg border px-3 py-2 text-sm" @click="editOpen = false">
              {{ t('seasonProjects.cancel') }}
            </button>
            <button type="submit" class="rounded-lg bg-brand-primary px-3 py-2 text-sm text-white">
              {{ t('seasonProjects.editCta') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Phase drawer -->
    <div
      v-if="phaseDrawer"
      class="fixed inset-0 z-40 flex justify-end bg-black/30"
      role="dialog"
      aria-modal="true"
      @click.self="phaseDrawer = false"
    >
      <div class="flex h-full w-full max-w-md flex-col bg-white shadow-xl">
        <header class="border-b px-4 py-3">
          <h2 class="text-lg font-semibold">{{ t('seasonProjects.phases.add') }}</h2>
        </header>
        <form class="flex flex-1 flex-col gap-3 overflow-y-auto p-4" @submit.prevent="submitPhase">
          <label class="block text-sm">
            <span class="mb-1 block">{{ t('seasonProjects.fields.phaseTitle') }}</span>
            <input v-model="phaseForm.title" class="w-full rounded-lg border px-3 py-2">
            <span v-if="phaseFieldErrors.title" class="text-xs text-red-600">{{ phaseFieldErrors.title }}</span>
          </label>
          <label class="block text-sm">
            <span class="mb-1 block">{{ t('seasonProjects.fields.sortOrder') }}</span>
            <input v-model="phaseForm.sort_order" type="number" class="w-full rounded-lg border px-3 py-2">
          </label>
          <label class="block text-sm">
            <span class="mb-1 block">{{ t('seasonProjects.fields.startsOn') }}</span>
            <input v-model="phaseForm.starts_on" type="date" class="w-full rounded-lg border px-3 py-2">
          </label>
          <label class="block text-sm">
            <span class="mb-1 block">{{ t('seasonProjects.fields.endsOn') }}</span>
            <input v-model="phaseForm.ends_on" type="date" class="w-full rounded-lg border px-3 py-2">
            <span v-if="phaseFieldErrors.ends_on" class="text-xs text-red-600">{{ phaseFieldErrors.ends_on }}</span>
          </label>
          <label class="block text-sm">
            <span class="mb-1 block">{{ t('seasonProjects.fields.phaseStatus') }}</span>
            <AppSelect v-model="phaseForm.status" :options="phaseStatusOptions" />
          </label>
          <p v-if="phaseError" class="rounded bg-red-50 px-3 py-2 text-sm text-red-700">{{ phaseError }}</p>
          <div class="mt-auto flex gap-2 border-t pt-3">
            <button type="button" class="rounded-lg border px-3 py-2 text-sm" @click="phaseDrawer = false">
              {{ t('seasonProjects.cancel') }}
            </button>
            <button type="submit" class="rounded-lg bg-brand-primary px-3 py-2 text-sm text-white">
              {{ t('seasonProjects.save') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Task drawer -->
    <div
      v-if="taskDrawer"
      class="fixed inset-0 z-40 flex justify-end bg-black/30"
      role="dialog"
      aria-modal="true"
      @click.self="taskDrawer = false"
    >
      <div class="flex h-full w-full max-w-md flex-col bg-white shadow-xl">
        <header class="border-b px-4 py-3">
          <h2 class="text-lg font-semibold">{{ t('seasonProjects.tasks.add') }}</h2>
        </header>
        <form class="flex flex-1 flex-col gap-3 overflow-y-auto p-4" @submit.prevent="submitTask">
          <label class="block text-sm">
            <span class="mb-1 block">{{ t('seasonProjects.fields.taskTitle') }}</span>
            <input v-model="taskTitle" required class="w-full rounded-lg border px-3 py-2">
          </label>
          <label class="block text-sm">
            <span class="mb-1 block">{{ t('seasonProjects.fields.taskPhase') }}</span>
            <AppSelect v-model="taskPhaseId" :options="phaseOptions" />
          </label>
          <label class="block text-sm">
            <span class="mb-1 block">{{ t('seasonProjects.fields.taskPriority') }}</span>
            <select v-model="taskPriority" class="w-full rounded-lg border px-3 py-2">
              <option value="low">low</option>
              <option value="medium">medium</option>
              <option value="high">high</option>
            </select>
          </label>
          <div class="mt-auto flex gap-2 border-t pt-3">
            <button type="button" class="rounded-lg border px-3 py-2 text-sm" @click="taskDrawer = false">
              {{ t('seasonProjects.cancel') }}
            </button>
            <button type="submit" class="rounded-lg bg-brand-primary px-3 py-2 text-sm text-white">
              {{ t('seasonProjects.save') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
