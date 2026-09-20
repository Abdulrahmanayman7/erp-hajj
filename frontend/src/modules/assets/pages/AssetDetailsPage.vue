<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeft,
  ArrowRight,
  Barcode,
  Building2,
  CalendarRange,
  FileText,
  MapPin,
  MessageSquareText,
  Package,
  Pencil,
  ShieldCheck,
  Tag,
  Trash2,
  UserRound,
  Warehouse,
} from 'lucide-vue-next'

import EntityDocumentsSection from '@/modules/documents/components/EntityDocumentsSection.vue'
import { useOrganizationUnitsFlatQuery } from '@/modules/organization/queries/useOrganizationUnitsQuery'
import { ApiError } from '@/shared/api/http'
import type { AppSelectOption } from '@/shared/components/AppSelect.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'

import AssetAssignDialog from '../components/AssetAssignDialog.vue'
import AssetFormDrawer from '../components/AssetFormDrawer.vue'
import AssetLifecycleReasonDialog from '../components/AssetLifecycleReasonDialog.vue'
import AssetReturnDialog from '../components/AssetReturnDialog.vue'
import {
  useAssignAssetCustodyMutation,
  useDeclareAssetLostMutation,
  useDeleteAssetMutation,
  useRestoreAssetMutation,
  useRetireAssetMutation,
  useReturnAssetCustodyMutation,
  useSendAssetToMaintenanceMutation,
  useUpdateAssetMutation,
} from '../mutations/useAssetMutations'
import { useAssetQuery } from '../queries/useAssetsQuery'
import { useAssetCategoriesQuery } from '../queries/useCategoriesQuery'
import type {
  AssetFormState,
  AssetLifecycleAction,
  AssignCustodyFormState,
  LifecycleReasonFormState,
  ReturnCustodyFormState,
} from '../types/assets'
import {
  assetStatusBadgeClass,
  assetStatusDotClass,
  availableAssetLifecycleActions,
  canDeleteAsset,
  canEditAsset,
  canShowAssignAction,
  conditionBadgeClass,
  custodyStatusBadgeClass,
  emptyAssetForm,
  emptyAssignForm,
  emptyReturnForm,
  formatDate,
  formatDateTime,
  formatMoney,
  mapAssetErrorCode,
  optionalAssignFromAssetForm,
  toAssetWritePayload,
  validateAssetForm,
  validateAssignForm,
  validateLifecycleReasonForm,
  validateReturnForm,
} from '../validation/assetValidation'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { permissions } = usePermissions()
const toast = useToast()
const { confirm } = useConfirm()

const id = computed(() => {
  const raw = route.params.id
  const value = Number(Array.isArray(raw) ? raw[0] : raw)
  return Number.isFinite(value) ? value : null
})

const { data, isLoading, isError, refetch } = useAssetQuery(id)
const asset = computed(() => data.value ?? null)

const hasActiveCustody = computed(
  () => asset.value?.current_custody != null || asset.value?.status === 'in_use',
)

const visibleLifecycleActions = computed(() => {
  if (!asset.value) return []
  const defs = availableAssetLifecycleActions(asset.value.status, {
    hasActiveCustody: hasActiveCustody.value,
  })
  return defs.filter((def) => permissions.value.includes(def.permission))
})

const custodies = computed(() => asset.value?.custodies ?? [])
const transitions = computed(() =>
  [...(asset.value?.transitions ?? [])].sort((a, b) => {
    const aTime = a.created_at ? Date.parse(a.created_at) : 0
    const bTime = b.created_at ? Date.parse(b.created_at) : 0
    return aTime - bTime
  }),
)

const { data: categoriesData } = useAssetCategoriesQuery({})
const { data: orgUnitsData } = useOrganizationUnitsFlatQuery({ status: 'active' })

const categoryFormOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('assets.noCategory') },
  ...(categoriesData.value ?? [])
    .filter((c) => c.is_active)
    .map((c) => ({ value: c.id, label: c.name })),
])
const orgUnitFormOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('assets.noOrgUnit') },
  ...(orgUnitsData.value?.data ?? []).map((u) => ({ value: u.id, label: u.name, hint: u.code })),
])

const updateMutation = useUpdateAssetMutation()
const deleteMutation = useDeleteAssetMutation()
const assignMutation = useAssignAssetCustodyMutation()
const returnMutation = useReturnAssetCustodyMutation()
const maintenanceMutation = useSendAssetToMaintenanceMutation()
const restoreMutation = useRestoreAssetMutation()
const retireMutation = useRetireAssetMutation()
const lostMutation = useDeclareAssetLostMutation()

