<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import {
  Check,
  ChevronDown,
  ChevronUp,
  Eye,
  Loader2,
  Plus,
  Repeat,
  Star,
  StarOff,
  X,
} from 'lucide-vue-next'

import { ApiError } from '@/shared/api/http'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'

import { useBankRevealSession } from '../composables/useBankRevealSession'
import {
  useClearPrimaryBankAccountMutation,
  useCreateBankAccountMutation,
  useRejectBankAccountMutation,
  useRevealBankAccountMutation,
  useRevokeBankAccountMutation,
  useSetPrimaryBankAccountMutation,
  useSupersedeBankAccountMutation,
  useUpdatePendingBankAccountMutation,
  useVerifyBankAccountMutation,
} from '../mutations/useBankAccountMutations'
import { useSupplierBankAccountsQuery } from '../queries/useBankAccountsQuery'
import type { BankAccountFormState, SupplierBankAccount } from '../types/banking'
import type { Supplier } from '../types/suppliers'
import {
  bankingActionVisibility,
  bankingStatusBadgeClass,
  emptyBankAccountForm,
  formForSupersede,
  formFromMaskedAccount,
  mapBankingErrorCode,
  resolveBankingListState,
  validateBankAccountForm,
  validateReasonMin10,
} from '../validation/bankingValidation'
import { mapSupplierErrorCode } from '../validation/supplierValidation'

const props = defineProps<{
  supplier: Supplier
}>()

const { t } = useI18n()
const toast = useToast()
const { can } = usePermissions()

const supplierId = computed(() => props.supplier.id)
const canManage = computed(() => can('suppliers.manage_banking'))
const canVerify = computed(() => can('suppliers.verify_banking'))
const canReveal = computed(() => can('suppliers.view_banking'))
const canViewList = computed(() => can('suppliers.view'))

const accountsQuery = useSupplierBankAccountsQuery(supplierId)
const accounts = computed(() => accountsQuery.data.value ?? [])

const currentAccounts = computed(() =>
  accounts.value.filter((a) => a.is_current).sort((a, b) => a.account_key.localeCompare(b.account_key)),
)
const historicalByKey = computed(() => {
  const map = new Map<string, SupplierBankAccount[]>()
  for (const account of accounts.value.filter((a) => !a.is_current)) {
    const list = map.get(account.account_key) ?? []
    list.push(account)
    map.set(account.account_key, list)
  }
  for (const list of map.values()) {
    list.sort((a, b) => b.version_no - a.version_no)
  }
  return map
})

const listState = computed(() =>
  resolveBankingListState({
    canView: canViewList.value,
    isLoading: accountsQuery.isLoading.value,
    isError: accountsQuery.isError.value,
    count: accounts.value.length,
  }),
)

const selectedId = ref<number | null>(null)
const selected = computed(
  () => accounts.value.find((a) => a.id === selectedId.value) ?? currentAccounts.value[0] ?? null,
)

watch(
  accounts,
  (list) => {
    if (selectedId.value != null && list.some((a) => a.id === selectedId.value)) return
    selectedId.value = currentAccounts.value[0]?.id ?? list[0]?.id ?? null
  },
  { immediate: true },
)

const expandedHistory = reactive<Record<string, boolean>>({})

const createMutation = useCreateBankAccountMutation()
const updateMutation = useUpdatePendingBankAccountMutation()
const verifyMutation = useVerifyBankAccountMutation()
const rejectMutation = useRejectBankAccountMutation()
const revokeMutation = useRevokeBankAccountMutation()
const supersedeMutation = useSupersedeBankAccountMutation()
const setPrimaryMutation = useSetPrimaryBankAccountMutation()
const clearPrimaryMutation = useClearPrimaryBankAccountMutation()
const revealMutation = useRevealBankAccountMutation()

const {
  open: revealOpen,
  reason: revealReason,
  revealed: revealPayload,
  begin: beginReveal,
  startRequest: startRevealRequest,
  accept: acceptReveal,
  close: closeRevealSession,
  clearPlaintext: clearRevealPlaintext,
  isRequestCurrent: isRevealRequestCurrent,
  finishRequest: finishRevealRequest,
  pendingGeneration: revealPendingGeneration,
} = useBankRevealSession({
  supplierId,
  accountId: selectedId,
  canReveal,
})

function apiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) return t('suppliers.errors.generic')
  const mapped = mapSupplierErrorCode(error.code)
  if (mapped !== 'generic') return t(`suppliers.errors.${mapped}`)
  const banking = mapBankingErrorCode(error.code)
  if (banking !== 'generic') return t(`suppliers.errors.${banking}`)
  return error.message || t('suppliers.errors.generic')
}

function actionsFor(account: SupplierBankAccount) {
  return bankingActionVisibility(account, {
    canManage: canManage.value,
    canVerify: canVerify.value,
    canReveal: canReveal.value,
  })
}

const inputClass =
  'w-full rounded-xl border border-brand-border bg-white px-3 py-2 text-sm text-brand-text outline-none focus:border-brand-primary'

/* ----------------------------- form drawer ----------------------------- */

type FormMode = 'create' | 'edit' | 'supersede'
const formOpen = ref(false)
const formMode = ref<FormMode>('create')
const formTarget = ref<SupplierBankAccount | null>(null)
const formError = ref('')
const fieldErrors = reactive<Record<string, string>>({})
const form = reactive<BankAccountFormState>(emptyBankAccountForm())

const formSubmitting = computed(
  () =>
    createMutation.isPending.value ||
    updateMutation.isPending.value ||
    supersedeMutation.isPending.value,
)

function clearFormErrors(): void {
  formError.value = ''
  for (const key of Object.keys(fieldErrors)) delete fieldErrors[key]
}

function openCreate(): void {
  clearFormErrors()
  formMode.value = 'create'
  formTarget.value = null
  Object.assign(form, emptyBankAccountForm())
  formOpen.value = true
}

function openEdit(account: SupplierBankAccount): void {
  clearFormErrors()
  formMode.value = 'edit'
  formTarget.value = account
  Object.assign(form, formFromMaskedAccount(account))
  formOpen.value = true
}

function openSupersede(account: SupplierBankAccount): void {
  clearFormErrors()
  formMode.value = 'supersede'
  formTarget.value = account
  Object.assign(form, formForSupersede(account))
  formOpen.value = true
}

function closeForm(): void {
  if (formSubmitting.value) return
  formOpen.value = false
  formTarget.value = null
}

async function submitForm(): Promise<void> {
  clearFormErrors()
  const validation = validateBankAccountForm(form)
  if (Object.keys(validation).length > 0) {
    Object.assign(fieldErrors, validation)
    return
  }
  const payload = {
    account_holder_name: form.account_holder_name,
    bank_name: form.bank_name,
    label: form.label.trim() || null,
    iban: form.iban.trim() || null,
    account_number: form.account_number.trim() || null,
    currency: 'SAR',
    evidence_document_id:
      form.evidence_document_id === '' ? null : Number(form.evidence_document_id),
  }
  try {
    if (formMode.value === 'create') {
      const created = await createMutation.mutateAsync({
        supplierId: props.supplier.id,
        payload,
      })
      selectedId.value = created.id
      toast.success(t('suppliers.banking.toasts.created'))
    } else if (formMode.value === 'edit' && formTarget.value) {
      await updateMutation.mutateAsync({
        supplierId: props.supplier.id,
        accountId: formTarget.value.id,
        payload,
      })
      toast.success(t('suppliers.banking.toasts.updated'))
    } else if (formMode.value === 'supersede' && formTarget.value) {
      const created = await supersedeMutation.mutateAsync({
        supplierId: props.supplier.id,
        accountId: formTarget.value.id,
        payload,
      })
      selectedId.value = created.id
      toast.success(t('suppliers.banking.toasts.superseded'))
    }
    formOpen.value = false
  } catch (error) {
    formError.value = apiMessage(error)
  }
}

/* ----------------------------- confirm dialogs ----------------------------- */

type ActionKind = 'verify' | 'reject' | 'revoke' | 'set_primary' | 'clear_primary'
const actionOpen = ref(false)
const actionKind = ref<ActionKind | null>(null)
const actionTarget = ref<SupplierBankAccount | null>(null)
const actionReason = ref('')
const actionError = ref('')
const actionFieldError = ref('')

const actionSubmitting = computed(
  () =>
    verifyMutation.isPending.value ||
    rejectMutation.isPending.value ||
    revokeMutation.isPending.value ||
    setPrimaryMutation.isPending.value ||
    clearPrimaryMutation.isPending.value,
)

