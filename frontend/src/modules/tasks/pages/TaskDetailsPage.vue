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
  Percent,
  Tag,
  UserRound,
} from 'lucide-vue-next'

import { useCurrentUserQuery } from '@/modules/auth/queries/useCurrentUserQuery'
import EntityDocumentsSection from '@/modules/documents/components/EntityDocumentsSection.vue'
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
import {
  taskPriorityBadgeClass,
  taskStatusBadgeClass,
  taskStatusDotClass,
  validateTaskForm,
} from '../validation/taskValidation'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { can } = usePermissions()
const toast = useToast()
const { data: currentUser } = useCurrentUserQuery()
const linkedEmployeeId = computed(() => currentUser.value?.employee_id ?? null)

const id = computed(() => {
  const raw = route.params.id
  const value = Number(Array.isArray(raw) ? raw[0] : raw)
  return Number.isFinite(value) ? value : null
})

const { data, isLoading, isError, refetch } = useTaskQuery(id)
const task = computed(() => data.value ?? null)

const { data: orgs } = useOrganizationUnitsFlatQuery({ status: 'active' })

const orgOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('tasks.noOrgUnit') },
  ...(orgs.value?.data ?? []).map((x) => ({ value: x.id, label: x.name })),
])

const update = useUpdateTaskMutation()
const isFormSubmitting = computed(() => update.isPending.value)
const open = ref(false)
const error = ref('')
const errors = reactive<Record<string, string>>({})
const form = reactive<TaskFormState>({
  title: '',
  description: '',
  notes: '',
  priority: 'medium',
  decision_id: '',
  organization_unit_id: '',
  assigned_to_employee_id: '',
  start_date: '',
  due_date: '',
})

const canEdit = computed(
  () =>
    !!task.value &&
    ['draft', 'assigned'].includes(task.value.status) &&
    can('tasks.update'),
)

function edit(): void {
  if (!task.value || !canEdit.value) return
  Object.assign(form, {
    title: task.value.title,
    description: task.value.description ?? '',
    notes: task.value.notes ?? '',
    priority: task.value.priority,
    decision_id: task.value.decision_id ?? '',
    organization_unit_id: task.value.organization_unit_id ?? '',
    assigned_to_employee_id: task.value.assigned_to_employee_id ?? '',
    start_date: task.value.start_date ?? '',
    due_date: task.value.due_date ?? '',
  })
  error.value = ''
  Object.keys(errors).forEach((k) => delete errors[k])
  open.value = true
}

async function save(): Promise<void> {
  Object.keys(errors).forEach((x) => delete errors[x])
  Object.assign(errors, validateTaskForm(form))
  if (Object.keys(errors).length || !task.value) return

  try {
    await update.mutateAsync({
      id: task.value.id,
      payload: {
        title: form.title.trim(),
        description: form.description.trim() || null,
        notes: form.notes.trim() || null,
        priority: form.priority,
        organization_unit_id: form.organization_unit_id || null,
        start_date: form.start_date || null,
        due_date: form.due_date || null,
      },
    })
    toast.success(t('tasks.toasts.updated'))
    open.value = false
    await refetch()
  } catch {
    error.value = t('tasks.errors.generic')
  }
}

function assignForm(next: TaskFormState): void {
  Object.assign(form, next)
}

async function onRefreshed(): Promise<void> {
  await refetch()
}
</script>

