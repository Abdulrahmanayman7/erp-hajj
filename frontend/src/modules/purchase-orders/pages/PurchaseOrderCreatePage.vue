<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { ArrowRight } from 'lucide-vue-next'

import { listContracts } from '@/modules/contracts/api/contractsApi'
import type { Contract } from '@/modules/contracts/types/contracts'
import { useRfqQuery } from '@/modules/rfq/queries/useRfqQuery'
import { ApiError } from '@/shared/api/http'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useToast } from '@/shared/composables/useToast'

import { useCreatePurchaseOrderMutation } from '../mutations/usePurchaseOrderMutations'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const toast = useToast()

const rfqId = computed(() => {
  const raw = route.query.rfq_id
  const value = Number(Array.isArray(raw) ? raw[0] : raw)
  return Number.isFinite(value) && value > 0 ? value : null
})

const { data: rfqData, isLoading, isError } = useRfqQuery(rfqId)
const rfq = computed(() => rfqData.value ?? null)

const notes = ref('')
const contractId = ref<string>('')
const contracts = ref<Contract[]>([])
const contractsError = ref('')
const actionError = ref('')

const createMutation = useCreatePurchaseOrderMutation()

watch(
  rfqId,
  async (id) => {
    if (id == null) return
    contractsError.value = ''
    try {
      const result = await listContracts({ per_page: 50 })
      contracts.value = result.data
    } catch {
      contractsError.value = t('purchaseOrders.create.contractsLoadError')
      contracts.value = []
    }
  },
  { immediate: true },
)

const canSubmit = computed(
  () =>
    rfq.value?.status === 'awarded'
    && rfq.value.selected_supplier_id != null
    && !createMutation.isPending.value,
)

async function onSubmit(): Promise<void> {
  if (rfqId.value == null || !canSubmit.value) return
  actionError.value = ''
  try {
    const payload: {
      rfq_id: number
      notes?: string
      contract_id?: number
    } = { rfq_id: rfqId.value }
    if (notes.value.trim() !== '') payload.notes = notes.value.trim()
    if (contractId.value !== '') payload.contract_id = Number(contractId.value)

    const po = await createMutation.mutateAsync(payload)
    toast.success(t('purchaseOrders.create.success'))
    await router.push(`/app/purchase-orders/${po.id}`)
  } catch (error) {
    const message =
      error instanceof ApiError
        ? error.message
        : t('purchaseOrders.create.failed')
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
      <h1 class="mt-2 text-2xl font-semibold text-foreground">
        {{ t('purchaseOrders.create.title') }}
      </h1>
      <p class="mt-1 text-sm text-muted-foreground">
        {{ t('purchaseOrders.create.subtitle') }}
      </p>
    </div>

    <div v-if="rfqId == null" class="text-sm text-destructive">
      {{ t('purchaseOrders.create.missingRfq') }}
    </div>
    <div v-else-if="isLoading" class="text-sm text-muted-foreground">{{ t('purchaseOrders.loading') }}</div>
    <div v-else-if="isError || !rfq" class="text-sm text-destructive">{{ t('purchaseOrders.create.rfqLoadError') }}</div>
    <div v-else-if="rfq.status !== 'awarded'" class="text-sm text-destructive">
      {{ t('purchaseOrders.create.rfqNotAwarded') }}
    </div>
    <form v-else class="max-w-xl space-y-4 rounded-md border border-border p-4" @submit.prevent="onSubmit">
      <div class="space-y-1 text-sm">
        <div>
          <span class="text-muted-foreground">{{ t('purchaseOrders.create.rfqLabel') }}:</span>
          <RouterLink class="ms-2 text-primary hover:underline" :to="`/app/rfqs/${rfq.id}`">
            {{ rfq.rfq_number }}
          </RouterLink>
        </div>
        <div>
          <span class="text-muted-foreground">{{ t('purchaseOrders.create.prLabel') }}:</span>
          <RouterLink
            class="ms-2 text-primary hover:underline"
            :to="`/app/purchase-requests/${rfq.purchase_request_id}`"
          >
            {{ rfq.purchase_request?.pr_number ?? `#${rfq.purchase_request_id}` }}
          </RouterLink>
        </div>
        <div>
          <span class="text-muted-foreground">{{ t('purchaseOrders.create.supplierLabel') }}:</span>
          <span class="ms-2">
            {{ rfq.selected_supplier?.display_name ?? `#${rfq.selected_supplier_id}` }}
          </span>
        </div>
      </div>

      <label class="block space-y-1 text-sm">
        <span>{{ t('purchaseOrders.create.notesField') }}</span>
        <textarea
          v-model="notes"
          rows="3"
          class="w-full rounded-md border border-border bg-background px-3 py-2"
        />
      </label>

      <label class="block space-y-1 text-sm">
        <span>{{ t('purchaseOrders.create.contractField') }}</span>
        <select v-model="contractId" class="w-full rounded-md border border-border bg-background px-3 py-2">
          <option value="">{{ t('purchaseOrders.create.contractNone') }}</option>
          <option v-for="contract in contracts" :key="contract.id" :value="String(contract.id)">
            {{ contract.contract_number }} — {{ contract.title }}
          </option>
        </select>
        <p v-if="contractsError" class="text-xs text-destructive">{{ contractsError }}</p>
        <p class="text-xs text-muted-foreground">{{ t('purchaseOrders.create.contractHint') }}</p>
      </label>

      <p v-if="actionError" class="text-sm text-destructive">{{ actionError }}</p>

      <PermissionGuard permission="purchase_orders.create">
        <button
          type="submit"
          class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground disabled:opacity-50"
          :disabled="!canSubmit"
        >
          {{ t('purchaseOrders.create.submit') }}
        </button>
      </PermissionGuard>
    </form>
  </div>
</template>
