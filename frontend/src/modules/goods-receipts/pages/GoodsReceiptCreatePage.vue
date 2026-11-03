<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { ArrowRight } from 'lucide-vue-next'

import { listWarehouses } from '@/modules/inventory/api/warehousesApi'
import type { Warehouse } from '@/modules/inventory/types/warehouses'
import { usePurchaseOrderQuery } from '@/modules/purchase-orders/queries/usePurchaseOrderQuery'
import { ApiError } from '@/shared/api/http'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useToast } from '@/shared/composables/useToast'

import { useCreateGoodsReceiptMutation } from '../mutations/useGoodsReceiptMutations'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const toast = useToast()

const poId = computed(() => {
  const raw = route.query.purchase_order_id
  const value = Number(Array.isArray(raw) ? raw[0] : raw)
  return Number.isFinite(value) && value > 0 ? value : null
})

const { data: poData, isLoading, isError } = usePurchaseOrderQuery(poId)
const po = computed(() => poData.value ?? null)

const warehouseId = ref('')
const notes = ref('')
const quantities = reactive<Record<number, string>>({})
const warehouses = ref<Warehouse[]>([])
const actionError = ref('')

const createMutation = useCreateGoodsReceiptMutation()

const needsWarehouse = computed(
  () => (po.value?.lines ?? []).some((line) => line.inventory_item_id != null),
)

watch(
  po,
  (value) => {
    if (!value?.lines) return
    value.lines.forEach((line) => {
      if (quantities[line.id] === undefined) {
        const remaining = Math.max(
          0,
          Number(line.quantity_ordered) - Number(line.quantity_received),
        )
        quantities[line.id] = remaining > 0 ? String(remaining) : ''
      }
    })
  },
  { immediate: true },
)

watch(
  poId,
  async () => {
    try {
      const result = await listWarehouses({ is_active: true, per_page: 50 })
      warehouses.value = result.data
    } catch {
      warehouses.value = []
    }
  },
  { immediate: true },
)

const canSubmit = computed(() => {
  if (!po.value || !['issued', 'partially_received'].includes(po.value.status)) return false
  if (needsWarehouse.value && warehouseId.value === '') return false
  if (createMutation.isPending.value) return false
  return (po.value.lines ?? []).some((line) => {
    const qty = Number(quantities[line.id] ?? 0)
    return Number.isFinite(qty) && qty > 0
  })
})

function remaining(line: { quantity_ordered: string; quantity_received: string }): number {
  return Math.max(0, Number(line.quantity_ordered) - Number(line.quantity_received))
}

async function onSubmit(): Promise<void> {
  if (poId.value == null || !po.value || !canSubmit.value) return
  actionError.value = ''
  const lines = (po.value.lines ?? [])
    .map((line) => ({
      purchase_order_line_id: line.id,
      quantity: quantities[line.id] ?? '',
    }))
    .filter((row) => Number(row.quantity) > 0)

  try {
    const payload: {
      purchase_order_id: number
      warehouse_id?: number
      notes?: string
      lines: Array<{ purchase_order_line_id: number; quantity: string }>
    } = {
      purchase_order_id: poId.value,
      lines,
    }
    if (warehouseId.value !== '') payload.warehouse_id = Number(warehouseId.value)
    if (notes.value.trim() !== '') payload.notes = notes.value.trim()

    const grn = await createMutation.mutateAsync(payload)
    toast.success(t('goodsReceipts.create.success'))
    await router.push(`/app/goods-receipts/${grn.id}`)
  } catch (error) {
    const message = error instanceof ApiError ? error.message : t('goodsReceipts.create.failed')
    actionError.value = message
    toast.error(message)
  }
}
</script>

