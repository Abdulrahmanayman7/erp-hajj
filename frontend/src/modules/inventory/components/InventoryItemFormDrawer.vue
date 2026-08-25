<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Loader2, X } from 'lucide-vue-next'

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
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-50" role="presentation">
      <div class="absolute inset-0 bg-black/30" @click="emit('close')" />
      <aside
        class="app-drawer-panel absolute inset-y-0 start-0 flex w-full max-w-[540px] flex-col bg-brand-surface shadow-xl"
        role="dialog"
        aria-modal="true"
        @click.stop
      >
        <header class="flex shrink-0 items-start justify-between border-b border-brand-border px-4 py-5 sm:px-6">
          <div>
            <h3 class="text-lg font-bold text-brand-text">
              {{ t(isEdit ? 'inventory.items.editTitle' : 'inventory.items.createTitle') }}
            </h3>
            <p class="mt-1 text-sm text-brand-text-secondary">
              {{ t(isEdit ? 'inventory.items.editSubtitle' : 'inventory.items.createSubtitle') }}
            </p>
          </div>
          <button
            type="button"
            class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-brand-text-muted hover:bg-brand-bg"
            :disabled="submitting"
            :aria-label="t('inventory.closeDrawer')"
            @click="emit('close')"
          >
            <X class="h-4 w-4" />
          </button>
        </header>

        <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="emit('submit')">
          <div class="flex-1 space-y-4 overflow-y-auto px-4 py-6 sm:px-6">
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
              <input
                :value="form.minimum_stock"
                type="number"
                min="0"
                step="0.001"
                class="mt-1 h-11 w-full rounded-xl border border-brand-border px-3 text-sm"
                @input="patch({ minimum_stock: ($event.target as HTMLInputElement).value })"
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
          </div>

          <footer
            class="flex shrink-0 flex-col-reverse gap-2 border-t border-brand-border px-4 py-3 sm:flex-row sm:justify-end"
            style="padding-bottom: max(12px, env(safe-area-inset-bottom))"
          >
            <button
              type="button"
              class="inline-flex h-11 items-center justify-center rounded-xl border border-brand-border px-4 text-sm font-semibold"
              :disabled="submitting"
              @click="emit('close')"
            >
              {{ t('inventory.cancel') }}
            </button>
            <button
              type="submit"
              class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white"
              :disabled="submitting"
            >
              <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
              {{ t(isEdit ? 'inventory.save' : 'inventory.items.createCta') }}
            </button>
          </footer>
        </form>
      </aside>
    </div>
  </Teleport>
</template>
