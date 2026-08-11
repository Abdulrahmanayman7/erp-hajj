<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { AlertTriangle, Loader2, X } from 'lucide-vue-next'

import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'

import type { StockActionFormState, StockActionKind } from '../types/stock'
import type { MovementDirection } from '../types/movements'

const props = defineProps<{
  open: boolean
  kind: StockActionKind | null
  form: StockActionFormState
  formError: string
  fieldErrors: Record<string, string>
  submitting: boolean
  warehouseOptions: AppSelectOption[]
  itemOptions: AppSelectOption[]
}>()

const emit = defineEmits<{
  close: []
  submit: []
  'update:form': [StockActionFormState]
}>()

const { t } = useI18n()

const titleKey = computed(() => {
  switch (props.kind) {
    case 'receive':
      return 'inventory.stock.receiveTitle'
    case 'issue':
      return 'inventory.stock.issueTitle'
    case 'return':
      return 'inventory.stock.returnTitle'
    case 'transfer':
      return 'inventory.stock.transferTitle'
    case 'adjust':
      return 'inventory.stock.adjustTitle'
    default:
      return 'inventory.stock.receiveTitle'
  }
})

const isDanger = computed(() => props.kind === 'adjust')

const directionOptions = computed<AppSelectOption[]>(() => [
  { value: 'in', label: t('inventory.direction.in') },
  { value: 'out', label: t('inventory.direction.out') },
])

function patch(part: Partial<StockActionFormState>): void {
  emit('update:form', { ...props.form, ...part })
}
</script>