const drawerOpen = ref(false)
const assignOpen = ref(false)
const returnOpen = ref(false)
const reasonOpen = ref(false)
const reasonKind = ref<'retire' | 'declare_lost' | null>(null)

const formError = ref('')
const fieldErrors = reactive<Record<string, string>>({})
const form = reactive<AssetFormState>(emptyAssetForm())

const assignForm = reactive<AssignCustodyFormState>(emptyAssignForm())
const assignFormError = ref('')
const assignFieldErrors = reactive<Record<string, string>>({})

const returnForm = reactive<ReturnCustodyFormState>(emptyReturnForm())
const returnFormError = ref('')
const returnFieldErrors = reactive<Record<string, string>>({})

const reasonForm = reactive<LifecycleReasonFormState>({ reason: '' })
const reasonFormError = ref('')
const reasonFieldErrors = reactive<Record<string, string>>({})

const isLifecyclePending = computed(
  () =>
    assignMutation.isPending.value ||
    returnMutation.isPending.value ||
    maintenanceMutation.isPending.value ||
    restoreMutation.isPending.value ||
    retireMutation.isPending.value ||
    lostMutation.isPending.value ||
    deleteMutation.isPending.value,
)

function apiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) return t('assets.errors.generic')
  const mapped = mapAssetErrorCode(error.code)
  if (mapped !== 'generic') return t(`assets.errors.${mapped}`)
  return error.message || t('assets.errors.generic')
}

async function refresh(): Promise<void> {
  await refetch()
}

function openEdit(): void {
  if (!asset.value || !canEditAsset(permissions.value)) return
  Object.assign(form, {
    name: asset.value.name,
    description: asset.value.description ?? '',
    category_id: asset.value.category?.id ?? '',
    serial_number: asset.value.serial_number ?? '',
    barcode: asset.value.barcode ?? '',
    condition: (asset.value.condition as AssetFormState['condition']) || 'good',
    warehouse_id: asset.value.warehouse?.id ?? '',
    organization_unit_id: asset.value.organization_unit?.id ?? '',
    purchase_value:
      asset.value.purchase_value != null ? String(asset.value.purchase_value) : '',
    acquisition_date: asset.value.acquisition_date ?? '',
    notes: asset.value.notes ?? '',
    employee_id: '',
  })
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  drawerOpen.value = true
}

async function submitEdit(): Promise<void> {
  if (!asset.value) return
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  Object.assign(fieldErrors, validateAssetForm(form))
  if (Object.keys(fieldErrors).length) return
  try {
    const updated = await updateMutation.mutateAsync({
      id: asset.value.id,
      payload: toAssetWritePayload(form),
    })
    const assignPayload = optionalAssignFromAssetForm(form)
    if (
      assignPayload &&
      canShowAssignAction(updated.status, permissions.value)
    ) {
      try {
        await assignMutation.mutateAsync({ id: updated.id, payload: assignPayload })
        toast.success(t('assets.toasts.assetUpdatedAssigned'))
      } catch (error) {
        toast.success(t('assets.toasts.assetUpdated'))
        toast.error(t('assets.toasts.assignAfterSaveFailed'))
        formError.value = apiMessage(error)
        drawerOpen.value = false
        await refresh()
        return
      }
    } else {
      toast.success(t('assets.toasts.assetUpdated'))
    }
    drawerOpen.value = false
    await refresh()
  } catch (error) {
    formError.value = apiMessage(error)
  }
}

