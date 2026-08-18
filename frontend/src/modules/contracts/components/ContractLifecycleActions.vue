<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { Loader2, X } from 'lucide-vue-next'

import { ApiError } from '@/shared/api/http'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'

import {
  useApproveContractMutation,
  useCancelContractMutation,
  useCloseContractMutation,
  useDeleteContractMutation,
  useExecuteContractMutation,
  useRenewContractMutation,
  useReturnContractDraftMutation,
  useSignContractMutation,
  useSubmitContractReviewMutation,
} from '../mutations/useContractMutations'
import type { Contract, ContractLifecycleAction } from '../types/contracts'
import {
  availableLifecycleActions,
  mapContractErrorCode,
} from '../validation/contractValidation'

const props = defineProps<{
  contract: Contract
}>()

const emit = defineEmits<{
  refreshed: []
}>()

const { t } = useI18n()
const router = useRouter()
const toast = useToast()
const { confirm } = useConfirm()
const { can } = usePermissions()

const submitMutation = useSubmitContractReviewMutation()
const returnMutation = useReturnContractDraftMutation()
const approveMutation = useApproveContractMutation()
const signMutation = useSignContractMutation()
const executeMutation = useExecuteContractMutation()
const closeMutation = useCloseContractMutation()
const cancelMutation = useCancelContractMutation()
const renewMutation = useRenewContractMutation()
const deleteMutation = useDeleteContractMutation()

const commentDialogOpen = ref(false)
const commentAction = ref<ContractLifecycleAction | null>(null)
const commentText = ref('')
const commentError = ref('')
const actionError = ref('')

const hasRenewalChild = computed(
  () => props.contract.renewal_child != null || props.contract.status === 'renewed',
)

const actions = computed(() =>
  availableLifecycleActions(props.contract.status, {
    hasRenewalChild: hasRenewalChild.value,
  }).filter((item) => can(item.permission)),
)

const canDelete = computed(
  () => props.contract.status === 'draft' && can('contracts.delete'),
)

const isPending = computed(
  () =>
    submitMutation.isPending.value ||
    returnMutation.isPending.value ||
    approveMutation.isPending.value ||
    signMutation.isPending.value ||
    executeMutation.isPending.value ||
    closeMutation.isPending.value ||
    cancelMutation.isPending.value ||
    renewMutation.isPending.value ||
    deleteMutation.isPending.value,
)

function apiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) {
    return t('contracts.errors.generic')
  }
  const mapped = mapContractErrorCode(error.code)
  if (mapped !== 'generic') {
    return t(`contracts.errors.${mapped}`)
  }
  return error.message || t('contracts.errors.generic')
}

function actionLabel(action: ContractLifecycleAction): string {
  return t(`contracts.lifecycle.${action}`)
}

function openCommentDialog(action: ContractLifecycleAction): void {
  commentAction.value = action
  commentText.value = ''
  commentError.value = ''
  commentDialogOpen.value = true
}

function closeCommentDialog(): void {
  commentDialogOpen.value = false
  commentAction.value = null
  commentText.value = ''
  commentError.value = ''
}

async function runAction(
  action: ContractLifecycleAction,
  comment?: string | null,
): Promise<void> {
  actionError.value = ''
  const id = props.contract.id
  const payload = comment ? { comment } : {}

  try {
    switch (action) {
      case 'submit_review':
        await submitMutation.mutateAsync({ id, payload })
        toast.success(t('contracts.toasts.submitted'))
        break
      case 'return_draft':
        await returnMutation.mutateAsync({ id, payload: { comment: comment ?? '' } })
        toast.success(t('contracts.toasts.returned'))
        break
      case 'approve':
        await approveMutation.mutateAsync({ id, payload })
        toast.success(t('contracts.toasts.approved'))
        break
      case 'sign':
        await signMutation.mutateAsync({ id, payload })
        toast.success(t('contracts.toasts.signed'))
        break
      case 'execute':
        await executeMutation.mutateAsync({ id, payload })
        toast.success(t('contracts.toasts.executed'))
        break
      case 'close':
        await closeMutation.mutateAsync({ id, payload })
        toast.success(t('contracts.toasts.closed'))
        break
      case 'cancel':
        await cancelMutation.mutateAsync({ id, payload: { comment: comment ?? '' } })
        toast.success(t('contracts.toasts.cancelled'))
        break
      case 'renew': {
        const result = await renewMutation.mutateAsync({ id, payload })
        toast.success(t('contracts.toasts.renewed'))
        await router.push(`/app/contracts/${result.successor.id}`)
        return
      }
    }
    emit('refreshed')
  } catch (error) {
    actionError.value = apiMessage(error)
    toast.error(apiMessage(error))
  }
}

async function handleAction(action: ContractLifecycleAction): Promise<void> {
  const def = actions.value.find((item) => item.action === action)
  if (!def) return

  if (def.requiresComment) {
    openCommentDialog(action)
    return
  }

  if (action === 'sign') {
    const confirmed = await confirm({
      title: t('contracts.confirmSignTitle'),
      message: t('contracts.confirmSignBody'),
      confirmLabel: t('contracts.confirmSignCta'),
      cancelLabel: t('contracts.cancel'),
      variant: 'warning',
    })
    if (!confirmed) return
    await runAction(action)
    return
  }

  if (action === 'renew') {
    const confirmed = await confirm({
      title: t('contracts.confirmRenewTitle'),
      message: t('contracts.confirmRenewBody'),
      confirmLabel: t('contracts.confirmRenewCta'),
      cancelLabel: t('contracts.cancel'),
      variant: 'primary',
    })
    if (!confirmed) return
    await runAction(action)
    return
  }

  if (action === 'approve' || action === 'execute' || action === 'close') {
    const confirmed = await confirm({
      title: t(`contracts.confirm.${action}.title`),
      message: t(`contracts.confirm.${action}.body`),
      confirmLabel: t(`contracts.confirm.${action}.cta`),
      cancelLabel: t('contracts.cancel'),
      variant: action === 'close' ? 'warning' : 'primary',
    })
    if (!confirmed) return
  }

  await runAction(action)
}

