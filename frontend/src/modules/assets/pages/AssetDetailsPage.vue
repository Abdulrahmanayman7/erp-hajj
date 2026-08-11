<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { ArrowRight, Pencil, Trash2 } from 'lucide-vue-next'

import EntityDocumentsSection from '@/modules/documents/components/EntityDocumentsSection.vue'
import { useEmployeesQuery } from '@/modules/employees/queries/useEmployeesQuery'
import { useWarehousesQuery } from '@/modules/inventory/queries/useWarehousesQuery'
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
  availableAssetLifecycleActions,
  canDeleteAsset,
  canEditAsset,
  conditionBadgeClass,
  custodyStatusBadgeClass,
  emptyAssetForm,
  emptyAssignForm,
  emptyReturnForm,
  formatDate,
  formatDateTime,
  formatMoney,
  mapAssetErrorCode,
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

const id = computed(() => Number(route.params.id))
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
const transitions = computed(() => asset.value?.transitions ?? [])

const { data: categoriesData } = useAssetCategoriesQuery({})
const { data: warehousesData } = useWarehousesQuery(
  computed(() => ({ is_active: true, per_page: 100 })),
)
const { data: orgUnitsData } = useOrganizationUnitsFlatQuery({ status: 'active' })
const { data: employeesData } = useEmployeesQuery(
  computed(() => ({ status: 'active' as const, per_page: 100 })),
)

const categoryFormOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('assets.noCategory') },
  ...(categoriesData.value ?? [])
    .filter((c) => c.is_active)
    .map((c) => ({ value: c.id, label: c.name })),
])
const warehouseFormOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('assets.noWarehouse') },
  ...(warehousesData.value?.data ?? []).map((w) => ({
    value: w.id,
    label: w.name,
    hint: w.warehouse_number ?? undefined,
  })),
])
const orgUnitFormOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('assets.noOrgUnit') },
  ...(orgUnitsData.value?.data ?? []).map((u) => ({ value: u.id, label: u.name, hint: u.code })),
])
const employeeOptions = computed<AppSelectOption[]>(() =>
  (employeesData.value?.data ?? []).map((e) => ({
    value: e.id,
    label: e.full_name,
    hint: e.employee_number,
  })),
)

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

function apiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) return t('assets.errors.generic')
  const mapped = mapAssetErrorCode(error.code)
  if (mapped !== 'generic') return t(`assets.errors.${mapped}`)
  return error.message || t('assets.errors.generic')
}

function openEdit(): void {
  if (!asset.value) return
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
    await updateMutation.mutateAsync({
      id: asset.value.id,
      payload: {
        name: form.name.trim(),
        description: form.description.trim() || null,
        category_id: form.category_id === '' ? null : Number(form.category_id),
        serial_number: form.serial_number.trim() || null,
        barcode: form.barcode.trim() || null,
        condition: form.condition || null,
        warehouse_id: form.warehouse_id === '' ? null : Number(form.warehouse_id),
        organization_unit_id:
          form.organization_unit_id === '' ? null : Number(form.organization_unit_id),
        purchase_value: form.purchase_value.trim() === '' ? null : form.purchase_value.trim(),
        acquisition_date: form.acquisition_date.trim() || null,
        notes: form.notes.trim() || null,
      },
    })
    toast.success(t('assets.toasts.assetUpdated'))
    drawerOpen.value = false
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
    router.push('/app/assets')
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
  if (action === 'retire' || action === 'declare_lost') {
    return 'border-red-200 bg-red-50 text-red-800 hover:bg-red-100'
  }
  if (action === 'assign' || action === 'return') {
    return 'bg-brand-primary-dark text-white hover:opacity-90'
  }
  return 'border border-brand-border bg-brand-surface hover:bg-brand-bg'
}
</script>

