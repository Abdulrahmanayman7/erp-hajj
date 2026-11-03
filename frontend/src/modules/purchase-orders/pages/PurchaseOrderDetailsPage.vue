<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, RouterLink } from 'vue-router'
import { ArrowRight } from 'lucide-vue-next'

import { listContracts } from '@/modules/contracts/api/contractsApi'
import type { Contract } from '@/modules/contracts/types/contracts'
import { ApiError } from '@/shared/api/http'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'

import {
  useCancelPurchaseOrderMutation,
  useClosePurchaseOrderMutation,
  useIssuePurchaseOrderMutation,
  useUpdatePurchaseOrderMutation,
} from '../mutations/usePurchaseOrderMutations'
import { usePurchaseOrderQuery } from '../queries/usePurchaseOrderQuery'
import { nextCycleHint } from '../utils/procurementCycleHints'

const { t } = useI18n()
const route = useRoute()
const toast = useToast()
const { can } = usePermissions()

const poId = computed(() => {
  const raw = route.params.id
  const value = Number(Array.isArray(raw) ? raw[0] : raw)
  return Number.isFinite(value) ? value : null
})

const { data, isLoading, isError, refetch } = usePurchaseOrderQuery(poId)
const po = computed(() => data.value ?? null)

const notes = ref('')
const contractId = ref('')
const cancelReason = ref('')
const closeReason = ref('')
const actionError = ref('')
const contracts = ref<Contract[]>([])

const issueMutation = useIssuePurchaseOrderMutation()
const cancelMutation = useCancelPurchaseOrderMutation()
const closeMutation = useClosePurchaseOrderMutation()
const updateMutation = useUpdatePurchaseOrderMutation()

watch(
  po,
  (value) => {
    if (!value) return
    notes.value = value.notes ?? ''
    contractId.value = value.contract_id != null ? String(value.contract_id) : ''
  },
  { immediate: true },
)

watch(
  () => po.value?.status,
  async (status) => {
    if (status !== 'draft') return
    try {
      const result = await listContracts({ per_page: 50 })
      contracts.value = result.data
    } catch {
      contracts.value = []
    }
  },
  { immediate: true },
)

function remainingOf(line: { quantity_ordered: string; quantity_received: string; quantity_remaining?: string }): string {
  if (line.quantity_remaining != null) return line.quantity_remaining
  const ordered = Number(line.quantity_ordered)
  const received = Number(line.quantity_received)
  if (!Number.isFinite(ordered) || !Number.isFinite(received)) return '—'
  return Math.max(0, ordered - received).toFixed(4)
}

const quantitySummary = computed(() => {
  if (po.value?.quantity_summary) return po.value.quantity_summary
  const lines = po.value?.lines ?? []
  let ordered = 0
  let received = 0
  for (const line of lines) {
    ordered += Number(line.quantity_ordered) || 0
    received += Number(line.quantity_received) || 0
  }
  return {
    ordered: ordered.toFixed(4),
    received: received.toFixed(4),
    remaining: Math.max(0, ordered - received).toFixed(4),
  }
})

const hasRemainder = computed(() => {
  const rem = Number(quantitySummary.value.remaining)
  return Number.isFinite(rem) && rem > 0
})

const canIssue = computed(
  () => po.value?.status === 'draft' && can('purchase_orders.issue') && !issueMutation.isPending.value,
)
const canCancel = computed(
  () =>
    po.value != null
    && ['draft', 'issued'].includes(po.value.status)
    && can('purchase_orders.cancel')
    && !cancelMutation.isPending.value,
)
const canUpdate = computed(
  () => po.value?.status === 'draft' && can('purchase_orders.update') && !updateMutation.isPending.value,
)
const canReceive = computed(
  () => po.value != null && ['issued', 'partially_received'].includes(po.value.status),
)
const canClose = computed(
  () =>
    po.value != null
    && ['issued', 'partially_received', 'received'].includes(po.value.status)
    && can('purchase_orders.close')
    && !closeMutation.isPending.value,
)
const cycleHint = computed(() =>
  po.value
    ? nextCycleHint({
        kind: 'po',
        status: po.value.status,
        remaining: po.value.quantity_summary?.remaining,
      })
    : null,
)
const closeSubmitEnabled = computed(() => {
  if (!canClose.value) return false
  if (!hasRemainder.value) return true
  return closeReason.value.trim().length >= 10
})

