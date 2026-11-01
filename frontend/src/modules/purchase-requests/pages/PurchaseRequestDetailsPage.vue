<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, RouterLink } from 'vue-router'
import { ArrowRight } from 'lucide-vue-next'

import EntityDocumentsSection from '@/modules/documents/components/EntityDocumentsSection.vue'
import { ApiError } from '@/shared/api/http'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'

import PurchaseRequestApprovalActions from '../components/PurchaseRequestApprovalActions.vue'
import {
  useApprovePurchaseRequestMutation,
  useCancelApprovedPurchaseRequestMutation,
  useCancelPurchaseRequestMutation,
  useRecordFinancialReviewMutation,
  useRejectPurchaseRequestMutation,
  useReplacePurchaseRequestLinesMutation,
  useReturnPurchaseRequestMutation,
  useSubmitPurchaseRequestMutation,
  useUpdatePurchaseRequestMutation,
  useVerifyPriorityMutation,
} from '../mutations/usePurchaseRequestMutations'
import {
  useDuplicateHintsQuery,
  usePurchaseRequestFinancialReviewsQuery,
  usePurchaseRequestQuery,
  usePurchaseRequestVersionsQuery,
} from '../queries/usePurchaseRequestsQuery'
import { mapPurchaseRequestErrorCode } from '../validation/purchaseRequestValidation'

const { t } = useI18n()
const route = useRoute()
const toast = useToast()
const { can } = usePermissions()

const purchaseRequestId = computed(() => {
  const raw = route.params.id
  const value = Number(Array.isArray(raw) ? raw[0] : raw)
  return Number.isFinite(value) ? value : null
})

const { data, isLoading, isError, refetch } = usePurchaseRequestQuery(purchaseRequestId)
const pr = computed(() => data.value ?? null)
const { data: versionsData, refetch: refetchVersions } = usePurchaseRequestVersionsQuery(purchaseRequestId)
const versions = computed(() => versionsData.value ?? [])
const { data: reviewsData, refetch: refetchReviews } = usePurchaseRequestFinancialReviewsQuery(
  purchaseRequestId,
)
const reviews = computed(() => reviewsData.value ?? [])
const { data: hintsData, refetch: refetchHints } = useDuplicateHintsQuery(purchaseRequestId)
const hints = computed(() => hintsData.value ?? [])

const actionError = ref('')
const ackDuplicate = ref(false)
const cancelReason = ref('')
const reviewForm = reactive({
  outcome: 'cleared',
  justification: '',
})
const editTitle = ref('')
const editPurpose = ref('')
const editLines = ref<
  Array<{
    description: string
    specifications: string
    quantity: number
    unit: string
    need_date: string
    unit_estimate: number | null
    unpriced_reason: string
  }>
>([])
const linesError = ref('')

const submitMutation = useSubmitPurchaseRequestMutation()
const cancelMutation = useCancelPurchaseRequestMutation()
const verifyMutation = useVerifyPriorityMutation()
const reviewMutation = useRecordFinancialReviewMutation()
const updateMutation = useUpdatePurchaseRequestMutation()
const replaceLinesMutation = useReplacePurchaseRequestLinesMutation()
const approveMutation = useApprovePurchaseRequestMutation()
const rejectMutation = useRejectPurchaseRequestMutation()
const returnMutation = useReturnPurchaseRequestMutation()
const cancelApprovedMutation = useCancelApprovedPurchaseRequestMutation()

const isEditable = computed(() => pr.value?.status === 'draft' || pr.value?.status === 'returned')
const canEditLines = computed(() => isEditable.value && can('purchase_requests.update'))
const canCancel = computed(
  () =>
    pr.value != null
    && ['draft', 'submitted', 'returned', 'under_review'].includes(pr.value.status)
    && can('purchase_requests.cancel'),
)
const showRoutingBlocked = computed(
  () =>
    pr.value?.status === 'submitted'
    && !!pr.value.routing_block_code,
)
const activeApprovalInstance = computed(() => {
  const instances = pr.value?.approval_instances ?? []
  return instances.find((item) => item.status === 'active') ?? instances[0] ?? null
})
const approvalSteps = computed(() => activeApprovalInstance.value?.steps ?? [])

