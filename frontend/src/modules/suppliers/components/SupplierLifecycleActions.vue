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
  useDeleteSupplierMutation,
  useTransitionSupplierMutation,
} from '../mutations/useSupplierMutations'
import {
  useEligibilityDefsQuery,
  useSupplierEligibilityQuery,
} from '../queries/useEligibilityQueries'
import type { EligibilityReason } from '../types/eligibility'
import type { Supplier, SupplierLifecycleActionDef } from '../types/suppliers'
import { normalizeEligibilityReasons } from '../validation/eligibilityValidation'
import {
  availableSupplierLifecycleActions,
  mapSupplierErrorCode,
} from '../validation/supplierValidation'
import EligibilityReasonList from './EligibilityReasonList.vue'

const props = defineProps<{
  supplier: Supplier
}>()

const emit = defineEmits<{
  refreshed: []
}>()

const { t } = useI18n()
const router = useRouter()
const toast = useToast()
const { confirm } = useConfirm()
const { can } = usePermissions()

const transitionMutation = useTransitionSupplierMutation()
const deleteMutation = useDeleteSupplierMutation()

// Activation hint (UX only): the server stays the source of truth on click.
const showEligibilityHint = computed(
  () => props.supplier.status === 'under_review' && can('suppliers.view'),
)
const eligibilityQuery = useSupplierEligibilityQuery(() => props.supplier.id, {
  enabled: showEligibilityHint,
})
const eligibility = computed(() => eligibilityQuery.data.value ?? null)
const eligibilityConfigIds = computed(() => {
  const value = eligibility.value
  if (!value) return []
  return (value.configs?.length ? value.configs : value.config ? [value.config] : []).map((c) => c.id)
})
const defsQuery = useEligibilityDefsQuery(eligibilityConfigIds, { enabled: showEligibilityHint })
const requirementLabels = computed<Record<string, string>>(() =>
  Object.fromEntries((defsQuery.data.value ?? []).map((def) => [def.requirement_key, def.label_ar])),
)
const activationReasons = ref<EligibilityReason[]>([])
const hintReasons = computed(() =>
  showEligibilityHint.value && eligibility.value && !eligibility.value.eligible
    ? eligibility.value.reasons
    : [],
)

const reasonDialogOpen = ref(false)
const pendingAction = ref<SupplierLifecycleActionDef | null>(null)
const reasonText = ref('')
const reasonError = ref('')
const actionError = ref('')

const actions = computed(() =>
  availableSupplierLifecycleActions(props.supplier.status).filter((item) => can(item.permission)),
)

const canDelete = computed(
  () => props.supplier.status === 'draft' && can('suppliers.delete'),
)

const isPending = computed(
  () => transitionMutation.isPending.value || deleteMutation.isPending.value,
)

function apiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) {
    return t('suppliers.errors.generic')
  }
  const mapped = mapSupplierErrorCode(error.code)
  if (mapped !== 'generic') {
    return t(`suppliers.errors.${mapped}`)
  }
  return error.message || t('suppliers.errors.generic')
}

function actionLabel(action: SupplierLifecycleActionDef): string {
  if (action.to === 'activate') {
    return t('suppliers.lifecycle.activate')
  }
  return t(`suppliers.lifecycle.to_${action.to}`)
}

function openReasonDialog(action: SupplierLifecycleActionDef): void {
  pendingAction.value = action
  reasonText.value = ''
  reasonError.value = ''
  reasonDialogOpen.value = true
}

function closeReasonDialog(): void {
  reasonDialogOpen.value = false
  pendingAction.value = null
  reasonText.value = ''
}

async function runTransition(action: SupplierLifecycleActionDef, reason?: string | null): Promise<void> {
  actionError.value = ''
  activationReasons.value = []
  const toStatus = action.to === 'activate' ? 'active' : action.to
  try {
    await transitionMutation.mutateAsync({
      id: props.supplier.id,
      payload: {
        to: toStatus,
        reason: reason?.trim() || undefined,
      },
    })
    toast.success(t('suppliers.toasts.transitioned'))
    emit('refreshed')
  } catch (error) {
    const message = apiMessage(error)
    actionError.value = message
    if (error instanceof ApiError && error.code === 'SUPPLIER_NOT_ELIGIBLE') {
      activationReasons.value = normalizeEligibilityReasons(error.reasons)
      toast.info(t('suppliers.eligibility.banner.activationBlocked'))
    } else {
      toast.error(message)
    }
  }
}

async function onActionClick(action: SupplierLifecycleActionDef): Promise<void> {
  if (action.requiresReason) {
    openReasonDialog(action)
    return
  }
  if (action.to === 'activate') {
    const confirmed = await confirm({
      title: t('suppliers.confirmActivateTitle'),
      message: t('suppliers.confirmActivateBody'),
      confirmLabel: t('suppliers.lifecycle.activate'),
      cancelLabel: t('suppliers.cancel'),
      variant: 'primary',
    })
    if (!confirmed) return
  }
  await runTransition(action)
}

