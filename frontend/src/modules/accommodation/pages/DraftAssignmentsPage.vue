<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import {
  ArrowLeftRight,
  CheckCircle2,
  ChevronLeft,
  ChevronRight,
  LogIn,
  LogOut,
  MapPin,
  Pencil,
  Plus,
  Trash2,
  Users,
  XCircle,
} from 'lucide-vue-next'

import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'
import { toSelectId } from '@/shared/lookups/selectOptions'

import { listSeasonFamilyGroupsLookup, listSeasonPilgrimsLookup } from '../api/seasonLookupsApi'
import AccommodationSeasonBar from '../components/AccommodationSeasonBar.vue'
import AccommodationSubnav from '../components/AccommodationSubnav.vue'
import BookingStatusBadge from '../components/BookingStatusBadge.vue'
import OperationalStatusBadge from '../components/OperationalStatusBadge.vue'
import { useAccommodationErrors } from '../composables/useAccommodationErrors'
import {
  useArriveAssignmentMutation,
  useCancelConfirmedAssignmentMutation,
  useCheckInAssignmentMutation,
  useCheckOutAssignmentMutation,
  useConfirmAssignmentMutation,
  useConfirmFamilyAssignmentsMutation,
  useCreateAssignmentMutation,
  useDeleteAssignmentMutation,
  useRelocateAssignmentMutation,
  useUpdateAssignmentMutation,
} from '../mutations/useAccommodationMutations'
import { useAssignmentsQuery, useSeasonPositionsQuery, useSeasonUnitsQuery } from '../queries/useAccommodationQueries'
import { useAccommodationSeasonStore } from '../stores/seasonContextStore'
import type {
  AccommodationAssignment,
  AssignmentFormState,
  SeasonFamilyGroupLookup,
  SeasonPilgrimLookup,
} from '../types/accommodation'
import { ACCOMMODATION_OPS_PERMISSIONS, ACCOMMODATION_READ_PERMISSIONS } from '../types/accommodation'
import {
  buildConfirmFamilyPayload,
  buildRelocatePayload,
  canShowArriveAction,
  canShowCancelAssignmentAction,
  canShowCheckInAction,
  canShowCheckOutAction,
  canShowConfirmAction,
  canShowRelocateAction,
  capacityDisplayKind,
  emptyAssignmentForm,
  fromLocalDatetimeInput,
  resolveListState,
  toUtcInputValue,
  validateAssignmentForm,
} from '../validation/accommodationValidation'

const { t } = useI18n()
const { can, canAny } = usePermissions()
const toast = useToast()
const { confirm } = useConfirm()
const { message } = useAccommodationErrors()
const seasonStore = useAccommodationSeasonStore()
const { canWrite, canConfirm, canOps, status: seasonStatus } = storeToRefs(seasonStore)

const canRead = computed(() =>
  canAny([
    ...ACCOMMODATION_READ_PERMISSIONS,
    'accommodation.draft',
    'accommodation.confirm',
    ...ACCOMMODATION_OPS_PERMISSIONS,
  ]),
)
const canDraft = computed(() => can('accommodation.draft'))
const hasConfirmPermission = computed(() => can('accommodation.confirm'))
const hasAnyOpsPermission = computed(() => canAny([...ACCOMMODATION_OPS_PERMISSIONS]))
const canPickPilgrim = computed(() => can('season_pilgrims.view'))
const canPickFamily = computed(() => can('season_family_groups.view'))

const page = ref(1)
const { data, isLoading, isError, refetch } = useAssignmentsQuery(page, canRead)
const assignments = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta)
const listState = computed(() =>
  resolveListState({ isLoading: isLoading.value, isError: isError.value, count: assignments.value.length }),
)

const { data: seasonUnitsData } = useSeasonUnitsQuery(ref(1), canRead)
const seasonUnitOptions = computed<AppSelectOption[]>(() =>
  (seasonUnitsData.value?.data ?? []).map((row) => ({
    value: row.id,
    label: `#${row.id} · ${t('accommodation.fields.unitId')} ${row.unit_id}`,
  })),
)

const emptySeasonPosition = computed<AppSelectOption>(() => ({
  value: '',
  label: t('accommodation.drafts.noSeasonPosition'),
}))

const { data: seasonPositionsData } = useSeasonPositionsQuery(ref(1), canRead)
const allSeasonPositions = computed(() => seasonPositionsData.value?.data ?? [])
const seasonPositionOptions = computed<AppSelectOption[]>(() => [
  emptySeasonPosition.value,
  ...allSeasonPositions.value.map((row) => ({
    value: row.id,
    label: `#${row.id} · ${t('accommodation.fields.positionId')} ${row.position_id}`,
  })),
])

function pilgrimSelectOption(row: SeasonPilgrimLookup): AppSelectOption {
  const name = row.pilgrim?.full_name ?? row.reference_number
  return { value: row.id, label: name, hint: row.reference_number }
}

function familySelectOption(row: SeasonFamilyGroupLookup): AppSelectOption {
  return { value: row.id, label: row.name, hint: `#${row.id}` }
}

const drawerOpen = ref(false)
const editingId = ref<number | null>(null)
const formError = ref('')
const form = reactive<AssignmentFormState>(emptyAssignmentForm())

