<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

import { listEmployees } from '@/modules/employees/api/employeesApi'
import AppFormDrawer from '@/shared/components/AppFormDrawer.vue'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { employeeSelectOption, toSelectId } from '@/shared/lookups/selectOptions'

import type { Warehouse, WarehouseFormState } from '../types/warehouses'

const props = defineProps<{
  open: boolean
  editing: Warehouse | null
  form: WarehouseFormState
  formError: string
  fieldErrors: Record<string, string>
  submitting: boolean
  orgUnitOptions: AppSelectOption[]
}>()

const emit = defineEmits<{
  close: []
  submit: []
  'update:form': [WarehouseFormState]
}>()

const { t } = useI18n()
const isEdit = computed(() => props.editing != null)

function patch(part: Partial<WarehouseFormState>): void {
  emit('update:form', { ...props.form, ...part })
}

const fetchActiveEmployees = (params: { search?: string; page: number; per_page: number }) =>
  listEmployees({ ...params, status: 'active' })
const emptyEmployee = computed<AppSelectOption>(() => ({
  value: '',
  label: t('inventory.noEmployee'),
}))
const selectedEmployee = computed(() =>
  props.editing?.responsible_employee
    ? employeeSelectOption(props.editing.responsible_employee)
    : null,
)

function onOpenUpdate(value: boolean): void {
  if (!value) emit('close')
}
</script>

<template>
  <AppFormDrawer
    :open="open"
    :title="t(isEdit ? 'inventory.warehouses.editTitle' : 'inventory.warehouses.createTitle')"
    :description="t(isEdit ? 'inventory.warehouses.editSubtitle' : 'inventory.warehouses.createSubtitle')"
    size="md"
    :submitting="submitting"
    :cancel-label="t('inventory.cancel')"
    :submit-label="t(isEdit ? 'inventory.save' : 'inventory.warehouses.createCta')"
    @update:open="onOpenUpdate"
    @close="emit('close')"
    @submit="emit('submit')"
  >
    <label class="block">
      <span class="text-sm font-medium text-brand-text">{{ t('inventory.fields.warehouseNumber') }}</span>
      <input
        readonly
        :value="editing?.warehouse_number ?? t('inventory.numberPlaceholder')"
        class="mt-1 h-11 w-full rounded-xl border border-brand-border bg-brand-bg px-3 text-sm text-brand-text-muted"
      />
    </label>

    <label class="block">
      <span class="text-sm font-medium text-brand-text">{{ t('inventory.fields.name') }}</span>
      <input
        :value="form.name"
        required
        class="mt-1 h-11 w-full rounded-xl border border-brand-border px-3 text-sm"
        @input="patch({ name: ($event.target as HTMLInputElement).value })"
      />
      <p v-if="fieldErrors.name" class="mt-1 text-xs text-red-600">
        {{ t(`inventory.validation.${fieldErrors.name}`) }}
      </p>
    </label>

    <label class="block">
      <span class="text-sm font-medium text-brand-text">{{ t('inventory.fields.location') }}</span>
      <input
        :value="form.location"
        class="mt-1 h-11 w-full rounded-xl border border-brand-border px-3 text-sm"
        @input="patch({ location: ($event.target as HTMLInputElement).value })"
      />
    </label>

    <label class="block">
      <span class="text-sm font-medium text-brand-text">{{ t('inventory.fields.description') }}</span>
      <textarea
        :value="form.description"
        rows="3"
        class="mt-1 w-full rounded-xl border border-brand-border p-3 text-sm"
        @input="patch({ description: ($event.target as HTMLTextAreaElement).value })"
      />
    </label>

    <label class="block">
      <span class="text-sm font-medium text-brand-text">{{ t('inventory.fields.organizationUnit') }}</span>
      <AppSelect
        class="mt-1"
        :model-value="form.organization_unit_id"
        :options="orgUnitOptions"
        searchable
        @update:model-value="
          patch({
            organization_unit_id: $event === '' || $event === null ? '' : Number($event),
          })
        "
      />
    </label>

    <label class="block">
      <span class="text-sm font-medium text-brand-text">{{ t('inventory.fields.responsibleEmployee') }}</span>
      <AppRemoteSelect
        class="mt-1"
        :model-value="form.responsible_employee_id"
        query-key="employees-active"
        :fetcher="fetchActiveEmployees"
        :map-option="employeeSelectOption"
        :empty-option="emptyEmployee"
        :selected-option="selectedEmployee"
        :enabled="open"
        @update:model-value="patch({ responsible_employee_id: toSelectId($event) })"
      />
    </label>

    <label class="flex items-center gap-2">
      <input
        type="checkbox"
        class="h-4 w-4 rounded border-brand-border"
        :checked="form.is_active"
        @change="patch({ is_active: ($event.target as HTMLInputElement).checked })"
      />
      <span class="text-sm text-brand-text">{{ t('inventory.fields.isActive') }}</span>
    </label>

    <label class="block">
      <span class="text-sm font-medium text-brand-text">{{ t('inventory.fields.notes') }}</span>
      <textarea
        :value="form.notes"
        rows="2"
        class="mt-1 w-full rounded-xl border border-brand-border p-3 text-sm"
        @input="patch({ notes: ($event.target as HTMLTextAreaElement).value })"
      />
    </label>

    <p v-if="formError" class="text-sm text-red-700">{{ formError }}</p>
  </AppFormDrawer>
</template>
