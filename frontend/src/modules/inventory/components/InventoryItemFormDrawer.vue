<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

import AppFormDrawer from '@/shared/components/AppFormDrawer.vue'
import AppNumberInput from '@/shared/components/AppNumberInput.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'

import type { InventoryItem, InventoryItemFormState } from '../types/items'
import { INVENTORY_UNITS } from '../types/items'

const props = defineProps<{
  open: boolean
  editing: InventoryItem | null
  form: InventoryItemFormState
  formError: string
  fieldErrors: Record<string, string>
  submitting: boolean
  categoryOptions: AppSelectOption[]
}>()

const emit = defineEmits<{
  close: []
  submit: []
  'update:form': [InventoryItemFormState]
}>()

const { t } = useI18n()
const isEdit = computed(() => props.editing != null)

const unitOptions = computed<AppSelectOption[]>(() =>
  INVENTORY_UNITS.map((unit) => ({
    value: unit,
    label: t(`inventory.units.${unit}`),
  })),
)

function patch(part: Partial<InventoryItemFormState>): void {
  emit('update:form', { ...props.form, ...part })
}

function onOpenUpdate(value: boolean): void {
  if (!value) emit('close')
}
</script>

<template>
  <AppFormDrawer
    :open="open"
    :title="t(isEdit ? 'inventory.items.editTitle' : 'inventory.items.createTitle')"
    :description="t(isEdit ? 'inventory.items.editSubtitle' : 'inventory.items.createSubtitle')"
    size="md"
    :submitting="submitting"
    :cancel-label="t('inventory.cancel')"
    :submit-label="t(isEdit ? 'inventory.save' : 'inventory.items.createCta')"
    @update:open="onOpenUpdate"
    @close="emit('close')"
    @submit="emit('submit')"
  >
    <label class="block">
      <span class="text-sm font-medium text-brand-text">{{ t('inventory.fields.itemNumber') }}</span>
      <input
        readonly
        :value="editing?.item_number ?? t('inventory.numberPlaceholder')"
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
      <span class="text-sm font-medium text-brand-text">{{ t('inventory.fields.category') }}</span>
      <AppSelect
        class="mt-1"
        :model-value="form.category_id"
        :options="categoryOptions"
        searchable
        @update:model-value="
          patch({ category_id: $event === '' || $event === null ? '' : Number($event) })
        "
      />
    </label>

    <label class="block">
      <span class="text-sm font-medium text-brand-text">{{ t('inventory.fields.unit') }}</span>
      <AppSelect
        class="mt-1"
        :model-value="form.unit"
        :options="unitOptions"
        @update:model-value="patch({ unit: ($event || '') as InventoryItemFormState['unit'] })"
      />
      <p v-if="fieldErrors.unit" class="mt-1 text-xs text-red-600">
        {{ t(`inventory.validation.${fieldErrors.unit}`) }}
      </p>
    </label>

    <label class="block">
      <span class="text-sm font-medium text-brand-text">{{ t('inventory.fields.barcode') }}</span>
      <input
        :value="form.barcode"
        class="mt-1 h-11 w-full rounded-xl border border-brand-border px-3 text-sm"
        @input="patch({ barcode: ($event.target as HTMLInputElement).value })"
      />
    </label>

    <label class="block">
      <span class="text-sm font-medium text-brand-text">{{ t('inventory.fields.minimumStock') }}</span>
      <AppNumberInput
        :model-value="form.minimum_stock"
        class="mt-1 h-11 w-full rounded-xl border border-brand-border px-3 text-sm"
        min="0"
        @update:model-value="patch({ minimum_stock: $event })"
      />
      <p v-if="fieldErrors.minimum_stock" class="mt-1 text-xs text-red-600">
        {{ t(`inventory.validation.${fieldErrors.minimum_stock}`) }}
      </p>
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
