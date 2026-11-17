<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

import { listCategories } from '../api/categoriesApi'
import { listEmployees } from '@/modules/employees/api/employeesApi'
import AppDateInput from '@/shared/components/AppDateInput.vue'
import AppFormDrawer from '@/shared/components/AppFormDrawer.vue'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { employeeSelectOption, namedCodeOption, toSelectId } from '@/shared/lookups/selectOptions'

import type { Contract, ContractFormState } from '../types/contracts'

const props = defineProps<{
  open: boolean
  editing: Contract | null
  form: ContractFormState
  formError: string
  fieldErrors: Record<string, string>
  submitting: boolean
  orgUnitOptions: AppSelectOption[]
  counterpartyKindOptions: AppSelectOption[]
}>()

const emit = defineEmits<{
  close: []
  submit: []
  'update:form': [ContractFormState]
}>()

const { t } = useI18n()

const isEdit = computed(() => props.editing != null)
const title = computed(() => (isEdit.value ? t('contracts.editTitle') : t('contracts.createTitle')))
const subtitle = computed(() =>
  isEdit.value ? t('contracts.editSubtitle') : t('contracts.createSubtitle'),
)
const primaryLabel = computed(() =>
  isEdit.value ? t('contracts.editCta') : t('contracts.createCta'),
)

const contractNumberDisplay = computed(() => {
  if (isEdit.value && props.editing) {
    return props.editing.contract_number
  }
  return t('contracts.contractNumberPlaceholder')
})

function patch(partial: Partial<ContractFormState>): void {
  emit('update:form', { ...props.form, ...partial })
}

const fetchActiveEmployees = (params: { search?: string; page: number; per_page: number }) =>
  listEmployees({ ...params, status: 'active' })
const fetchActiveCategories = (params: { search?: string; page: number; per_page: number }) =>
  listCategories({ ...params, is_active: true })