<template>
  <div class="mx-auto max-w-[1200px] space-y-5">
    <div class="flex flex-wrap items-center gap-3">
      <button
        type="button"
        class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-brand-border bg-brand-surface px-3 text-sm font-semibold text-brand-text transition hover:bg-brand-bg"
        @click="router.push('/app/tasks')"
      >
        <ArrowRight class="h-4 w-4" />
        {{ t('tasks.backToList') }}
      </button>
    </div>

    <div
      v-if="isLoading"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('tasks.loadingDetails') }}
    </div>

    <div
      v-else-if="isError || !task"
      class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center"
    >
      <p class="text-sm text-red-700">{{ t('tasks.errors.loadDetails') }}</p>
      <button
        type="button"
        class="mt-3 text-sm font-semibold text-brand-primary-dark underline"
        @click="() => refetch()"
      >
        {{ t('tasks.retry') }}
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
                {{ task.task_number }}
              </p>
              <span
                class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold tracking-wide"
                :class="taskStatusBadgeClass(task.status)"
              >
                <span
                  class="h-1.5 w-1.5 shrink-0 rounded-full"
                  :class="taskStatusDotClass(task.status)"
                  aria-hidden="true"
                />
                {{ t(`tasks.status.${task.status}`) }}
              </span>
              <span
                class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold"
                :class="taskPriorityBadgeClass(task.priority)"
              >
                {{ t(`tasks.priority.${task.priority}`) }}
              </span>
              <span
                v-if="task.is_overdue"
                class="inline-flex rounded-full bg-red-100 px-2.5 py-0.5 text-[11px] font-bold text-brand-text ring-1 ring-inset ring-red-300/80"
              >
                {{ t('tasks.overdueBadge') }}
              </span>
            </div>
            <h2 class="mt-2 text-[1.65rem] font-bold leading-snug text-brand-text sm:text-[1.85rem]">
              {{ task.title }}
            </h2>
            <p class="mt-2 text-sm text-brand-text-secondary">
              <span class="font-semibold text-brand-text">
                {{ task.assigned_to_employee?.full_name ?? t('tasks.noEmployee') }}
              </span>
              <span class="mx-1.5 text-brand-text-muted">·</span>
              {{ task.organization_unit?.name ?? t('tasks.noOrgUnit') }}
            </p>
          </div>

          <PermissionGuard v-if="canEdit" permission="tasks.update">
            <button
              type="button"
              class="inline-flex h-10 items-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-4 text-sm font-semibold text-brand-primary-dark transition hover:bg-brand-primary-soft"
              @click="edit"
            >
              <Pencil class="h-4 w-4" />
              {{ t('tasks.actions.edit') }}
            </button>
          </PermissionGuard>
        </div>
      </section>

      <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
        <div class="space-y-5">
          <section class="rounded-2xl border border-brand-border bg-brand-surface">
            <header class="border-b border-brand-border px-5 py-4">
              <h3 class="text-sm font-bold text-brand-text">{{ t('tasks.detailsSummary') }}</h3>
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
                    {{ t('tasks.fields.startDate') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text" dir="ltr">
                    {{ task.start_date ?? '—' }}
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
                    {{ t('tasks.fields.dueDate') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text" dir="ltr">
                    {{ task.due_date ?? '—' }}
                  </dd>
                </div>
              </div>

              <div class="flex gap-3 border-b border-brand-border/80 px-5 py-4 sm:border-e">
                <span
                  class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary"
                >
                  <Percent class="h-4 w-4" :stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                  <dt class="text-xs font-semibold text-brand-text-muted">
                    {{ t('tasks.fields.progressPercent') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text">
                    {{ task.progress_percent }}%
                  </dd>
                </div>
              </div>

              <div class="flex gap-3 border-b border-brand-border/80 px-5 py-4">
                <span
                  class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary"
                >
                  <Tag class="h-4 w-4" :stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                  <dt class="text-xs font-semibold text-brand-text-muted">
                    {{ t('tasks.fields.priority') }}
                  </dt>
                  <dd class="mt-1">
                    <span
                      class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold"
                      :class="taskPriorityBadgeClass(task.priority)"
                    >
                      {{ t(`tasks.priority.${task.priority}`) }}
                    </span>
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
                    {{ t('tasks.fields.organizationUnit') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text">
                    {{ task.organization_unit?.name ?? '—' }}
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
                    {{ t('tasks.fields.assignee') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text">
                    {{ task.assigned_to_employee?.full_name ?? '—' }}
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
                    {{ t('tasks.fields.createdBy') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text">
                    {{ task.created_by?.name ?? '—' }}
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
                    {{ t('tasks.sections.source') }}
                  </dt>
                  <dd v-if="!task.decision" class="mt-1 text-sm font-semibold text-brand-text">
                    {{ t('tasks.standaloneSource') }}
                  </dd>
                  <dd v-else class="mt-1">
                    <RouterLink
                      :to="`/app/decisions/${task.decision.id}`"
                      class="text-sm font-semibold text-brand-primary-dark hover:underline"
                    >
                      {{ task.decision.decision_number }} — {{ task.decision.title }}
                    </RouterLink>
                  </dd>
                </div>
              </div>
            </dl>
          </section>

          <section
            v-if="task.description"
            class="rounded-2xl border border-brand-border bg-brand-surface"
          >
            <header class="flex items-center gap-2 border-b border-brand-border px-5 py-4">
              <FileText class="h-4 w-4 text-brand-primary" :stroke-width="1.75" />
              <h3 class="text-sm font-bold text-brand-text">{{ t('tasks.fields.description') }}</h3>
            </header>
            <p class="whitespace-pre-wrap px-5 py-4 text-sm leading-relaxed text-brand-text">
              {{ task.description }}
            </p>
          </section>

          <section
            v-if="task.notes"
            class="rounded-2xl border border-brand-border bg-brand-surface"
          >
            <header class="flex items-center gap-2 border-b border-brand-border px-5 py-4">
              <FileText class="h-4 w-4 text-brand-primary" :stroke-width="1.75" />
              <h3 class="text-sm font-bold text-brand-text">{{ t('tasks.fields.notes') }}</h3>
            </header>
            <p class="whitespace-pre-wrap px-5 py-4 text-sm leading-relaxed text-brand-text-secondary">
              {{ task.notes }}
            </p>
          </section>

          <section
            v-if="task.status === 'completed'"
            class="rounded-2xl border border-brand-border bg-brand-surface"
          >
            <header class="flex items-center gap-2 border-b border-brand-border px-5 py-4">
              <FileText class="h-4 w-4 text-brand-primary" :stroke-width="1.75" />
              <h3 class="text-sm font-bold text-brand-text">{{ t('tasks.sections.outcome') }}</h3>
            </header>
            <div class="space-y-3 px-5 py-4">
              <div>
                <p class="text-xs font-semibold text-brand-text-muted">
                  {{ t('tasks.fields.completedAt') }}
                </p>
                <p class="mt-1 text-sm font-semibold text-brand-text" dir="ltr">
                  {{ task.completed_at ?? '—' }}
                </p>
              </div>
              <div v-if="task.completion_notes">
                <p class="text-xs font-semibold text-brand-text-muted">
                  {{ t('tasks.fields.completionNotes') }}
                </p>
                <p class="mt-1 whitespace-pre-wrap text-sm leading-relaxed text-brand-text">
                  {{ task.completion_notes }}
                </p>
              </div>
            </div>
          </section>

          <TaskTimeline
            :transitions="task.status_transitions ?? []"
            :assignments="task.assignment_history ?? []"
          />

          <EntityDocumentsSection
            linkable-type="task"
            :linkable-id="task.id"
            :link-label="`${task.task_number} — ${task.title}`"
          />
        </div>

        <aside class="space-y-5 xl:sticky xl:top-4 xl:self-start">
          <section class="rounded-2xl border border-brand-border bg-brand-surface">
            <header class="border-b border-brand-border px-5 py-4">
              <h3 class="text-sm font-bold text-brand-text">{{ t('tasks.lifecycleTitle') }}</h3>
              <p class="mt-1 text-xs text-brand-text-secondary">
                {{ t('tasks.lifecycleHint') }}
              </p>
            </header>
            <div class="px-4 py-4">
              <TaskLifecycleActions
                :task="task"
                :linked-employee-id="linkedEmployeeId"
                @refreshed="onRefreshed"
              />
            </div>
          </section>
        </aside>
      </div>
    </template>

    <TaskFormDrawer
      :open="open"
      :editing="task"
      :form="form"
      :form-error="error"
      :field-errors="errors"
      :submitting="isFormSubmitting"
      :org-unit-options="orgOptions"
      @close="open = false"
      @submit="save"
      @update:form="assignForm"
    />
  </div>
</template>