async function removeAsset(): Promise<void> {
  if (!asset.value || !canDeleteAsset(permissions.value)) return
  const ok = await confirm({
    title: t('assets.confirm.deleteAsset.title'),
    message: t('assets.confirm.deleteAsset.body'),
    confirmLabel: t('assets.actions.delete'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deleteMutation.mutateAsync(asset.value.id)
    toast.success(t('assets.toasts.assetDeleted'))
    await router.push('/app/assets')
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

function openAssign(): void {
  Object.assign(assignForm, emptyAssignForm())
  assignFormError.value = ''
  Object.keys(assignFieldErrors).forEach((k) => delete assignFieldErrors[k])
  assignOpen.value = true
}

function openReturn(): void {
  Object.assign(returnForm, emptyReturnForm())
  returnFormError.value = ''
  Object.keys(returnFieldErrors).forEach((k) => delete returnFieldErrors[k])
  returnOpen.value = true
}

function openReason(kind: 'retire' | 'declare_lost'): void {
  reasonKind.value = kind
  reasonForm.reason = ''
  reasonFormError.value = ''
  Object.keys(reasonFieldErrors).forEach((k) => delete reasonFieldErrors[k])
  reasonOpen.value = true
}

async function runLifecycleAction(action: AssetLifecycleAction): Promise<void> {
  if (!asset.value) return
  switch (action) {
    case 'assign':
      openAssign()
      return
    case 'return':
      openReturn()
      return
    case 'retire':
      openReason('retire')
      return
    case 'declare_lost':
      openReason('declare_lost')
      return
    case 'maintenance': {
      const ok = await confirm({
        title: t('assets.confirm.maintenance.title'),
        message: t('assets.confirm.maintenance.body'),
        confirmLabel: t('assets.actions.maintenance'),
        variant: 'warning',
      })
      if (!ok) return
      try {
        await maintenanceMutation.mutateAsync(asset.value.id)
        toast.success(t('assets.toasts.sentToMaintenance'))
        await refresh()
      } catch (error) {
        toast.error(apiMessage(error))
      }
      return
    }
    case 'restore': {
      const ok = await confirm({
        title: t('assets.confirm.restore.title'),
        message: t('assets.confirm.restore.body'),
        confirmLabel: t('assets.actions.restore'),
        variant: 'warning',
      })
      if (!ok) return
      try {
        await restoreMutation.mutateAsync(asset.value.id)
        toast.success(t('assets.toasts.restored'))
        await refresh()
      } catch (error) {
        toast.error(apiMessage(error))
      }
    }
  }
}

async function submitAssign(): Promise<void> {
  if (!asset.value) return
  assignFormError.value = ''
  Object.keys(assignFieldErrors).forEach((k) => delete assignFieldErrors[k])
  Object.assign(assignFieldErrors, validateAssignForm(assignForm))
  if (Object.keys(assignFieldErrors).length) return
  try {
    await assignMutation.mutateAsync({
      id: asset.value.id,
      payload: {
        employee_id: Number(assignForm.employee_id),
        expected_return_at: assignForm.expected_return_at.trim() || null,
        condition_at_assignment: assignForm.condition_at_assignment || null,
        assignment_notes: assignForm.assignment_notes.trim() || null,
      },
    })
    toast.success(t('assets.toasts.assigned'))
    assignOpen.value = false
    await refresh()
  } catch (error) {
    assignFormError.value = apiMessage(error)
  }
}

async function submitReturn(): Promise<void> {
  if (!asset.value) return
  returnFormError.value = ''
  Object.keys(returnFieldErrors).forEach((k) => delete returnFieldErrors[k])
  Object.assign(returnFieldErrors, validateReturnForm(returnForm))
  if (Object.keys(returnFieldErrors).length) return
  try {
    await returnMutation.mutateAsync({
      id: asset.value.id,
      payload: {
        next_status: returnForm.next_status,
        condition_at_return: returnForm.condition_at_return || null,
        return_notes: returnForm.return_notes.trim() || null,
      },
    })
    toast.success(t('assets.toasts.returned'))
    returnOpen.value = false
    await refresh()
  } catch (error) {
    returnFormError.value = apiMessage(error)
  }
}

async function submitReason(): Promise<void> {
  if (!asset.value || !reasonKind.value) return
  reasonFormError.value = ''
  Object.keys(reasonFieldErrors).forEach((k) => delete reasonFieldErrors[k])
  Object.assign(reasonFieldErrors, validateLifecycleReasonForm(reasonForm))
  if (Object.keys(reasonFieldErrors).length) return
  try {
    if (reasonKind.value === 'retire') {
      await retireMutation.mutateAsync({ id: asset.value.id, reason: reasonForm.reason.trim() })
      toast.success(t('assets.toasts.retired'))
    } else {
      await lostMutation.mutateAsync({ id: asset.value.id, reason: reasonForm.reason.trim() })
      toast.success(t('assets.toasts.declaredLost'))
    }
    reasonOpen.value = false
    await refresh()
  } catch (error) {
    reasonFormError.value = apiMessage(error)
  }
}

function actionLabel(action: AssetLifecycleAction): string {
  switch (action) {
    case 'assign':
      return t('assets.actions.assign')
    case 'return':
      return t('assets.actions.return')
    case 'maintenance':
      return t('assets.actions.maintenance')
    case 'restore':
      return t('assets.actions.restore')
    case 'retire':
      return t('assets.actions.retire')
    case 'declare_lost':
      return t('assets.actions.declareLost')
  }
}

function actionButtonClass(action: AssetLifecycleAction): string {
  switch (action) {
    case 'assign':
      return 'border-sky-200 bg-sky-50 text-sky-950 hover:bg-sky-100'
    case 'return':
      return 'border-emerald-200 bg-emerald-50 text-emerald-950 hover:bg-emerald-100'
    case 'maintenance':
      return 'border-amber-200 bg-amber-50 text-amber-950 hover:bg-amber-100'
    case 'restore':
      return 'border-teal-200 bg-teal-50 text-teal-950 hover:bg-teal-100'
    case 'retire':
    case 'declare_lost':
      return 'border-red-200 bg-red-50 text-red-800 hover:bg-red-100'
    default:
      return 'border-brand-border bg-brand-surface text-brand-text hover:bg-brand-bg'
  }
}
</script>

<template>
  <div class="mx-auto min-w-0 max-w-[1200px] space-y-5">
    <div class="flex flex-wrap items-center gap-3">
      <button
        type="button"
        class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-brand-border bg-brand-surface px-3 text-sm font-semibold text-brand-text transition hover:bg-brand-bg"
        @click="router.push('/app/assets')"
      >
        <ArrowRight class="h-4 w-4" />
        {{ t('assets.details.backToList') }}
      </button>
    </div>

    <div
      v-if="isLoading"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('assets.loadingDetails') }}
    </div>

    <div
      v-else-if="isError || !asset"
      class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center"
    >
      <p class="text-sm text-red-700">{{ t('assets.errors.loadDetails') }}</p>
      <button
        type="button"
        class="mt-3 text-sm font-semibold text-brand-primary-dark underline"
        @click="() => refetch()"
      >
        {{ t('assets.retry') }}
      </button>
    </div>

    <template v-else>
      <section class="rounded-2xl border border-brand-border bg-brand-surface px-5 py-5 sm:px-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <p
                class="font-mono text-xs font-semibold tracking-wide text-brand-text-muted"
                dir="ltr"
              >
                {{ asset.asset_number }}
              </p>
              <span
                class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold tracking-wide"
                :class="assetStatusBadgeClass(asset.status)"
              >
                <span
                  class="h-1.5 w-1.5 shrink-0 rounded-full"
                  :class="assetStatusDotClass(asset.status)"
                  aria-hidden="true"
                />
                {{ t(`assets.status.${asset.status}`) }}
              </span>
              <span
                v-if="asset.condition"
                class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold"
                :class="conditionBadgeClass(asset.condition)"
              >
                {{ t(`assets.condition.${asset.condition}`) }}
              </span>
            </div>
            <h2 class="mt-2 break-words text-[1.35rem] font-bold leading-snug text-brand-text sm:text-[1.85rem]">
              {{ asset.name }}
            </h2>
            <p class="mt-2 break-words text-sm text-brand-text-secondary">
              <span class="font-semibold text-brand-text">
                {{
                  asset.current_custody?.employee?.full_name ??
                  asset.warehouse?.name ??
                  t('assets.noWarehouse')
                }}
              </span>
              <span class="mx-1.5 text-brand-text-muted">·</span>
              {{ asset.organization_unit?.name ?? t('assets.noOrgUnit') }}
              <template v-if="asset.category?.name">
                <span class="mx-1.5 text-brand-text-muted">·</span>
                {{ asset.category.name }}
              </template>
            </p>
          </div>

          <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap">
            <PermissionGuard v-if="canEditAsset(permissions)" permission="assets.update">
              <button
                type="button"
                class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-4 text-sm font-semibold text-brand-primary-dark transition hover:bg-brand-primary-soft sm:h-10 sm:w-auto"
                @click="openEdit"
              >
                <Pencil class="h-4 w-4" />
                {{ t('assets.actions.edit') }}
              </button>
            </PermissionGuard>
            <PermissionGuard v-if="canDeleteAsset(permissions)" permission="assets.delete">
              <button
                type="button"
                class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 text-sm font-semibold text-red-800 transition hover:bg-red-100 sm:h-10 sm:w-auto"
                :disabled="isLifecyclePending"
                @click="removeAsset"
              >
                <Trash2 class="h-4 w-4" />
                {{ t('assets.actions.delete') }}
              </button>
            </PermissionGuard>
          </div>
        </div>
      </section>

      <div class="grid min-w-0 gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
        <div class="order-2 min-w-0 space-y-5 xl:order-1">
          <section class="rounded-2xl border border-brand-border bg-brand-surface">
            <header class="border-b border-brand-border px-5 py-4">
              <h3 class="text-sm font-bold text-brand-text">
                {{ t('assets.details.detailsSummary') }}
              </h3>
            </header>
            <dl class="grid gap-0 md:grid-cols-2">
              <div class="flex gap-3 border-b border-brand-border/80 px-5 py-4 sm:border-e">
                <span
                  class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary"
                >
                  <Tag class="h-4 w-4" :stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                  <dt class="text-xs font-semibold text-brand-text-muted">
                    {{ t('assets.fields.category') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text">
                    {{ asset.category?.name || '—' }}
                  </dd>
                </div>
              </div>

              <div class="flex gap-3 border-b border-brand-border/80 px-5 py-4">
                <span
                  class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary"
                >
                  <Package class="h-4 w-4" :stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                  <dt class="text-xs font-semibold text-brand-text-muted">
                    {{ t('assets.fields.serialNumber') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text" dir="ltr">
                    {{ asset.serial_number || '—' }}
                  </dd>
                </div>
              </div>

              <div class="flex gap-3 border-b border-brand-border/80 px-5 py-4 sm:border-e">
                <span
                  class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary"
                >
                  <Barcode class="h-4 w-4" :stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                  <dt class="text-xs font-semibold text-brand-text-muted">
                    {{ t('assets.fields.barcode') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text" dir="ltr">
                    {{ asset.barcode || '—' }}
                  </dd>
                </div>
              </div>

              <div class="flex gap-3 border-b border-brand-border/80 px-5 py-4">
                <span
                  class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary"
                >
                  <ShieldCheck class="h-4 w-4" :stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                  <dt class="text-xs font-semibold text-brand-text-muted">
                    {{ t('assets.fields.condition') }}
                  </dt>
                  <dd class="mt-1">
                    <span
                      v-if="asset.condition"
                      class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold"
                      :class="conditionBadgeClass(asset.condition)"
                    >
                      {{ t(`assets.condition.${asset.condition}`) }}
                    </span>
                    <span v-else class="text-sm font-semibold text-brand-text">—</span>
                  </dd>
                </div>
              </div>

              <div class="flex gap-3 border-b border-brand-border/80 px-5 py-4 sm:border-e">
                <span
                  class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary"
                >
                  <CalendarRange class="h-4 w-4" :stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                  <dt class="text-xs font-semibold text-brand-text-muted">
                    {{ t('assets.fields.acquisitionDate') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text" dir="ltr">
                    {{ formatDate(asset.acquisition_date) }}
                  </dd>
                </div>
              </div>

              <div class="flex gap-3 border-b border-brand-border/80 px-5 py-4">
                <span
                  class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary"
                >
                  <Tag class="h-4 w-4" :stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                  <dt class="text-xs font-semibold text-brand-text-muted">
                    {{ t('assets.fields.purchaseValue') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text">
                    {{ formatMoney(asset.purchase_value) }}
                  </dd>
                </div>
              </div>

              <div class="flex gap-3 border-b border-brand-border/80 px-5 py-4 sm:border-e">
                <span
                  class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary"
                >
                  <Warehouse class="h-4 w-4" :stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                  <dt class="text-xs font-semibold text-brand-text-muted">
                    {{ t('assets.fields.warehouse') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text">
                    {{ asset.warehouse?.name || '—' }}
                  </dd>
                </div>
              </div>

              <div class="flex gap-3 border-b border-brand-border/80 px-5 py-4">
                <span
                  class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary"
                >
                  <Building2 class="h-4 w-4" :stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                  <dt class="text-xs font-semibold text-brand-text-muted">
                    {{ t('assets.fields.organizationUnit') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text">
                    {{ asset.organization_unit?.name || '—' }}
                  </dd>
                </div>
              </div>

              <div class="flex gap-3 px-5 py-4 sm:col-span-2">
                <span
                  class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary"
                >
                  <UserRound class="h-4 w-4" :stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                  <dt class="text-xs font-semibold text-brand-text-muted">
                    {{ t('assets.fields.createdBy') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text">
                    {{ asset.created_by?.name || '—' }}
                  </dd>
                </div>
              </div>
            </dl>
          </section>

          <section
            v-if="asset.description"
            class="rounded-2xl border border-brand-border bg-brand-surface"
          >
            <header class="flex items-center gap-2 border-b border-brand-border px-5 py-4">
              <FileText class="h-4 w-4 text-brand-primary" :stroke-width="1.75" />
              <h3 class="text-sm font-bold text-brand-text">{{ t('assets.fields.description') }}</h3>
            </header>
            <p class="whitespace-pre-wrap px-5 py-4 text-sm leading-relaxed text-brand-text">
              {{ asset.description }}
            </p>
          </section>

          <section
            v-if="asset.notes"
            class="rounded-2xl border border-brand-border bg-brand-surface"
          >
            <header class="flex items-center gap-2 border-b border-brand-border px-5 py-4">
              <FileText class="h-4 w-4 text-brand-primary" :stroke-width="1.75" />
              <h3 class="text-sm font-bold text-brand-text">{{ t('assets.fields.notes') }}</h3>
            </header>
            <p class="whitespace-pre-wrap px-5 py-4 text-sm leading-relaxed text-brand-text-secondary">
              {{ asset.notes }}
            </p>
          </section>

          <section class="rounded-2xl border border-brand-border bg-brand-surface">
            <header class="flex items-center gap-2 border-b border-brand-border px-5 py-4">
              <MapPin class="h-4 w-4 text-brand-primary" :stroke-width="1.75" />
              <h3 class="text-sm font-bold text-brand-text">
                {{ t('assets.details.currentCustody') }}
              </h3>
            </header>
            <p
              v-if="!asset.current_custody"
              class="px-5 py-8 text-center text-sm text-brand-text-muted"
            >
              {{ t('assets.details.noCurrentCustody') }}
            </p>
            <dl v-else class="grid gap-0 md:grid-cols-2">
              <div class="flex gap-3 border-b border-brand-border/80 px-5 py-4 sm:border-e">
                <span
                  class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary"
                >
                  <ShieldCheck class="h-4 w-4" :stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                  <dt class="text-xs font-semibold text-brand-text-muted">
                    {{ t('assets.fields.custodyNumber') }}
                  </dt>
                  <dd class="mt-1 font-mono text-sm font-semibold text-brand-text" dir="ltr">
                    {{ asset.current_custody.custody_number }}
                  </dd>
                </div>
              </div>
              <div class="flex gap-3 border-b border-brand-border/80 px-5 py-4">
                <span
                  class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary"
                >
                  <UserRound class="h-4 w-4" :stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                  <dt class="text-xs font-semibold text-brand-text-muted">
                    {{ t('assets.fields.employee') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text">
                    {{ asset.current_custody.employee?.full_name || '—' }}
                  </dd>
                </div>
              </div>
              <div class="flex gap-3 border-b border-brand-border/80 px-5 py-4 sm:border-e sm:border-b-0">
                <span
                  class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary"
                >
                  <CalendarRange class="h-4 w-4" :stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                  <dt class="text-xs font-semibold text-brand-text-muted">
                    {{ t('assets.fields.assignedAt') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text">
                    {{ formatDateTime(asset.current_custody.assigned_at) }}
                  </dd>
                </div>
              </div>
              <div class="flex gap-3 px-5 py-4">
                <span
                  class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary"
                >
                  <CalendarRange class="h-4 w-4" :stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                  <dt class="text-xs font-semibold text-brand-text-muted">
                    {{ t('assets.fields.expectedReturnAt') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text">
                    {{ formatDateTime(asset.current_custody.expected_return_at) }}
                  </dd>
                </div>
              </div>
            </dl>
          </section>

          <section class="rounded-2xl border border-brand-border bg-brand-surface">
            <header class="border-b border-brand-border px-5 py-4">
              <h3 class="text-sm font-bold text-brand-text">
                {{ t('assets.details.custodyHistory') }}
              </h3>
            </header>
            <p
              v-if="!custodies.length"
              class="px-5 py-8 text-center text-sm text-brand-text-muted"
            >
              {{ t('assets.details.noCustodyHistory') }}
            </p>
            <div v-else class="space-y-0">
              <div class="divide-y divide-brand-border/80 lg:hidden">
                <article
                  v-for="custody in custodies"
                  :key="`m-${custody.id}`"
                  class="space-y-2 px-4 py-3.5"
                >
                  <div class="flex flex-wrap items-center justify-between gap-2">
                    <span
                      class="inline-flex rounded-lg border border-brand-border bg-brand-bg px-2 py-1 font-mono text-[11px] font-bold text-brand-text"
                      dir="ltr"
                    >
                      {{ custody.custody_number }}
                    </span>
                    <span
                      class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold"
                      :class="custodyStatusBadgeClass(custody.status)"
                    >
                      {{ t(`assets.custodyStatus.${custody.status}`) }}
                    </span>
                  </div>
                  <p class="text-sm font-semibold text-brand-text">
                    {{ custody.employee?.full_name || '—' }}
                  </p>
                  <p class="text-xs text-brand-text-secondary">
                    <span class="font-medium text-brand-text-muted">{{ t('assets.columns.assignedAt') }}:</span>
                    {{ formatDateTime(custody.assigned_at) }}
                    <span class="mx-1.5 text-brand-text-muted">·</span>
                    <span class="font-medium text-brand-text-muted">{{ t('assets.columns.returnedAt') }}:</span>
                    {{ formatDateTime(custody.returned_at) }}
                  </p>
                </article>
              </div>

              <div class="hidden overflow-x-auto lg:block">
                <table class="min-w-full border-separate border-spacing-0 text-sm">
                  <thead>
                    <tr class="bg-[#F4F6F5]">
                      <th
                        v-for="key in [
                          'custodyNumber',
                          'employee',
                          'status',
                          'assignedAt',
                          'returnedAt',
                        ]"
                        :key="key"
                        class="whitespace-nowrap border-b border-brand-border px-5 py-3 text-center text-xs font-bold text-brand-text"
                      >
                        {{ t(`assets.columns.${key}`) }}
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="(custody, index) in custodies"
                      :key="custody.id"
                      class="group"
                      :class="index % 2 === 1 ? 'bg-[#FAFBFA]' : 'bg-brand-surface'"
                    >
                      <td
                        class="whitespace-nowrap border-b border-brand-border/80 px-5 py-3 text-center transition-colors group-hover:bg-[#EDF6F1]"
                      >
                        <span
                          class="inline-flex rounded-lg border border-brand-border bg-brand-bg px-2 py-1 font-mono text-[11px] font-bold text-brand-text"
                          dir="ltr"
                        >
                          {{ custody.custody_number }}
                        </span>
                      </td>
                      <td
                        class="max-w-[12rem] whitespace-nowrap border-b border-brand-border/80 px-5 py-3 text-center text-brand-text transition-colors group-hover:bg-[#EDF6F1]"
                      >
                        <span class="block truncate">
                          {{ custody.employee?.full_name || '—' }}
                        </span>
                      </td>
                      <td
                        class="whitespace-nowrap border-b border-brand-border/80 px-5 py-3 text-center transition-colors group-hover:bg-[#EDF6F1]"
                      >
                        <span
                          class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold"
                          :class="custodyStatusBadgeClass(custody.status)"
                        >
                          {{ t(`assets.custodyStatus.${custody.status}`) }}
                        </span>
                      </td>
                      <td
                        class="whitespace-nowrap border-b border-brand-border/80 px-5 py-3 text-center text-brand-text transition-colors group-hover:bg-[#EDF6F1]"
                      >
                        {{ formatDateTime(custody.assigned_at) }}
                      </td>
                      <td
                        class="whitespace-nowrap border-b border-brand-border/80 px-5 py-3 text-center text-brand-text transition-colors group-hover:bg-[#EDF6F1]"
                      >
                        {{ formatDateTime(custody.returned_at) }}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </section>

          <section class="rounded-2xl border border-brand-border bg-brand-surface">
            <header class="border-b border-brand-border px-5 py-4">
              <h3 class="text-base font-bold text-brand-text">
                {{ t('assets.details.timelineTitle') }}
              </h3>
              <p class="mt-1 text-sm text-brand-text-secondary">
                {{ t('assets.details.timelineSubtitle') }}
              </p>
            </header>

            <div
              v-if="!transitions.length"
              class="px-5 py-10 text-center text-sm text-brand-text-muted"
            >
              {{ t('assets.details.noTransitions') }}
            </div>

            <ol v-else class="relative space-y-0 px-5 py-5">
              <li
                v-for="(transition, index) in transitions"
                :key="transition.id"
                class="relative flex gap-4 pb-6 last:pb-0"
              >
                <div class="relative flex w-4 shrink-0 flex-col items-center">
                  <span
                    class="mt-1.5 z-10 h-3 w-3 rounded-full bg-brand-primary ring-[3px] ring-brand-primary/15"
                  />
                  <span
                    v-if="index < transitions.length - 1"
                    class="absolute top-5 bottom-0 w-px bg-brand-border"
                    aria-hidden="true"
                  />
                </div>

                <div
                  class="min-w-0 flex-1 rounded-xl border border-brand-border/80 bg-brand-bg/40 px-4 py-3"
                >
                  <div class="flex flex-wrap items-center gap-2">
                    <span
                      v-if="transition.from_status"
                      class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold"
                      :class="assetStatusBadgeClass(transition.from_status)"
                    >
                      {{ t(`assets.status.${transition.from_status}`) }}
                    </span>
                    <ArrowLeft
                      v-if="transition.from_status"
                      class="h-3.5 w-3.5 shrink-0 text-brand-text-muted"
                      :stroke-width="2"
                      aria-hidden="true"
                    />
                    <span
                      class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold"
                      :class="assetStatusBadgeClass(transition.to_status)"
                    >
                      {{ t(`assets.status.${transition.to_status}`) }}
                    </span>
                  </div>

                  <div
                    class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-brand-text-muted"
                  >
                    <span class="inline-flex items-center gap-1.5 font-semibold text-brand-text">
                      <UserRound
                        class="h-3.5 w-3.5 text-brand-text-secondary"
                        :stroke-width="1.75"
                      />
                      {{ transition.performed_by?.name ?? '—' }}
                    </span>
                    <span>{{ formatDateTime(transition.created_at) }}</span>
                  </div>

                  <p
                    v-if="transition.reason"
                    class="mt-3 flex gap-2 rounded-lg border border-brand-border/70 bg-brand-surface px-3 py-2 text-sm text-brand-text-secondary"
                  >
                    <MessageSquareText
                      class="mt-0.5 h-3.5 w-3.5 shrink-0 text-brand-text-muted"
                      :stroke-width="1.75"
                    />
                    <span>{{ transition.reason }}</span>
                  </p>
                </div>
              </li>
            </ol>
          </section>

          <EntityDocumentsSection
            linkable-type="asset"
            :linkable-id="asset.id"
            :link-label="`${asset.asset_number} — ${asset.name}`"
          />
        </div>

        <aside class="order-1 space-y-5 xl:order-2 xl:sticky xl:top-4 xl:self-start">
          <section class="rounded-2xl border border-brand-border bg-brand-surface">
            <header class="border-b border-brand-border px-5 py-4">
              <h3 class="text-sm font-bold text-brand-text">
                {{ t('assets.details.lifecycleTitle') }}
              </h3>
              <p class="mt-1 text-xs text-brand-text-secondary">
                {{ t('assets.details.lifecycleHint') }}
              </p>
            </header>
            <div class="space-y-2 px-4 py-4">
              <p
                v-if="!visibleLifecycleActions.length"
                class="px-1 py-4 text-center text-sm text-brand-text-muted"
              >
                {{ t('assets.details.lifecycleEmpty') }}
              </p>
              <button
                v-for="def in visibleLifecycleActions"
                :key="def.action"
                type="button"
                class="inline-flex h-10 w-full items-center justify-center rounded-xl border px-4 text-sm font-semibold transition disabled:opacity-50 xl:justify-start"
                :class="actionButtonClass(def.action)"
                :disabled="isLifecyclePending"
                @click="runLifecycleAction(def.action)"
              >
                {{ actionLabel(def.action) }}
              </button>
            </div>
          </section>
        </aside>
      </div>
    </template>

    <AssetFormDrawer
      :open="drawerOpen"
      :editing="asset"
      :form="form"
      :form-error="formError"
      :field-errors="fieldErrors"
      :submitting="updateMutation.isPending.value || assignMutation.isPending.value"
      :category-options="categoryFormOptions"
      :org-unit-options="orgUnitFormOptions"
      :allow-assign="canShowAssignAction(asset?.status ?? 'available', permissions)"
      :current-employee-name="asset?.current_custody?.employee?.full_name ?? null"
      @close="drawerOpen = false"
      @submit="submitEdit"
      @update:form="Object.assign(form, $event)"
    />

    <AssetAssignDialog
      :open="assignOpen"
      :form="assignForm"
      :form-error="assignFormError"
      :field-errors="assignFieldErrors"
      :submitting="assignMutation.isPending.value"
      @close="assignOpen = false"
      @submit="submitAssign"
      @update:form="Object.assign(assignForm, $event)"
    />

    <AssetReturnDialog
      :open="returnOpen"
      :form="returnForm"
      :form-error="returnFormError"
      :field-errors="returnFieldErrors"
      :submitting="returnMutation.isPending.value"
      @close="returnOpen = false"
      @submit="submitReturn"
      @update:form="Object.assign(returnForm, $event)"
    />

    <AssetLifecycleReasonDialog
      :open="reasonOpen"
      :kind="reasonKind"
      :form="reasonForm"
      :form-error="reasonFormError"
      :field-errors="reasonFieldErrors"
      :submitting="retireMutation.isPending.value || lostMutation.isPending.value"
      @close="reasonOpen = false"
      @submit="submitReason"
      @update:form="Object.assign(reasonForm, $event)"
    />
  </div>
</template>