const familyPanelOpen = ref(false)
const familyGroupId = ref<number | ''>('')
const selectedFamilyAssignmentIds = ref<number[]>([])
const familyError = ref('')

const checkoutOpen = ref(false)
const checkoutRow = ref<AccommodationAssignment | null>(null)
const checkoutOccurredAt = ref('')
const checkoutError = ref('')

const relocateOpen = ref(false)
const relocateRow = ref<AccommodationAssignment | null>(null)
const relocateDestUnitId = ref<number | ''>('')
const relocateDestPositionId = ref<number | ''>('')
const relocateOccurredAt = ref('')
const relocateCheckInAfter = ref(false)
const relocateError = ref('')
const lastRelocatePair = ref<{ sourceId: number; destinationId: number } | null>(null)

const createMutation = useCreateAssignmentMutation()
const updateMutation = useUpdateAssignmentMutation()
const deleteMutation = useDeleteAssignmentMutation()
const confirmMutation = useConfirmAssignmentMutation()
const confirmFamilyMutation = useConfirmFamilyAssignmentsMutation()
const arriveMutation = useArriveAssignmentMutation()
const checkInMutation = useCheckInAssignmentMutation()
const checkOutMutation = useCheckOutAssignmentMutation()
const cancelMutation = useCancelConfirmedAssignmentMutation()
const relocateMutation = useRelocateAssignmentMutation()

const confirmBusy = computed(() => confirmMutation.isPending.value || confirmFamilyMutation.isPending.value)
const opsBusy = computed(
  () =>
    arriveMutation.isPending.value ||
    checkInMutation.isPending.value ||
    checkOutMutation.isPending.value ||
    cancelMutation.isPending.value ||
    relocateMutation.isPending.value,
)
const actionBusy = computed(() => confirmBusy.value || opsBusy.value)

const draftAssignments = computed(() =>
  assignments.value.filter((row) => row.booking_status === 'draft'),
)

const selectedFamilyAssignments = computed(() =>
  draftAssignments.value.filter((row) => selectedFamilyAssignmentIds.value.includes(row.id)),
)

const relocatePositionOptions = computed<AppSelectOption[]>(() => {
  const unitId = relocateDestUnitId.value
  const filtered =
    unitId === ''
      ? allSeasonPositions.value
      : allSeasonPositions.value.filter((row) => row.season_unit_id === Number(unitId))
  return [
    emptySeasonPosition.value,
    ...filtered.map((row) => ({
      value: row.id,
      label: `#${row.id} · ${t('accommodation.fields.positionId')} ${row.position_id}`,
    })),
  ]
})

watch(relocateDestUnitId, () => {
  relocateDestPositionId.value = ''
})

function formatInterval(row: AccommodationAssignment): string {
  return `${row.starts_at} → ${row.original_ends_at}`
}

function formatEffective(row: AccommodationAssignment): string {
  return row.effective_ends_at
}

function showConfirmFor(row: AccommodationAssignment): boolean {
  return canShowConfirmAction({
    hasConfirmPermission: hasConfirmPermission.value,
    seasonStatus: seasonStatus.value,
    bookingStatus: row.booking_status,
  })
}

function showArriveFor(row: AccommodationAssignment): boolean {
  return canShowArriveAction({
    hasPermission: can('accommodation.arrive'),
    seasonStatus: seasonStatus.value,
    bookingStatus: row.booking_status,
    operationalStatus: row.operational_status,
  })
}

function showCheckInFor(row: AccommodationAssignment): boolean {
  return canShowCheckInAction({
    hasPermission: can('accommodation.check_in'),
    seasonStatus: seasonStatus.value,
    bookingStatus: row.booking_status,
    operationalStatus: row.operational_status,
  })
}

function showCheckOutFor(row: AccommodationAssignment): boolean {
  return canShowCheckOutAction({
    hasPermission: can('accommodation.check_out'),
    seasonStatus: seasonStatus.value,
    bookingStatus: row.booking_status,
    operationalStatus: row.operational_status,
  })
}

function showCancelFor(row: AccommodationAssignment): boolean {
  return canShowCancelAssignmentAction({
    hasPermission: can('accommodation.cancel_assignment'),
    seasonStatus: seasonStatus.value,
    bookingStatus: row.booking_status,
    operationalStatus: row.operational_status,
  })
}

function showRelocateFor(row: AccommodationAssignment): boolean {
  return canShowRelocateAction({
    hasPermission: can('accommodation.relocate'),
    seasonStatus: seasonStatus.value,
    bookingStatus: row.booking_status,
  })
}

function capacityKind(row: AccommodationAssignment): 'reserved' | 'historical' | 'none' {
  return capacityDisplayKind({
    bookingStatus: row.booking_status,
    capacityReserved: row.capacity_reserved,
  })
}

function closeDrawer(): void {
  drawerOpen.value = false
  editingId.value = null
  formError.value = ''
}

function openCreate(): void {
  Object.assign(form, emptyAssignmentForm())
  editingId.value = null
  formError.value = ''
  drawerOpen.value = true
}

function openEdit(row: AccommodationAssignment): void {
  if (row.booking_status !== 'draft') return
  Object.assign(form, {
    season_pilgrim_id: row.season_pilgrim_id,
    season_unit_id: row.season_unit_id,
    season_position_id: row.season_position_id ?? '',
    starts_at: toUtcInputValue(row.starts_at),
    original_ends_at: toUtcInputValue(row.original_ends_at),
  })
  editingId.value = row.id
  formError.value = ''
  drawerOpen.value = true
}

