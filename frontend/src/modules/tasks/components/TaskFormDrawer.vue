<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { listDecisions } from '@/modules/decisions/api/decisionsApi'
import { listEmployees } from '@/modules/employees/api/employeesApi'
import AppDateInput from '@/shared/components/AppDateInput.vue'
import AppFormDrawer from '@/shared/components/AppFormDrawer.vue'
import { useCaptureOnOpen, useFormDirty } from '@/shared/composables/useFormDirty'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { employeeSelectOption, numberedEntityOption, toSelectId } from '@/shared/lookups/selectOptions'
import type { Task, TaskFormState } from '../types/tasks'
import { TASK_PRIORITIES } from '../validation/taskValidation'

const props = defineProps<{
  open: boolean
  editing: Task | null
  form: TaskFormState
  formError: string
  fieldErrors: Record<string, string>
  submitting: boolean
  orgUnitOptions: AppSelectOption[]
  lockedDecisionId?: number | null
  lockedDecisionLabel?: string
}>()
const emit = defineEmits<{ close: []; submit: []; 'update:form': [TaskFormState] }>()
const { t } = useI18n()
const isEdit = computed(() => props.editing != null)
const patch = (part: Partial<TaskFormState>) => emit('update:form', { ...props.form, ...part })
const priorityOptions = computed<AppSelectOption[]>(() =>
  TASK_PRIORITIES.map((x) => ({ value: x, label: t(`tasks.priority.${x}`) })),
)
const isDecisionLocked = computed(() => props.lockedDecisionId != null)
const lockedLabel = computed(
  () =>
    props.lockedDecisionLabel ||
    props.editing?.decision?.title ||
    t('tasks.standaloneSource'),
)
const fetchActiveEmployees = (params: { search?: string; page: number; per_page: number }) =>
  listEmployees({ ...params, status: 'active' })
const fetchApprovedDecisions = (params: { search?: string; page: number; per_page: number }) =>
  listDecisions({ ...params, status: 'approved' })
const emptyEmployee = computed<AppSelectOption>(() => ({ value: '', label: t('tasks.noEmployee') }))
const emptyDecision = computed<AppSelectOption>(() => ({
  value: '',
  label: t('tasks.standaloneSource'),
}))

const { dirty, capture, reset: resetDirty } = useFormDirty(() => props.form)
useCaptureOnOpen(() => props.open, capture, { onClose: () => resetDirty() })

