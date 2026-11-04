<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, RouterLink } from 'vue-router'
import { ArrowRight } from 'lucide-vue-next'

import { ApiError } from '@/shared/api/http'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'

import { nextCycleHint } from '@/modules/purchase-orders/utils/procurementCycleHints'

import {
  useCancelRfqMutation,
  useCreateOfferMutation,
  useInviteSupplierMutation,
  usePublishRfqMutation,
  useSelectSupplierMutation,
  useSubmitOfferMutation,
  useWithdrawOfferMutation,
} from '../mutations/useRfqMutations'
import { useRfqQuery } from '../queries/useRfqQuery'
import type { SupplierOffer } from '../types/rfq'

const { t } = useI18n()
const route = useRoute()
const toast = useToast()
const { can } = usePermissions()

const rfqId = computed(() => {
  const raw = route.params.id
  const value = Number(Array.isArray(raw) ? raw[0] : raw)
  return Number.isFinite(value) ? value : null
})

const { data, isLoading, isError, refetch } = useRfqQuery(rfqId)
const rfq = computed(() => data.value ?? null)
const actionError = ref('')
const cancelReason = ref('')
const inviteSupplierId = ref('')
const selectSupplierId = ref<number | null>(null)
const selectOfferId = ref<number | null>(null)
const selectionRationale = ref('')

const offerForm = reactive({
  supplier_id: '',
  notes: '',
  prices: {} as Record<number, string>,
})

watch(
  rfq,
  (value) => {
    if (!value?.lines) return
    value.lines.forEach((line) => {
      if (line.id != null && offerForm.prices[line.id] === undefined) {
        offerForm.prices[line.id] = ''
      }
    })
  },
  { immediate: true },
)

const publishMutation = usePublishRfqMutation()
const cancelMutation = useCancelRfqMutation()
const inviteMutation = useInviteSupplierMutation()
const createOfferMutation = useCreateOfferMutation()
const submitOfferMutation = useSubmitOfferMutation()
const withdrawOfferMutation = useWithdrawOfferMutation()
const selectMutation = useSelectSupplierMutation()

const canPublish = computed(
  () => rfq.value?.status === 'draft' && can('rfq.publish') && !publishMutation.isPending.value,
)
const canCancel = computed(
  () =>
    rfq.value != null
    && ['draft', 'published'].includes(rfq.value.status)
    && can('rfq.cancel')
    && !cancelMutation.isPending.value,
)
const isPublished = computed(() => rfq.value?.status === 'published')
const submittedOffers = computed(
  () => (rfq.value?.offers ?? []).filter((offer) => offer.status === 'submitted'),
)
const cycleHint = computed(() =>
  rfq.value ? nextCycleHint({ kind: 'rfq', status: rfq.value.status }) : null,
)

function mapError(error: unknown, fallback: string): string {
  if (!(error instanceof ApiError)) return fallback
  if (error.code === 'SUPPLIER_NOT_ELIGIBLE' || error.code === 'SUPPLIER_NOT_ACTIVE') {
    return t('rfq.errors.notEligible')
  }
  if (error.code === 'SELECTION_RATIONALE_REQUIRED') {
    return t('rfq.errors.rationaleRequired')
  }
  return fallback
}

async function onPublish(): Promise<void> {
  if (rfqId.value == null) return
  actionError.value = ''
  try {
    await publishMutation.mutateAsync(rfqId.value)
    toast.success(t('rfq.details.publishSuccess'))
    await refetch()
  } catch (error) {
    actionError.value = mapError(error, t('rfq.details.publishFailed'))
  }
}

async function onCancel(): Promise<void> {
  if (rfqId.value == null) return
  actionError.value = ''
  if (cancelReason.value.trim().length < 10) {
    actionError.value = t('rfq.errors.cancelReasonShort')
    return
  }
  try {
    await cancelMutation.mutateAsync({ id: rfqId.value, reason: cancelReason.value.trim() })
    toast.success(t('rfq.details.cancelSuccess'))
    await refetch()
  } catch {
    actionError.value = t('rfq.details.cancelFailed')
  }
}

async function onInvite(): Promise<void> {
  if (rfqId.value == null) return
  actionError.value = ''
  const supplierId = Number(inviteSupplierId.value)
  if (!Number.isFinite(supplierId) || supplierId < 1) {
    actionError.value = t('rfq.errors.generic')
    return
  }
  try {
    await inviteMutation.mutateAsync({ rfqId: rfqId.value, supplierId })
    toast.success(t('rfq.details.inviteSuccess'))
    inviteSupplierId.value = ''
    await refetch()
  } catch (error) {
    actionError.value = mapError(error, t('rfq.details.inviteFailed'))
  }
}