function openAction(kind: ActionKind, account: SupplierBankAccount): void {
  actionKind.value = kind
  actionTarget.value = account
  actionReason.value = ''
  actionError.value = ''
  actionFieldError.value = ''
  actionOpen.value = true
}

function closeAction(): void {
  if (actionSubmitting.value) return
  actionOpen.value = false
  actionKind.value = null
  actionTarget.value = null
  actionReason.value = ''
}

async function submitAction(): Promise<void> {
  if (!actionKind.value || !actionTarget.value) return
  actionError.value = ''
  actionFieldError.value = ''
  const needsReason = actionKind.value === 'reject' || actionKind.value === 'revoke'
  if (needsReason) {
    const reasonErrors = validateReasonMin10(actionReason.value)
    if (reasonErrors.reason) {
      actionFieldError.value = reasonErrors.reason
      return
    }
  }
  try {
    const accountId = actionTarget.value.id
    if (actionKind.value === 'verify') {
      await verifyMutation.mutateAsync({ supplierId: props.supplier.id, accountId })
      toast.success(t('suppliers.banking.toasts.verified'))
    } else if (actionKind.value === 'reject') {
      await rejectMutation.mutateAsync({
        supplierId: props.supplier.id,
        accountId,
        reason: actionReason.value,
      })
      toast.success(t('suppliers.banking.toasts.rejected'))
    } else if (actionKind.value === 'revoke') {
      await revokeMutation.mutateAsync({
        supplierId: props.supplier.id,
        accountId,
        reason: actionReason.value,
      })
      toast.success(t('suppliers.banking.toasts.revoked'))
    } else if (actionKind.value === 'set_primary') {
      await setPrimaryMutation.mutateAsync({ supplierId: props.supplier.id, accountId })
      toast.success(t('suppliers.banking.toasts.primarySet'))
    } else if (actionKind.value === 'clear_primary') {
      await clearPrimaryMutation.mutateAsync({ supplierId: props.supplier.id, accountId })
      toast.success(t('suppliers.banking.toasts.primaryCleared'))
    }
    actionOpen.value = false
  } catch (error) {
    actionError.value = apiMessage(error)
  }
}

/* ----------------------------- reveal ----------------------------- */

const revealError = ref('')
const revealFieldError = ref('')
/** True only for the active generation's in-flight request (stale pendings ignored). */
const revealSubmitting = computed(
  () => revealMutation.isPending.value && revealPendingGeneration.value != null,
)

function openReveal(account: SupplierBankAccount): void {
  selectedId.value = account.id
  revealError.value = ''
  revealFieldError.value = ''
  beginReveal(account.id)
}

function closeReveal(): void {
  // Always allow close during loading — invalidates generation so late responses cannot accept.
  closeRevealSession()
  revealMutation.reset()
  revealError.value = ''
  revealFieldError.value = ''
}

async function submitReveal(): Promise<void> {
  if (!selected.value) return
  revealError.value = ''
  revealFieldError.value = ''
  const reasonErrors = validateReasonMin10(revealReason.value)
  if (reasonErrors.reason) {
    revealFieldError.value = reasonErrors.reason
    return
  }

  const snapshot = {
    supplierId: props.supplier.id,
    accountId: selected.value.id,
  }
  const token = startRevealRequest()

  try {
    const payload = await revealMutation.mutateAsync({
      supplierId: snapshot.supplierId,
      accountId: snapshot.accountId,
      reason: revealReason.value,
    })
    const accepted = acceptReveal(token, payload, snapshot)
    if (!accepted) {
      return
    }
    if (isRevealRequestCurrent(token)) {
      revealMutation.reset()
    }
  } catch (error) {
    if (isRevealRequestCurrent(token)) {
      clearRevealPlaintext()
      revealMutation.reset()
      revealError.value = apiMessage(error)
    }
  } finally {
    finishRevealRequest(token)
  }
}

function selectAccount(account: SupplierBankAccount): void {
  if (revealOpen.value) closeReveal()
  selectedId.value = account.id
}
</script>