async function onIssue(): Promise<void> {
  if (poId.value == null || !canIssue.value) return
  actionError.value = ''
  try {
    await issueMutation.mutateAsync(poId.value)
    toast.success(t('purchaseOrders.details.issueSuccess'))
    await refetch()
  } catch (error) {
    const message = error instanceof ApiError ? error.message : t('purchaseOrders.details.issueFailed')
    actionError.value = message
    toast.error(message)
  }
}

async function onCancel(): Promise<void> {
  if (poId.value == null || !canCancel.value) return
  actionError.value = ''
  try {
    await cancelMutation.mutateAsync({ id: poId.value, reason: cancelReason.value.trim() })
    toast.success(t('purchaseOrders.details.cancelSuccess'))
    cancelReason.value = ''
    await refetch()
  } catch (error) {
    const message = error instanceof ApiError ? error.message : t('purchaseOrders.details.cancelFailed')
    actionError.value = message
    toast.error(message)
  }
}

async function onClose(): Promise<void> {
  if (poId.value == null || !closeSubmitEnabled.value) return
  actionError.value = ''
  try {
    await closeMutation.mutateAsync({
      id: poId.value,
      closeReason: hasRemainder.value ? closeReason.value.trim() : (closeReason.value.trim() || null),
    })
    toast.success(t('purchaseOrders.details.closeSuccess'))
    closeReason.value = ''
    await refetch()
  } catch (error) {
    const message = error instanceof ApiError ? error.message : t('purchaseOrders.details.closeFailed')
    actionError.value = message
    toast.error(message)
  }
}

async function onSaveDraft(): Promise<void> {
  if (poId.value == null || !canUpdate.value) return
  actionError.value = ''
  try {
    await updateMutation.mutateAsync({
      id: poId.value,
      payload: {
        notes: notes.value.trim() === '' ? null : notes.value.trim(),
        contract_id: contractId.value === '' ? null : Number(contractId.value),
      },
    })
    toast.success(t('purchaseOrders.details.updateSuccess'))
    await refetch()
  } catch (error) {
    const message = error instanceof ApiError ? error.message : t('purchaseOrders.details.updateFailed')
    actionError.value = message
    toast.error(message)
  }
}
</script>

