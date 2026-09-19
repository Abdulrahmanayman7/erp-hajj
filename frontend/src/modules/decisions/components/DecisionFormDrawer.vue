<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Loader2, X } from 'lucide-vue-next'
import { listEmployees } from '@/modules/employees/api/employeesApi'
import AppDateInput from '@/shared/components/AppDateInput.vue'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { employeeSelectOption, toSelectId } from '@/shared/lookups/selectOptions'
import type { Decision, DecisionFormState } from '../types/decisions'

const props = defineProps<{
  open: boolean
  editing: Decision | null
  form: DecisionFormState
  formError: string
  fieldErrors: Record<string, string>
  submitting: boolean
  orgUnitOptions: AppSelectOption[]
}>()
const emit = defineEmits<{ close: []; submit: []; 'update:form': [DecisionFormState] }>()
const { t } = useI18n()
const isEdit = computed(() => props.editing != null)
const patch = (part: Partial<DecisionFormState>) => emit('update:form', { ...props.form, ...part })
const fetchActiveEmployees = (params: { search?: string; page: number; per_page: number }) =>
  listEmployees({ ...params, status: 'active' })
const emptyIssuer = computed<AppSelectOption>(() => ({
  value: '',
  label: t('decisions.noIssuer'),
}))
const emptyResponsible = computed<AppSelectOption>(() => ({
  value: '',
  label: t('decisions.noResponsible'),
}))
const selectedIssuedBy = computed(() =>
  props.editing?.issued_by_employee ? employeeSelectOption(props.editing.issued_by_employee) : null,
)
const selectedResponsible = computed(() =>
  props.editing?.responsible_employee ? employeeSelectOption(props.editing.responsible_employee) : null,
)
</script>
<template>
  <Teleport to="body"><div v-if="open" class="fixed inset-0 z-50" role="presentation">
    <div class="absolute inset-0 bg-black/30" @click="emit('close')" />
    <aside class="app-drawer-panel absolute inset-y-0 start-0 flex w-full max-w-[540px] flex-col bg-brand-surface shadow-xl" role="dialog" aria-modal="true" v-autofocus-when @click.stop>
      <header class="flex items-start justify-between border-b border-brand-border px-4 py-5 sm:px-6"><div><h3 class="text-lg font-bold">{{ t(isEdit ? 'decisions.editTitle' : 'decisions.createTitle') }}</h3><p class="mt-1 text-sm text-brand-text-secondary">{{ t(isEdit ? 'decisions.editSubtitle' : 'decisions.createSubtitle') }}</p></div><button class="h-9 w-9" :disabled="submitting" @click="emit('close')"><X class="h-4 w-4" /></button></header>
      <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="emit('submit')"><div class="flex-1 space-y-6 overflow-y-auto px-4 py-6 sm:px-6">
        <section class="space-y-3"><h4 class="font-bold">{{ t('decisions.sections.basics') }}</h4><label class="block"><span>{{ t('decisions.fields.decisionNumber') }}</span><input readonly :value="editing?.decision_number ?? t('decisions.numberPlaceholder')" class="mt-1 h-11 w-full rounded-xl border border-brand-border bg-brand-bg px-3" /></label><label class="block"><span>{{ t('decisions.fields.title') }}</span><input :value="form.title" required class="mt-1 h-11 w-full rounded-xl border border-brand-border px-3" @input="patch({ title: ($event.target as HTMLInputElement).value })" /><p v-if="fieldErrors.title" class="text-xs text-red-600">{{ t(`decisions.validation.${fieldErrors.title}`) }}</p></label><label class="block"><span>{{ t('decisions.fields.body') }}</span><textarea :value="form.body" required rows="4" class="mt-1 w-full rounded-xl border border-brand-border p-3" @input="patch({ body: ($event.target as HTMLTextAreaElement).value })" /><p v-if="fieldErrors.body" class="text-xs text-red-600">{{ t(`decisions.validation.${fieldErrors.body}`) }}</p></label></section>
        <section><h4 class="font-bold">{{ t('decisions.sections.source') }}</h4><p class="mt-2 rounded-xl bg-brand-bg p-3 text-sm text-brand-text-secondary">{{ editing?.source_recommendation ? editing.source_recommendation.title : t('decisions.standaloneSource') }}</p></section>
        <section class="space-y-3">
          <h4 class="font-bold">{{ t('decisions.sections.responsibility') }}</h4>
          <label class="block">
            <span class="mb-1.5 block text-sm font-semibold text-brand-text">{{ t('decisions.fields.organizationUnit') }}</span>
            <AppSelect
              :model-value="form.organization_unit_id"
              :options="orgUnitOptions"
              searchable
              @update:model-value="patch({ organization_unit_id: toSelectId($event) })"
            />
          </label>
          <label class="block">
            <span class="mb-1.5 block text-sm font-semibold text-brand-text">{{ t('decisions.fields.issuedBy') }}</span>
            <AppRemoteSelect
              :model-value="form.issued_by_employee_id"
              query-key="employees-active"
              :fetcher="fetchActiveEmployees"
              :map-option="employeeSelectOption"
              :empty-option="emptyIssuer"
              :selected-option="selectedIssuedBy"
              :placeholder="t('decisions.fields.issuedBy')"
              :enabled="open"
              @update:model-value="patch({ issued_by_employee_id: toSelectId($event) })"
            />
          </label>
          <label class="block">
            <span class="mb-1.5 block text-sm font-semibold text-brand-text">{{ t('decisions.fields.responsible') }}</span>
            <AppRemoteSelect
              :model-value="form.responsible_employee_id"
              query-key="employees-active"
              :fetcher="fetchActiveEmployees"
              :map-option="employeeSelectOption"
              :empty-option="emptyResponsible"
              :selected-option="selectedResponsible"
              :placeholder="t('decisions.fields.responsible')"
              :enabled="open"
              @update:model-value="patch({ responsible_employee_id: toSelectId($event) })"
            />
          </label>
        </section>
        <section class="space-y-3"><h4 class="font-bold">{{ t('decisions.sections.dates') }}</h4><label><span>{{ t('decisions.fields.effectiveDate') }}</span><AppDateInput class="mt-1" :model-value="form.effective_date" @update:model-value="patch({ effective_date: $event })" /></label><label><span>{{ t('decisions.fields.dueDate') }}</span><AppDateInput class="mt-1" :model-value="form.due_date" @update:model-value="patch({ due_date: $event })" /></label></section>
        <section><h4 class="font-bold">{{ t('decisions.sections.notes') }}</h4><textarea :value="form.notes" rows="3" class="mt-2 w-full rounded-xl border border-brand-border p-3" @input="patch({ notes: ($event.target as HTMLTextAreaElement).value })" /></section><p v-if="formError" class="text-red-700">{{ formError }}</p>
      </div><footer class="flex flex-col-reverse gap-2 border-t border-brand-border px-4 py-3 sm:flex-row sm:justify-end" style="padding-bottom: max(12px, env(safe-area-inset-bottom))"><button type="button" class="inline-flex h-11 items-center justify-center rounded-xl border border-brand-border px-4 text-sm font-semibold" @click="emit('close')">{{ t('decisions.cancel') }}</button><button type="submit" class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white" :disabled="submitting"><Loader2 v-if="submitting" class="inline h-4 w-4 animate-spin" /> {{ t(isEdit ? 'decisions.editCta' : 'decisions.createCta') }}</button></footer></form>
    </aside></div></Teleport>
</template>