<template>
  <div class="space-y-6">
    <button
      type="button"
      class="rounded-lg border px-3 py-2 text-sm"
      @click="router.push('/app/assets')"
    >
      <ArrowRight class="inline h-4 w-4" />
      {{ t('assets.details.backToList') }}
    </button>

    <div v-if="isLoading" class="rounded-2xl border p-10 text-center">
      {{ t('assets.loadingDetails') }}
    </div>
    <div v-else-if="isError || !asset" class="rounded-2xl border p-10 text-center">
      {{ t('assets.errors.loadDetails') }}
      <button type="button" class="ms-2 underline" @click="() => refetch()">
        {{ t('assets.retry') }}
      </button>
    </div>

    <template v-else>
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
          <div class="flex flex-wrap items-center gap-2">
            <span class="font-mono text-sm">{{ asset.asset_number }}</span>
            <span
              class="rounded-full px-2 py-0.5 text-xs font-semibold"
              :class="assetStatusBadgeClass(asset.status)"
            >
              {{ t(`assets.status.${asset.status}`) }}
            </span>
          </div>
          <h2 class="mt-2 text-2xl font-bold">{{ asset.name }}</h2>
        </div>
        <div class="flex flex-wrap gap-2">
          <PermissionGuard v-if="canEditAsset(permissions)" permission="assets.update">
            <button
              type="button"
              class="rounded-xl border px-4 py-2 text-sm font-semibold"
              @click="openEdit"
            >
              <Pencil class="inline h-4 w-4" />
              {{ t('assets.actions.edit') }}
            </button>
          </PermissionGuard>
          <PermissionGuard v-if="canDeleteAsset(permissions)" permission="assets.delete">
            <button
              type="button"
              class="rounded-xl border border-red-200 px-4 py-2 text-sm font-semibold text-red-700"
              @click="removeAsset"
            >
              <Trash2 class="inline h-4 w-4" />
              {{ t('assets.actions.delete') }}
            </button>
          </PermissionGuard>
        </div>
      </div>

      <div v-if="visibleLifecycleActions.length" class="flex flex-wrap gap-2">
        <button
          v-for="def in visibleLifecycleActions"
          :key="def.action"
          type="button"
          class="inline-flex h-11 items-center rounded-xl px-4 text-sm font-semibold"
          :class="actionButtonClass(def.action)"
          @click="runLifecycleAction(def.action)"
        >
          {{ actionLabel(def.action) }}
        </button>
      </div>

      <section>
        <h3 class="mb-3 font-bold">{{ t('assets.details.overview') }}</h3>
        <div class="grid gap-4 md:grid-cols-3">
          <div
            v-for="row in [
              { label: 'category', value: asset.category?.name },
              { label: 'serialNumber', value: asset.serial_number },
              { label: 'barcode', value: asset.barcode },
              {
                label: 'condition',
                value: asset.condition ? t(`assets.condition.${asset.condition}`) : null,
              },
              { label: 'purchaseValue', value: formatMoney(asset.purchase_value) },
              { label: 'acquisitionDate', value: formatDate(asset.acquisition_date) },
              { label: 'createdBy', value: asset.created_by?.name },
            ]"
            :key="row.label"
            class="rounded-2xl border bg-brand-surface p-4"
          >
            <p class="text-xs text-brand-text-muted">{{ t(`assets.fields.${row.label}`) }}</p>
            <p class="mt-1 font-semibold">
              <span
                v-if="row.label === 'condition' && asset.condition"
                class="rounded-full px-2 py-0.5 text-xs font-semibold"
                :class="conditionBadgeClass(asset.condition)"
              >
                {{ row.value }}
              </span>
              <template v-else>{{ row.value || '—' }}</template>
            </p>
          </div>
        </div>
        <div class="mt-4 rounded-2xl border p-4">
          <p class="text-xs text-brand-text-muted">{{ t('assets.fields.notes') }}</p>
          <p class="mt-2 whitespace-pre-wrap">{{ asset.notes || '—' }}</p>
        </div>
        <div v-if="asset.description" class="mt-4 rounded-2xl border p-4">
          <p class="text-xs text-brand-text-muted">{{ t('assets.fields.description') }}</p>
          <p class="mt-2 whitespace-pre-wrap">{{ asset.description }}</p>
        </div>
      </section>

      <section class="rounded-2xl border p-5">
        <h3 class="mb-3 font-bold">{{ t('assets.details.statusLocation') }}</h3>
        <div class="grid gap-4 md:grid-cols-3">
          <div>
            <p class="text-xs text-brand-text-muted">{{ t('assets.fields.status') }}</p>
            <span
              class="mt-1 inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
              :class="assetStatusBadgeClass(asset.status)"
            >
              {{ t(`assets.status.${asset.status}`) }}
            </span>
          </div>
          <div>
            <p class="text-xs text-brand-text-muted">{{ t('assets.fields.warehouse') }}</p>
            <p class="mt-1 font-semibold">{{ asset.warehouse?.name || '—' }}</p>
          </div>
          <div>
            <p class="text-xs text-brand-text-muted">{{ t('assets.fields.organizationUnit') }}</p>
            <p class="mt-1 font-semibold">{{ asset.organization_unit?.name || '—' }}</p>
          </div>
        </div>
      </section>

      <section class="rounded-2xl border p-5">
        <h3 class="mb-3 font-bold">{{ t('assets.details.currentCustody') }}</h3>
        <div v-if="!asset.current_custody" class="text-sm text-brand-text-muted">
          {{ t('assets.details.noCurrentCustody') }}
        </div>
        <div v-else class="grid gap-3 md:grid-cols-2">
          <div>
            <p class="text-xs text-brand-text-muted">{{ t('assets.fields.custodyNumber') }}</p>
            <p class="mt-1 font-mono text-sm font-semibold">
              {{ asset.current_custody.custody_number }}
            </p>
          </div>
          <div>
            <p class="text-xs text-brand-text-muted">{{ t('assets.fields.employee') }}</p>
            <p class="mt-1 font-semibold">
              {{ asset.current_custody.employee?.full_name || '—' }}
            </p>
          </div>
          <div>
            <p class="text-xs text-brand-text-muted">{{ t('assets.fields.assignedAt') }}</p>
            <p class="mt-1 font-semibold">
              {{ formatDateTime(asset.current_custody.assigned_at) }}
            </p>
          </div>
          <div>
            <p class="text-xs text-brand-text-muted">{{ t('assets.fields.expectedReturnAt') }}</p>
            <p class="mt-1 font-semibold">
              {{ formatDateTime(asset.current_custody.expected_return_at) }}
            </p>
          </div>
        </div>
      </section>

      <section class="rounded-2xl border p-5">
        <h3 class="mb-3 font-bold">{{ t('assets.details.custodyHistory') }}</h3>
        <div v-if="!custodies.length" class="text-sm text-brand-text-muted">
          {{ t('assets.details.noCustodyHistory') }}
        </div>
        <div v-else class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="text-xs text-brand-text-muted">
                <th class="py-2 pe-4 text-start">{{ t('assets.columns.custodyNumber') }}</th>
                <th class="py-2 pe-4 text-start">{{ t('assets.columns.employee') }}</th>
                <th class="py-2 pe-4 text-start">{{ t('assets.columns.status') }}</th>
                <th class="py-2 pe-4 text-start">{{ t('assets.columns.assignedAt') }}</th>
                <th class="py-2 text-start">{{ t('assets.columns.returnedAt') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="custody in custodies" :key="custody.id" class="border-t">
                <td class="py-2 pe-4 font-mono text-xs">{{ custody.custody_number }}</td>
                <td class="py-2 pe-4">{{ custody.employee?.full_name || '—' }}</td>
                <td class="py-2 pe-4">
                  <span
                    class="rounded-full px-2 py-0.5 text-xs font-semibold"
                    :class="custodyStatusBadgeClass(custody.status)"
                  >
                    {{ t(`assets.custodyStatus.${custody.status}`) }}
                  </span>
                </td>
                <td class="py-2 pe-4">{{ formatDateTime(custody.assigned_at) }}</td>
                <td class="py-2">{{ formatDateTime(custody.returned_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="rounded-2xl border p-5">
        <h3 class="mb-3 font-bold">{{ t('assets.details.statusTransitions') }}</h3>
        <div v-if="!transitions.length" class="text-sm text-brand-text-muted">
          {{ t('assets.details.noTransitions') }}
        </div>
        <ul v-else class="space-y-2">
          <li
            v-for="transition in transitions"
            :key="transition.id"
            class="rounded-xl border px-3 py-3 text-sm"
          >
            <div class="flex flex-wrap items-center gap-2">
              <span
                v-if="transition.from_status"
                class="rounded-full px-2 py-0.5 text-xs font-semibold"
                :class="assetStatusBadgeClass(transition.from_status)"
              >
                {{ t(`assets.status.${transition.from_status}`) }}
              </span>
              <span v-else class="text-xs text-brand-text-muted">—</span>
              <span class="text-brand-text-muted">→</span>
              <span
                class="rounded-full px-2 py-0.5 text-xs font-semibold"
                :class="assetStatusBadgeClass(transition.to_status)"
              >
                {{ t(`assets.status.${transition.to_status}`) }}
              </span>
            </div>
            <p class="mt-2 text-xs text-brand-text-secondary">
              {{ formatDateTime(transition.created_at) }}
              <span v-if="transition.performed_by"> · {{ transition.performed_by.name }}</span>
            </p>
            <p v-if="transition.reason" class="mt-1 text-sm">{{ transition.reason }}</p>
          </li>
        </ul>
      </section>

      <EntityDocumentsSection
        linkable-type="asset"
        :linkable-id="asset.id"
        :link-label="`${asset.asset_number} — ${asset.name}`"
      />
    </template>

    <AssetFormDrawer
      :open="drawerOpen"
      :editing="asset"
      :form="form"
      :form-error="formError"
      :field-errors="fieldErrors"
      :submitting="updateMutation.isPending.value"
      :category-options="categoryFormOptions"
      :warehouse-options="warehouseFormOptions"
      :org-unit-options="orgUnitFormOptions"
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
      :employee-options="employeeOptions"
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