async function submit(): Promise<void> {
  const errors = validateAssignmentForm(form)
  if (Object.keys(errors).length) {
    formError.value = t('accommodation.errors.validation')
    return
  }
  const payload = {
    season_unit_id: Number(form.season_unit_id),
    season_position_id: form.season_position_id === '' ? null : Number(form.season_position_id),
    starts_at: fromLocalDatetimeInput(form.starts_at),
    original_ends_at: fromLocalDatetimeInput(form.original_ends_at),
  }
  try {
    if (editingId.value) {
      await updateMutation.mutateAsync({ id: editingId.value, payload })
      toast.success(t('accommodation.drafts.updated'))
    } else {
      await createMutation.mutateAsync({
        season_pilgrim_id: Number(form.season_pilgrim_id),
        ...payload,
      })
      toast.success(t('accommodation.drafts.created'))
    }
    closeDrawer()
  } catch (error) {
    formError.value = message(error)
  }
}

async function remove(row: AccommodationAssignment): Promise<void> {
  if (!canWrite.value || !canDraft.value || row.booking_status !== 'draft') return
  const ok = await confirm({
    title: t('accommodation.drafts.confirmDelete.title'),
    message: t('accommodation.drafts.confirmDelete.body'),
    confirmLabel: t('accommodation.delete'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deleteMutation.mutateAsync(row.id)
    toast.success(t('accommodation.drafts.deleted'))
  } catch (error) {
    toast.error(message(error))
  }
}

async function confirmOne(row: AccommodationAssignment): Promise<void> {
  if (!showConfirmFor(row) || actionBusy.value) return
  const ok = await confirm({
    title: t('accommodation.confirm.individualTitle'),
    message: t('accommodation.confirm.individualBody'),
    confirmLabel: t('accommodation.confirm.submit'),
  })
  if (!ok) return
  try {
    await confirmMutation.mutateAsync(row.id)
    toast.success(t('accommodation.confirm.success'))
  } catch (error) {
    toast.error(message(error))
  }
}

function openFamilyPanel(): void {
  familyGroupId.value = ''
  selectedFamilyAssignmentIds.value = []
  familyError.value = ''
  familyPanelOpen.value = true
}

function closeFamilyPanel(): void {
  familyPanelOpen.value = false
  familyError.value = ''
}

function toggleFamilyAssignment(id: number, checked: boolean): void {
  if (checked) {
    if (!selectedFamilyAssignmentIds.value.includes(id)) {
      selectedFamilyAssignmentIds.value = [...selectedFamilyAssignmentIds.value, id]
    }
    return
  }
  selectedFamilyAssignmentIds.value = selectedFamilyAssignmentIds.value.filter((rowId) => rowId !== id)
}

async function submitFamilyConfirm(): Promise<void> {
  if (!hasConfirmPermission.value || !canConfirm.value || actionBusy.value) return
  if (familyGroupId.value === '' || selectedFamilyAssignmentIds.value.length === 0) {
    familyError.value = t('accommodation.errors.validation')
    return
  }
  const payload = buildConfirmFamilyPayload(Number(familyGroupId.value), selectedFamilyAssignmentIds.value)
  const ok = await confirm({
    title: t('accommodation.confirm.familyTitle'),
    message: t('accommodation.confirm.familyBody', { count: payload.assignment_ids.length }),
    confirmLabel: t('accommodation.confirm.submitFamily'),
  })
  if (!ok) return
  try {
    await confirmFamilyMutation.mutateAsync(payload)
    toast.success(t('accommodation.confirm.familySuccess'))
    closeFamilyPanel()
  } catch (error) {
    familyError.value = message(error)
    toast.error(message(error))
  }
}

async function arriveOne(row: AccommodationAssignment): Promise<void> {
  if (!showArriveFor(row) || actionBusy.value) return
  const ok = await confirm({
    title: t('accommodation.ops.arriveTitle'),
    message: t('accommodation.ops.arriveBody'),
    confirmLabel: t('accommodation.ops.arrive'),
  })
  if (!ok) return
  try {
    await arriveMutation.mutateAsync({ id: row.id })
    toast.success(t('accommodation.ops.arriveSuccess'))
  } catch (error) {
    toast.error(message(error))
  }
}

async function checkInOne(row: AccommodationAssignment): Promise<void> {
  if (!showCheckInFor(row) || actionBusy.value) return
  const ok = await confirm({
    title: t('accommodation.ops.checkInTitle'),
    message: t('accommodation.ops.checkInBody'),
    confirmLabel: t('accommodation.ops.checkIn'),
  })
  if (!ok) return
  try {
    await checkInMutation.mutateAsync({ id: row.id })
    toast.success(t('accommodation.ops.checkInSuccess'))
  } catch (error) {
    toast.error(message(error))
  }
}

function openCheckout(row: AccommodationAssignment): void {
  if (!showCheckOutFor(row) || actionBusy.value) return
  checkoutRow.value = row
  checkoutOccurredAt.value = ''
  checkoutError.value = ''
  checkoutOpen.value = true
}

function closeCheckout(): void {
  checkoutOpen.value = false
  checkoutRow.value = null
  checkoutError.value = ''
}

async function submitCheckout(): Promise<void> {
  const row = checkoutRow.value
  if (!row || !showCheckOutFor(row) || actionBusy.value) return
  try {
    await checkOutMutation.mutateAsync({
      id: row.id,
      payload: checkoutOccurredAt.value
        ? { occurred_at: fromLocalDatetimeInput(checkoutOccurredAt.value) }
        : undefined,
    })
    toast.success(t('accommodation.ops.checkOutSuccess'))
    closeCheckout()
  } catch (error) {
    checkoutError.value = message(error)
    toast.error(message(error))
  }
}

async function cancelOne(row: AccommodationAssignment): Promise<void> {
  if (!showCancelFor(row) || actionBusy.value) return
  const ok = await confirm({
    title: t('accommodation.ops.cancelTitle'),
    message: t('accommodation.ops.cancelBody'),
    confirmLabel: t('accommodation.ops.submitCancel'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await cancelMutation.mutateAsync({ id: row.id })
    toast.success(t('accommodation.ops.cancelSuccess'))
  } catch (error) {
    toast.error(message(error))
  }
}

function openRelocate(row: AccommodationAssignment): void {
  if (!showRelocateFor(row) || actionBusy.value) return
  relocateRow.value = row
  relocateDestUnitId.value = ''
  relocateDestPositionId.value = ''
  relocateOccurredAt.value = ''
  relocateCheckInAfter.value = false
  relocateError.value = ''
  relocateOpen.value = true
}

function closeRelocate(): void {
  relocateOpen.value = false
  relocateRow.value = null
  relocateError.value = ''
}

async function submitRelocate(): Promise<void> {
  const row = relocateRow.value
  if (!row || !showRelocateFor(row) || actionBusy.value) return
  if (relocateDestUnitId.value === '') {
    relocateError.value = t('accommodation.errors.validation')
    return
  }
  const payload = buildRelocatePayload({
    destinationSeasonUnitId: Number(relocateDestUnitId.value),
    destinationSeasonPositionId: relocateDestPositionId.value,
    occurredAtLocal: relocateOccurredAt.value,
    checkInAfter: relocateCheckInAfter.value,
  })
  const ok = await confirm({
    title: t('accommodation.ops.relocateTitle'),
    message: t('accommodation.ops.relocateBody', {
      sourceId: row.id,
      unitId: payload.destination_season_unit_id,
    }),
    confirmLabel: t('accommodation.ops.submitRelocate'),
  })
  if (!ok) return
  try {
    const result = await relocateMutation.mutateAsync({ id: row.id, payload })
    lastRelocatePair.value = {
      sourceId: result.source.id,
      destinationId: result.destination.id,
    }
    toast.success(t('accommodation.ops.relocateSuccess'))
    closeRelocate()
  } catch (error) {
    relocateError.value = message(error)
    toast.error(message(error))
  }
}
</script>

<template>
  <div class="space-y-5">
    <AppPageHeader :title="t('accommodation.drafts.title')" :subtitle="t('accommodation.drafts.subtitle')">
      <template #actions>
        <div class="flex flex-wrap gap-2">
          <PermissionGuard permission="accommodation.confirm">
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-lg border border-brand-primary px-3 py-2 text-sm font-semibold text-brand-primary disabled:opacity-50"
              :disabled="!canConfirm || actionBusy"
              @click="openFamilyPanel"
            >
              <Users class="h-4 w-4" />
              {{ t('accommodation.confirm.familyAction') }}
            </button>
          </PermissionGuard>
          <PermissionGuard permission="accommodation.draft">
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-lg bg-brand-primary px-3 py-2 text-sm font-semibold text-white disabled:opacity-50"
              :disabled="!canWrite"
              @click="openCreate"
            >
              <Plus class="h-4 w-4" />
              {{ t('accommodation.drafts.create') }}
            </button>
          </PermissionGuard>
        </div>
      </template>
    </AppPageHeader>

    <AccommodationSubnav />
    <AccommodationSeasonBar />

    <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-950 ring-1 ring-amber-200/80">
      {{ t('accommodation.drafts.noCapacityBanner') }}
    </p>
    <p
      v-if="hasConfirmPermission && !canConfirm"
      class="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-800 ring-1 ring-slate-200/80"
    >
      {{ t('accommodation.confirm.activeOnlyNotice') }}
    </p>
    <p
      v-if="hasAnyOpsPermission && !canOps"
      class="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-800 ring-1 ring-slate-200/80"
    >
      {{ t('accommodation.ops.activeOnlyNotice') }}
    </p>
    <p
      v-if="lastRelocatePair"
      class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-950 ring-1 ring-emerald-200/80"
    >
      {{
        t('accommodation.ops.relocateHistory', {
          sourceId: lastRelocatePair.sourceId,
          destinationId: lastRelocatePair.destinationId,
        })
      }}
    </p>

    <div v-if="!canRead" class="rounded-xl border border-dashed px-4 py-10 text-center text-sm">
      {{ t('accommodation.errors.forbidden') }}
    </div>
    <div v-else-if="listState === 'loading'" class="rounded-xl border px-4 py-10 text-center text-sm text-brand-text-muted">
      {{ t('accommodation.loading') }}
    </div>
    <div v-else-if="listState === 'error'" class="rounded-xl border border-red-200 bg-red-50 px-4 py-8 text-center">
      <button type="button" class="text-sm underline" @click="() => refetch()">{{ t('accommodation.retry') }}</button>
    </div>
    <div v-else-if="listState === 'empty'" class="rounded-xl border border-dashed px-4 py-10 text-center text-sm">
      {{ t('accommodation.drafts.empty') }}
    </div>
    <div v-else class="overflow-x-auto rounded-xl border border-brand-border bg-brand-surface">
      <table class="min-w-full text-sm">
        <thead class="bg-brand-bg text-brand-text-secondary">
          <tr>
            <th class="px-4 py-3 text-start">{{ t('accommodation.fields.status') }}</th>
            <th class="px-4 py-3 text-start">{{ t('accommodation.fields.seasonPilgrimId') }}</th>
            <th class="px-4 py-3 text-start">{{ t('accommodation.fields.seasonUnitId') }}</th>
            <th class="px-4 py-3 text-start">{{ t('accommodation.fields.interval') }}</th>
            <th class="px-4 py-3 text-start">{{ t('accommodation.fields.capacity') }}</th>
            <th class="px-4 py-3 text-start">{{ t('accommodation.fields.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in assignments" :key="row.id" class="border-t border-brand-border align-top">
            <td class="px-4 py-3">
              <div class="flex flex-col items-start gap-1.5">
                <BookingStatusBadge :status="row.booking_status" />
                <OperationalStatusBadge
                  v-if="row.booking_status !== 'draft' && row.booking_status !== 'cancelled'"
                  :status="row.operational_status"
                />
                <span
                  v-if="row.relocated_from_assignment_id"
                  class="text-[11px] text-brand-text-secondary"
                >
                  {{ t('accommodation.fields.relocatedFrom') }} #{{ row.relocated_from_assignment_id }}
                </span>
              </div>
            </td>
            <td class="px-4 py-3">{{ row.season_pilgrim_id }}</td>
            <td class="px-4 py-3">{{ row.season_unit_id }}</td>
            <td class="px-4 py-3 text-xs text-brand-text-secondary">
              <div>{{ t('accommodation.fields.originalInterval') }}: {{ formatInterval(row) }}</div>
              <div v-if="row.effective_ends_at !== row.original_ends_at">
                {{ t('accommodation.fields.effectiveEndsAt') }}: {{ formatEffective(row) }}
              </div>
              <div v-if="row.actual_departed_at">
                {{ t('accommodation.fields.actualDepartedAt') }}: {{ row.actual_departed_at }}
              </div>
            </td>
            <td class="px-4 py-3">
              <span v-if="capacityKind(row) === 'reserved'" class="text-xs text-emerald-800">
                {{ t('accommodation.capacityReserved') }}
              </span>
              <span v-else-if="capacityKind(row) === 'historical'" class="text-xs text-sky-800">
                {{ t('accommodation.capacityHistorical') }}
              </span>
              <span v-else class="text-xs text-amber-800">{{ t('accommodation.capacityNone') }}</span>
            </td>
            <td class="px-4 py-3">
              <div class="flex max-w-[18rem] flex-wrap items-center gap-1.5 sm:max-w-none">
                <PermissionGuard permission="accommodation.confirm">
                  <button
                    v-if="showConfirmFor(row)"
                    type="button"
                    class="inline-flex items-center gap-1 rounded-md bg-emerald-700 px-2 py-1 text-xs font-semibold text-white disabled:opacity-50"
                    :disabled="actionBusy"
                    :aria-label="t('accommodation.confirm.submit')"
                    @click="confirmOne(row)"
                  >
                    <CheckCircle2 class="h-3.5 w-3.5" />
                    {{ t('accommodation.confirm.submit') }}
                  </button>
                </PermissionGuard>
                <PermissionGuard permission="accommodation.arrive">
                  <button
                    v-if="showArriveFor(row)"
                    type="button"
                    class="inline-flex items-center gap-1 rounded-md border border-indigo-700 px-2 py-1 text-xs font-semibold text-indigo-800 disabled:opacity-50"
                    :disabled="actionBusy"
                    @click="arriveOne(row)"
                  >
                    <MapPin class="h-3.5 w-3.5" />
                    {{ t('accommodation.ops.arrive') }}
                  </button>
                </PermissionGuard>
                <PermissionGuard permission="accommodation.check_in">
                  <button
                    v-if="showCheckInFor(row)"
                    type="button"
                    class="inline-flex items-center gap-1 rounded-md bg-teal-700 px-2 py-1 text-xs font-semibold text-white disabled:opacity-50"
                    :disabled="actionBusy"
                    @click="checkInOne(row)"
                  >
                    <LogIn class="h-3.5 w-3.5" />
                    {{ t('accommodation.ops.checkIn') }}
                  </button>
                </PermissionGuard>
                <PermissionGuard permission="accommodation.check_out">
                  <button
                    v-if="showCheckOutFor(row)"
                    type="button"
                    class="inline-flex items-center gap-1 rounded-md bg-slate-700 px-2 py-1 text-xs font-semibold text-white disabled:opacity-50"
                    :disabled="actionBusy"
                    @click="openCheckout(row)"
                  >
                    <LogOut class="h-3.5 w-3.5" />
                    {{ t('accommodation.ops.checkOut') }}
                  </button>
                </PermissionGuard>
                <PermissionGuard permission="accommodation.relocate">
                  <button
                    v-if="showRelocateFor(row)"
                    type="button"
                    class="inline-flex items-center gap-1 rounded-md border border-brand-primary px-2 py-1 text-xs font-semibold text-brand-primary disabled:opacity-50"
                    :disabled="actionBusy"
                    @click="openRelocate(row)"
                  >
                    <ArrowLeftRight class="h-3.5 w-3.5" />
                    {{ t('accommodation.ops.relocate') }}
                  </button>
                </PermissionGuard>
                <PermissionGuard permission="accommodation.cancel_assignment">
                  <button
                    v-if="showCancelFor(row)"
                    type="button"
                    class="inline-flex items-center gap-1 rounded-md border border-red-700 px-2 py-1 text-xs font-semibold text-red-800 disabled:opacity-50"
                    :disabled="actionBusy"
                    @click="cancelOne(row)"
                  >
                    <XCircle class="h-3.5 w-3.5" />
                    {{ t('accommodation.ops.cancelAssignment') }}
                  </button>
                </PermissionGuard>
                <PermissionGuard v-if="row.booking_status === 'draft'" permission="accommodation.draft">
                  <div class="flex gap-2">
                    <button type="button" class="text-brand-primary disabled:opacity-50" :disabled="!canWrite" @click="openEdit(row)">
                      <Pencil class="h-4 w-4" />
                    </button>
                    <button type="button" class="text-red-700 disabled:opacity-50" :disabled="!canWrite" @click="remove(row)">
                      <Trash2 class="h-4 w-4" />
                    </button>
                  </div>
                </PermissionGuard>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="meta" class="flex items-center justify-between border-t px-4 py-3 text-sm">
        <span>{{ t('accommodation.pagination.page', { page: meta.current_page, last: meta.last_page }) }}</span>
        <div class="flex gap-2">
          <button type="button" class="rounded border px-2 disabled:opacity-40" :disabled="page <= 1" @click="page -= 1"><ChevronRight class="h-4 w-4" /></button>
          <button type="button" class="rounded border px-2 disabled:opacity-40" :disabled="page >= meta.last_page" @click="page += 1"><ChevronLeft class="h-4 w-4" /></button>
        </div>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="drawerOpen" class="fixed inset-0 z-50">
        <div class="absolute inset-0 bg-black/30" @click="closeDrawer" />
        <aside class="app-drawer-panel absolute inset-y-0 start-0 flex w-full max-w-[480px] flex-col bg-brand-surface shadow-xl">
          <header class="border-b px-5 py-4">
            <h3 class="text-lg font-bold">
              {{ editingId ? t('accommodation.drafts.editTitle') : t('accommodation.drafts.createTitle') }}
            </h3>
          </header>
          <form class="flex flex-1 flex-col" @submit.prevent="submit">
            <div class="flex-1 space-y-4 overflow-y-auto px-5 py-5">
              <label v-if="!editingId && canPickPilgrim" class="block text-sm">
                <span class="mb-1 block text-brand-text-secondary">{{ t('accommodation.fields.seasonPilgrim') }}</span>
                <AppRemoteSelect
                  :model-value="form.season_pilgrim_id === '' ? null : form.season_pilgrim_id"
                  query-key="accommodation-season-pilgrims"
                  :fetcher="listSeasonPilgrimsLookup"
                  :map-option="pilgrimSelectOption"
                  @update:model-value="form.season_pilgrim_id = toSelectId($event)"
                />
              </label>
              <label v-else-if="!editingId" class="block text-sm">
                <span class="mb-1 block text-brand-text-secondary">{{ t('accommodation.fields.seasonPilgrimId') }}</span>
                <input v-model.number="form.season_pilgrim_id" type="number" min="1" class="w-full rounded-lg border px-3 py-2">
              </label>
              <label class="block text-sm">
                <span class="mb-1 block text-brand-text-secondary">{{ t('accommodation.fields.seasonUnit') }}</span>
                <AppSelect
                  :model-value="form.season_unit_id === '' ? '' : form.season_unit_id"
                  :options="seasonUnitOptions"
                  @update:model-value="form.season_unit_id = toSelectId($event)"
                />
              </label>
              <label class="block text-sm">
                <span class="mb-1 block text-brand-text-secondary">{{ t('accommodation.fields.seasonPosition') }}</span>
                <AppSelect
                  :model-value="form.season_position_id === '' ? '' : form.season_position_id"
                  :options="seasonPositionOptions"
                  @update:model-value="form.season_position_id = toSelectId($event)"
                />
              </label>
              <label class="block text-sm">
                <span class="mb-1 block text-brand-text-secondary">{{ t('accommodation.fields.startsAt') }}</span>
                <input v-model="form.starts_at" type="datetime-local" class="w-full rounded-lg border px-3 py-2">
              </label>
              <label class="block text-sm">
                <span class="mb-1 block text-brand-text-secondary">{{ t('accommodation.fields.endsAt') }}</span>
                <input v-model="form.original_ends_at" type="datetime-local" class="w-full rounded-lg border px-3 py-2">
              </label>
              <p v-if="formError" class="text-sm text-red-700">{{ formError }}</p>
            </div>
            <footer class="flex justify-end gap-2 border-t px-5 py-4">
              <button type="button" class="rounded-lg px-3 py-2 text-sm" @click="closeDrawer">{{ t('accommodation.cancel') }}</button>
              <button type="submit" class="rounded-lg bg-brand-primary px-3 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="!canWrite">
                {{ t('accommodation.save') }}
              </button>
            </footer>
          </form>
        </aside>
      </div>
    </Teleport>

    <Teleport to="body">
      <div v-if="familyPanelOpen" class="fixed inset-0 z-50" role="dialog" aria-modal="true" :aria-label="t('accommodation.confirm.familyTitle')">
        <div class="absolute inset-0 bg-black/30" @click="closeFamilyPanel" />
        <aside class="app-drawer-panel absolute inset-y-0 start-0 flex w-full max-w-[520px] flex-col bg-brand-surface shadow-xl">
          <header class="border-b px-5 py-4">
            <h3 class="text-lg font-bold">{{ t('accommodation.confirm.familyTitle') }}</h3>
            <p class="mt-1 text-sm text-brand-text-secondary">{{ t('accommodation.confirm.familyHint') }}</p>
          </header>
          <div class="flex flex-1 flex-col">
            <div class="flex-1 space-y-4 overflow-y-auto px-5 py-5">
              <label v-if="canPickFamily" class="block text-sm">
                <span class="mb-1 block text-brand-text-secondary">{{ t('accommodation.fields.familyGroup') }}</span>
                <AppRemoteSelect
                  :model-value="familyGroupId === '' ? null : familyGroupId"
                  query-key="accommodation-season-family-groups"
                  :fetcher="listSeasonFamilyGroupsLookup"
                  :map-option="familySelectOption"
                  @update:model-value="familyGroupId = toSelectId($event)"
                />
              </label>
              <label v-else class="block text-sm">
                <span class="mb-1 block text-brand-text-secondary">{{ t('accommodation.fields.familyGroupId') }}</span>
                <input v-model.number="familyGroupId" type="number" min="1" class="w-full rounded-lg border px-3 py-2">
              </label>

              <div>
                <p class="mb-2 text-sm font-medium text-brand-text-secondary">{{ t('accommodation.confirm.selectDrafts') }}</p>
                <div v-if="draftAssignments.length === 0" class="rounded-lg border border-dashed px-3 py-6 text-center text-sm">
                  {{ t('accommodation.confirm.noDrafts') }}
                </div>
                <ul v-else class="space-y-2">
                  <li
                    v-for="row in draftAssignments"
                    :key="row.id"
                    class="flex items-start gap-3 rounded-lg border border-brand-border px-3 py-2"
                  >
                    <input
                      :id="`family-asg-${row.id}`"
                      type="checkbox"
                      class="mt-1"
                      :checked="selectedFamilyAssignmentIds.includes(row.id)"
                      :disabled="actionBusy"
                      @change="toggleFamilyAssignment(row.id, ($event.target as HTMLInputElement).checked)"
                    >
                    <label :for="`family-asg-${row.id}`" class="flex-1 text-sm">
                      <span class="font-medium">#{{ row.id }}</span>
                      <span class="ms-2 text-brand-text-secondary">
                        {{ t('accommodation.fields.seasonPilgrimId') }} {{ row.season_pilgrim_id }}
                      </span>
                      <div class="text-xs text-brand-text-muted">{{ formatInterval(row) }}</div>
                    </label>
                  </li>
                </ul>
              </div>

              <div v-if="selectedFamilyAssignments.length" class="rounded-lg bg-slate-50 px-3 py-3 text-sm ring-1 ring-slate-200/80">
                <p class="font-semibold">{{ t('accommodation.confirm.selectedSummary', { count: selectedFamilyAssignments.length }) }}</p>
                <p class="mt-1 text-brand-text-secondary">{{ t('accommodation.confirm.allOrNothing') }}</p>
              </div>
              <p v-if="familyError" class="text-sm text-red-700">{{ familyError }}</p>
            </div>
            <footer class="flex justify-end gap-2 border-t px-5 py-4">
              <button type="button" class="rounded-lg px-3 py-2 text-sm" :disabled="actionBusy" @click="closeFamilyPanel">
                {{ t('accommodation.cancel') }}
              </button>
              <button
                type="button"
                class="rounded-lg bg-emerald-700 px-3 py-2 text-sm font-semibold text-white disabled:opacity-50"
                :disabled="!canConfirm || actionBusy || selectedFamilyAssignmentIds.length === 0 || familyGroupId === ''"
                @click="submitFamilyConfirm"
              >
                {{ t('accommodation.confirm.submitFamily') }}
              </button>
            </footer>
          </div>
        </aside>
      </div>
    </Teleport>

    <Teleport to="body">
      <div
        v-if="checkoutOpen && checkoutRow"
        class="fixed inset-0 z-50"
        role="dialog"
        aria-modal="true"
        :aria-label="t('accommodation.ops.checkOutTitle')"
      >
        <div class="absolute inset-0 bg-black/30" @click="closeCheckout" />
        <aside class="app-drawer-panel absolute inset-y-0 start-0 flex w-full max-w-[480px] flex-col bg-brand-surface shadow-xl">
          <header class="border-b px-5 py-4">
            <h3 class="text-lg font-bold">{{ t('accommodation.ops.checkOutTitle') }}</h3>
            <p class="mt-1 text-sm text-brand-text-secondary">{{ t('accommodation.ops.checkOutHint') }}</p>
          </header>
          <div class="flex flex-1 flex-col">
            <div class="flex-1 space-y-4 overflow-y-auto px-5 py-5">
              <p class="text-sm">
                {{
                  t('accommodation.ops.checkOutBody', {
                    original: formatInterval(checkoutRow),
                  })
                }}
              </p>
              <div class="rounded-lg bg-slate-50 px-3 py-3 text-xs text-slate-800 ring-1 ring-slate-200/80">
                <div>{{ t('accommodation.fields.effectiveEndsAt') }}: {{ checkoutRow.effective_ends_at }}</div>
                <div class="mt-1">{{ t('accommodation.fields.actualDepartedAt') }} ≠ {{ t('accommodation.fields.endsAt') }}</div>
              </div>
              <label class="block text-sm">
                <span class="mb-1 block text-brand-text-secondary">{{ t('accommodation.fields.occurredAt') }}</span>
                <input v-model="checkoutOccurredAt" type="datetime-local" class="w-full rounded-lg border px-3 py-2" :disabled="actionBusy">
              </label>
              <p v-if="checkoutError" class="text-sm text-red-700">{{ checkoutError }}</p>
            </div>
            <footer class="flex justify-end gap-2 border-t px-5 py-4">
              <button type="button" class="rounded-lg px-3 py-2 text-sm" :disabled="actionBusy" @click="closeCheckout">
                {{ t('accommodation.cancel') }}
              </button>
              <button
                type="button"
                class="rounded-lg bg-slate-700 px-3 py-2 text-sm font-semibold text-white disabled:opacity-50"
                :disabled="actionBusy || !canOps"
                @click="submitCheckout"
              >
                {{ t('accommodation.ops.submitCheckOut') }}
              </button>
            </footer>
          </div>
        </aside>
      </div>
    </Teleport>

    <Teleport to="body">
      <div
        v-if="relocateOpen && relocateRow"
        class="fixed inset-0 z-50"
        role="dialog"
        aria-modal="true"
        :aria-label="t('accommodation.ops.relocateTitle')"
      >
        <div class="absolute inset-0 bg-black/30" @click="closeRelocate" />
        <aside class="app-drawer-panel absolute inset-y-0 start-0 flex w-full max-w-[520px] flex-col bg-brand-surface shadow-xl">
          <header class="border-b px-5 py-4">
            <h3 class="text-lg font-bold">{{ t('accommodation.ops.relocateTitle') }}</h3>
            <p class="mt-1 text-sm text-brand-text-secondary">{{ t('accommodation.ops.relocateHint') }}</p>
          </header>
          <div class="flex flex-1 flex-col">
            <div class="flex-1 space-y-4 overflow-y-auto px-5 py-5">
              <div class="rounded-lg bg-slate-50 px-3 py-3 text-sm ring-1 ring-slate-200/80">
                <p class="font-semibold">#{{ relocateRow.id }}</p>
                <p class="text-xs text-brand-text-secondary">{{ formatInterval(relocateRow) }}</p>
                <p class="mt-1 text-xs text-amber-900">{{ t('accommodation.ops.noAvailabilityGuarantee') }}</p>
              </div>
              <label class="block text-sm">
                <span class="mb-1 block text-brand-text-secondary">{{ t('accommodation.fields.destinationSeasonUnit') }}</span>
                <AppSelect
                  :model-value="relocateDestUnitId === '' ? '' : relocateDestUnitId"
                  :options="seasonUnitOptions"
                  :disabled="actionBusy"
                  @update:model-value="relocateDestUnitId = toSelectId($event)"
                />
              </label>
              <label class="block text-sm">
                <span class="mb-1 block text-brand-text-secondary">{{ t('accommodation.fields.destinationSeasonPosition') }}</span>
                <AppSelect
                  :model-value="relocateDestPositionId === '' ? '' : relocateDestPositionId"
                  :options="relocatePositionOptions"
                  :disabled="actionBusy"
                  @update:model-value="relocateDestPositionId = toSelectId($event)"
                />
              </label>
              <label class="block text-sm">
                <span class="mb-1 block text-brand-text-secondary">{{ t('accommodation.fields.occurredAt') }}</span>
                <input v-model="relocateOccurredAt" type="datetime-local" class="w-full rounded-lg border px-3 py-2" :disabled="actionBusy">
              </label>
              <label class="flex items-center gap-2 text-sm">
                <input v-model="relocateCheckInAfter" type="checkbox" :disabled="actionBusy">
                <span>{{ t('accommodation.fields.checkInAfter') }}</span>
              </label>
              <p v-if="relocateError" class="text-sm text-red-700">{{ relocateError }}</p>
            </div>
            <footer class="flex justify-end gap-2 border-t px-5 py-4">
              <button type="button" class="rounded-lg px-3 py-2 text-sm" :disabled="actionBusy" @click="closeRelocate">
                {{ t('accommodation.cancel') }}
              </button>
              <button
                type="button"
                class="rounded-lg bg-brand-primary px-3 py-2 text-sm font-semibold text-white disabled:opacity-50"
                :disabled="actionBusy || !canOps || relocateDestUnitId === ''"
                @click="submitRelocate"
              >
                {{ t('accommodation.ops.submitRelocate') }}
              </button>
            </footer>
          </div>
        </aside>
      </div>
    </Teleport>
  </div>
</template>