async function onCreateOffer(): Promise<void> {
  if (rfqId.value == null || !rfq.value?.lines?.length) return
  actionError.value = ''
  const supplierId = Number(offerForm.supplier_id)
  if (!Number.isFinite(supplierId) || supplierId < 1) {
    actionError.value = t('rfq.errors.generic')
    return
  }
  try {
    await createOfferMutation.mutateAsync({
      rfqId: rfqId.value,
      payload: {
        supplier_id: supplierId,
        notes: offerForm.notes.trim() || undefined,
        lines: rfq.value.lines.map((line) => ({
          procurement_rfq_line_id: line.id!,
          quantity: line.quantity,
          unit_price: offerForm.prices[line.id!] || '0',
        })),
      },
    })
    toast.success(t('rfq.details.createOfferSuccess'))
    await refetch()
  } catch (error) {
    actionError.value = mapError(error, t('rfq.details.createOfferFailed'))
  }
}

async function onSubmitOffer(offer: SupplierOffer): Promise<void> {
  if (rfqId.value == null) return
  actionError.value = ''
  try {
    await submitOfferMutation.mutateAsync({ offerId: offer.id, rfqId: rfqId.value })
    toast.success(t('rfq.details.submitOfferSuccess'))
    await refetch()
  } catch (error) {
    actionError.value = mapError(error, t('rfq.errors.generic'))
  }
}

async function onWithdrawOffer(offer: SupplierOffer): Promise<void> {
  if (rfqId.value == null) return
  actionError.value = ''
  try {
    await withdrawOfferMutation.mutateAsync({ offerId: offer.id, rfqId: rfqId.value })
    toast.success(t('rfq.details.withdrawOfferSuccess'))
    await refetch()
  } catch (error) {
    actionError.value = mapError(error, t('rfq.errors.generic'))
  }
}

async function onSelect(): Promise<void> {
  if (rfqId.value == null || selectSupplierId.value == null) return
  actionError.value = ''
  if (!selectionRationale.value.trim()) {
    actionError.value = t('rfq.errors.rationaleRequired')
    return
  }
  try {
    await selectMutation.mutateAsync({
      rfqId: rfqId.value,
      payload: {
        supplier_id: selectSupplierId.value,
        selection_rationale: selectionRationale.value.trim(),
        offer_id: selectOfferId.value,
      },
    })
    toast.success(t('rfq.details.selectSuccess'))
    await refetch()
  } catch (error) {
    actionError.value = mapError(error, t('rfq.details.selectFailed'))
  }
}

function prepareSelect(offer: SupplierOffer): void {
  selectSupplierId.value = offer.supplier_id
  selectOfferId.value = offer.id
}
</script>