async function submitCommentAction(): Promise<void> {
  if (!commentAction.value) return
  const trimmed = commentText.value.trim()
  if (!trimmed) {
    commentError.value = t('contracts.validation.commentRequired')
    return
  }
  const action = commentAction.value
  closeCommentDialog()
  await runAction(action, trimmed)
}

async function handleDelete(): Promise<void> {
  const confirmed = await confirm({
    title: t('contracts.confirmDeleteTitle'),
    message: t('contracts.confirmDeleteBody'),
    confirmLabel: t('contracts.confirmDeleteCta'),
    cancelLabel: t('contracts.cancel'),
    variant: 'danger',
  })
  if (!confirmed) return
  try {
    await deleteMutation.mutateAsync(props.contract.id)
    toast.success(t('contracts.toasts.deleted'))
    await router.push('/app/contracts')
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

function actionButtonClass(action: ContractLifecycleAction): string {
  if (action === 'cancel') {
    return 'border-red-200 bg-red-50 text-red-800 hover:bg-red-100'
  }
  if (action === 'renew') {
    return 'border-teal-200 bg-teal-50 text-teal-900 hover:bg-teal-100'
  }
  if (action === 'close' || action === 'return_draft') {
    return 'border-amber-200 bg-amber-50 text-amber-900 hover:bg-amber-100'
  }
  return 'border-brand-border bg-brand-surface text-brand-primary-dark hover:bg-brand-primary-soft'
}
</script>

<template>
  <div class="space-y-3">
    <div class="flex flex-col gap-2">
      <button
        v-for="item in actions"
        :key="item.action"
        type="button"
        class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl border px-4 text-sm font-semibold transition disabled:opacity-50 xl:justify-start"
        :class="actionButtonClass(item.action)"
        :disabled="isPending"
        @click="handleAction(item.action)"
      >
        <Loader2 v-if="isPending" class="h-3.5 w-3.5 animate-spin" />
        {{ actionLabel(item.action) }}
      </button>

      <PermissionGuard v-if="canDelete" permission="contracts.delete">
        <button
          type="button"
          class="inline-flex h-10 w-full items-center justify-center rounded-xl border border-red-200 bg-white px-4 text-sm font-semibold text-red-700 transition hover:bg-red-50 disabled:opacity-50 xl:justify-start"
          :disabled="isPending"
          @click="handleDelete"
        >
          {{ t('contracts.actions.delete') }}
        </button>
      </PermissionGuard>
    </div>

    <p
      v-if="actionError"
      class="rounded-xl border border-red-200 bg-red-50 px-3.5 py-2.5 text-sm text-red-700"
      role="alert"
    >
      {{ actionError }}
    </p>

    <Teleport to="body">
      <div
        v-if="commentDialogOpen"
        class="fixed inset-0 z-[460] flex items-center justify-center p-4"
        role="presentation"
      >
        <div
          class="absolute inset-0 bg-[rgba(15,23,20,0.38)]"
          aria-hidden="true"
          @click="closeCommentDialog"
        />
        <div
          class="relative w-full max-w-[420px] rounded-2xl border border-brand-border bg-brand-surface shadow-xl"
          role="dialog"
          aria-modal="true"
          @click.stop
        >
          <button
            type="button"
            class="absolute end-3 top-3 inline-flex h-8 w-8 items-center justify-center rounded-lg text-brand-text-muted hover:bg-brand-bg"
            :aria-label="t('contracts.closeDrawer')"
            @click="closeCommentDialog"
          >
            <X class="h-4 w-4" />
          </button>
          <div class="px-6 pb-4 pt-6">
            <h3 class="text-lg font-bold text-brand-text">
              {{
                commentAction
                  ? t(`contracts.confirm.${commentAction}.title`)
                  : t('contracts.commentRequiredTitle')
              }}
            </h3>
            <p class="mt-2 text-sm text-brand-text-secondary">
              {{
                commentAction
                  ? t(`contracts.confirm.${commentAction}.body`)
                  : t('contracts.commentRequiredBody')
              }}
            </p>
            <label class="mt-4 block">
              <span class="mb-1.5 block text-sm font-semibold text-brand-text">
                {{ t('contracts.fields.comment') }}
              </span>
              <textarea
                v-model="commentText"
                rows="3"
                class="w-full rounded-xl border border-brand-border px-3 py-2.5 text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
                :placeholder="t('contracts.commentPlaceholder')"
              />
              <p v-if="commentError" class="mt-1.5 text-xs text-red-600">{{ commentError }}</p>
            </label>
          </div>
          <div
            class="flex items-center justify-end gap-2 border-t border-brand-border bg-[#F7F8F6] px-6 py-4"
          >
            <button
              type="button"
              class="inline-flex h-10 items-center rounded-[10px] border border-brand-border px-4 text-sm font-semibold"
              @click="closeCommentDialog"
            >
              {{ t('contracts.cancel') }}
            </button>
            <button
              type="button"
              class="inline-flex h-10 items-center rounded-[10px] bg-brand-primary-dark px-4 text-sm font-semibold text-white"
              :disabled="isPending"
              @click="submitCommentAction"
            >
              {{
                commentAction
                  ? t(`contracts.confirm.${commentAction}.cta`)
                  : t('contracts.confirmGeneric')
              }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