function onOpenUpdate(value: boolean): void {
  if (!value) emit('close')
}
</script>
<template>
  <AppFormDrawer
    :open="open"
    :title="t(isEdit ? 'tasks.editTitle' : 'tasks.createTitle')"
    :description="t(isEdit ? 'tasks.editSubtitle' : 'tasks.createSubtitle')"
    size="md"
    :dirty="dirty"
    :submitting="submitting"
    :cancel-label="t('tasks.cancel')"
    :submit-label="t(isEdit ? 'tasks.editCta' : 'tasks.createCta')"
    @update:open="onOpenUpdate"
    @close="emit('close')"
    @submit="emit('submit')"
  >
    <section class="space-y-3">
      <h4 class="font-bold">{{ t('tasks.sections.basics') }}</h4>
      <label class="block">
        <span>{{ t('tasks.fields.taskNumber') }}</span>
        <input
          readonly
          :value="editing?.task_number ?? t('tasks.numberPlaceholder')"
          class="mt-1 h-11 w-full rounded-xl border border-brand-border bg-brand-bg px-3"
        />
      </label>
      <label class="block">
        <span>{{ t('tasks.fields.title') }}</span>
        <input
          :value="form.title"
          required
          class="mt-1 h-11 w-full rounded-xl border border-brand-border px-3"
          @input="patch({ title: ($event.target as HTMLInputElement).value })"
        />
        <p v-if="fieldErrors.title" class="text-xs text-red-600">
          {{ t(`tasks.validation.${fieldErrors.title}`) }}
        </p>
      </label>
      <label class="block">
        <span>{{ t('tasks.fields.description') }}</span>
        <textarea
          :value="form.description"
          rows="3"
          class="mt-1 w-full rounded-xl border border-brand-border p-3"
          @input="patch({ description: ($event.target as HTMLTextAreaElement).value })"
        />
      </label>
      <label class="block">
        <span>{{ t('tasks.fields.priority') }}</span>
        <AppSelect
          class="mt-1"
          :model-value="form.priority"
          :options="priorityOptions"
          @update:model-value="patch({ priority: $event as TaskFormState['priority'] })"
        />
      </label>
      <label class="block">
        <span>{{ t('tasks.fields.notes') }}</span>
        <textarea
          :value="form.notes"
          rows="2"
          class="mt-1 w-full rounded-xl border border-brand-border p-3"
          @input="patch({ notes: ($event.target as HTMLTextAreaElement).value })"
        />
      </label>
    </section>
    <section class="space-y-2">
      <h4 class="font-bold">{{ t('tasks.sections.source') }}</h4>
      <p v-if="isDecisionLocked" class="rounded-xl bg-brand-bg p-3 text-sm text-brand-text-secondary">
        {{ lockedLabel }}
      </p>
      <p v-else-if="isEdit" class="rounded-xl bg-brand-bg p-3 text-sm text-brand-text-secondary">
        {{ editing?.decision?.title ?? t('tasks.standaloneSource') }}
      </p>
      <AppRemoteSelect
        v-else
        :model-value="form.decision_id"
        query-key="approved-decisions"
        :fetcher="fetchApprovedDecisions"
        :map-option="numberedEntityOption"
        :empty-option="emptyDecision"
        :enabled="open"
        @update:model-value="patch({ decision_id: toSelectId($event) })"
      />
    </section>
    <section class="space-y-3">
      <h4 class="font-bold">{{ t('tasks.sections.assignment') }}</h4>
      <label class="block">
        <span>{{ t('tasks.fields.organizationUnit') }}</span>
        <AppSelect
          class="mt-1"
          :model-value="form.organization_unit_id"
          :options="orgUnitOptions"
          searchable
          @update:model-value="patch({ organization_unit_id: toSelectId($event) })"
        />
      </label>
      <label class="block">
        <span>{{ t('tasks.fields.assignee') }}</span>
        <AppRemoteSelect
          v-if="!isEdit"
          class="mt-1"
          :model-value="form.assigned_to_employee_id"
          query-key="employees-active"
          :fetcher="fetchActiveEmployees"
          :map-option="employeeSelectOption"
          :empty-option="emptyEmployee"
          :enabled="open"
          @update:model-value="patch({ assigned_to_employee_id: toSelectId($event) })"
        />
        <p v-else class="mt-1 rounded-xl bg-brand-bg p-3 text-sm text-brand-text-secondary">
          {{ editing?.assigned_to_employee?.full_name ?? t('tasks.noEmployee') }} —
          {{ t('tasks.assigneeChangeHint') }}
        </p>
      </label>
    </section>
    <section class="space-y-3">
      <h4 class="font-bold">{{ t('tasks.sections.dates') }}</h4>
      <label class="block">
        <span>{{ t('tasks.fields.startDate') }}</span>
        <AppDateInput
          class="mt-1"
          :model-value="form.start_date"
          @update:model-value="patch({ start_date: $event })"
        />
      </label>
      <label class="block">
        <span>{{ t('tasks.fields.dueDate') }}</span>
        <AppDateInput
          class="mt-1"
          :model-value="form.due_date"
          :invalid="Boolean(fieldErrors.due_date)"
          @update:model-value="patch({ due_date: $event })"
        />
        <p v-if="fieldErrors.due_date" class="text-xs text-red-600">
          {{ t(`tasks.validation.${fieldErrors.due_date}`) }}
        </p>
      </label>
    </section>
    <p v-if="formError" class="text-red-700">{{ formError }}</p>
  </AppFormDrawer>
</template>