async function submitReasonDialog(): Promise<void> {
  if (!pendingAction.value) return
  if (!reasonText.value.trim()) {
    reasonError.value = t('suppliers.validation.reasonRequired')
    return
  }
  const action = pendingAction.value
  closeReasonDialog()
  await runTransition(action, reasonText.value)
}

async function confirmDelete(): Promise<void> {
  const confirmed = await confirm({
    title: t('suppliers.confirmDeleteTitle'),
    message: t('suppliers.confirmDeleteBody'),
    confirmLabel: t('suppliers.confirmDeleteCta'),
    cancelLabel: t('suppliers.cancel'),
    variant: 'danger',
  })
  if (!confirmed) return
  try {
    await deleteMutation.mutateAsync(props.supplier.id)
    toast.success(t('suppliers.toasts.deleted'))
    await router.push('/app/suppliers')
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

function buttonClass(action: SupplierLifecycleActionDef): string {
  if (action.variant === 'danger') {
    return 'border-red-200 bg-red-50 text-red-900 hover:bg-red-100'
  }
  if (action.variant === 'warning') {
    return 'border-amber-200 bg-amber-50 text-amber-950 hover:bg-amber-100'
  }
  if (action.to === 'activate') {
    return 'border-brand-primary bg-brand-primary text-white hover:bg-brand-primary/90'
  }
  return 'border-brand-border bg-brand-surface text-brand-text hover:bg-brand-bg'
}
</script>

<template>
  <section class="rounded-2xl border border-brand-border bg-brand-surface p-5">
    <h3 class="text-base font-bold text-brand-text">{{ t('suppliers.lifecycleTitle') }}</h3>
    <p class="mt-1 text-sm text-brand-text-secondary">{{ t('suppliers.lifecycleHint') }}</p>

    <div
      v-if="actionError"
      class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-950"
      role="alert"
      data-testid="lifecycle-error"
    >
      <p>{{ actionError }}</p>
      <div v-if="activationReasons.length > 0" class="mt-2">
        <EligibilityReasonList :reasons="activationReasons" :labels="requirementLabels" />
      </div>
    </div>

    <div
      v-else-if="hintReasons.length > 0"
      class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-950"
      data-testid="lifecycle-eligibility-hint"
    >
      <p class="font-semibold">{{ t('suppliers.eligibility.banner.ineligibleTitle') }}</p>
      <div class="mt-1">
        <EligibilityReasonList :reasons="hintReasons" :labels="requirementLabels" />
      </div>
    </div>

    <div class="mt-4 flex flex-wrap gap-2">
      <PermissionGuard permission="suppliers.transition">
        <button
          v-for="action in actions"
          :key="action.to"
          type="button"
          class="inline-flex items-center gap-2 rounded-xl border px-3 py-2 text-sm font-semibold transition disabled:opacity-60"
          :class="buttonClass(action)"
          :disabled="isPending"
          @click="onActionClick(action)"
        >
          <Loader2 v-if="isPending" class="h-4 w-4 animate-spin" />
          {{ actionLabel(action) }}
        </button>
      </PermissionGuard>

      <PermissionGuard permission="suppliers.delete">
        <button
          v-if="canDelete"
          type="button"
          class="inline-flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-900 hover:bg-red-100 disabled:opacity-60"
          :disabled="isPending"
          @click="confirmDelete"
        >
          {{ t('suppliers.deleteCta') }}
        </button>
      </PermissionGuard>
    </div>

    <Teleport to="body">
      <div
        v-if="reasonDialogOpen"
        class="fixed inset-0 z-[80] flex items-center justify-center bg-black/40 p-4"
        role="dialog"
        aria-modal="true"
      >
        <div class="w-full max-w-md rounded-2xl bg-brand-surface p-5 shadow-xl">
          <div class="flex items-start justify-between gap-3">
            <div>
              <h4 class="text-base font-bold text-brand-text">{{ t('suppliers.reasonRequiredTitle') }}</h4>
              <p class="mt-1 text-sm text-brand-text-secondary">{{ t('suppliers.reasonRequiredBody') }}</p>
            </div>
            <button type="button" class="rounded-lg p-1 hover:bg-brand-bg" @click="closeReasonDialog">
              <X class="h-5 w-5" />
            </button>
          </div>
          <textarea
            v-model="reasonText"
            rows="4"
            class="mt-4 w-full rounded-xl border border-brand-border px-3 py-2 text-sm"
            :placeholder="t('suppliers.reasonPlaceholder')"
          />
          <p v-if="reasonError" class="mt-2 text-sm text-red-600">{{ reasonError }}</p>
          <div class="mt-4 flex justify-end gap-2">
            <button
              type="button"
              class="rounded-xl border border-brand-border px-4 py-2 text-sm font-semibold"
              @click="closeReasonDialog"
            >
              {{ t('suppliers.cancel') }}
            </button>
            <button
              type="button"
              class="rounded-xl bg-brand-primary px-4 py-2 text-sm font-semibold text-white"
              :disabled="isPending"
              @click="submitReasonDialog"
            >
              {{ t('suppliers.confirmGeneric') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </section>
</template>
