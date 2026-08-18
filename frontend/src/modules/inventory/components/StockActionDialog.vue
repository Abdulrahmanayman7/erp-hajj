<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  AlertTriangle,
  ArrowLeftRight,
  Loader2,
  PackageMinus,
  PackagePlus,
  RotateCcw,
  Scale,
  X,
} from 'lucide-vue-next'

import { listInventoryItems } from '../api/itemsApi'
import { listWarehouses } from '../api/warehousesApi'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { inventoryItemSelectOption, toSelectId, warehouseSelectOption } from '@/shared/lookups/selectOptions'

import type { StockActionFormState, StockActionKind } from '../types/stock'
import type { MovementDirection } from '../types/movements'

const props = defineProps<{
  open: boolean
  kind: StockActionKind | null
  form: StockActionFormState
  formError: string
  fieldErrors: Record<string, string>
  submitting: boolean
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
const actionIcon = computed(() => {
  switch (props.kind) {
    case 'receive':
      return PackagePlus
    case 'issue':
      return PackageMinus
    case 'return':
      return RotateCcw
    case 'transfer':
      return ArrowLeftRight
    case 'adjust':
      return Scale
    default:
      return PackagePlus
  }
})

const directionOptions = computed<AppSelectOption[]>(() => [
  { value: 'in', label: t('inventory.direction.in') },
  { value: 'out', label: t('inventory.direction.out') },
])

function patch(part: Partial<StockActionFormState>): void {
  emit('update:form', { ...props.form, ...part })
}

const fetchActiveWarehouses = (params: { search?: string; page: number; per_page: number }) =>
  listWarehouses({ ...params, is_active: true })
const fetchActiveItems = (params: { search?: string; page: number; per_page: number }) =>
  listInventoryItems({ ...params, is_active: true })
</script>

<template>
  <Teleport to="body">
    <div v-if="open && kind" class="fixed inset-0 z-50 flex items-center justify-center p-4" role="presentation">
      <div class="absolute inset-0 bg-black/30" @click="emit('close')" />
      <div
        class="relative z-10 flex max-h-[calc(100vh-2rem)] w-full max-w-xl flex-col overflow-hidden rounded-2xl border border-brand-border bg-brand-surface shadow-2xl"
        role="dialog"
        aria-modal="true"
        @click.stop
      >
        <header
          class="flex items-start justify-between border-b border-brand-border px-5 py-5"
          :class="isDanger ? 'bg-red-50/80' : 'bg-brand-bg/50'"
        >
          <div class="flex items-start gap-3">
            <span
              class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
              :class="isDanger ? 'bg-red-100 text-red-700' : 'bg-brand-primary-dark/10 text-brand-primary-dark'"
            >
              <AlertTriangle v-if="isDanger" class="h-5 w-5" />
              <component :is="actionIcon" v-else class="h-5 w-5" />
            </span>
            <div>
              <h3 class="text-lg font-bold text-brand-text">{{ t(titleKey) }}</h3>
            <p class="mt-1 text-sm text-brand-text-secondary">
              {{ t(`inventory.stock.${kind}Subtitle`) }}
            </p>
            </div>
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

        <form class="space-y-5 overflow-y-auto px-5 py-5" @submit.prevent="emit('submit')">
          <div
            v-if="isDanger"
            class="flex gap-2 rounded-xl border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-800"
          >
            <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
            <p>{{ t(`inventory.stock.${kind}Subtitle`) }}</p>
          </div>

          <section class="space-y-4">
            <div class="flex items-center gap-2">
              <span class="h-px flex-1 bg-brand-border" />
              <span class="text-xs font-bold text-brand-text-muted">{{ t('inventory.fields.warehouse') }}</span>
              <span class="h-px flex-1 bg-brand-border" />
            </div>
            <template v-if="kind === 'transfer'">
            <label class="block">
              <span class="text-sm font-medium">{{ t('inventory.fields.sourceWarehouse') }}</span>
              <AppRemoteSelect
                class="mt-1"
                :model-value="form.source_warehouse_id"
                query-key="warehouses-active"
                :fetcher="fetchActiveWarehouses"
                :map-option="warehouseSelectOption"
                :enabled="open"
                @update:model-value="patch({ source_warehouse_id: toSelectId($event) })"
              />
              <p v-if="fieldErrors.source_warehouse_id" class="mt-1 text-xs text-red-600">
                {{ t(`inventory.validation.${fieldErrors.source_warehouse_id}`) }}
              </p>
            </label>
            <label class="block">
              <span class="text-sm font-medium">{{ t('inventory.fields.destinationWarehouse') }}</span>
              <AppRemoteSelect
                class="mt-1"
                :model-value="form.destination_warehouse_id"
                query-key="warehouses-active"
                :fetcher="fetchActiveWarehouses"
                :map-option="warehouseSelectOption"
                :enabled="open"
                @update:model-value="patch({ destination_warehouse_id: toSelectId($event) })"
              />
              <p v-if="fieldErrors.destination_warehouse_id" class="mt-1 text-xs text-red-600">
                {{ t(`inventory.validation.${fieldErrors.destination_warehouse_id}`) }}
              </p>
            </label>
            </template>

            <label v-else class="block">
            <span class="text-sm font-medium">{{ t('inventory.fields.warehouse') }}</span>
            <AppRemoteSelect
              class="mt-1"
              :model-value="form.warehouse_id"
              query-key="warehouses-active"
              :fetcher="fetchActiveWarehouses"
              :map-option="warehouseSelectOption"
              :enabled="open"
              @update:model-value="patch({ warehouse_id: toSelectId($event) })"
            />
            <p v-if="fieldErrors.warehouse_id" class="mt-1 text-xs text-red-600">
              {{ t(`inventory.validation.${fieldErrors.warehouse_id}`) }}
            </p>
            </label>
          </section>

          <section class="space-y-4 rounded-2xl border border-brand-border bg-brand-bg/35 p-4">
            <label class="block">
            <span class="text-sm font-medium">{{ t('inventory.fields.item') }}</span>
            <AppRemoteSelect
              class="mt-1"
              :model-value="form.inventory_item_id"
              query-key="inventory-items-active"
              :fetcher="fetchActiveItems"
              :map-option="inventoryItemSelectOption"
              :enabled="open"
              @update:model-value="patch({ inventory_item_id: toSelectId($event) })"
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

            <label v-if="kind === 'receive'" class="flex items-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-3 py-2.5">
            <input
              type="checkbox"
              class="h-4 w-4 rounded border-brand-border"
              :checked="form.as_opening"
              @change="patch({ as_opening: ($event.target as HTMLInputElement).checked })"
            />
            <span class="text-sm">{{ t('inventory.stock.asOpening') }}</span>
            </label>
          </section>

          <section class="space-y-4">
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
          </section>

          <p v-if="formError" class="rounded-xl border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-700">
            {{ formError }}
          </p>

          <footer class="sticky bottom-0 flex justify-end gap-2 border-t border-brand-border bg-brand-surface pt-4">
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
