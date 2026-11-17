<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

import { listPositions } from '../api/positionsApi'
import AppDateInput from '@/shared/components/AppDateInput.vue'
import AppFormDrawer from '@/shared/components/AppFormDrawer.vue'
import AppPhoneInput from '@/shared/components/AppPhoneInput.vue'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { positionSelectOption, toSelectId } from '@/shared/lookups/selectOptions'

import type { Employee, EmployeeFormState } from '../types/employees'

const props = defineProps<{
  open: boolean
  editing: Employee | null
  form: EmployeeFormState
  formError: string
  fieldErrors: Record<string, string>
  submitting: boolean
  orgUnitOptions: AppSelectOption[]
}>()

const emit = defineEmits<{
  close: []
  submit: []
  'update:form': [EmployeeFormState]
}>()

const { t } = useI18n()

const isEdit = computed(() => props.editing != null)
const fetchActivePositions = (params: { search?: string; page: number; per_page: number }) =>
  listPositions({ ...params, is_active: true })
const emptyPosition = computed<AppSelectOption>(() => ({ value: '', label: t('employees.noPosition') }))
const selectedPosition = computed(() =>
  props.editing?.position ? positionSelectOption(props.editing.position) : null,
)
const title = computed(() => (isEdit.value ? t('employees.editTitle') : t('employees.createTitle')))
const subtitle = computed(() =>
  isEdit.value ? t('employees.editSubtitle') : t('employees.createSubtitle'),
)
const primaryLabel = computed(() =>
  isEdit.value ? t('employees.editCta') : t('employees.createCta'),
)

const employeeNumberDisplay = computed(() => {
  if (isEdit.value && props.editing) {
    return props.editing.employee_number
  }
  return t('employees.employeeNumberPlaceholder')
})

function patch(partial: Partial<EmployeeFormState>): void {
  emit('update:form', { ...props.form, ...partial })
}

function onOpenUpdate(value: boolean): void {
  if (!value) emit('close')
}
</script>

<template>
  <AppFormDrawer
    :open="open"
    :title="title"
    :description="subtitle"
    size="md"
    :submitting="submitting"
    :cancel-label="t('employees.cancel')"
    :submit-label="primaryLabel"
    form-id="employee-form-drawer"
    @update:open="onOpenUpdate"
    @close="emit('close')"
    @submit="emit('submit')"
  >
    <label class="block">
      <span class="mb-2 block text-sm font-semibold text-brand-text">
        {{ t('employees.fields.fullName') }}
      </span>
      <input
        :value="form.full_name"
        type="text"
        required
        autocomplete="name"
        class="h-12 w-full rounded-[11px] border border-brand-border bg-brand-surface px-3.5 text-sm text-brand-text outline-none transition placeholder:text-brand-text-muted focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg disabled:opacity-70"
        :disabled="submitting"
        @input="patch({ full_name: ($event.target as HTMLInputElement).value })"
      />
      <p v-if="fieldErrors.full_name" class="mt-1.5 text-xs text-red-600">
        {{ fieldErrors.full_name }}
      </p>
    </label>

    <label class="block">
      <span class="mb-2 block text-sm font-semibold text-brand-text">
        {{ t('employees.fields.employeeNumber') }}
      </span>
      <input
        :value="employeeNumberDisplay"
        type="text"
        readonly
        class="h-12 w-full rounded-[11px] border border-brand-border bg-brand-bg px-3.5 text-sm text-brand-text-secondary outline-none"
        :aria-describedby="'employee-number-hint'"
      />
      <p id="employee-number-hint" class="mt-1.5 text-xs text-brand-text-muted">
        {{ t('employees.employeeNumberHint') }}
      </p>
    </label>

    <div>
      <span class="mb-2 block text-sm font-semibold text-brand-text">
        {{ t('employees.fields.organizationUnit') }}
      </span>
      <AppSelect
        :model-value="form.organization_unit_id"
        :options="orgUnitOptions"
        :placeholder="t('employees.selectOrgUnit')"
        :disabled="submitting"
        searchable
        @update:model-value="
          patch({
            organization_unit_id:
              $event === null || $event === '' ? '' : Number($event),
          })
        "
      />
      <p v-if="fieldErrors.organization_unit_id" class="mt-1.5 text-xs text-red-600">
        {{ fieldErrors.organization_unit_id }}
      </p>
    </div>

    <div>
      <span class="mb-2 block text-sm font-semibold text-brand-text">
        {{ t('employees.fields.position') }}
      </span>
      <AppRemoteSelect
        :model-value="form.position_id"
        query-key="positions-active"
        :fetcher="fetchActivePositions"
        :map-option="positionSelectOption"
        :empty-option="emptyPosition"
        :selected-option="selectedPosition"
        :placeholder="t('employees.selectPosition')"
        :disabled="submitting"
        :enabled="open"
        @update:model-value="patch({ position_id: toSelectId($event) })"
      />
      <p v-if="fieldErrors.position_id" class="mt-1.5 text-xs text-red-600">
        {{ fieldErrors.position_id }}
      </p>
    </div>

    <label class="block">
      <span class="mb-2 block text-sm font-semibold text-brand-text">
        {{ t('employees.fields.phone') }}
      </span>
      <AppPhoneInput
        :model-value="form.phone"
        :disabled="submitting"
        @update:model-value="patch({ phone: $event })"
      />
    </label>

    <label class="block">
      <span class="mb-2 block text-sm font-semibold text-brand-text">
        {{ t('employees.fields.email') }}
      </span>
      <input
        :value="form.email"
        type="email"
        dir="ltr"
        class="h-12 w-full rounded-[11px] border border-brand-border bg-brand-surface px-3.5 text-sm text-brand-text outline-none transition placeholder:text-brand-text-muted focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg disabled:opacity-70"
        :disabled="submitting"
        @input="patch({ email: ($event.target as HTMLInputElement).value })"
      />
      <p v-if="fieldErrors.email" class="mt-1.5 text-xs text-red-600">
        {{ fieldErrors.email }}
      </p>
    </label>

    <label class="block">
      <span class="mb-2 block text-sm font-semibold text-brand-text">
        {{ t('employees.fields.hireDate') }}
      </span>
      <AppDateInput
        :model-value="form.hire_date"
        :disabled="submitting"
        @update:model-value="patch({ hire_date: $event })"
      />
    </label>

    <label class="block">
      <span class="mb-2 block text-sm font-semibold text-brand-text">
        {{ t('employees.fields.notes') }}
      </span>
      <textarea
        :value="form.notes"
        rows="3"
        class="w-full rounded-[11px] border border-brand-border bg-brand-surface px-3.5 py-3 text-sm text-brand-text outline-none transition placeholder:text-brand-text-muted focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg disabled:opacity-70"
        :disabled="submitting"
        @input="patch({ notes: ($event.target as HTMLTextAreaElement).value })"
      />
    </label>

    <p
      v-if="formError"
      class="rounded-[11px] border border-red-200 bg-red-50 px-3.5 py-3 text-sm text-red-700"
      role="alert"
    >
      {{ formError }}
    </p>
  </AppFormDrawer>
</template>