<template>
  <div class="space-y-6">
    <div>
      <RouterLink class="inline-flex items-center gap-1 text-sm text-primary hover:underline" to="/app/purchase-orders">
        <ArrowRight class="h-4 w-4" />
        {{ t('purchaseOrders.backToList') }}
      </RouterLink>
    </div>

    <div v-if="isLoading" class="text-sm text-muted-foreground">{{ t('purchaseOrders.loading') }}</div>
    <div v-else-if="isError || !po" class="text-sm text-destructive">{{ t('purchaseOrders.loadError') }}</div>
    <template v-else>
      <header class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 class="text-2xl font-semibold text-foreground">{{ po.po_number }}</h1>
          <p class="mt-1 text-sm text-muted-foreground">
            {{ t(`purchaseOrders.statuses.${po.status}`) }} · {{ po.currency }}
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <PermissionGuard permission="purchase_orders.issue">
            <button
              v-if="canIssue"
              type="button"
              class="rounded-md bg-primary px-3 py-2 text-sm text-primary-foreground"
              @click="onIssue"
            >
              {{ t('purchaseOrders.details.issue') }}
            </button>
          </PermissionGuard>
          <PermissionGuard permission="goods_receipts.create">
            <RouterLink
              v-if="canReceive"
              class="rounded-md border border-border px-3 py-2 text-sm"
              :to="`/app/goods-receipts/create?purchase_order_id=${po.id}`"
            >
              {{ t('purchaseOrders.details.createGoodsReceipt') }}
            </RouterLink>
          </PermissionGuard>
        </div>
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
          <span class="text-muted-foreground">{{ t('purchaseOrders.columns.supplier') }}:</span>
          <span class="ms-2">{{ po.supplier?.display_name ?? `#${po.supplier_id}` }}</span>
        </div>
        <div>
          <span class="text-muted-foreground">{{ t('purchaseOrders.columns.rfq') }}:</span>
          <RouterLink class="ms-2 text-primary hover:underline" :to="`/app/rfqs/${po.procurement_rfq_id}`">
            {{ po.rfq?.rfq_number ?? `#${po.procurement_rfq_id}` }}
          </RouterLink>
        </div>
        <div>
          <span class="text-muted-foreground">{{ t('purchaseOrders.columns.pr') }}:</span>
          <RouterLink
            class="ms-2 text-primary hover:underline"
            :to="`/app/purchase-requests/${po.purchase_request_id}`"
          >
            {{ po.purchase_request?.pr_number ?? `#${po.purchase_request_id}` }}
          </RouterLink>
        </div>
        <div>
          <span class="text-muted-foreground">{{ t('purchaseOrders.details.offer') }}:</span>
          <span class="ms-2">{{ po.winning_offer?.offer_number ?? (po.supplier_offer_id ? `#${po.supplier_offer_id}` : '—') }}</span>
        </div>
        <div>
          <span class="text-muted-foreground">{{ t('purchaseOrders.details.contract') }}:</span>
          <span class="ms-2">
            <template v-if="po.contract">
              {{ po.contract.contract_number }} — {{ po.contract.title }}
            </template>
            <template v-else>—</template>
          </span>
        </div>
        <template v-if="po.status === 'closed'">
          <div>
            <span class="text-muted-foreground">{{ t('purchaseOrders.details.closedAt') }}:</span>
            <span class="ms-2">{{ po.closed_at ?? '—' }}</span>
          </div>
          <div>
            <span class="text-muted-foreground">{{ t('purchaseOrders.details.closedBy') }}:</span>
            <span class="ms-2">{{ po.closed_by?.name ?? (po.closed_by?.id != null ? `#${po.closed_by.id}` : '—') }}</span>
          </div>
          <div class="md:col-span-2">
            <span class="text-muted-foreground">{{ t('purchaseOrders.details.closeReason') }}:</span>
            <span class="ms-2">{{ po.close_reason ?? '—' }}</span>
          </div>
        </template>
      </section>

      <section class="grid gap-3 rounded-md border border-border p-4 text-sm md:grid-cols-3">
        <h2 class="font-medium md:col-span-3">{{ t('purchaseOrders.details.quantitySummary') }}</h2>
        <div>
          <span class="text-muted-foreground">{{ t('purchaseOrders.details.summaryOrdered') }}:</span>
          <span class="ms-2">{{ quantitySummary.ordered }}</span>
        </div>
        <div>
          <span class="text-muted-foreground">{{ t('purchaseOrders.details.summaryReceived') }}:</span>
          <span class="ms-2">{{ quantitySummary.received }}</span>
        </div>
        <div>
          <span class="text-muted-foreground">{{ t('purchaseOrders.details.summaryRemaining') }}:</span>
          <span class="ms-2">{{ quantitySummary.remaining }}</span>
        </div>
      </section>

      <section v-if="canUpdate" class="space-y-3 rounded-md border border-border p-4">
        <h2 class="font-medium">{{ t('purchaseOrders.details.editDraft') }}</h2>
        <label class="block space-y-1 text-sm">
          <span>{{ t('purchaseOrders.create.notesField') }}</span>
          <textarea v-model="notes" rows="3" class="w-full rounded-md border border-border bg-background px-3 py-2" />
        </label>
        <label class="block space-y-1 text-sm">
          <span>{{ t('purchaseOrders.create.contractField') }}</span>
          <select v-model="contractId" class="w-full rounded-md border border-border bg-background px-3 py-2">
            <option value="">{{ t('purchaseOrders.create.contractNone') }}</option>
            <option v-for="contract in contracts" :key="contract.id" :value="String(contract.id)">
              {{ contract.contract_number }} — {{ contract.title }}
            </option>
          </select>
        </label>
        <PermissionGuard permission="purchase_orders.update">
          <button
            type="button"
            class="rounded-md border border-border px-3 py-2 text-sm"
            @click="onSaveDraft"
          >
            {{ t('purchaseOrders.details.saveDraft') }}
          </button>
        </PermissionGuard>
      </section>

      <section class="space-y-3">
        <h2 class="font-medium">{{ t('purchaseOrders.details.lines') }}</h2>
        <div v-if="!po.lines?.length" class="text-sm text-muted-foreground">
          {{ t('purchaseOrders.details.noLines') }}
        </div>
        <div v-else class="overflow-x-auto rounded-md border border-border">
          <table class="min-w-full text-sm">
            <thead class="bg-muted/40 text-muted-foreground">
              <tr>
                <th class="px-3 py-2 text-start">#</th>
                <th class="px-3 py-2 text-start">{{ t('purchaseOrders.details.description') }}</th>
                <th class="px-3 py-2 text-start">{{ t('purchaseOrders.details.qtyOrdered') }}</th>
                <th class="px-3 py-2 text-start">{{ t('purchaseOrders.details.qtyReceived') }}</th>
                <th class="px-3 py-2 text-start">{{ t('purchaseOrders.details.qtyRemaining') }}</th>
                <th class="px-3 py-2 text-start">{{ t('purchaseOrders.details.unit') }}</th>
                <th class="px-3 py-2 text-start">{{ t('purchaseOrders.details.unitPrice') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="line in po.lines" :key="line.id" class="border-t border-border">
                <td class="px-3 py-2">{{ line.line_no }}</td>
                <td class="px-3 py-2">{{ line.description }}</td>
                <td class="px-3 py-2">{{ line.quantity_ordered }}</td>
                <td class="px-3 py-2">{{ line.quantity_received }}</td>
                <td class="px-3 py-2">{{ remainingOf(line) }}</td>
                <td class="px-3 py-2">{{ line.unit }}</td>
                <td class="px-3 py-2">{{ line.unit_price }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section v-if="canClose" class="space-y-3 rounded-md border border-border p-4">
        <h2 class="font-medium">{{ t('purchaseOrders.details.close') }}</h2>
        <p v-if="hasRemainder" class="text-sm text-muted-foreground">
          {{ t('purchaseOrders.details.closeReasonHint') }}
        </p>
        <textarea
          v-if="hasRemainder"
          v-model="closeReason"
          rows="2"
          class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
          :placeholder="t('purchaseOrders.details.closeReasonPlaceholder')"
        />
        <PermissionGuard permission="purchase_orders.close">
          <button
            type="button"
            class="rounded-md bg-primary px-3 py-2 text-sm text-primary-foreground"
            :disabled="!closeSubmitEnabled"
            @click="onClose"
          >
            {{ t('purchaseOrders.details.closeSubmit') }}
          </button>
        </PermissionGuard>
      </section>

      <section v-if="canCancel" class="space-y-3 rounded-md border border-destructive/30 p-4">
        <h2 class="font-medium text-destructive">{{ t('purchaseOrders.details.cancel') }}</h2>
        <textarea
          v-model="cancelReason"
          rows="2"
          class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
          :placeholder="t('purchaseOrders.details.cancelReasonPlaceholder')"
        />
        <PermissionGuard permission="purchase_orders.cancel">
          <button
            type="button"
            class="rounded-md border border-destructive px-3 py-2 text-sm text-destructive"
            :disabled="cancelReason.trim().length < 10"
            @click="onCancel"
          >
            {{ t('purchaseOrders.details.cancelSubmit') }}
          </button>
        </PermissionGuard>
      </section>
    </template>
  </div>
</template>