<template>
  <div class="mx-auto max-w-5xl space-y-6">
    <RouterLink
      to="/app/rfqs"
      class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
    >
      <ArrowRight class="h-4 w-4" />
      {{ t('rfq.backToList') }}
    </RouterLink>

    <div v-if="isLoading" class="text-sm text-muted-foreground">{{ t('rfq.loading') }}</div>
    <div v-else-if="isError || !rfq" class="text-sm text-destructive">{{ t('rfq.loadError') }}</div>
    <template v-else>
      <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 class="text-2xl font-semibold">{{ rfq.rfq_number }}</h1>
          <p class="mt-1 text-muted-foreground">{{ rfq.title }}</p>
          <p class="mt-2 text-sm">
            {{ t('rfq.columns.status') }}:
            <span class="font-medium">{{ t(`rfq.statuses.${rfq.status}`) }}</span>
          </p>
          <p v-if="rfq.selected_supplier" class="mt-2 text-sm">
            {{ t('rfq.details.selectedSupplier') }}:
            <strong>{{ rfq.selected_supplier.display_name }}</strong>
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <PermissionGuard permission="rfq.publish">
            <button
              v-if="canPublish"
              type="button"
              class="rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground disabled:opacity-50"
              :disabled="!canPublish"
              @click="onPublish"
            >
              {{ t('rfq.details.publish') }}
            </button>
          </PermissionGuard>
          <PermissionGuard permission="purchase_orders.create">
            <RouterLink
              v-if="rfq.status === 'awarded'"
              class="rounded-md border border-border px-4 py-2 text-sm"
              :to="`/app/purchase-orders/create?rfq_id=${rfq.id}`"
            >
              {{ t('rfq.details.createPurchaseOrder') }}
            </RouterLink>
          </PermissionGuard>
        </div>
      </header>

      <p
        v-if="cycleHint"
        class="rounded-md border border-border bg-muted/30 px-3 py-2 text-sm text-muted-foreground"
      >
        <span class="font-medium text-foreground">{{ t('procurementCycle.nextAction') }}:</span>
        {{ t(cycleHint.labelKey) }}
      </p>

      <section class="space-y-2 rounded-md border border-border p-4 text-sm">
        <div>
          {{ t('rfq.details.linkedPr') }}:
          <RouterLink
            class="text-primary hover:underline"
            :to="`/app/purchase-requests/${rfq.purchase_request_id}`"
          >
            {{ rfq.purchase_request?.pr_number ?? `#${rfq.purchase_request_id}` }}
          </RouterLink>
        </div>
        <div v-if="rfq.purchase_request?.procurement_engagement_ref">
          {{ t('rfq.details.engagementRef') }}:
          <strong>{{ rfq.purchase_request.procurement_engagement_ref }}</strong>
        </div>
        <div v-if="rfq.selection_rationale" class="text-muted-foreground">
          {{ t('rfq.details.rationale') }}: {{ rfq.selection_rationale }}
        </div>
      </section>

      <section class="space-y-2">
        <h2 class="text-lg font-medium">{{ t('rfq.details.lines') }}</h2>
        <div
          v-if="!rfq.lines?.length"
          class="rounded-md border border-dashed border-border p-6 text-sm text-muted-foreground"
        >
          {{ t('rfq.details.noLines') }}
        </div>
        <div v-else class="overflow-x-auto rounded-md border border-border">
          <table class="min-w-full text-sm">
            <thead class="bg-muted/40 text-muted-foreground">
              <tr>
                <th class="px-3 py-2 text-start">#</th>
                <th class="px-3 py-2 text-start">{{ t('rfq.details.description') }}</th>
                <th class="px-3 py-2 text-start">{{ t('rfq.details.qty') }}</th>
                <th class="px-3 py-2 text-start">{{ t('rfq.details.unit') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="line in rfq.lines" :key="line.id ?? line.line_no" class="border-t border-border">
                <td class="px-3 py-2">{{ line.line_no }}</td>
                <td class="px-3 py-2">{{ line.description }}</td>
                <td class="px-3 py-2">{{ line.quantity }}</td>
                <td class="px-3 py-2">{{ line.unit }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section v-if="isPublished || rfq.status === 'awarded'" class="space-y-3 rounded-md border border-border p-4">
        <h2 class="text-lg font-medium">{{ t('rfq.details.invitations') }}</h2>
        <PermissionGuard v-if="isPublished" permission="rfq.invite">
          <div class="flex flex-wrap gap-2">
            <input
              v-model="inviteSupplierId"
              type="number"
              min="1"
              class="rounded-md border border-border bg-background px-3 py-2 text-sm"
              :placeholder="t('rfq.details.inviteSupplierId')"
            />
            <button
              type="button"
              class="rounded-md bg-primary px-3 py-2 text-sm text-primary-foreground"
              :disabled="inviteMutation.isPending.value"
              @click="onInvite"
            >
              {{ t('rfq.details.inviteSubmit') }}
            </button>
          </div>
        </PermissionGuard>
        <div v-if="!(rfq.invitations?.length)" class="text-sm text-muted-foreground">
          {{ t('rfq.details.noInvitations') }}
        </div>
        <ul v-else class="space-y-2 text-sm">
          <li
            v-for="invitation in rfq.invitations"
            :key="invitation.id"
            class="rounded-md border border-border px-3 py-2"
          >
            #{{ invitation.supplier_id }}
            <span v-if="invitation.supplier"> — {{ invitation.supplier.display_name }}</span>
            <span class="text-muted-foreground"> ({{ invitation.status }})</span>
          </li>
        </ul>
      </section>

      <section v-if="isPublished || rfq.status === 'awarded'" class="space-y-3 rounded-md border border-border p-4">
        <h2 class="text-lg font-medium">{{ t('rfq.details.offers') }}</h2>
        <p class="text-xs text-muted-foreground">{{ t('rfq.details.evaluationsNote') }}</p>

        <PermissionGuard v-if="isPublished" permission="rfq.record_offer">
          <div class="space-y-3 rounded-md bg-muted/30 p-3">
            <h3 class="font-medium">{{ t('rfq.details.createOffer') }}</h3>
            <input
              v-model="offerForm.supplier_id"
              type="number"
              min="1"
              class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
              :placeholder="t('rfq.details.inviteSupplierId')"
            />
            <div v-for="line in rfq.lines || []" :key="line.id" class="grid gap-2 sm:grid-cols-2">
              <div class="text-sm">{{ line.description }} ({{ line.quantity }} {{ line.unit }})</div>
              <input
                v-model="offerForm.prices[line.id!]"
                type="number"
                min="0"
                step="0.0001"
                class="rounded-md border border-border bg-background px-3 py-2 text-sm"
                :placeholder="t('rfq.details.unitPrice')"
              />
            </div>
            <textarea
              v-model="offerForm.notes"
              rows="2"
              class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
            />
            <button
              type="button"
              class="rounded-md bg-primary px-3 py-2 text-sm text-primary-foreground"
              :disabled="createOfferMutation.isPending.value"
              @click="onCreateOffer"
            >
              {{ t('rfq.details.createOffer') }}
            </button>
          </div>
        </PermissionGuard>

        <div v-if="!(rfq.offers?.length)" class="text-sm text-muted-foreground">
          {{ t('rfq.details.noOffers') }}
        </div>
        <div v-else class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-muted/40 text-muted-foreground">
              <tr>
                <th class="px-3 py-2 text-start">{{ t('rfq.columns.number') }}</th>
                <th class="px-3 py-2 text-start">{{ t('rfq.details.inviteSupplierId') }}</th>
                <th class="px-3 py-2 text-start">{{ t('rfq.columns.status') }}</th>
                <th class="px-3 py-2 text-start">{{ t('rfq.details.linesTotal') }}</th>
                <th class="px-3 py-2 text-start" />
              </tr>
            </thead>
            <tbody>
              <tr v-for="offer in rfq.offers" :key="offer.id" class="border-t border-border">
                <td class="px-3 py-2">{{ offer.offer_number }}</td>
                <td class="px-3 py-2">
                  {{ offer.supplier?.display_name || `#${offer.supplier_id}` }}
                </td>
                <td class="px-3 py-2">{{ t(`rfq.details.offerStatuses.${offer.status}`) }}</td>
                <td class="px-3 py-2">{{ offer.lines_total ?? '—' }} {{ offer.currency }}</td>
                <td class="px-3 py-2">
                  <div class="flex flex-wrap gap-2">
                    <PermissionGuard permission="rfq.record_offer">
                      <button
                        v-if="offer.status === 'draft' && isPublished"
                        type="button"
                        class="rounded-md border border-border px-2 py-1 text-xs"
                        @click="onSubmitOffer(offer)"
                      >
                        {{ t('rfq.details.submitOffer') }}
                      </button>
                      <button
                        v-if="offer.status === 'submitted' && isPublished"
                        type="button"
                        class="rounded-md border border-border px-2 py-1 text-xs"
                        @click="onWithdrawOffer(offer)"
                      >
                        {{ t('rfq.details.withdrawOffer') }}
                      </button>
                    </PermissionGuard>
                    <PermissionGuard permission="rfq.select_supplier">
                      <button
                        v-if="offer.status === 'submitted' && isPublished"
                        type="button"
                        class="rounded-md border border-primary px-2 py-1 text-xs text-primary"
                        @click="prepareSelect(offer)"
                      >
                        {{ t('rfq.details.selectSupplier') }}
                      </button>
                    </PermissionGuard>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section
        v-if="isPublished && can('rfq.select_supplier')"
        class="space-y-3 rounded-md border border-border p-4"
      >
        <h2 class="text-lg font-medium">{{ t('rfq.details.comparison') }}</h2>
        <div v-if="submittedOffers.length === 0" class="text-sm text-muted-foreground">
          {{ t('rfq.details.noOffers') }}
        </div>
        <template v-else>
          <p class="text-sm text-muted-foreground">
            {{ t('rfq.details.selectedSupplier') }}:
            <strong v-if="selectSupplierId">#{{ selectSupplierId }}</strong>
            <span v-else>—</span>
          </p>
          <textarea
            v-model="selectionRationale"
            rows="3"
            class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
            :placeholder="t('rfq.details.rationalePlaceholder')"
          />
          <button
            type="button"
            class="rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground disabled:opacity-50"
            :disabled="selectSupplierId == null || selectMutation.isPending.value"
            @click="onSelect"
          >
            {{ t('rfq.details.selectSupplier') }}
          </button>
        </template>
      </section>

      <section v-if="canCancel" class="space-y-2 rounded-md border border-border p-4">
        <h2 class="text-lg font-medium">{{ t('rfq.details.cancel') }}</h2>
        <textarea
          v-model="cancelReason"
          rows="3"
          class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
          :placeholder="t('rfq.details.cancelReasonPlaceholder')"
        />
        <button
          type="button"
          class="rounded-md border border-destructive px-4 py-2 text-sm text-destructive disabled:opacity-50"
          :disabled="!canCancel"
          @click="onCancel"
        >
          {{ t('rfq.details.cancelSubmit') }}
        </button>
      </section>

      <p v-if="actionError" class="text-sm text-destructive">{{ actionError }}</p>
    </template>
  </div>
</template>
