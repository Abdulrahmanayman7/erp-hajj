<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import {
  ArrowRight,
  Building2,
  CalendarRange,
  FileText,
  Link2,
  Pencil,
  Plus,
  UserRound,
} from 'lucide-vue-next'

import EntityDocumentsSection from '@/modules/documents/components/EntityDocumentsSection.vue'
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
import {
  decisionStatusBadgeClass,
  decisionStatusDotClass,
  validateDecisionForm,
} from '../validation/decisionValidation'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { can } = usePermissions()
const toast = useToast()

const id = computed(() => {
  const raw = route.params.id
  const value = Number(Array.isArray(raw) ? raw[0] : raw)
  return Number.isFinite(value) ? value : null
})

const { data, isLoading, isError, refetch } = useDecisionQuery(id)
const decision = computed(() => data.value ?? null)

const { data: orgs } = useOrganizationUnitsFlatQuery({ status: 'active' })

const orgOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('decisions.noOrgUnit') },
  ...(orgs.value?.data ?? []).map((x) => ({ value: x.id, label: x.name, hint: x.code })),
])

const update = useUpdateDecisionMutation()
const isFormSubmitting = computed(() => update.isPending.value)

const open = ref(false)
const error = ref('')
const errors = reactive<Record<string, string>>({})
const form = reactive<DecisionFormState>({
  title: '',
  body: '',
  notes: '',
  organization_unit_id: '',
  issued_by_employee_id: '',
  responsible_employee_id: '',
  effective_date: '',
  due_date: '',
})

const canEdit = computed(
  () => decision.value?.status === 'draft' && can('decisions.update'),
)

function edit(): void {
  if (!decision.value || !canEdit.value) return
  Object.assign(form, {
    title: decision.value.title,
    body: decision.value.body,
    notes: decision.value.notes ?? '',
    organization_unit_id: decision.value.organization_unit_id ?? '',
    issued_by_employee_id: decision.value.issued_by_employee_id ?? '',
    responsible_employee_id: decision.value.responsible_employee_id ?? '',
    effective_date: decision.value.effective_date ?? '',
    due_date: decision.value.due_date ?? '',
  })
  error.value = ''
  Object.keys(errors).forEach((k) => delete errors[k])
  open.value = true
}

async function save(): Promise<void> {
  Object.keys(errors).forEach((x) => delete errors[x])
  Object.assign(errors, validateDecisionForm(form))
  if (Object.keys(errors).length || !decision.value) return

  try {
    await update.mutateAsync({
      id: decision.value.id,
      payload: {
        title: form.title.trim(),
        body: form.body.trim(),
        notes: form.notes.trim() || null,
        organization_unit_id: form.organization_unit_id || null,
        issued_by_employee_id: form.issued_by_employee_id || null,
        responsible_employee_id: form.responsible_employee_id || null,
        effective_date: form.effective_date || null,
        due_date: form.due_date || null,
      },
    })
    toast.success(t('decisions.toasts.updated'))
    open.value = false
    await refetch()
  } catch {
    error.value = t('decisions.errors.generic')
  }
}

function assignForm(next: DecisionFormState): void {
  Object.assign(form, next)
}

async function onRefreshed(): Promise<void> {
  await refetch()
}

const tasksParams = computed(() => ({
  decision_id: id.value ?? undefined,
  per_page: 50,
}))
const { data: linkedTasksData } = useTasksQuery(tasksParams)
const linkedTasks = computed(() => linkedTasksData.value?.data ?? [])
const tasksSummary = computed(
  () => decision.value?.tasks_summary ?? { total: 0, open: 0, completed: 0, cancelled: 0 },
)
const canCreateTask = computed(
  () => decision.value?.status === 'approved' && can('tasks.create'),
)

const createTask = useCreateTaskMutation()
const taskDrawerOpen = ref(false)
const taskError = ref('')
const taskFieldErrors = reactive<Record<string, string>>({})
const emptyTaskForm: TaskFormState = {
  title: '',
  description: '',
  notes: '',
  priority: 'medium',
  decision_id: '',
  organization_unit_id: '',
  assigned_to_employee_id: '',
  start_date: '',
  due_date: '',
}
const taskForm = reactive<TaskFormState>({ ...emptyTaskForm })

