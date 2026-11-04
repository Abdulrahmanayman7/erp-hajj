<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, RouterLink } from 'vue-router'
import { ArrowRight } from 'lucide-vue-next'

import { ApiError } from '@/shared/api/http'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'

import { nextCycleHint } from '@/modules/purchase-orders/utils/procurementCycleHints'

import { usePostGoodsReceiptMutation } from '../mutations/useGoodsReceiptMutations'
import { useGoodsReceiptQuery } from '../queries/useGoodsReceiptQuery'

const { t } = useI18n()
const route = useRoute()
const toast = useToast()
const { can } = usePermissions()

const grnId = computed(() => {
  const raw = route.params.id
  const value = Number(Array.isArray(raw) ? raw[0] : raw)
  return Number.isFinite(value) ? value : null
})

const { data, isLoading, isError, refetch } = useGoodsReceiptQuery(grnId)
const grn = computed(() => data.value ?? null)
const actionError = ref('')
const postMutation = usePostGoodsReceiptMutation()

const canPost = computed(
  () => grn.value?.status === 'draft' && can('goods_receipts.post') && !postMutation.isPending.value,
)
const cycleHint = computed(() =>
  grn.value ? nextCycleHint({ kind: 'grn', status: grn.value.status }) : null,
)

async function onPost(): Promise<void> {
  if (grnId.value == null || !canPost.value) return
  actionError.value = ''
  try {
    await postMutation.mutateAsync(grnId.value)
    toast.success(t('goodsReceipts.details.postSuccess'))
    await refetch()
  } catch (error) {
    const message = error instanceof ApiError ? error.message : t('goodsReceipts.details.postFailed')
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
    </div>

    <div v-if="isLoading" class="text-sm text-muted-foreground">{{ t('goodsReceipts.loading') }}</div>
    <div v-else-if="isError || !grn" class="text-sm text-destructive">{{ t('goodsReceipts.loadError') }}</div>
    <template v-else>
      <header class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 class="text-2xl font-semibold text-foreground">{{ grn.grn_number }}</h1>
          <p class="mt-1 text-sm text-muted-foreground">
            {{ t(`goodsReceipts.statuses.${grn.status}`) }}
          </p>
        </div>
        <PermissionGuard permission="goods_receipts.post">
          <button
            v-if="canPost"
            type="button"
            class="rounded-md bg-primary px-3 py-2 text-sm text-primary-foreground"
            @click="onPost"
          >
            {{ t('goodsReceipts.details.post') }}
          </button>
        </PermissionGuard>
      </header>

      <p v-if="actionError" class="text-sm text-destructive">{{ actionError }}</p>

      <p
        v-if="cycleHint"
        class="rounded-md border border-border bg-muted/30 px-3 py-2 text-sm text-muted-foreground"
      >
        <span class="font-medium text-foreground">{{ t('procurementCycle.nextAction') }}:</span>
        {{ t(cycleHint.labelKey) }}
      </p>

      <section class="grid gap-3 rounded-md border border-border p-4 text-sm md:grid-cols-2">
        <div>
          <span class="text-muted-foreground">{{ t('goodsReceipts.columns.po') }}:</span>
          <RouterLink class="ms-2 text-primary hover:underline" :to="`/app/purchase-orders/${grn.purchase_order_id}`">
            {{ grn.purchase_order?.po_number ?? `#${grn.purchase_order_id}` }}
          </RouterLink>
        </div>
        <div>
          <span class="text-muted-foreground">{{ t('goodsReceipts.columns.warehouse') }}:</span>
          <span class="ms-2">{{ grn.warehouse?.name ?? '—' }}</span>
        </div>
        <div v-if="grn.purchase_order?.procurement_rfq_id">
          <span class="text-muted-foreground">RFQ:</span>
          <RouterLink
            class="ms-2 text-primary hover:underline"
            :to="`/app/rfqs/${grn.purchase_order.procurement_rfq_id}`"
          >
            #{{ grn.purchase_order.procurement_rfq_id }}
          </RouterLink>
        </div>
        <div v-if="grn.purchase_order?.purchase_request_id">
          <span class="text-muted-foreground">PR:</span>
          <RouterLink
            class="ms-2 text-primary hover:underline"
            :to="`/app/purchase-requests/${grn.purchase_order.purchase_request_id}`"
          >
            #{{ grn.purchase_order.purchase_request_id }}
          </RouterLink>
        </div>
      </section>

      <section class="space-y-3">
        <h2 class="font-medium">{{ t('goodsReceipts.details.lines') }}</h2>
        <div v-if="!grn.lines?.length" class="text-sm text-muted-foreground">
          {{ t('goodsReceipts.details.noLines') }}
        </div>
        <div v-else class="overflow-x-auto rounded-md border border-border">
          <table class="min-w-full text-sm">
            <thead class="bg-muted/40 text-muted-foreground">
              <tr>
                <th class="px-3 py-2 text-start">#</th>
                <th class="px-3 py-2 text-start">{{ t('goodsReceipts.details.description') }}</th>
                <th class="px-3 py-2 text-start">{{ t('goodsReceipts.details.qty') }}</th>
                <th class="px-3 py-2 text-start">{{ t('goodsReceipts.details.movement') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="line in grn.lines" :key="line.id" class="border-t border-border">
                <td class="px-3 py-2">{{ line.line_no }}</td>
                <td class="px-3 py-2">{{ line.purchase_order_line?.description ?? `#${line.purchase_order_line_id}` }}</td>
                <td class="px-3 py-2">{{ line.quantity }}</td>
                <td class="px-3 py-2">
                  {{ line.inventory_movement?.movement_number ?? (line.inventory_item_id ? '—' : t('goodsReceipts.create.nonStock')) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>
  </div>
</template>