watch(
  pr,
  (value) => {
    if (!value) return
    editTitle.value = value.title
    editPurpose.value = value.purpose
    editLines.value = (value.lines ?? []).map((line) => ({
      description: line.description,
      specifications: line.specifications ?? '',
      quantity: Number(line.quantity),
      unit: line.unit,
      need_date: line.need_date,
      unit_estimate: line.unit_estimate != null ? Number(line.unit_estimate) : null,
      unpriced_reason: line.unpriced_reason ?? '',
    }))
    if (editLines.value.length === 0) {
      editLines.value = [
        {
          description: '',
          specifications: '',
          quantity: 1,
          unit: 'قطعة',
          need_date: '',
          unit_estimate: null,
          unpriced_reason: '',
        },
      ]
    }
  },
  { immediate: true },
)

function addEditLine(): void {
  editLines.value.push({
    description: '',
    specifications: '',
    quantity: 1,
    unit: 'قطعة',
    need_date: '',
    unit_estimate: null,
    unpriced_reason: '',
  })
}

function removeEditLine(index: number): void {
  if (editLines.value.length <= 1) return
  editLines.value.splice(index, 1)
}

async function saveLines(): Promise<void> {
  if (!pr.value) return
  linesError.value = ''
  actionError.value = ''
  const payload = editLines.value.map((line, index) => ({
    line_no: index + 1,
    description: line.description.trim(),
    specifications: line.specifications.trim() || null,
    quantity: Number(line.quantity),
    unit: line.unit.trim(),
    need_date: line.need_date,
    unit_estimate:
      line.unit_estimate != null && String(line.unit_estimate) !== ''
        ? Number(line.unit_estimate)
        : null,
    unpriced_reason: line.unpriced_reason.trim() || null,
  }))
  for (const [index, line] of payload.entries()) {
    if (!line.description || !(line.quantity > 0) || !line.unit || !line.need_date) {
      linesError.value = t('purchaseRequests.errors.validation')
      return
    }
    const priced = line.unit_estimate != null
    if (!priced && !line.unpriced_reason) {
      linesError.value = t('purchaseRequests.errors.unpricedReason')
      return
    }
    void index
  }
  try {
    await replaceLinesMutation.mutateAsync({ id: pr.value.id, lines: payload })
    toast.success(t('purchaseRequests.linesUpdated'))
    await Promise.all([refetch(), refetchHints()])
  } catch (error) {
    actionError.value = t(mapPurchaseRequestErrorCode(error instanceof ApiError ? error.code : undefined))
  }
}

async function saveHeader(): Promise<void> {
  if (!pr.value) return
  actionError.value = ''
  try {
    await updateMutation.mutateAsync({
      id: pr.value.id,
      payload: { title: editTitle.value, purpose: editPurpose.value },
    })
    toast.success(t('purchaseRequests.updated'))
    await refetch()
  } catch (error) {
    actionError.value = t(mapPurchaseRequestErrorCode(error instanceof ApiError ? error.code : undefined))
  }
}

async function submitPr(): Promise<void> {
  if (!pr.value) return
  actionError.value = ''
  try {
    await submitMutation.mutateAsync({
      id: pr.value.id,
      acknowledgeDuplicateWarning: ackDuplicate.value,
    })
    toast.success(t('purchaseRequests.submitted'))
    await Promise.all([refetch(), refetchVersions(), refetchHints()])
  } catch (error) {
    actionError.value = t(mapPurchaseRequestErrorCode(error instanceof ApiError ? error.code : undefined))
  }
}

async function cancelPr(): Promise<void> {
  if (!pr.value) return
  actionError.value = ''
  try {
    await cancelMutation.mutateAsync({ id: pr.value.id, reason: cancelReason.value.trim() })
    toast.success(t('purchaseRequests.cancelled'))
    await refetch()
  } catch (error) {
    actionError.value = t(mapPurchaseRequestErrorCode(error instanceof ApiError ? error.code : undefined))
  }
}

async function verifyPriority(): Promise<void> {
  if (!pr.value) return
  actionError.value = ''
  try {
    await verifyMutation.mutateAsync(pr.value.id)
    toast.success(t('purchaseRequests.priorityVerified'))
    await refetch()
  } catch (error) {
    actionError.value = t(mapPurchaseRequestErrorCode(error instanceof ApiError ? error.code : undefined))
  }
}

