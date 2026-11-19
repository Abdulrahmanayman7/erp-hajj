<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

import { listEmployees } from '@/modules/employees/api/employeesApi'
import { listWarehouses } from '@/modules/inventory/api/warehousesApi'
import AppDateInput from '@/shared/components/AppDateInput.vue'
import AppFormDrawer from '@/shared/components/AppFormDrawer.vue'
import { useCaptureOnOpen, useFormDirty } from '@/shared/composables/useFormDirty'
import AppNumberInput from '@/shared/components/AppNumberInput.vue'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { employeeSelectOption, toSelectId, warehouseSelectOption } from '@/shared/lookups/selectOptions'

import type { Asset, AssetFormState } from '../types/assets'
import { ASSET_CONDITIONS } from '../types/assets'

const props = defineProps<{
  open: boolean
  editing: Asset | null
  form: AssetFormState
  formError: string
  fieldErrors: Record<string, string>
  submitting: boolean
  categoryOptions: AppSelectOption[]
  orgUnitOptions: AppSelectOption[]
  allowAssign?: boolean
  currentEmployeeName?: string | null
}>()

const emit = defineEmits<{
  close: []
  submit: []
  'update:form': [AssetFormState]
}>()

const { t } = useI18n()
const isEdit = computed(() => props.editing != null)
const showEmployeeField = computed(() => Boolean(props.allowAssign || props.currentEmployeeName))
const employeeLocked = computed(() => Boolean(props.currentEmployeeName) && !props.allowAssign)

const conditionOptions = computed<AppSelectOption[]>(() =>
  ASSET_CONDITIONS.map((condition) => ({
    value: condition,
    label: t(`assets.condition.${condition}`),
  })),
)

const emptyEmployeeOption = computed<AppSelectOption>(() => ({
  value: '',
  label: t('assets.noEmployee'),
}))

const fetchActiveEmployees = (params: { search?: string; page: number; per_page: number }) =>
  listEmployees({ ...params, status: 'active' })

function patch(part: Partial<AssetFormState>): void {
  emit('update:form', { ...props.form, ...part })
}

const fetchActiveWarehouses = (params: { search?: string; page: number; per_page: number }) =>
  listWarehouses({ ...params, is_active: true })
const selectedWarehouse = computed(() =>
  props.editing?.warehouse ? warehouseSelectOption(props.editing.warehouse) : null,
)

const { dirty, capture, reset: resetDirty } = useFormDirty(() => props.form)
useCaptureOnOpen(() => props.open, capture, { onClose: () => resetDirty() })

function onOpenUpdate(value: boolean): void {
  if (!value) emit('close')
}
</script>