function openCreateTask(): void {
  if (!decision.value) return
  Object.assign(taskForm, {
    ...emptyTaskForm,
    decision_id: decision.value.id,
    organization_unit_id: decision.value.organization_unit_id ?? '',
  })
  taskError.value = ''
  Object.keys(taskFieldErrors).forEach((k) => delete taskFieldErrors[k])
  taskDrawerOpen.value = true
}

async function saveTask(): Promise<void> {
  Object.keys(taskFieldErrors).forEach((x) => delete taskFieldErrors[x])
  Object.assign(taskFieldErrors, validateTaskForm(taskForm))
  if (Object.keys(taskFieldErrors).length || !decision.value) return

  try {
    const created = await createTask.mutateAsync({
      title: taskForm.title.trim(),
      description: taskForm.description.trim() || null,
      notes: taskForm.notes.trim() || null,
      priority: taskForm.priority,
      decision_id: decision.value.id,
      organization_unit_id: taskForm.organization_unit_id || null,
      assigned_to_employee_id: taskForm.assigned_to_employee_id || null,
      start_date: taskForm.start_date || null,
      due_date: taskForm.due_date || null,
    })
    taskDrawerOpen.value = false
    await router.push(`/app/tasks/${created.id}`)
  } catch {
    taskError.value = t('decisions.errors.generic')
  }
}
</script>