<template>
  <div class="space-y-6">
    <div>
      <RouterLink class="inline-flex items-center gap-1 text-sm text-primary hover:underline" to="/app/goods-receipts">
        <ArrowRight class="h-4 w-4" />
        {{ t('goodsReceipts.backToList') }}
      </RouterLink>
      <h1 class="mt-2 text-2xl font-semibold text-foreground">{{ t('goodsReceipts.create.title') }}</h1>
      <p class="mt-1 text-sm text-muted-foreground">{{ t('goodsReceipts.create.subtitle') }}</p>
    </div>

    <div v-if="poId == null" class="text-sm text-destructive">{{ t('goodsReceipts.create.missingPo') }}</div>
    <div v-else-if="isLoading" class="text-sm text-muted-foreground">{{ t('goodsReceipts.loading') }}</div>
    <div v-else-if="isError || !po" class="text-sm text-destructive">{{ t('goodsReceipts.create.poLoadError') }}</div>
    <div v-else-if="!['issued', 'partially_received'].includes(po.status)" class="text-sm text-destructive">
      {{ t('goodsReceipts.create.poNotReceivable') }}
    </div>
    <form v-else class="space-y-4 rounded-md border border-border p-4" @submit.prevent="onSubmit">
      <div class="text-sm">
        <span class="text-muted-foreground">{{ t('goodsReceipts.columns.po') }}:</span>
        <RouterLink class="ms-2 text-primary hover:underline" :to="`/app/purchase-orders/${po.id}`">
          {{ po.po_number }}
        </RouterLink>
      </div>

      <label v-if="needsWarehouse || warehouses.length" class="block space-y-1 text-sm">
        <span>{{ t('goodsReceipts.create.warehouseField') }}</span>
        <select v-model="warehouseId" class="w-full rounded-md border border-border bg-background px-3 py-2">
          <option value="">{{ t('goodsReceipts.create.warehousePlaceholder') }}</option>
          <option v-for="wh in warehouses" :key="wh.id" :value="String(wh.id)">
            {{ wh.warehouse_number }} — {{ wh.name }}
          </option>
        </select>
        <p v-if="needsWarehouse" class="text-xs text-muted-foreground">{{ t('goodsReceipts.create.warehouseHint') }}</p>
      </label>

      <label class="block space-y-1 text-sm">
        <span>{{ t('goodsReceipts.create.notesField') }}</span>
        <textarea v-model="notes" rows="2" class="w-full rounded-md border border-border bg-background px-3 py-2" />
      </label>

      <div class="overflow-x-auto rounded-md border border-border">
        <table class="min-w-full text-sm">
          <thead class="bg-muted/40 text-muted-foreground">
            <tr>
              <th class="px-3 py-2 text-start">{{ t('goodsReceipts.details.description') }}</th>
              <th class="px-3 py-2 text-start">{{ t('goodsReceipts.details.ordered') }}</th>
              <th class="px-3 py-2 text-start">{{ t('goodsReceipts.details.received') }}</th>
              <th class="px-3 py-2 text-start">{{ t('goodsReceipts.details.remaining') }}</th>
              <th class="px-3 py-2 text-start">{{ t('goodsReceipts.create.qtyToReceive') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="line in po.lines" :key="line.id" class="border-t border-border">
              <td class="px-3 py-2">
                {{ line.description }}
                <span v-if="!line.inventory_item_id" class="ms-1 text-xs text-muted-foreground">
                  ({{ t('goodsReceipts.create.nonStock') }})
                </span>
              </td>
              <td class="px-3 py-2">{{ line.quantity_ordered }}</td>
              <td class="px-3 py-2">{{ line.quantity_received }}</td>
              <td class="px-3 py-2">{{ remaining(line) }}</td>
              <td class="px-3 py-2">
                <input
                  v-model="quantities[line.id]"
                  type="number"
                  min="0"
                  step="0.0001"
                  class="w-28 rounded-md border border-border bg-background px-2 py-1"
                  :disabled="remaining(line) <= 0"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <p v-if="actionError" class="text-sm text-destructive">{{ actionError }}</p>

      <PermissionGuard permission="goods_receipts.create">
        <button
          type="submit"
          class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground disabled:opacity-50"
          :disabled="!canSubmit"
        >
          {{ t('goodsReceipts.create.submit') }}
        </button>
      </PermissionGuard>
    </form>
  </div>
</template>