<template>
  <AppFormDrawer
    :open="open"
    :title="t(isEdit ? 'assets.form.editTitle' : 'assets.form.createTitle')"
    :description="t(isEdit ? 'assets.form.editSubtitle' : 'assets.form.createSubtitle')"
    size="md"
    :dirty="dirty"
    :submitting="submitting"
    :cancel-label="t('assets.cancel')"
    :submit-label="t('assets.save')"
    @update:open="onOpenUpdate"
    @close="emit('close')"
    @submit="emit('submit')"
  >
    <section class="space-y-4">
      <h4 class="text-sm font-bold text-brand-text">{{ t('assets.form.sections.identity') }}</h4>
      <label class="app-field">
        <span class="app-field__label">{{ t('assets.fields.assetNumber') }}</span>
        <input
          readonly
          :value="editing?.asset_number ?? t('assets.numberPlaceholder')"
          class="app-field__control app-field__control--input bg-brand-bg text-brand-text-muted"
        />
      </label>
      <label class="app-field">
        <span class="app-field__label">{{ t('assets.fields.name') }}</span>
        <input
          :value="form.name"
          required
          class="app-field__control app-field__control--input"
          @input="patch({ name: ($event.target as HTMLInputElement).value })"
        />
        <p v-if="fieldErrors.name" class="mt-1 text-xs text-red-600">
          {{ t(`assets.validation.${fieldErrors.name}`) }}
        </p>
      </label>
      <label class="app-field">
        <span class="app-field__label">{{ t('assets.fields.description') }}</span>
        <textarea
          :value="form.description"
          rows="3"
          class="app-field__control app-field__control--textarea"
          @input="patch({ description: ($event.target as HTMLTextAreaElement).value })"
        />
      </label>
      <div class="grid gap-4 sm:grid-cols-2">
        <label class="app-field">
          <span class="app-field__label">{{ t('assets.fields.serialNumber') }}</span>
          <input
            :value="form.serial_number"
            class="app-field__control app-field__control--input"
            @input="patch({ serial_number: ($event.target as HTMLInputElement).value })"
          />
        </label>
        <label class="app-field">
          <span class="app-field__label">{{ t('assets.fields.barcode') }}</span>
          <input
            :value="form.barcode"
            class="app-field__control app-field__control--input"
            @input="patch({ barcode: ($event.target as HTMLInputElement).value })"
          />
        </label>
      </div>
      <label class="app-field">
        <span class="app-field__label">{{ t('assets.fields.condition') }}</span>
        <AppSelect
          :model-value="form.condition"
          :options="conditionOptions"
          @update:model-value="patch({ condition: $event as AssetFormState['condition'] })"
        />
      </label>
    </section>

    <section class="space-y-4">
      <h4 class="text-sm font-bold text-brand-text">{{ t('assets.form.sections.category') }}</h4>
      <label class="app-field">
        <span class="app-field__label">{{ t('assets.fields.category') }}</span>
        <AppSelect
          searchable
          :model-value="form.category_id"
          :options="categoryOptions"
          @update:model-value="patch({ category_id: $event as number | '' })"
        />
      </label>
    </section>

    <section class="space-y-4">
      <h4 class="text-sm font-bold text-brand-text">{{ t('assets.form.sections.location') }}</h4>
      <label class="app-field">
        <span class="app-field__label">{{ t('assets.fields.warehouse') }}</span>
        <AppRemoteSelect
          :model-value="form.warehouse_id"
          query-key="warehouses-active"
          :fetcher="fetchActiveWarehouses"
          :map-option="warehouseSelectOption"
          :selected-option="selectedWarehouse"
          :enabled="open"
          @update:model-value="patch({ warehouse_id: toSelectId($event) })"
        />
      </label>
      <label class="app-field">
        <span class="app-field__label">{{ t('assets.fields.organizationUnit') }}</span>
        <AppSelect
          searchable
          :model-value="form.organization_unit_id"
          :options="orgUnitOptions"
          @update:model-value="patch({ organization_unit_id: $event as number | '' })"
        />
      </label>
    </section>

    <section v-if="showEmployeeField" class="space-y-4">
      <h4 class="text-sm font-bold text-brand-text">{{ t('assets.form.sections.custody') }}</h4>
      <p class="text-xs text-brand-text-muted">
        {{ employeeLocked ? t('assets.form.custodyLockedHint') : t('assets.form.custodyHint') }}
      </p>
      <label class="app-field">
        <span class="app-field__label">{{ t('assets.fields.employee') }}</span>
        <input
          v-if="employeeLocked"
          readonly
          :value="currentEmployeeName"
          class="app-field__control app-field__control--input bg-brand-bg"
        />
        <AppRemoteSelect
          v-else
          searchable
          :model-value="form.employee_id"
          query-key="employees-active"
          :fetcher="fetchActiveEmployees"
          :map-option="employeeSelectOption"
          :empty-option="emptyEmployeeOption"
          :enabled="open"
          @update:model-value="patch({ employee_id: toSelectId($event) })"
        />
      </label>
    </section>

    <section class="space-y-4">
      <h4 class="text-sm font-bold text-brand-text">{{ t('assets.form.sections.purchase') }}</h4>
      <p class="text-xs text-brand-text-muted">{{ t('assets.form.purchaseHint') }}</p>
      <div class="grid gap-4 sm:grid-cols-2">
        <label class="app-field">
          <span class="app-field__label">{{ t('assets.fields.purchaseValue') }}</span>
          <AppNumberInput
            :model-value="form.purchase_value"
            class="app-field__control app-field__control--input"
            min="0"
            @update:model-value="patch({ purchase_value: $event })"
          />
          <p v-if="fieldErrors.purchase_value" class="mt-1 text-xs text-red-600">
            {{ t(`assets.validation.${fieldErrors.purchase_value}`) }}
          </p>
        </label>
        <label class="app-field">
          <span class="app-field__label">{{ t('assets.fields.acquisitionDate') }}</span>
          <AppDateInput
            :model-value="form.acquisition_date"
            @update:model-value="patch({ acquisition_date: $event })"
          />
        </label>
      </div>
    </section>

    <section class="space-y-4">
      <h4 class="text-sm font-bold text-brand-text">{{ t('assets.form.sections.notes') }}</h4>
      <label class="app-field">
        <span class="app-field__label">{{ t('assets.fields.notes') }}</span>
        <textarea
          :value="form.notes"
          rows="3"
          class="app-field__control app-field__control--textarea"
          @input="patch({ notes: ($event.target as HTMLTextAreaElement).value })"
        />
      </label>
    </section>

    <p v-if="formError" class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
      {{ formError }}
    </p>
  </AppFormDrawer>
</template>