<template>
  <div class="mx-auto max-w-[1200px] space-y-5">
    <div class="flex flex-wrap items-center gap-3">
      <button
        type="button"
        class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-brand-border bg-brand-surface px-3 text-sm font-semibold text-brand-text transition hover:bg-brand-bg"
        @click="router.push('/app/decisions')"
      >
        <ArrowRight class="h-4 w-4" />
        {{ t('decisions.backToList') }}
      </button>
    </div>

    <div
      v-if="isLoading"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('decisions.loadingDetails') }}
    </div>

    <div
      v-else-if="isError || !decision"
      class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center"
    >
      <p class="text-sm text-red-700">{{ t('decisions.errors.loadDetails') }}</p>
      <button
        type="button"
        class="mt-3 text-sm font-semibold text-brand-primary-dark underline"
        @click="() => refetch()"
      >
        {{ t('decisions.retry') }}
      </button>
    </div>

    <template v-else>
      <section class="rounded-2xl border border-brand-border bg-brand-surface px-5 py-5 sm:px-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <p
                class="font-mono text-xs font-semibold tracking-wide text-brand-text-muted"
                dir="ltr"
              >
                {{ decision.decision_number }}
              </p>
              <span
                class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold tracking-wide"
                :class="decisionStatusBadgeClass(decision.status)"
              >
                <span
                  class="h-1.5 w-1.5 shrink-0 rounded-full"
                  :class="decisionStatusDotClass(decision.status)"
                  aria-hidden="true"
                />
                {{ t(`decisions.status.${decision.status}`) }}
              </span>
            </div>
            <h2 class="mt-2 text-[1.65rem] font-bold leading-snug text-brand-text sm:text-[1.85rem]">
              {{ decision.title }}
            </h2>
            <p class="mt-2 text-sm text-brand-text-secondary">
              <span class="font-semibold text-brand-text">
                {{ decision.responsible_employee?.full_name ?? t('decisions.noEmployee') }}
              </span>
              <span class="mx-1.5 text-brand-text-muted">·</span>
              {{ decision.organization_unit?.name ?? t('decisions.noOrgUnit') }}
            </p>
          </div>

          <PermissionGuard v-if="canEdit" permission="decisions.update">
            <button
              type="button"
              class="inline-flex h-10 items-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-4 text-sm font-semibold text-brand-primary-dark transition hover:bg-brand-primary-soft"
              @click="edit"
            >
              <Pencil class="h-4 w-4" />
              {{ t('decisions.actions.edit') }}
            </button>
          </PermissionGuard>
        </div>
      </section>

      <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
        <div class="space-y-5">
          <section class="rounded-2xl border border-brand-border bg-brand-surface">
            <header class="border-b border-brand-border px-5 py-4">
              <h3 class="text-sm font-bold text-brand-text">{{ t('decisions.detailsSummary') }}</h3>
            </header>
            <dl class="grid gap-0 sm:grid-cols-2">
              <div class="flex gap-3 border-b border-brand-border/80 px-5 py-4 sm:border-e">
                <span
                  class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary"
                >
                  <CalendarRange class="h-4 w-4" :stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                  <dt class="text-xs font-semibold text-brand-text-muted">
                    {{ t('decisions.fields.effectiveDate') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text" dir="ltr">
                    {{ decision.effective_date ?? '—' }}
                  </dd>
                </div>
              </div>

              <div class="flex gap-3 border-b border-brand-border/80 px-5 py-4">
                <span
                  class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary"
                >
                  <CalendarRange class="h-4 w-4" :stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                  <dt class="text-xs font-semibold text-brand-text-muted">
                    {{ t('decisions.fields.dueDate') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text" dir="ltr">
                    {{ decision.due_date ?? '—' }}
                  </dd>
                </div>
              </div>

              <div class="flex gap-3 border-b border-brand-border/80 px-5 py-4 sm:border-e">
                <span
                  class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary"
                >
                  <Building2 class="h-4 w-4" :stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                  <dt class="text-xs font-semibold text-brand-text-muted">
                    {{ t('decisions.fields.organizationUnit') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text">
                    {{ decision.organization_unit?.name ?? '—' }}
                  </dd>
                </div>
              </div>

              <div class="flex gap-3 border-b border-brand-border/80 px-5 py-4">
                <span
                  class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary"
                >
                  <UserRound class="h-4 w-4" :stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                  <dt class="text-xs font-semibold text-brand-text-muted">
                    {{ t('decisions.fields.issuedBy') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text">
                    {{ decision.issued_by_employee?.full_name ?? '—' }}
                  </dd>
                </div>
              </div>

              <div class="flex gap-3 border-b border-brand-border/80 px-5 py-4 sm:border-e sm:border-b-0">
                <span
                  class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary"
                >
                  <UserRound class="h-4 w-4" :stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                  <dt class="text-xs font-semibold text-brand-text-muted">
                    {{ t('decisions.fields.responsible') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text">
                    {{ decision.responsible_employee?.full_name ?? '—' }}
                  </dd>
                </div>
              </div>

              <div class="flex gap-3 px-5 py-4">
                <span
                  class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary"
                >
                  <Link2 class="h-4 w-4" :stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                  <dt class="text-xs font-semibold text-brand-text-muted">
                    {{ t('decisions.sections.source') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text">
                    {{
                      decision.source_recommendation?.title ?? t('decisions.standaloneSource')
                    }}
                  </dd>
                  <dd v-if="decision.source_meeting" class="mt-1">
                    <RouterLink
                      :to="`/app/meetings/${decision.source_meeting.id}`"
                      class="text-xs font-semibold text-brand-primary-dark hover:underline"
                    >
                      {{ decision.source_meeting.meeting_number }} —
                      {{ decision.source_meeting.title }}
                    </RouterLink>
                  </dd>
                </div>
              </div>
            </dl>
          </section>

          <section class="rounded-2xl border border-brand-border bg-brand-surface">
            <header class="flex items-center gap-2 border-b border-brand-border px-5 py-4">
              <FileText class="h-4 w-4 text-brand-primary" :stroke-width="1.75" />
              <h3 class="text-sm font-bold text-brand-text">{{ t('decisions.fields.body') }}</h3>
            </header>
            <p class="whitespace-pre-wrap px-5 py-4 text-sm leading-relaxed text-brand-text">
              {{ decision.body }}
            </p>
          </section>

          <section
            v-if="decision.notes"
            class="rounded-2xl border border-brand-border bg-brand-surface"
          >
            <header class="flex items-center gap-2 border-b border-brand-border px-5 py-4">
              <FileText class="h-4 w-4 text-brand-primary" :stroke-width="1.75" />
              <h3 class="text-sm font-bold text-brand-text">{{ t('decisions.fields.notes') }}</h3>
            </header>
            <p class="whitespace-pre-wrap px-5 py-4 text-sm leading-relaxed text-brand-text-secondary">
              {{ decision.notes }}
            </p>
          </section>

          <DecisionTimeline :transitions="decision.status_transitions ?? []" />

          <section class="rounded-2xl border border-brand-border bg-brand-surface">
            <header
              class="flex flex-wrap items-center justify-between gap-3 border-b border-brand-border px-5 py-4"
            >
              <h3 class="text-sm font-bold text-brand-text">
                {{ t('decisions.tasksSection.title') }}
              </h3>
              <PermissionGuard v-if="canCreateTask" permission="tasks.create">
                <button
                  type="button"
                  class="inline-flex h-9 items-center gap-2 rounded-xl bg-brand-primary-dark px-3.5 text-sm font-semibold text-white transition hover:bg-brand-primary"
                  @click="openCreateTask"
                >
                  <Plus class="h-4 w-4" :stroke-width="2.25" />
                  {{ t('decisions.tasksSection.createTask') }}
                </button>
              </PermissionGuard>
            </header>

            <div class="flex flex-wrap gap-2 px-5 py-4">
              <span
                class="inline-flex rounded-full bg-brand-bg px-3 py-1 text-xs font-semibold text-brand-text"
              >
                {{ t('decisions.tasksSection.total', { count: tasksSummary.total }) }}
              </span>
              <span
                class="inline-flex rounded-full bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-900 ring-1 ring-inset ring-sky-200/80"
              >
                {{ t('decisions.tasksSection.open', { count: tasksSummary.open }) }}
              </span>
              <span
                class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-800 ring-1 ring-inset ring-emerald-200/80"
              >
                {{ t('decisions.tasksSection.completed', { count: tasksSummary.completed }) }}
              </span>
              <span
                class="inline-flex rounded-full bg-red-50 px-3 py-1 text-xs font-semibold text-red-800 ring-1 ring-inset ring-red-200/80"
              >
                {{ t('decisions.tasksSection.cancelled', { count: tasksSummary.cancelled }) }}
              </span>
            </div>

            <p
              v-if="!linkedTasks.length"
              class="border-t border-brand-border px-5 py-8 text-center text-sm text-brand-text-muted"
            >
              {{ t('decisions.tasksSection.empty') }}
            </p>
            <div v-else class="overflow-x-auto border-t border-brand-border">
              <table class="min-w-full border-separate border-spacing-0 text-sm">
                <thead>
                  <tr class="bg-[#F4F6F5]">
                    <th
                      class="whitespace-nowrap border-b border-brand-border px-5 py-3 text-start text-xs font-bold text-brand-text"
                    >
                      {{ t('tasks.columns.number') }}
                    </th>
                    <th
                      class="whitespace-nowrap border-b border-brand-border px-5 py-3 text-start text-xs font-bold text-brand-text"
                    >
                      {{ t('tasks.columns.title') }}
                    </th>
                    <th
                      class="whitespace-nowrap border-b border-brand-border px-5 py-3 text-center text-xs font-bold text-brand-text"
                    >
                      {{ t('tasks.columns.assignee') }}
                    </th>
                    <th
                      class="whitespace-nowrap border-b border-brand-border px-5 py-3 text-center text-xs font-bold text-brand-text"
                    >
                      {{ t('tasks.columns.status') }}
                    </th>
                    <th
                      class="whitespace-nowrap border-b border-brand-border px-5 py-3 text-center text-xs font-bold text-brand-text"
                    >
                      {{ t('tasks.columns.dueDate') }}
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="(taskItem, index) in linkedTasks"
                    :key="taskItem.id"
                    class="group"
                    :class="index % 2 === 1 ? 'bg-[#FAFBFA]' : 'bg-brand-surface'"
                  >
                    <td
                      class="whitespace-nowrap border-b border-brand-border/80 px-5 py-3 transition-colors group-hover:bg-[#EDF6F1]"
                    >
                      <RouterLink
                        :to="`/app/tasks/${taskItem.id}`"
                        class="inline-flex items-center rounded-lg border border-brand-border bg-brand-bg px-2.5 py-1 font-mono text-[12px] font-bold tracking-wide text-brand-primary-dark transition hover:bg-brand-primary-soft hover:text-brand-primary"
                        dir="ltr"
                      >
                        {{ taskItem.task_number }}
                      </RouterLink>
                    </td>
                    <td
                      class="border-b border-brand-border/80 px-5 py-3 font-semibold text-brand-text transition-colors group-hover:bg-[#EDF6F1]"
                    >
                      <RouterLink
                        :to="`/app/tasks/${taskItem.id}`"
                        class="transition hover:text-brand-primary-dark hover:underline hover:underline-offset-2"
                      >
                        {{ taskItem.title }}
                      </RouterLink>
                    </td>
                    <td
                      class="border-b border-brand-border/80 px-5 py-3 text-center text-brand-text transition-colors group-hover:bg-[#EDF6F1]"
                    >
                      {{ taskItem.assigned_to_employee?.full_name ?? '—' }}
                    </td>
                    <td
                      class="border-b border-brand-border/80 px-5 py-3 text-center transition-colors group-hover:bg-[#EDF6F1]"
                    >
                      <div class="flex flex-wrap items-center justify-center gap-1.5">
                        <span
                          class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-bold"
                          :class="taskStatusBadgeClass(taskItem.status)"
                        >
                          {{ t(`tasks.status.${taskItem.status}`) }}
                        </span>
                        <span
                          v-if="taskItem.is_overdue"
                          class="inline-flex rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-semibold text-red-800 ring-1 ring-inset ring-red-200/80"
                        >
                          {{ t('tasks.overdueBadge') }}
                        </span>
                      </div>
                    </td>
                    <td
                      class="border-b border-brand-border/80 px-5 py-3 text-center text-brand-text transition-colors group-hover:bg-[#EDF6F1]"
                      dir="ltr"
                    >
                      {{ taskItem.due_date ?? '—' }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>

          <EntityDocumentsSection
            linkable-type="decision"
            :linkable-id="decision.id"
            :link-label="`${decision.decision_number} — ${decision.title}`"
          />
        </div>

        <aside class="space-y-5 xl:sticky xl:top-4 xl:self-start">
          <section class="rounded-2xl border border-brand-border bg-brand-surface">
            <header class="border-b border-brand-border px-5 py-4">
              <h3 class="text-sm font-bold text-brand-text">{{ t('decisions.lifecycleTitle') }}</h3>
              <p class="mt-1 text-xs text-brand-text-secondary">
                {{ t('decisions.lifecycleHint') }}
              </p>
            </header>
            <div class="px-4 py-4">
              <DecisionLifecycleActions :decision="decision" @refreshed="onRefreshed" />
            </div>
          </section>
        </aside>
      </div>
    </template>

    <DecisionFormDrawer
      :open="open"
      :editing="decision"
      :form="form"
      :form-error="error"
      :field-errors="errors"
      :submitting="isFormSubmitting"
      :org-unit-options="orgOptions"
      @close="open = false"
      @submit="save"
      @update:form="assignForm"
    />

    <TaskFormDrawer
      :open="taskDrawerOpen"
      :editing="null"
      :form="taskForm"
      :form-error="taskError"
      :field-errors="taskFieldErrors"
      :submitting="createTask.isPending.value"
      :org-unit-options="orgOptions"
      :locked-decision-id="decision?.id ?? null"
      :locked-decision-label="decision ? `${decision.decision_number} — ${decision.title}` : ''"
      @close="taskDrawerOpen = false"
      @submit="saveTask"
      @update:form="Object.assign(taskForm, $event)"
    />
  </div>
</template>