<template>
  <Teleport to="body">
    <div v-if="open && kind" class="fixed inset-0 z-50 flex items-center justify-center p-4" role="presentation">
      <div class="absolute inset-0 bg-black/30" @click="emit('close')" />
      <div
        class="relative z-10 w-full max-w-lg overflow-hidden rounded-2xl bg-brand-surface shadow-xl"
        role="dialog"
        aria-modal="true"
        @click.stop
      >
        <header
          class="flex items-start justify-between border-b border-brand-border px-5 py-4"
          :class="isDanger ? 'bg-red-50/60' : ''"
        >
          <div>
            <h3 class="flex items-center gap-2 text-lg font-bold text-brand-text">
              <AlertTriangle v-if="isDanger" class="h-5 w-5 text-red-700" />
              {{ t(titleKey) }}
            </h3>
            <p class="mt-1 text-sm text-brand-text-secondary">
              {{ t(`inventory.stock.${kind}Subtitle`) }}
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

        <form class="space-y-4 px-5 py-5" @submit.prevent="emit('submit')">
          <template v-if="kind === 'transfer'">
            <label class="block">
              <span class="text-sm font-medium">{{ t('inventory.fields.sourceWarehouse') }}</span>
              <AppSelect
                class="mt-1"
                :model-value="form.source_warehouse_id"
                :options="warehouseOptions"
                searchable
                @update:model-value="
                  patch({
                    source_warehouse_id: $event === '' || $event === null ? '' : Number($event),
                  })
                "
              />
              <p v-if="fieldErrors.source_warehouse_id" class="mt-1 text-xs text-red-600">
                {{ t(`inventory.validation.${fieldErrors.source_warehouse_id}`) }}
              </p>
            </label>
            <label class="block">
              <span class="text-sm font-medium">{{ t('inventory.fields.destinationWarehouse') }}</span>
              <AppSelect
                class="mt-1"
                :model-value="form.destination_warehouse_id"
                :options="warehouseOptions"
                searchable
                @update:model-value="
                  patch({
                    destination_warehouse_id: $event === '' || $event === null ? '' : Number($event),
                  })
                "
              />
              <p v-if="fieldErrors.destination_warehouse_id" class="mt-1 text-xs text-red-600">
                {{ t(`inventory.validation.${fieldErrors.destination_warehouse_id}`) }}
              </p>
            </label>
          </template>

          <label v-else class="block">
            <span class="text-sm font-medium">{{ t('inventory.fields.warehouse') }}</span>
            <AppSelect
              class="mt-1"
              :model-value="form.warehouse_id"
              :options="warehouseOptions"
              searchable
              @update:model-value="
                patch({ warehouse_id: $event === '' || $event === null ? '' : Number($event) })
              "
            />
            <p v-if="fieldErrors.warehouse_id" class="mt-1 text-xs text-red-600">
              {{ t(`inventory.validation.${fieldErrors.warehouse_id}`) }}
            </p>
          </label>

          <label class="block">
            <span class="text-sm font-medium">{{ t('inventory.fields.item') }}</span>
            <AppSelect
              class="mt-1"
              :model-value="form.inventory_item_id"
              :options="itemOptions"
              searchable
              @update:model-value="
                patch({
                  inventory_item_id: $event === '' || $event === null ? '' : Number($event),
                })
              "
            />
            <p v-if="fieldErrors.inventory_item_id" class="mt-1 text-xs text-red-600">
              {{ t(`inventory.validation.${fieldErrors.inventory_item_id}`) }}
            </p>
          </label>

          <label v-if="kind === 'adjust'" class="block">
            <span class="text-sm font-medium">{{ t('inventory.fields.direction') }}</span>
            <AppSelect
              class="mt-1"
              :model-value="form.direction"
              :options="directionOptions"
              @update:model-value="
                patch({ direction: ($event || '') as MovementDirection | '' })
              "
            />
            <p v-if="fieldErrors.direction" class="mt-1 text-xs text-red-600">
              {{ t(`inventory.validation.${fieldErrors.direction}`) }}
            </p>
          </label>

          <label class="block">
            <span class="text-sm font-medium">{{ t('inventory.fields.quantity') }}</span>
            <input
              :value="form.quantity"
              type="number"
              min="0.001"
              step="0.001"
              required
              class="mt-1 h-11 w-full rounded-xl border border-brand-border px-3 text-sm"
              @input="patch({ quantity: ($event.target as HTMLInputElement).value })"
            />
            <p v-if="fieldErrors.quantity" class="mt-1 text-xs text-red-600">
              {{ t(`inventory.validation.${fieldErrors.quantity}`) }}
            </p>
          </label>

          <label v-if="kind === 'receive'" class="flex items-center gap-2">
            <input
              type="checkbox"
              class="h-4 w-4 rounded border-brand-border"
              :checked="form.as_opening"
              @change="patch({ as_opening: ($event.target as HTMLInputElement).checked })"
            />
            <span class="text-sm">{{ t('inventory.stock.asOpening') }}</span>
          </label>

          <label class="block">
            <span class="text-sm font-medium">
              {{ t('inventory.fields.reason') }}
              <span v-if="kind === 'adjust'" class="text-red-700">*</span>
            </span>
            <textarea
              :value="form.reason"
              rows="3"
              required
              class="mt-1 w-full rounded-xl border border-brand-border p-3 text-sm"
              :class="isDanger ? 'border-red-300 focus:border-red-500 focus:ring-red-200' : ''"
              @input="patch({ reason: ($event.target as HTMLTextAreaElement).value })"
            />
            <p v-if="fieldErrors.reason" class="mt-1 text-xs text-red-600">
              {{ t(`inventory.validation.${fieldErrors.reason}`) }}
            </p>
          </label>

          <label class="block">
            <span class="text-sm font-medium">{{ t('inventory.fields.reference') }}</span>
            <input
              :value="form.reference"
              class="mt-1 h-11 w-full rounded-xl border border-brand-border px-3 text-sm"
              @input="patch({ reference: ($event.target as HTMLInputElement).value })"
            />
          </label>

          <p v-if="formError" class="text-sm text-red-700">{{ formError }}</p>

          <footer class="flex justify-end gap-2 pt-2">
            <button
              type="button"
              class="rounded-xl border border-brand-border px-4 py-2 text-sm font-semibold"
              :disabled="submitting"
              @click="emit('close')"
            >
              {{ t('inventory.cancel') }}
            </button>
            <button
              type="submit"
              class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold text-white"
              :class="isDanger ? 'bg-red-700 hover:bg-red-800' : 'bg-brand-primary-dark hover:bg-brand-primary'"
              :disabled="submitting"
            >
              <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
              {{ t(`inventory.stock.${kind}Cta`) }}
            </button>
          </footer>
        </form>
      </div>
    </div>
  </Teleport>
</template>