async function saveReview(): Promise<void> {
  if (!pr.value) return
  actionError.value = ''
  try {
    await reviewMutation.mutateAsync({
      id: pr.value.id,
      outcome: reviewForm.outcome,
      justification: reviewForm.justification.trim(),
      version_no: pr.value.version_no,
    })
    toast.success(t('purchaseRequests.reviewRecorded'))
    reviewForm.justification = ''
    await Promise.all([refetch(), refetchReviews()])
  } catch (error) {
    actionError.value = t(mapPurchaseRequestErrorCode(error instanceof ApiError ? error.code : undefined))
  }
}

async function onApprove(comment: string): Promise<void> {
  if (!pr.value) return
  actionError.value = ''
  try {
    await approveMutation.mutateAsync({ id: pr.value.id, comment })
    toast.success(t('purchaseRequests.actions.approvedToast'))
    await refetch()
  } catch (error) {
    actionError.value = t(mapPurchaseRequestErrorCode(error instanceof ApiError ? error.code : undefined))
  }
}

async function onReject(reason: string): Promise<void> {
  if (!pr.value) return
  actionError.value = ''
  try {
    await rejectMutation.mutateAsync({ id: pr.value.id, reason })
    toast.success(t('purchaseRequests.actions.rejectedToast'))
    await refetch()
  } catch (error) {
    actionError.value = t(mapPurchaseRequestErrorCode(error instanceof ApiError ? error.code : undefined))
  }
}

async function onReturn(reason: string): Promise<void> {
  if (!pr.value) return
  actionError.value = ''
  try {
    await returnMutation.mutateAsync({ id: pr.value.id, reason })
    toast.success(t('purchaseRequests.actions.returnedToast'))
    await refetch()
  } catch (error) {
    actionError.value = t(mapPurchaseRequestErrorCode(error instanceof ApiError ? error.code : undefined))
  }
}

async function onCancelApproved(reason: string): Promise<void> {
  if (!pr.value) return
  actionError.value = ''
  try {
    await cancelApprovedMutation.mutateAsync({ id: pr.value.id, reason })
    toast.success(t('purchaseRequests.actions.cancelApprovedToast'))
    await refetch()
  } catch (error) {
    actionError.value = t(mapPurchaseRequestErrorCode(error instanceof ApiError ? error.code : undefined))
  }
}
</script>

