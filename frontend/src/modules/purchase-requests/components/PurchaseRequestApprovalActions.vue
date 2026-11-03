<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { usePermissions } from '@/shared/composables/usePermissions'

import type { PurchaseRequest } from '../types/purchaseRequests'

const props = defineProps<{
  purchaseRequest: PurchaseRequest
}>()

const emit = defineEmits<{
  approve: [comment: string]
  reject: [reason: string]
  return: [reason: string]
  cancelApproved: [reason: string]
}>()

const { t } = useI18n()
const { can } = usePermissions()

const dialog = ref<'approve' | 'reject' | 'return' | 'cancelApproved' | null>(null)
const reason = ref('')
const comment = ref('')
const localError = ref('')

const underReview = computed(() => props.purchaseRequest.status === 'under_review')
const isApproved = computed(() => props.purchaseRequest.status === 'approved')

const canShowApprove = computed(
  () => underReview.value && can('purchase_requests.approve'),
)
const canShowReject = computed(
  () => underReview.value && can('purchase_requests.reject'),
)
const canShowReturn = computed(
  () => underReview.value && can('purchase_requests.return'),
)
const isProcurementEngaged = computed(
  () => props.purchaseRequest.procurement_engaged_at != null
    && props.purchaseRequest.procurement_engaged_at !== '',
)

const canShowCancelApproved = computed(
  () =>
    isApproved.value
    && !isProcurementEngaged.value
    && can('purchase_requests.cancel_approved'),
)

function open(kind: typeof dialog.value): void {
  dialog.value = kind
  reason.value = ''
  comment.value = ''
  localError.value = ''
}

function close(): void {
  dialog.value = null
  localError.value = ''
}

function submit(): void {
  localError.value = ''
  if (dialog.value === 'approve') {
    emit('approve', comment.value.trim())
    close()
    return
  }
  const text = reason.value.trim()
  if (text.length < 10) {
    localError.value = t('purchaseRequests.errors.reasonShort')
    return
  }
  if (dialog.value === 'reject') emit('reject', text)
  if (dialog.value === 'return') emit('return', text)
  if (dialog.value === 'cancelApproved') emit('cancelApproved', text)
  close()
}
</script>

<template>
  <div class="space-y-3">
    <div class="flex flex-wrap gap-2">
      <PermissionGuard v-if="canShowApprove" permission="purchase_requests.approve">
        <button
          type="button"
          data-testid="pr-approve-btn"
          class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground"
          @click="open('approve')"
        >
          {{ t('purchaseRequests.actions.approve') }}
        </button>
      </PermissionGuard>
      <PermissionGuard v-if="canShowReject" permission="purchase_requests.reject">
        <button
          type="button"
          class="rounded-md border border-border px-4 py-2 text-sm"
          @click="open('reject')"
        >
          {{ t('purchaseRequests.actions.reject') }}
        </button>
      </PermissionGuard>
      <PermissionGuard v-if="canShowReturn" permission="purchase_requests.return">
        <button
          type="button"
          class="rounded-md border border-border px-4 py-2 text-sm"
          @click="open('return')"
        >
          {{ t('purchaseRequests.actions.return') }}
        </button>
      </PermissionGuard>
      <PermissionGuard v-if="canShowCancelApproved" permission="purchase_requests.cancel_approved">
        <button
          type="button"
          class="rounded-md border border-destructive px-4 py-2 text-sm text-destructive"
          @click="open('cancelApproved')"
        >
          {{ t('purchaseRequests.actions.cancelApproved') }}
        </button>
      </PermissionGuard>
    </div>

    <div
      v-if="dialog"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
      role="dialog"
      aria-modal="true"
    >
      <div class="w-full max-w-md space-y-3 rounded-md bg-background p-4 shadow-lg">
        <h3 class="text-lg font-medium">
          <template v-if="dialog === 'approve'">{{ t('purchaseRequests.actions.approve') }}</template>
          <template v-else-if="dialog === 'reject'">{{ t('purchaseRequests.actions.reject') }}</template>
          <template v-else-if="dialog === 'return'">{{ t('purchaseRequests.actions.return') }}</template>
          <template v-else>{{ t('purchaseRequests.actions.cancelApproved') }}</template>
        </h3>
        <textarea
          v-if="dialog === 'approve'"
          v-model="comment"
          rows="3"
          class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
          :placeholder="t('purchaseRequests.actions.commentOptional')"
        />
        <textarea
          v-else
          v-model="reason"
          rows="3"
          class="w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
          :placeholder="t('purchaseRequests.actions.reasonRequired')"
        />
        <p v-if="localError" class="text-sm text-destructive">{{ localError }}</p>
        <div class="flex justify-end gap-2">
          <button type="button" class="rounded-md border border-border px-3 py-2 text-sm" @click="close">
            {{ t('purchaseRequests.discard') }}
          </button>
          <button
            type="button"
            class="rounded-md bg-primary px-3 py-2 text-sm text-primary-foreground"
            @click="submit"
          >
            {{ t('purchaseRequests.actions.confirm') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
