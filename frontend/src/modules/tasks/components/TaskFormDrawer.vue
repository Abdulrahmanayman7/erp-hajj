<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Loader2, X } from 'lucide-vue-next'
import { listDecisions } from '@/modules/decisions/api/decisionsApi'
import { listEmployees } from '@/modules/employees/api/employeesApi'
import AppDateInput from '@/shared/components/AppDateInput.vue'
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
</script>
<template>
  <Teleport to="body"><div v-if="open" class="fixed inset-0 z-50" role="presentation">
    <div class="absolute inset-0 bg-black/30" @click="emit('close')" />
    <aside class="app-drawer-panel absolute inset-y-0 start-0 flex w-full max-w-[540px] flex-col bg-brand-surface shadow-xl" role="dialog" aria-modal="true" v-autofocus-when @click.stop>
      <header class="flex items-start justify-between border-b border-brand-border px-4 py-5 sm:px-6">
        <div><h3 class="text-lg font-bold">{{ t(isEdit ? 'tasks.editTitle' : 'tasks.createTitle') }}</h3><p class="mt-1 text-sm text-brand-text-secondary">{{ t(isEdit ? 'tasks.editSubtitle' : 'tasks.createSubtitle') }}</p></div>
        <button class="h-9 w-9" :disabled="submitting" @click="emit('close')"><X class="h-4 w-4" /></button>
      </header>
      <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="emit('submit')"><div class="flex-1 space-y-6 overflow-y-auto px-4 py-6 sm:px-6">
        <section class="space-y-3">
          <h4 class="font-bold">{{ t('tasks.sections.basics') }}</h4>
          <label class="block"><span>{{ t('tasks.fields.taskNumber') }}</span><input readonly :value="editing?.task_number ?? t('tasks.numberPlaceholder')" class="mt-1 h-11 w-full rounded-xl border border-brand-border bg-brand-bg px-3" /></label>
          <label class="block"><span>{{ t('tasks.fields.title') }}</span><input :value="form.title" required class="mt-1 h-11 w-full rounded-xl border border-brand-border px-3" @input="patch({ title: ($event.target as HTMLInputElement).value })" /><p v-if="fieldErrors.title" class="text-xs text-red-600">{{ t(`tasks.validation.${fieldErrors.title}`) }}</p></label>
          <label class="block"><span>{{ t('tasks.fields.description') }}</span><textarea :value="form.description" rows="3" class="mt-1 w-full rounded-xl border border-brand-border p-3" @input="patch({ description: ($event.target as HTMLTextAreaElement).value })" /></label>
          <label class="block"><span>{{ t('tasks.fields.priority') }}</span><AppSelect class="mt-1" :model-value="form.priority" :options="priorityOptions" @update:model-value="patch({ priority: $event as TaskFormState['priority'] })" /></label>
          <label class="block"><span>{{ t('tasks.fields.notes') }}</span><textarea :value="form.notes" rows="2" class="mt-1 w-full rounded-xl border border-brand-border p-3" @input="patch({ notes: ($event.target as HTMLTextAreaElement).value })" /></label>
        </section>
        <section class="space-y-2">
          <h4 class="font-bold">{{ t('tasks.sections.source') }}</h4>
          <p v-if="isDecisionLocked" class="rounded-xl bg-brand-bg p-3 text-sm text-brand-text-secondary">{{ lockedLabel }}</p>
          <p v-else-if="isEdit" class="rounded-xl bg-brand-bg p-3 text-sm text-brand-text-secondary">{{ editing?.decision?.title ?? t('tasks.standaloneSource') }}</p>
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
          <label class="block"><span>{{ t('tasks.fields.organizationUnit') }}</span><AppSelect class="mt-1" :model-value="form.organization_unit_id" :options="orgUnitOptions" searchable @update:model-value="patch({ organization_unit_id: toSelectId($event) })" /></label>
          <label class="block"><span>{{ t('tasks.fields.assignee') }}</span>
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
            <p v-else class="mt-1 rounded-xl bg-brand-bg p-3 text-sm text-brand-text-secondary">{{ editing?.assigned_to_employee?.full_name ?? t('tasks.noEmployee') }} — {{ t('tasks.assigneeChangeHint') }}</p>
          </label>
        </section>
        <section class="space-y-3">
          <h4 class="font-bold">{{ t('tasks.sections.dates') }}</h4>
          <label class="block"><span>{{ t('tasks.fields.startDate') }}</span><AppDateInput class="mt-1" :model-value="form.start_date" @update:model-value="patch({ start_date: $event })" /></label>
          <label class="block"><span>{{ t('tasks.fields.dueDate') }}</span><AppDateInput class="mt-1" :model-value="form.due_date" :invalid="Boolean(fieldErrors.due_date)" @update:model-value="patch({ due_date: $event })" /><p v-if="fieldErrors.due_date" class="text-xs text-red-600">{{ t(`tasks.validation.${fieldErrors.due_date}`) }}</p></label>
        </section>
        <p v-if="formError" class="text-red-700">{{ formError }}</p>
      </div><footer class="flex flex-col-reverse gap-2 border-t border-brand-border px-4 py-3 sm:flex-row sm:justify-end" style="padding-bottom: max(12px, env(safe-area-inset-bottom))"><button type="button" class="inline-flex h-11 items-center justify-center rounded-xl border border-brand-border px-4 text-sm font-semibold" @click="emit('close')">{{ t('tasks.cancel') }}</button><button type="submit" class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white" :disabled="submitting"><Loader2 v-if="submitting" class="inline h-4 w-4 animate-spin" /> {{ t(isEdit ? 'tasks.editCta' : 'tasks.createCta') }}</button></footer></form>
    </aside></div></Teleport>
</template>