<template>
  <section class="space-y-4" data-testid="supplier-banking-panel" dir="rtl">
    <header class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h2 class="text-lg font-bold text-brand-text">{{ t('suppliers.banking.title') }}</h2>
        <p class="mt-1 text-sm text-brand-text-secondary">{{ t('suppliers.banking.subtitle') }}</p>
      </div>
      <PermissionGuard permission="suppliers.manage_banking">
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-3 py-2 text-sm font-semibold text-white"
          data-testid="banking-create"
          @click="openCreate"
        >
          <Plus class="h-4 w-4" />
          {{ t('suppliers.banking.addAccount') }}
        </button>
      </PermissionGuard>
    </header>

    <p
      v-if="listState === 'forbidden'"
      class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950"
      role="status"
    >
      {{ t('suppliers.banking.permissionDenied') }}
    </p>

    <div
      v-else-if="listState === 'loading'"
      class="flex items-center gap-2 rounded-xl border border-brand-border px-4 py-8 text-sm text-brand-text-secondary"
      data-testid="banking-loading"
    >
      <Loader2 class="h-4 w-4 animate-spin" />
      {{ t('suppliers.banking.loading') }}
    </div>

    <div
      v-else-if="listState === 'error'"
      class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900"
      role="alert"
      data-testid="banking-error"
    >
      <p>{{ t('suppliers.banking.error') }}</p>
      <button type="button" class="mt-2 font-semibold underline" @click="accountsQuery.refetch()">
        {{ t('suppliers.retry') }}
      </button>
    </div>

    <p
      v-else-if="listState === 'empty'"
      class="rounded-xl border border-dashed border-brand-border bg-brand-bg/40 px-4 py-8 text-center text-sm text-brand-text-muted"
      data-testid="banking-empty"
    >
      {{ t('suppliers.banking.empty') }}
    </p>

    <div v-else class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
      <ul class="space-y-3" data-testid="banking-list">
        <li
          v-for="account in currentAccounts"
          :key="account.id"
          class="rounded-xl border p-4 transition"
          :class="
            selected?.id === account.id
              ? 'border-brand-primary bg-brand-primary/5'
              : 'border-brand-border bg-brand-surface'
          "
        >
          <button type="button" class="w-full text-start" @click="selectAccount(account)">
            <div class="flex flex-wrap items-start justify-between gap-2">
              <div>
                <p class="font-semibold text-brand-text">{{ account.bank_name }}</p>
                <p class="mt-0.5 text-sm text-brand-text-secondary">{{ account.account_holder_name }}</p>
              </div>
              <div class="flex flex-wrap items-center gap-2">
                <span
                  v-if="account.is_primary"
                  class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-950"
                >
                  <Star class="h-3 w-3" />
                  {{ t('suppliers.banking.primary') }}
                </span>
                <span
                  class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                  :class="bankingStatusBadgeClass(account.status)"
                >
                  {{ t(`suppliers.banking.status.${account.status}`) }}
                </span>
              </div>
            </div>
            <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
              <div>
                <dt class="text-xs text-brand-text-muted">{{ t('suppliers.banking.ibanMasked') }}</dt>
                <dd dir="ltr" class="font-mono text-start">{{ account.iban_masked || '—' }}</dd>
              </div>
              <div>
                <dt class="text-xs text-brand-text-muted">{{ t('suppliers.banking.accountMasked') }}</dt>
                <dd dir="ltr" class="font-mono text-start">{{ account.account_masked || '—' }}</dd>
              </div>
              <div>
                <dt class="text-xs text-brand-text-muted">{{ t('suppliers.banking.version') }}</dt>
                <dd>{{ account.version_no }} · {{ account.is_current ? t('suppliers.banking.current') : t('suppliers.banking.historical') }}</dd>
              </div>
              <div>
                <dt class="text-xs text-brand-text-muted">{{ t('suppliers.banking.currency') }}</dt>
                <dd>{{ account.currency }}</dd>
              </div>
            </dl>
          </button>

          <div
            v-if="(historicalByKey.get(account.account_key) ?? []).length > 0"
            class="mt-3 border-t border-brand-border/60 pt-2"
          >
            <button
              type="button"
              class="inline-flex items-center gap-1 text-xs font-semibold text-brand-text-secondary"
              @click="expandedHistory[account.account_key] = !expandedHistory[account.account_key]"
            >
              <ChevronUp v-if="expandedHistory[account.account_key]" class="h-3 w-3" />
              <ChevronDown v-else class="h-3 w-3" />
              {{
                expandedHistory[account.account_key]
                  ? t('suppliers.banking.historyHide')
                  : t('suppliers.banking.historyToggle', {
                      count: (historicalByKey.get(account.account_key) ?? []).length,
                    })
              }}
            </button>
            <ul v-if="expandedHistory[account.account_key]" class="mt-2 space-y-1">
              <li
                v-for="hist in historicalByKey.get(account.account_key) ?? []"
                :key="hist.id"
                class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-brand-bg/60 px-3 py-2 text-xs"
              >
                <button type="button" class="font-semibold underline" @click="selectAccount(hist)">
                  {{ t('suppliers.banking.versionLabel', { version: hist.version_no }) }}
                </button>
                <span
                  class="inline-flex rounded-full px-2 py-0.5 font-semibold"
                  :class="bankingStatusBadgeClass(hist.status)"
                >
                  {{ t(`suppliers.banking.status.${hist.status}`) }}
                </span>
                <span v-if="hist.is_primary" class="text-amber-800">{{ t('suppliers.banking.primary') }}</span>
              </li>
            </ul>
          </div>
        </li>
      </ul>

      <aside
        v-if="selected"
        class="rounded-2xl border border-brand-border bg-brand-surface p-5"
        data-testid="banking-details"
      >
        <div class="flex flex-wrap items-start justify-between gap-2">
          <div>
            <h3 class="text-base font-bold text-brand-text">{{ t('suppliers.banking.detailsTitle') }}</h3>
            <p class="mt-1 text-sm text-brand-text-secondary">{{ selected.bank_name }}</p>
          </div>
          <span
            class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
            :class="bankingStatusBadgeClass(selected.status)"
          >
            {{ t(`suppliers.banking.status.${selected.status}`) }}
          </span>
        </div>

        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
          <div>
            <dt class="text-xs text-brand-text-muted">{{ t('suppliers.banking.holder') }}</dt>
            <dd>{{ selected.account_holder_name }}</dd>
          </div>
          <div>
            <dt class="text-xs text-brand-text-muted">{{ t('suppliers.banking.label') }}</dt>
            <dd>{{ selected.label || '—' }}</dd>
          </div>
          <div>
            <dt class="text-xs text-brand-text-muted">{{ t('suppliers.banking.ibanMasked') }}</dt>
            <dd dir="ltr" class="font-mono text-start" data-testid="banking-iban-masked">
              {{ selected.iban_masked || '—' }}
            </dd>
          </div>
          <div>
            <dt class="text-xs text-brand-text-muted">{{ t('suppliers.banking.accountMasked') }}</dt>
            <dd dir="ltr" class="font-mono text-start">{{ selected.account_masked || '—' }}</dd>
          </div>
          <div>
            <dt class="text-xs text-brand-text-muted">{{ t('suppliers.banking.version') }}</dt>
            <dd>
              {{ selected.version_no }}
              ·
              {{ selected.is_current ? t('suppliers.banking.current') : t('suppliers.banking.historical') }}
            </dd>
          </div>
          <div>
            <dt class="text-xs text-brand-text-muted">{{ t('suppliers.banking.primary') }}</dt>
            <dd>{{ selected.is_primary ? t('suppliers.banking.yes') : t('suppliers.banking.no') }}</dd>
          </div>
        </dl>

        <div class="mt-4 flex flex-wrap gap-2">
          <template v-if="actionsFor(selected).edit">
            <button
              type="button"
              class="rounded-xl border border-brand-border px-3 py-2 text-sm font-semibold"
              data-testid="banking-edit"
              @click="openEdit(selected)"
            >
              {{ t('suppliers.banking.editPending') }}
            </button>
          </template>
          <template v-if="actionsFor(selected).verify">
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-xl border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-950"
              data-testid="banking-verify"
              @click="openAction('verify', selected)"
            >
              <Check class="h-4 w-4" />
              {{ t('suppliers.banking.verify') }}
            </button>
          </template>
          <template v-if="actionsFor(selected).reject">
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-900"
              data-testid="banking-reject"
              @click="openAction('reject', selected)"
            >
              <X class="h-4 w-4" />
              {{ t('suppliers.banking.reject') }}
            </button>
          </template>
          <template v-if="actionsFor(selected).supersede">
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-xl border border-brand-border px-3 py-2 text-sm font-semibold"
              data-testid="banking-supersede"
              @click="openSupersede(selected)"
            >
              <Repeat class="h-4 w-4" />
              {{ t('suppliers.banking.supersede') }}
            </button>
          </template>
          <template v-if="actionsFor(selected).revoke">
            <button
              type="button"
              class="rounded-xl border border-red-200 px-3 py-2 text-sm font-semibold text-red-900"
              data-testid="banking-revoke"
              @click="openAction('revoke', selected)"
            >
              {{ t('suppliers.banking.revoke') }}
            </button>
          </template>
          <template v-if="actionsFor(selected).setPrimary">
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-xl border border-amber-300 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-950"
              data-testid="banking-set-primary"
              @click="openAction('set_primary', selected)"
            >
              <Star class="h-4 w-4" />
              {{ t('suppliers.banking.setPrimary') }}
            </button>
          </template>
          <template v-if="actionsFor(selected).clearPrimary">
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-xl border border-brand-border px-3 py-2 text-sm font-semibold"
              data-testid="banking-clear-primary"
              @click="openAction('clear_primary', selected)"
            >
              <StarOff class="h-4 w-4" />
              {{ t('suppliers.banking.clearPrimary') }}
            </button>
          </template>
          <template v-if="actionsFor(selected).reveal">
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-900"
              data-testid="banking-reveal"
              @click="openReveal(selected)"
            >
              <Eye class="h-4 w-4" />
              {{ t('suppliers.banking.reveal') }}
            </button>
          </template>
        </div>

        <p
          v-if="!canManage && !canVerify && !canReveal"
          class="mt-3 text-xs text-brand-text-muted"
        >
          {{ t('suppliers.banking.readOnlyHint') }}
        </p>
      </aside>
    </div>

    <!-- Create / edit / supersede drawer -->
    <Teleport to="body">
      <div v-if="formOpen" class="fixed inset-0 z-[70] flex justify-start">
        <button
          type="button"
          class="absolute inset-0 bg-black/40"
          :aria-label="t('suppliers.closeDrawer')"
          :disabled="formSubmitting"
          @click="closeForm"
        />
        <aside
          class="relative z-10 flex h-full w-full max-w-lg flex-col bg-brand-surface shadow-2xl"
          role="dialog"
          aria-modal="true"
          data-testid="banking-form-drawer"
        >
          <header class="flex items-start justify-between border-b border-brand-border px-5 py-4">
            <div>
              <h2 class="text-lg font-bold text-brand-text">
                {{
                  formMode === 'create'
                    ? t('suppliers.banking.createTitle')
                    : formMode === 'edit'
                      ? t('suppliers.banking.editTitle')
                      : t('suppliers.banking.supersedeTitle')
                }}
              </h2>
              <p class="mt-1 text-sm text-brand-text-secondary">
                {{
                  formMode === 'supersede'
                    ? t('suppliers.banking.supersedeHint')
                    : t('suppliers.banking.formHint')
                }}
              </p>
            </div>
            <button
              type="button"
              class="rounded-lg p-1 hover:bg-brand-bg"
              :disabled="formSubmitting"
              :aria-label="t('suppliers.closeDrawer')"
              @click="closeForm"
            >
              <X class="h-5 w-5" />
            </button>
          </header>

          <form class="flex flex-1 flex-col overflow-hidden" @submit.prevent="submitForm">
            <fieldset class="flex flex-1 flex-col overflow-hidden" :disabled="formSubmitting">
              <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
                <p v-if="formError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">
                  {{ formError }}
                </p>

                <div>
                  <label class="mb-1 block text-sm font-semibold" for="bank-holder">
                    {{ t('suppliers.banking.holder') }}
                  </label>
                  <input id="bank-holder" v-model="form.account_holder_name" :class="inputClass" />
                  <p v-if="fieldErrors.account_holder_name" class="mt-1 text-xs text-red-600">
                    {{ t(`suppliers.banking.validation.${fieldErrors.account_holder_name}`) }}
                  </p>
                </div>

                <div>
                  <label class="mb-1 block text-sm font-semibold" for="bank-name">
                    {{ t('suppliers.banking.bankName') }}
                  </label>
                  <input id="bank-name" v-model="form.bank_name" :class="inputClass" />
                  <p v-if="fieldErrors.bank_name" class="mt-1 text-xs text-red-600">
                    {{ t(`suppliers.banking.validation.${fieldErrors.bank_name}`) }}
                  </p>
                </div>

                <div>
                  <label class="mb-1 block text-sm font-semibold" for="bank-label">
                    {{ t('suppliers.banking.label') }}
                  </label>
                  <input id="bank-label" v-model="form.label" :class="inputClass" />
                </div>

                <div>
                  <label class="mb-1 block text-sm font-semibold" for="bank-iban">
                    {{ t('suppliers.banking.iban') }}
                  </label>
                  <input
                    id="bank-iban"
                    v-model="form.iban"
                    dir="ltr"
                    autocomplete="off"
                    :class="[inputClass, 'text-start']"
                    :placeholder="t('suppliers.banking.ibanPlaceholder')"
                  />
                </div>

                <div>
                  <label class="mb-1 block text-sm font-semibold" for="bank-local">
                    {{ t('suppliers.banking.accountNumber') }}
                  </label>
                  <input
                    id="bank-local"
                    v-model="form.account_number"
                    dir="ltr"
                    autocomplete="off"
                    :class="[inputClass, 'text-start']"
                  />
                  <p v-if="fieldErrors.identifier" class="mt-1 text-xs text-red-600">
                    {{ t(`suppliers.banking.validation.${fieldErrors.identifier}`) }}
                  </p>
                </div>

                <div>
                  <label class="mb-1 block text-sm font-semibold" for="bank-currency">
                    {{ t('suppliers.banking.currency') }}
                  </label>
                  <input
                    id="bank-currency"
                    v-model="form.currency"
                    dir="ltr"
                    readonly
                    :class="[inputClass, 'bg-brand-bg text-start']"
                  />
                  <p class="mt-1 text-xs text-brand-text-muted">{{ t('suppliers.banking.currencyHint') }}</p>
                  <p v-if="fieldErrors.currency" class="mt-1 text-xs text-red-600">
                    {{ t(`suppliers.banking.validation.${fieldErrors.currency}`) }}
                  </p>
                </div>

                <div>
                  <label class="mb-1 block text-sm font-semibold" for="bank-evidence">
                    {{ t('suppliers.banking.evidenceDocumentId') }}
                  </label>
                  <input
                    id="bank-evidence"
                    v-model.number="form.evidence_document_id"
                    type="number"
                    min="1"
                    dir="ltr"
                    :class="[inputClass, 'text-start']"
                  />
                  <p class="mt-1 text-xs text-brand-text-muted">{{ t('suppliers.banking.evidenceHint') }}</p>
                </div>
              </div>

              <footer class="flex justify-end gap-2 border-t border-brand-border px-5 py-4">
                <button
                  type="button"
                  class="rounded-xl border border-brand-border px-4 py-2 text-sm font-semibold"
                  :disabled="formSubmitting"
                  @click="closeForm"
                >
                  {{ t('suppliers.cancel') }}
                </button>
                <button
                  type="submit"
                  class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
                  :disabled="formSubmitting"
                >
                  <Loader2 v-if="formSubmitting" class="h-4 w-4 animate-spin" />
                  {{ t('suppliers.save') }}
                </button>
              </footer>
            </fieldset>
          </form>
        </aside>
      </div>
    </Teleport>

    <!-- Confirm action modal -->
    <Teleport to="body">
      <div
        v-if="actionOpen && actionKind && actionTarget"
        class="fixed inset-0 z-[80] flex items-center justify-center p-4"
      >
        <button
          type="button"
          class="absolute inset-0 bg-black/40"
          :disabled="actionSubmitting"
          :aria-label="t('suppliers.cancel')"
          @click="closeAction"
        />
        <div
          class="relative z-10 w-full max-w-md rounded-2xl bg-brand-surface p-5 shadow-2xl"
          role="dialog"
          aria-modal="true"
          data-testid="banking-action-dialog"
        >
          <h3 class="text-lg font-bold text-brand-text">
            {{ t(`suppliers.banking.actions.${actionKind}.title`) }}
          </h3>
          <p class="mt-2 text-sm text-brand-text-secondary">
            {{ t(`suppliers.banking.actions.${actionKind}.body`) }}
          </p>
          <p v-if="actionError" class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">
            {{ actionError }}
          </p>
          <div v-if="actionKind === 'reject' || actionKind === 'revoke'" class="mt-4">
            <label class="mb-1 block text-sm font-semibold" for="bank-action-reason">
              {{ t('suppliers.banking.reason') }}
            </label>
            <textarea
              id="bank-action-reason"
              v-model="actionReason"
              rows="3"
              class="w-full rounded-xl border border-brand-border px-3 py-2 text-sm"
              :placeholder="t('suppliers.banking.reasonPlaceholder')"
            />
            <p v-if="actionFieldError" class="mt-1 text-xs text-red-600">
              {{ t(`suppliers.banking.validation.${actionFieldError}`) }}
            </p>
          </div>
          <div class="mt-5 flex justify-end gap-2">
            <button
              type="button"
              class="rounded-xl border border-brand-border px-4 py-2 text-sm font-semibold"
              :disabled="actionSubmitting"
              @click="closeAction"
            >
              {{ t('suppliers.cancel') }}
            </button>
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
              :disabled="actionSubmitting"
              data-testid="banking-action-confirm"
              @click="submitAction"
            >
              <Loader2 v-if="actionSubmitting" class="h-4 w-4 animate-spin" />
              {{ t('suppliers.confirmGeneric') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Reveal modal — ephemeral plaintext surface -->
    <Teleport to="body">
      <div v-if="revealOpen" class="fixed inset-0 z-[90] flex items-center justify-center p-4">
        <button
          type="button"
          class="absolute inset-0 bg-black/50"
          :aria-label="t('suppliers.banking.revealClose')"
          data-testid="banking-reveal-backdrop"
          @click="closeReveal"
        />
        <div
          class="relative z-10 w-full max-w-md rounded-2xl bg-brand-surface p-5 shadow-2xl"
          role="dialog"
          aria-modal="true"
          data-testid="banking-reveal-dialog"
        >
          <header class="flex items-start justify-between gap-3">
            <div>
              <h3 class="text-lg font-bold text-brand-text">{{ t('suppliers.banking.revealTitle') }}</h3>
              <p class="mt-1 text-sm text-brand-text-secondary">{{ t('suppliers.banking.revealBody') }}</p>
            </div>
            <button
              type="button"
              class="rounded-lg p-1 hover:bg-brand-bg"
              :aria-label="t('suppliers.banking.revealClose')"
              data-testid="banking-reveal-close"
              @click="closeReveal"
            >
              <X class="h-5 w-5" />
            </button>
          </header>

          <p v-if="revealError" class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">
            {{ revealError }}
          </p>

          <template v-if="!revealPayload">
            <div class="mt-4">
              <label class="mb-1 block text-sm font-semibold" for="bank-reveal-reason">
                {{ t('suppliers.banking.revealReason') }}
              </label>
              <textarea
                id="bank-reveal-reason"
                v-model="revealReason"
                rows="3"
                class="w-full rounded-xl border border-brand-border px-3 py-2 text-sm"
                data-testid="banking-reveal-reason"
                :placeholder="t('suppliers.banking.revealReasonPlaceholder')"
              />
              <p v-if="revealFieldError" class="mt-1 text-xs text-red-600">
                {{ t(`suppliers.banking.validation.${revealFieldError}`) }}
              </p>
            </div>
            <div class="mt-5 flex justify-end gap-2">
              <button
                type="button"
                class="rounded-xl border border-brand-border px-4 py-2 text-sm font-semibold"
                data-testid="banking-reveal-cancel"
                @click="closeReveal"
              >
                {{ t('suppliers.cancel') }}
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
                :disabled="revealSubmitting"
                data-testid="banking-reveal-submit"
                @click="submitReveal"
              >
                <Loader2 v-if="revealSubmitting" class="h-4 w-4 animate-spin" />
                {{ t('suppliers.banking.revealSubmit') }}
              </button>
            </div>
          </template>

          <div v-else class="mt-4 space-y-3" data-testid="banking-reveal-plaintext">
            <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-950">
              {{ t('suppliers.banking.revealEphemeralNote') }}
            </p>
            <div>
              <p class="text-xs text-brand-text-muted">{{ t('suppliers.banking.iban') }}</p>
              <p dir="ltr" class="font-mono text-sm text-start">
                {{ revealPayload.iban || '—' }}
              </p>
            </div>
            <div>
              <p class="text-xs text-brand-text-muted">{{ t('suppliers.banking.accountNumber') }}</p>
              <p dir="ltr" class="font-mono text-sm text-start">
                {{ revealPayload.account_number || '—' }}
              </p>
            </div>
            <button
              type="button"
              class="mt-2 w-full rounded-xl border border-brand-border px-4 py-2 text-sm font-semibold"
              data-testid="banking-reveal-hide"
              @click="closeReveal"
            >
              {{ t('suppliers.banking.revealHide') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </section>
</template>