const emptyEmployee = computed<AppSelectOption>(() => ({ value: '', label: t('contracts.noEmployee') }))
const selectedEmployee = computed(() =>
  props.editing?.employee ? employeeSelectOption(props.editing.employee) : null,
)
const selectedCategory = computed(() =>
  props.editing?.category ? namedCodeOption(props.editing.category) : null,
)

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
    :cancel-label="t('contracts.cancel')"
    :submit-label="primaryLabel"
    form-id="contract-form-drawer"
    @update:open="onOpenUpdate"
    @close="emit('close')"
    @submit="emit('submit')"
  >
    <section class="space-y-4">
      <h4 class="text-sm font-bold text-brand-text">
        {{ t('contracts.sections.basics') }}
      </h4>

      <label class="block">
        <span class="mb-2 block text-sm font-semibold text-brand-text">
          {{ t('contracts.fields.contractNumber') }}
        </span>
        <input
          :value="contractNumberDisplay"
          type="text"
          readonly
          class="h-12 w-full rounded-[11px] border border-brand-border bg-brand-bg px-3.5 text-sm text-brand-text-secondary outline-none"
        />
        <p class="mt-1.5 text-xs text-brand-text-muted">
          {{ t('contracts.contractNumberHint') }}
        </p>
      </label>

      <label class="block">
        <span class="mb-2 block text-sm font-semibold text-brand-text">
          {{ t('contracts.fields.title') }}
        </span>
        <input
          :value="form.title"
          type="text"
          required
          class="h-12 w-full rounded-[11px] border border-brand-border bg-brand-surface px-3.5 text-sm text-brand-text outline-none transition placeholder:text-brand-text-muted focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg disabled:opacity-70"
          :disabled="submitting"
          @input="patch({ title: ($event.target as HTMLInputElement).value })"
        />
        <p v-if="fieldErrors.title" class="mt-1.5 text-xs text-red-600">
          {{ fieldErrors.title }}
        </p>
      </label>

      <div>
        <span class="mb-2 block text-sm font-semibold text-brand-text">
          {{ t('contracts.fields.category') }}
        </span>
        <AppRemoteSelect
          :model-value="form.contract_category_id"
          query-key="contract-categories-active"
          :fetcher="fetchActiveCategories"
          :map-option="namedCodeOption"
          :selected-option="selectedCategory"
          :placeholder="t('contracts.selectCategory')"
          :disabled="submitting"
          :enabled="open"
          @update:model-value="patch({ contract_category_id: toSelectId($event) })"
        />
        <p v-if="fieldErrors.contract_category_id" class="mt-1.5 text-xs text-red-600">
          {{ fieldErrors.contract_category_id }}
        </p>
      </div>
    </section>

    <section class="space-y-4">
      <h4 class="text-sm font-bold text-brand-text">
        {{ t('contracts.sections.counterparty') }}
      </h4>

      <label class="block">
        <span class="mb-2 block text-sm font-semibold text-brand-text">
          {{ t('contracts.fields.counterpartyName') }}
        </span>
        <input
          :value="form.counterparty_name"
          type="text"
          required
          class="h-12 w-full rounded-[11px] border border-brand-border bg-brand-surface px-3.5 text-sm text-brand-text outline-none transition focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg disabled:opacity-70"
          :disabled="submitting"
          @input="patch({ counterparty_name: ($event.target as HTMLInputElement).value })"
        />
        <p v-if="fieldErrors.counterparty_name" class="mt-1.5 text-xs text-red-600">
          {{ fieldErrors.counterparty_name }}
        </p>
      </label>

      <div>
        <span class="mb-2 block text-sm font-semibold text-brand-text">
          {{ t('contracts.fields.counterpartyKind') }}
        </span>
        <AppSelect
          :model-value="form.counterparty_kind"
          :options="counterpartyKindOptions"
          :disabled="submitting"
          @update:model-value="
            patch({
              counterparty_kind:
                ($event as ContractFormState['counterparty_kind']) || 'organization',
            })
          "
        />
      </div>

      <div>
        <span class="mb-2 block text-sm font-semibold text-brand-text">
          {{ t('contracts.fields.employee') }}
        </span>
        <AppRemoteSelect
          :model-value="form.employee_id"
          query-key="employees-active"
          :fetcher="fetchActiveEmployees"
          :map-option="employeeSelectOption"
          :empty-option="emptyEmployee"
          :selected-option="selectedEmployee"
          :placeholder="t('contracts.selectEmployee')"
          :disabled="submitting"
          :enabled="open"
          @update:model-value="patch({ employee_id: toSelectId($event) })"
        />
      </div>
    </section>

    <section class="space-y-4">
      <h4 class="text-sm font-bold text-brand-text">
        {{ t('contracts.sections.organization') }}
      </h4>
      <div>
        <span class="mb-2 block text-sm font-semibold text-brand-text">
          {{ t('contracts.fields.organizationUnit') }}
        </span>
        <AppSelect
          :model-value="form.organization_unit_id"
          :options="orgUnitOptions"
          :placeholder="t('contracts.selectOrgUnit')"
          :disabled="submitting"
          searchable
          @update:model-value="
            patch({
              organization_unit_id:
                $event === null || $event === '' ? '' : Number($event),
            })
          "
        />
      </div>
    </section>

    <section class="space-y-4">
      <h4 class="text-sm font-bold text-brand-text">
        {{ t('contracts.sections.duration') }}
      </h4>
      <div class="grid gap-4 sm:grid-cols-2">
        <label class="block">
          <span class="mb-2 block text-sm font-semibold text-brand-text">
            {{ t('contracts.fields.startDate') }}
          </span>
          <AppDateInput
            :model-value="form.start_date"
            :disabled="submitting"
            :invalid="Boolean(fieldErrors.start_date)"
            @update:model-value="patch({ start_date: $event })"
          />
          <p v-if="fieldErrors.start_date" class="mt-1.5 text-xs text-red-600">
            {{ fieldErrors.start_date }}
          </p>
        </label>
        <label class="block">
          <span class="mb-2 block text-sm font-semibold text-brand-text">
            {{ t('contracts.fields.endDate') }}
          </span>
          <AppDateInput
            :model-value="form.end_date"
            :disabled="submitting"
            :invalid="Boolean(fieldErrors.end_date)"
            @update:model-value="patch({ end_date: $event })"
          />
          <p v-if="fieldErrors.end_date" class="mt-1.5 text-xs text-red-600">
            {{ fieldErrors.end_date }}
          </p>
        </label>
      </div>
    </section>

    <section class="space-y-4">
      <h4 class="text-sm font-bold text-brand-text">
        {{ t('contracts.sections.value') }}
      </h4>
      <div class="grid gap-4 sm:grid-cols-2">
        <label class="block">
          <span class="mb-2 block text-sm font-semibold text-brand-text">
            {{ t('contracts.fields.value') }}
          </span>
          <input
            :value="form.value"
            type="text"
            inputmode="decimal"
            dir="ltr"
            class="h-12 w-full rounded-[11px] border border-brand-border bg-brand-surface px-3.5 text-sm text-brand-text outline-none transition focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg disabled:opacity-70"
            :disabled="submitting"
            @input="patch({ value: ($event.target as HTMLInputElement).value })"
          />
          <p v-if="fieldErrors.value" class="mt-1.5 text-xs text-red-600">
            {{ fieldErrors.value }}
          </p>
        </label>
        <label class="block">
          <span class="mb-2 block text-sm font-semibold text-brand-text">
            {{ t('contracts.fields.currency') }}
          </span>
          <input
            :value="form.currency"
            type="text"
            readonly
            class="h-12 w-full rounded-[11px] border border-brand-border bg-brand-bg px-3.5 text-sm text-brand-text-secondary outline-none"
          />
        </label>
      </div>
    </section>

    <section class="space-y-4">
      <h4 class="text-sm font-bold text-brand-text">
        {{ t('contracts.sections.notes') }}
      </h4>
      <label class="block">
        <span class="mb-2 block text-sm font-semibold text-brand-text">
          {{ t('contracts.fields.notes') }}
        </span>
        <textarea
          :value="form.notes"
          rows="3"
          class="w-full rounded-[11px] border border-brand-border bg-brand-surface px-3.5 py-3 text-sm text-brand-text outline-none transition placeholder:text-brand-text-muted focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg disabled:opacity-70"
          :disabled="submitting"
          @input="patch({ notes: ($event.target as HTMLTextAreaElement).value })"
        />
      </label>
    </section>

    <p
      v-if="formError"
      class="rounded-[11px] border border-red-200 bg-red-50 px-3.5 py-3 text-sm text-red-700"
      role="alert"
    >
      {{ formError }}
    </p>
  </AppFormDrawer>
</template>