<template>
  <div class="mx-auto max-w-5xl space-y-6">
    <RouterLink
      to="/app/purchase-requests"
      class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
    >
      <ArrowRight class="h-4 w-4" />
      {{ t('purchaseRequests.backToList') }}
    </RouterLink>

    <div v-if="isLoading" class="text-sm text-muted-foreground">{{ t('purchaseRequests.loading') }}</div>
    <div v-else-if="isError || !pr" class="text-sm text-destructive">{{ t('purchaseRequests.loadError') }}</div>
    <template v-else>
      <header class="space-y-2">
        <div class="flex flex-wrap items-center gap-3">
          <h1 class="text-2xl font-semibold">{{ pr.pr_number }}</h1>
          <span class="rounded-md bg-muted px-2 py-1 text-xs">
            {{ t(`purchaseRequests.statuses.${pr.status === 'under_review' ? 'underReview' : pr.status}`) }}
          </span>
          <span
            v-if="pr.pending_approval_engine && !showRoutingBlocked"
            class="rounded-md bg-amber-50 px-2 py-1 text-xs text-amber-800"
          >
            {{ t('purchaseRequests.pendingApprovalEngine') }}
          </span>
        </div>
        <p class="text-sm text-muted-foreground">
          {{ t('purchaseRequests.versionLabel', { n: pr.version_no }) }}
          · {{ t(`purchaseRequests.kinds.${pr.request_kind}`) }}
          · {{ t(`purchaseRequests.priorities.${pr.priority}`) }}
        </p>
      </header>

      <section
        v-if="showRoutingBlocked"
        class="rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950"
        data-testid="pr-routing-blocked-banner"
      >
        <h2 class="font-medium">{{ t('purchaseRequests.routingBlocked.title') }}</h2>
        <p class="mt-1">
          {{ pr.routing_block_message || t('purchaseRequests.routingBlocked.fallback') }}
        </p>
        <p class="mt-1 text-xs">{{ pr.routing_block_code }}</p>
        <PermissionGuard permission="purchase_requests.manage_approval_config">
          <RouterLink
            to="/app/purchase-approval-settings"
            class="mt-2 inline-block text-sm text-primary hover:underline"
          >
            {{ t('purchaseRequests.routingBlocked.adminCta') }}
          </RouterLink>
        </PermissionGuard>
      </section>

      <section v-if="approvalSteps.length" class="space-y-3 rounded-md border border-border p-4">
        <h2 class="text-lg font-medium">{{ t('purchaseRequests.approvalTimeline.title') }}</h2>
        <ol class="space-y-2 text-sm">
          <li
            v-for="step in approvalSteps"
            :key="step.id"
            class="rounded-md border border-border px-3 py-2"
          >
            <div class="flex flex-wrap items-center justify-between gap-2">
              <span class="font-medium">
                {{ t('purchaseRequests.approvalTimeline.level', { n: step.level_order }) }}
              </span>
              <span class="text-xs text-muted-foreground">{{ step.status }}</span>
            </div>
            <div v-if="step.due_at" class="mt-1 text-xs text-muted-foreground">
              {{ t('purchaseRequests.approvalTimeline.due', { at: step.due_at }) }}
            </div>
            <div v-if="step.escalated_at" class="mt-1 text-xs text-amber-700">
              {{ t('purchaseRequests.approvalTimeline.escalated') }}
            </div>
            <div v-if="step.decision_reason" class="mt-1 text-xs">{{ step.decision_reason }}</div>
          </li>
        </ol>
      </section>

      <PurchaseRequestApprovalActions
        v-if="pr.status === 'under_review' || pr.status === 'approved'"
        :purchase-request="pr"
        @approve="onApprove"
        @reject="onReject"
        @return="onReturn"
        @cancel-approved="onCancelApproved"
      />

      <section class="grid gap-4 rounded-md border border-border p-4 sm:grid-cols-2">
        <template v-if="isEditable && can('purchase_requests.update')">
          <label class="block space-y-1 text-sm sm:col-span-2">
            <span>{{ t('purchaseRequests.fields.title') }}</span>
            <input v-model="editTitle" type="text" class="w-full rounded-md border border-border bg-background px-3 py-2" />
          </label>
          <label class="block space-y-1 text-sm sm:col-span-2">
            <span>{{ t('purchaseRequests.fields.purpose') }}</span>
            <textarea v-model="editPurpose" rows="3" class="w-full rounded-md border border-border bg-background px-3 py-2" />
          </label>
          <button
            type="button"
            class="rounded-md border border-border px-3 py-2 text-sm"
            @click="saveHeader"
          >
            {{ t('purchaseRequests.saveHeader') }}
          </button>
        </template>
        <template v-else>
          <div>
            <div class="text-xs text-muted-foreground">{{ t('purchaseRequests.fields.title') }}</div>
            <div class="font-medium">{{ pr.title }}</div>
          </div>
          <div class="sm:col-span-2">
            <div class="text-xs text-muted-foreground">{{ t('purchaseRequests.fields.purpose') }}</div>
            <div>{{ pr.purpose }}</div>
          </div>
        </template>
        <div>
          <div class="text-xs text-muted-foreground">{{ t('purchaseRequests.columns.total') }}</div>
          <div class="font-medium">{{ pr.estimated_total_priced }} {{ pr.currency }}</div>
          <div v-if="pr.has_unpriced_lines" class="text-xs text-amber-700">
            {{ t('purchaseRequests.hasUnpriced') }}
          </div>
        </div>
        <div>
          <div class="text-xs text-muted-foreground">{{ t('purchaseRequests.fields.requester') }}</div>
          <div>{{ pr.requested_by?.name || '—' }}</div>
        </div>
        <div v-if="pr.optional_supplier_id">
          <div class="text-xs text-muted-foreground">{{ t('purchaseRequests.fields.optionalSupplierId') }}</div>
          <div>#{{ pr.optional_supplier_id }}</div>
        </div>
      </section>

      <section class="space-y-3">
        <div class="flex items-center justify-between gap-3">
          <h2 class="text-lg font-medium">{{ t('purchaseRequests.linesTitle') }}</h2>
          <button
            v-if="canEditLines"
            type="button"
            class="text-sm text-primary"
            @click="addEditLine"
          >
            {{ t('purchaseRequests.addLine') }}
          </button>
        </div>

        <template v-if="canEditLines">
          <div
            v-for="(line, index) in editLines"
            :key="index"
            class="space-y-3 rounded-md border border-border p-4"
          >
            <div class="flex items-center justify-between text-sm font-medium">
              <span>{{ t('purchaseRequests.lineN', { n: index + 1 }) }}</span>
              <button
                v-if="editLines.length > 1"
                type="button"
                class="text-destructive"
                @click="removeEditLine(index)"
              >
                {{ t('purchaseRequests.removeLine') }}
              </button>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
              <label class="block space-y-1 text-sm sm:col-span-2">
                <span>{{ t('purchaseRequests.fields.description') }}</span>
                <input v-model="line.description" type="text" class="w-full rounded-md border border-border bg-background px-3 py-2" />
              </label>
              <label class="block space-y-1 text-sm sm:col-span-2">
                <span>{{ t('purchaseRequests.fields.specifications') }}</span>
                <input v-model="line.specifications" type="text" class="w-full rounded-md border border-border bg-background px-3 py-2" />
              </label>
              <label class="block space-y-1 text-sm">
                <span>{{ t('purchaseRequests.fields.quantity') }}</span>
                <input v-model.number="line.quantity" type="number" min="0.0001" step="any" class="w-full rounded-md border border-border bg-background px-3 py-2" />
              </label>
              <label class="block space-y-1 text-sm">
                <span>{{ t('purchaseRequests.fields.unitMeasure') }}</span>
                <input v-model="line.unit" type="text" class="w-full rounded-md border border-border bg-background px-3 py-2" />
              </label>
              <label class="block space-y-1 text-sm">
                <span>{{ t('purchaseRequests.fields.needDate') }}</span>
                <input v-model="line.need_date" type="date" class="w-full rounded-md border border-border bg-background px-3 py-2" />
              </label>
              <label class="block space-y-1 text-sm">
                <span>{{ t('purchaseRequests.fields.unitEstimate') }}</span>
                <input v-model.number="line.unit_estimate" type="number" min="0" step="0.01" class="w-full rounded-md border border-border bg-background px-3 py-2" />
              </label>
              <label class="block space-y-1 text-sm sm:col-span-2">
                <span>{{ t('purchaseRequests.fields.unpricedReason') }}</span>
                <input v-model="line.unpriced_reason" type="text" class="w-full rounded-md border border-border bg-background px-3 py-2" />
              </label>
            </div>
          </div>
          <p v-if="linesError" class="text-sm text-destructive">{{ linesError }}</p>
          <button
            type="button"
            class="rounded-md border border-border px-3 py-2 text-sm"
            :disabled="replaceLinesMutation.isPending.value"
            @click="saveLines"
          >
            {{ t('purchaseRequests.saveLines') }}
          </button>
        </template>

        <div v-else class="overflow-x-auto rounded-md border border-border">
          <table class="min-w-full text-sm">
            <thead class="bg-muted/40 text-muted-foreground">
              <tr>
                <th class="px-3 py-2 text-start">#</th>
                <th class="px-3 py-2 text-start">{{ t('purchaseRequests.fields.description') }}</th>
                <th class="px-3 py-2 text-start">{{ t('purchaseRequests.fields.quantity') }}</th>
                <th class="px-3 py-2 text-start">{{ t('purchaseRequests.fields.unitEstimate') }}</th>
                <th class="px-3 py-2 text-start">{{ t('purchaseRequests.fields.needDate') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="line in pr.lines ?? []" :key="line.id ?? line.line_no" class="border-t border-border">
                <td class="px-3 py-2">{{ line.line_no }}</td>
                <td class="px-3 py-2">
                  {{ line.description }}
                  <div v-if="line.unpriced_reason" class="text-xs text-amber-700">
                    {{ line.unpriced_reason }}
                  </div>
                </td>
                <td class="px-3 py-2">{{ line.quantity }} {{ line.unit }}</td>
                <td class="px-3 py-2">{{ line.unit_estimate ?? '—' }}</td>
                <td class="px-3 py-2">{{ line.need_date }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section v-if="hints.length" class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm">
        <h2 class="font-medium text-amber-900">{{ t('purchaseRequests.duplicatesTitle') }}</h2>
        <ul class="mt-2 list-disc ps-5 text-amber-900">
          <li v-for="hint in hints" :key="hint.purchase_request_id">
            {{ hint.pr_number }}
            ({{ hint.matched_line_nos.join(', ') }})
          </li>
        </ul>
        <label v-if="isEditable" class="mt-3 flex items-center gap-2">
          <input v-model="ackDuplicate" type="checkbox" />
          <span>{{ t('purchaseRequests.acknowledgeDuplicates') }}</span>
        </label>
      </section>

      <section class="flex flex-wrap gap-3">
        <PermissionGuard v-if="isEditable" permission="purchase_requests.submit">
          <button
            type="button"
            class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground"
            @click="submitPr"
          >
            {{ t('purchaseRequests.submit') }}
          </button>
        </PermissionGuard>
        <PermissionGuard permission="purchase_requests.verify_priority">
          <button
            type="button"
            class="rounded-md border border-border px-4 py-2 text-sm"
            @click="verifyPriority"
          >
            {{ t('purchaseRequests.verifyPriority') }}
          </button>
        </PermissionGuard>
      </section>

      <section
        v-if="canCancel"
        class="space-y-2 rounded-md border border-border p-4"
      >
        <h2 class="text-lg font-medium">{{ t('purchaseRequests.cancelTitle') }}</h2>
        <textarea
          v-model="cancelReason"
          rows="2"
          class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
          :placeholder="t('purchaseRequests.cancelReasonPlaceholder')"
        />
        <button
          type="button"
          class="rounded-md border border-destructive px-3 py-2 text-sm text-destructive"
          @click="cancelPr"
        >
          {{ t('purchaseRequests.cancelAction') }}
        </button>
      </section>

      <PermissionGuard permission="purchase_requests.financial_review">
        <section class="space-y-3 rounded-md border border-border p-4">
          <h2 class="text-lg font-medium">{{ t('purchaseRequests.financialReviewTitle') }}</h2>
          <p class="text-xs text-muted-foreground">
            {{ t('purchaseRequests.financialReviewHint', { version: pr.version_no }) }}
          </p>
          <div class="grid gap-3 sm:grid-cols-2">
            <label class="block space-y-1 text-sm">
              <span>{{ t('purchaseRequests.fields.outcome') }}</span>
              <select v-model="reviewForm.outcome" class="w-full rounded-md border border-border bg-background px-3 py-2">
                <option value="cleared">{{ t('purchaseRequests.outcomes.cleared') }}</option>
                <option value="cleared_with_conditions">
                  {{ t('purchaseRequests.outcomes.clearedWithConditions') }}
                </option>
                <option value="not_cleared">{{ t('purchaseRequests.outcomes.notCleared') }}</option>
              </select>
            </label>
            <label class="block space-y-1 text-sm sm:col-span-2">
              <span>{{ t('purchaseRequests.fields.justification') }}</span>
              <textarea
                v-model="reviewForm.justification"
                rows="2"
                class="w-full rounded-md border border-border bg-background px-3 py-2"
              />
            </label>
          </div>
          <button
            type="button"
            class="rounded-md border border-border px-3 py-2 text-sm"
            @click="saveReview"
          >
            {{ t('purchaseRequests.recordReview') }}
          </button>
        </section>
      </PermissionGuard>

      <section class="space-y-2">
        <h2 class="text-lg font-medium">{{ t('purchaseRequests.reviewsHistory') }}</h2>
        <div v-if="reviews.length === 0" class="text-sm text-muted-foreground">
          {{ t('purchaseRequests.noReviews') }}
        </div>
        <ul v-else class="space-y-2 text-sm">
          <li
            v-for="review in reviews"
            :key="review.id"
            class="rounded-md border border-border px-3 py-2"
          >
            v{{ review.version_no }} · {{ review.outcome }} · {{ review.justification }}
          </li>
        </ul>
      </section>

      <section class="space-y-2">
        <h2 class="text-lg font-medium">{{ t('purchaseRequests.versionsTitle') }}</h2>
        <div v-if="versions.length === 0" class="text-sm text-muted-foreground">
          {{ t('purchaseRequests.noVersions') }}
        </div>
        <ul v-else class="space-y-2 text-sm">
          <li
            v-for="version in versions"
            :key="version.id"
            class="rounded-md border border-border px-3 py-2"
          >
            v{{ version.version_no }} · {{ version.status }} · {{ version.snapshot_at }}
            · {{ version.estimated_total_priced }}
          </li>
        </ul>
      </section>

      <EntityDocumentsSection
        linkable-type="purchase_request"
        :linkable-id="pr.id"
        :link-label="`${pr.pr_number} — ${pr.title}`"
      />

      <p v-if="actionError" class="text-sm text-destructive">{{ actionError }}</p>
    </template>
  </div>
</template>
