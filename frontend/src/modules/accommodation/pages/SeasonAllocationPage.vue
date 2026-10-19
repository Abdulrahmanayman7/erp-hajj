<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { ChevronLeft, ChevronRight, Pencil, Plus, Trash2 } from 'lucide-vue-next'

import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'
import { toSelectId } from '@/shared/lookups/selectOptions'

import AccommodationSeasonBar from '../components/AccommodationSeasonBar.vue'
import AccommodationSubnav from '../components/AccommodationSubnav.vue'
import { useAccommodationErrors } from '../composables/useAccommodationErrors'
import {
  useCreateSeasonFacilityMutation,
  useCreateSeasonPositionMutation,
  useCreateSeasonUnitMutation,
  useDeleteSeasonFacilityMutation,
  useDeleteSeasonPositionMutation,
  useDeleteSeasonUnitMutation,
  useUpdateSeasonFacilityMutation,
  useUpdateSeasonPositionMutation,
  useUpdateSeasonUnitMutation,
} from '../mutations/useAccommodationMutations'
import {
  useFacilitiesQuery,
  usePositionsQuery,
  useSeasonFacilitiesQuery,
  useSeasonPositionsQuery,
  useSeasonUnitsQuery,
  useUnitsQuery,
} from '../queries/useAccommodationQueries'
import { useAccommodationSeasonStore } from '../stores/seasonContextStore'
import type {
  AccommodationSeasonFacility,
  AccommodationSeasonPosition,
  AccommodationSeasonUnit,
  AllocationMode,
  Audience,
  SeasonFacilityFormState,
  SeasonPositionFormState,
  SeasonUnitFormState,
} from '../types/accommodation'
import { ACCOMMODATION_READ_PERMISSIONS } from '../types/accommodation'
import {
  resolveListState,
  validateSeasonFacilityForm,
  validateSeasonPositionForm,
  validateSeasonUnitForm,
} from '../validation/accommodationValidation'

type Section = 'facility' | 'unit' | 'position'

const { t } = useI18n()
const { can, canAny } = usePermissions()
const toast = useToast()
const { confirm } = useConfirm()
const { message } = useAccommodationErrors()
const seasonStore = useAccommodationSeasonStore()
const { canWrite } = storeToRefs(seasonStore)

const canRead = computed(() => canAny([...ACCOMMODATION_READ_PERMISSIONS]))
const canManage = computed(() => can('accommodation.manage_season_allocation'))

const sfPage = ref(1)
const suPage = ref(1)
const spPage = ref(1)

const { data: sfData, isLoading: sfLoading, isError: sfError, refetch: refetchSf } = useSeasonFacilitiesQuery(
  sfPage,
  canRead,
)
const { data: suData, isLoading: suLoading, isError: suError, refetch: refetchSu } = useSeasonUnitsQuery(suPage, canRead)
const { data: spData, isLoading: spLoading, isError: spError, refetch: refetchSp } = useSeasonPositionsQuery(
  spPage,
  canRead,
)

const seasonFacilities = computed(() => sfData.value?.data ?? [])
const seasonUnits = computed(() => suData.value?.data ?? [])
const seasonPositions = computed(() => spData.value?.data ?? [])

const sfListState = computed(() =>
  resolveListState({ isLoading: sfLoading.value, isError: sfError.value, count: seasonFacilities.value.length }),
)
const suListState = computed(() =>
  resolveListState({ isLoading: suLoading.value, isError: suError.value, count: seasonUnits.value.length }),
)
const spListState = computed(() =>
  resolveListState({ isLoading: spLoading.value, isError: spError.value, count: seasonPositions.value.length }),
)

const { data: facilitiesData } = useFacilitiesQuery(ref(1), canRead)

const facilityOptions = computed<AppSelectOption[]>(() =>
  (facilitiesData.value?.data ?? []).map((row) => ({
    value: row.id,
    label: `${row.name} (${row.code})`,
  })),
)

const audienceOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('accommodation.allocation.audienceUnset') },
  { value: 'male', label: t('accommodation.audience.male') },
  { value: 'female', label: t('accommodation.audience.female') },
  { value: 'family', label: t('accommodation.audience.family') },
])

const audienceRequiredOptions = computed<AppSelectOption[]>(() => [
  { value: 'male', label: t('accommodation.audience.male') },
  { value: 'female', label: t('accommodation.audience.female') },
  { value: 'family', label: t('accommodation.audience.family') },
])

const modeOptions = computed<AppSelectOption[]>(() => [
  { value: 'capacity', label: t('accommodation.allocation.mode.capacity') },
  { value: 'position', label: t('accommodation.allocation.mode.position') },
])

const seasonFacilityOptions = computed<AppSelectOption[]>(() =>
  seasonFacilities.value.map((row) => ({
    value: row.id,
    label: `#${row.id} · ${t('accommodation.fields.facilityId')} ${row.facility_id}`,
  })),
)

const seasonUnitOptions = computed<AppSelectOption[]>(() =>
  seasonUnits.value.map((row) => ({
    value: row.id,
    label: `#${row.id} · ${t('accommodation.fields.unitId')} ${row.unit_id}`,
  })),
)

const drawerSection = ref<Section | null>(null)
const editingId = ref<number | null>(null)
const formError = ref('')

const sfForm = reactive<SeasonFacilityFormState>({
  facility_id: '',
  audience: '',
  is_active: true,
})
const suForm = reactive<SeasonUnitFormState>({
  season_facility_id: '',
  unit_id: '',
  allocation_mode: 'capacity',
  capacity: '',
  audience: 'male',
  is_active: true,
})
const spForm = reactive<SeasonPositionFormState>({
  season_unit_id: '',
  position_id: '',
  is_active: true,
})

const suFormFacilityId = computed(() => {
  if (suForm.season_facility_id === '') return null
  const row = seasonFacilities.value.find((item) => item.id === suForm.season_facility_id)
  return row?.facility_id ?? null
})

const { data: unitsForFacility } = useUnitsQuery(suFormFacilityId)
const unitOptions = computed<AppSelectOption[]>(() =>
  (unitsForFacility.value?.data ?? []).map((row) => ({ value: row.id, label: row.code })),
)

const spFormUnitId = computed(() => {
  if (spForm.season_unit_id === '') return null
  const row = seasonUnits.value.find((item) => item.id === spForm.season_unit_id)
  return row?.unit_id ?? null
})

const { data: positionsForUnit } = usePositionsQuery(spFormUnitId)
const positionOptions = computed<AppSelectOption[]>(() =>
  (positionsForUnit.value?.data ?? []).map((row) => ({ value: row.id, label: row.code })),
)

const createSfMutation = useCreateSeasonFacilityMutation()
const updateSfMutation = useUpdateSeasonFacilityMutation()
const deleteSfMutation = useDeleteSeasonFacilityMutation()
const createSuMutation = useCreateSeasonUnitMutation()
const updateSuMutation = useUpdateSeasonUnitMutation()
const deleteSuMutation = useDeleteSeasonUnitMutation()
const createSpMutation = useCreateSeasonPositionMutation()
const updateSpMutation = useUpdateSeasonPositionMutation()
const deleteSpMutation = useDeleteSeasonPositionMutation()

function closeDrawer(): void {
  drawerSection.value = null
  editingId.value = null
  formError.value = ''
}

function openSfCreate(): void {
  Object.assign(sfForm, { facility_id: '', audience: '', is_active: true })
  editingId.value = null
  drawerSection.value = 'facility'
}

function openSfEdit(row: AccommodationSeasonFacility): void {
  Object.assign(sfForm, {
    facility_id: row.facility_id,
    audience: row.audience ?? '',
    is_active: row.is_active,
  })
  editingId.value = row.id
  drawerSection.value = 'facility'
}

function openSuCreate(): void {
  Object.assign(suForm, {
    season_facility_id: '',
    unit_id: '',
    allocation_mode: 'capacity' as AllocationMode,
    capacity: '',
    audience: 'male' as Audience,
    is_active: true,
  })
  editingId.value = null
  drawerSection.value = 'unit'
}

function openSuEdit(row: AccommodationSeasonUnit): void {
  Object.assign(suForm, {
    season_facility_id: row.season_facility_id,
    unit_id: row.unit_id,
    allocation_mode: row.allocation_mode,
    capacity: row.capacity ?? '',
    audience: row.audience,
    is_active: row.is_active,
  })
  editingId.value = row.id
  drawerSection.value = 'unit'
}

function openSpCreate(): void {
  Object.assign(spForm, { season_unit_id: '', position_id: '', is_active: true })
  editingId.value = null
  drawerSection.value = 'position'
}

function openSpEdit(row: AccommodationSeasonPosition): void {
  Object.assign(spForm, {
    season_unit_id: row.season_unit_id,
    position_id: row.position_id,
    is_active: row.is_active,
  })
  editingId.value = row.id
  drawerSection.value = 'position'
}

async function submitSf(): Promise<void> {
  const errors = validateSeasonFacilityForm(sfForm)
  if (Object.keys(errors).length) {
    formError.value = t('accommodation.errors.validation')
    return
  }
  const facilityId = Number(sfForm.facility_id)
  try {
    if (editingId.value) {
      await updateSfMutation.mutateAsync({
        id: editingId.value,
        payload: {
          audience: sfForm.audience === '' ? null : sfForm.audience,
          is_active: sfForm.is_active,
        },
      })
      toast.success(t('accommodation.allocation.seasonFacilityUpdated'))
    } else {
      await createSfMutation.mutateAsync({
        facility_id: facilityId,
        audience: sfForm.audience === '' ? null : sfForm.audience,
        is_active: sfForm.is_active,
      })
      toast.success(t('accommodation.allocation.seasonFacilityCreated'))
    }
    closeDrawer()
  } catch (error) {
    formError.value = message(error)
  }
}

async function submitSu(): Promise<void> {
  const errors = validateSeasonUnitForm(suForm)
  if (Object.keys(errors).length) {
    formError.value = t('accommodation.errors.validation')
    return
  }
  const payload = {
    allocation_mode: suForm.allocation_mode,
    capacity: suForm.capacity === '' ? null : Number(suForm.capacity),
    audience: suForm.audience,
    is_active: suForm.is_active,
  }
  try {
    if (editingId.value) {
      await updateSuMutation.mutateAsync({ id: editingId.value, payload })
      toast.success(t('accommodation.allocation.seasonUnitUpdated'))
    } else {
      await createSuMutation.mutateAsync({
        season_facility_id: Number(suForm.season_facility_id),
        unit_id: Number(suForm.unit_id),
        ...payload,
      })
      toast.success(t('accommodation.allocation.seasonUnitCreated'))
    }
    closeDrawer()
  } catch (error) {
    formError.value = message(error)
  }
}

async function submitSp(): Promise<void> {
  const errors = validateSeasonPositionForm(spForm)
  if (Object.keys(errors).length) {
    formError.value = t('accommodation.errors.validation')
    return
  }
  try {
    if (editingId.value) {
      await updateSpMutation.mutateAsync({
        id: editingId.value,
        payload: { is_active: spForm.is_active },
      })
      toast.success(t('accommodation.allocation.seasonPositionUpdated'))
    } else {
      await createSpMutation.mutateAsync({
        season_unit_id: Number(spForm.season_unit_id),
        position_id: Number(spForm.position_id),
        is_active: spForm.is_active,
      })
      toast.success(t('accommodation.allocation.seasonPositionCreated'))
    }
    closeDrawer()
  } catch (error) {
    formError.value = message(error)
  }
}

async function deleteRow(section: Section, id: number): Promise<void> {
  if (!canWrite.value) return
  const ok = await confirm({
    title: t('accommodation.allocation.confirmDelete.title'),
    message: t('accommodation.allocation.confirmDelete.body'),
    confirmLabel: t('accommodation.delete'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    if (section === 'facility') await deleteSfMutation.mutateAsync(id)
    else if (section === 'unit') await deleteSuMutation.mutateAsync(id)
    else await deleteSpMutation.mutateAsync(id)
    toast.success(t('accommodation.allocation.deleted'))
  } catch (error) {
    toast.error(message(error))
  }
}

watch(
  () => suForm.season_facility_id,
  () => {
    if (drawerSection.value === 'unit' && !editingId.value) suForm.unit_id = ''
  },
)

watch(
  () => spForm.season_unit_id,
  () => {
    if (drawerSection.value === 'position' && !editingId.value) spForm.position_id = ''
  },
)
</script>

<template>
  <div class="space-y-5">
    <AppPageHeader
      :title="t('accommodation.allocation.title')"
      :subtitle="t('accommodation.allocation.subtitle')"
    />

    <AccommodationSubnav />
    <AccommodationSeasonBar />

    <p class="rounded-lg bg-sky-50 px-3 py-2 text-sm text-sky-950 ring-1 ring-sky-200/70">
      {{ t('accommodation.allocation.capacityNotice') }}
    </p>

    <div v-if="!canRead && !canManage" class="rounded-xl border border-dashed border-brand-border px-4 py-10 text-center text-sm">
      {{ t('accommodation.errors.forbidden') }}
    </div>

    <template v-else>
      <!-- Season facilities -->
      <section class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h2 class="text-base font-bold">{{ t('accommodation.allocation.seasonFacilities') }}</h2>
          <PermissionGuard permission="accommodation.manage_season_allocation">
            <button
              type="button"
              class="inline-flex items-center gap-1 rounded-lg bg-brand-primary px-3 py-1.5 text-sm font-semibold text-white disabled:opacity-50"
              :disabled="!canWrite"
              @click="openSfCreate"
            >
              <Plus class="h-4 w-4" />
              {{ t('accommodation.allocation.createSeasonFacility') }}
            </button>
          </PermissionGuard>
        </div>
        <div v-if="sfListState === 'loading'" class="rounded-xl border px-4 py-8 text-center text-sm text-brand-text-muted">
          {{ t('accommodation.loading') }}
        </div>
        <div v-else-if="sfListState === 'error'" class="rounded-xl border border-red-200 bg-red-50 px-4 py-8 text-center">
          <button type="button" class="text-sm underline" @click="() => refetchSf()">{{ t('accommodation.retry') }}</button>
        </div>
        <div v-else-if="sfListState === 'empty'" class="rounded-xl border border-dashed px-4 py-8 text-center text-sm text-brand-text-secondary">
          {{ t('accommodation.allocation.emptySeasonFacilities') }}
        </div>
        <div v-else class="overflow-hidden rounded-xl border border-brand-border">
          <table class="min-w-full text-sm">
            <thead class="bg-brand-bg text-brand-text-secondary">
              <tr>
                <th class="px-4 py-3 text-start">{{ t('accommodation.fields.facilityId') }}</th>
                <th class="px-4 py-3 text-start">{{ t('accommodation.fields.audience') }}</th>
                <th class="px-4 py-3 text-start">{{ t('accommodation.fields.status') }}</th>
                <th class="px-4 py-3 text-start">{{ t('accommodation.fields.capacity') }}</th>
                <th class="px-4 py-3 text-start">{{ t('accommodation.fields.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in seasonFacilities" :key="row.id" class="border-t border-brand-border">
                <td class="px-4 py-3">{{ row.facility_id }}</td>
                <td class="px-4 py-3">{{ row.audience ? t(`accommodation.audience.${row.audience}`) : '—' }}</td>
                <td class="px-4 py-3">{{ row.is_active ? t('accommodation.status.active') : t('accommodation.status.inactive') }}</td>
                <td class="px-4 py-3">
                  <span class="text-brand-text-muted">{{ t('accommodation.allocation.configOnly') }}</span>
                  <span v-if="!row.capacity_reserved" class="ms-1 text-xs text-amber-800">({{ t('accommodation.capacityNotReserved') }})</span>
                </td>
                <td class="px-4 py-3">
                  <PermissionGuard permission="accommodation.manage_season_allocation">
                    <div class="flex gap-2">
                      <button type="button" class="text-brand-primary" :disabled="!canWrite" @click="openSfEdit(row)"><Pencil class="inline h-3.5 w-3.5" /></button>
                      <button type="button" class="text-red-700 disabled:opacity-50" :disabled="!canWrite" @click="deleteRow('facility', row.id)"><Trash2 class="inline h-3.5 w-3.5" /></button>
                    </div>
                  </PermissionGuard>
                </td>
              </tr>
            </tbody>
          </table>
          <div v-if="sfData?.meta" class="flex justify-between border-t px-4 py-2 text-sm">
            <span>{{ t('accommodation.pagination.page', { page: sfData.meta.current_page, last: sfData.meta.last_page }) }}</span>
            <div class="flex gap-1">
              <button type="button" class="rounded border px-2 disabled:opacity-40" :disabled="sfPage <= 1" @click="sfPage -= 1"><ChevronRight class="h-4 w-4" /></button>
              <button type="button" class="rounded border px-2 disabled:opacity-40" :disabled="sfPage >= sfData.meta.last_page" @click="sfPage += 1"><ChevronLeft class="h-4 w-4" /></button>
            </div>
          </div>
        </div>
      </section>

      <!-- Season units -->
      <section class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h2 class="text-base font-bold">{{ t('accommodation.allocation.seasonUnits') }}</h2>
          <PermissionGuard permission="accommodation.manage_season_allocation">
            <button type="button" class="inline-flex items-center gap-1 rounded-lg bg-brand-primary px-3 py-1.5 text-sm font-semibold text-white disabled:opacity-50" :disabled="!canWrite" @click="openSuCreate">
              <Plus class="h-4 w-4" />{{ t('accommodation.allocation.createSeasonUnit') }}
            </button>
          </PermissionGuard>
        </div>
        <div v-if="suListState === 'loading'" class="rounded-xl border px-4 py-8 text-center text-sm">{{ t('accommodation.loading') }}</div>
        <div v-else-if="suListState === 'error'" class="rounded-xl border border-red-200 bg-red-50 px-4 py-8 text-center">
          <button type="button" class="underline" @click="() => refetchSu()">{{ t('accommodation.retry') }}</button>
        </div>
        <div v-else-if="suListState === 'empty'" class="rounded-xl border border-dashed px-4 py-8 text-center text-sm">{{ t('accommodation.allocation.emptySeasonUnits') }}</div>
        <div v-else class="overflow-hidden rounded-xl border">
          <table class="min-w-full text-sm">
            <thead class="bg-brand-bg text-brand-text-secondary">
              <tr>
                <th class="px-4 py-3 text-start">{{ t('accommodation.fields.unitId') }}</th>
                <th class="px-4 py-3 text-start">{{ t('accommodation.allocation.modeLabel') }}</th>
                <th class="px-4 py-3 text-start">{{ t('accommodation.fields.capacity') }}</th>
                <th class="px-4 py-3 text-start">{{ t('accommodation.fields.audience') }}</th>
                <th class="px-4 py-3 text-start">{{ t('accommodation.fields.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in seasonUnits" :key="row.id" class="border-t">
                <td class="px-4 py-3">{{ row.unit_id }}</td>
                <td class="px-4 py-3">{{ t(`accommodation.allocation.mode.${row.allocation_mode}`) }}</td>
                <td class="px-4 py-3">
                  {{ row.capacity ?? '—' }}
                  <span v-if="!row.capacity_reserved" class="ms-1 text-xs text-amber-800">({{ t('accommodation.capacityNotReserved') }})</span>
                </td>
                <td class="px-4 py-3">{{ t(`accommodation.audience.${row.audience}`) }}</td>
                <td class="px-4 py-3">
                  <PermissionGuard permission="accommodation.manage_season_allocation">
                    <div class="flex gap-2">
                      <button type="button" :disabled="!canWrite" @click="openSuEdit(row)"><Pencil class="h-3.5 w-3.5" /></button>
                      <button type="button" class="text-red-700 disabled:opacity-50" :disabled="!canWrite" @click="deleteRow('unit', row.id)"><Trash2 class="h-3.5 w-3.5" /></button>
                    </div>
                  </PermissionGuard>
                </td>
              </tr>
            </tbody>
          </table>
          <div v-if="suData?.meta" class="flex justify-between border-t px-4 py-2 text-sm">
            <span>{{ t('accommodation.pagination.page', { page: suData.meta.current_page, last: suData.meta.last_page }) }}</span>
            <div class="flex gap-1">
              <button type="button" class="rounded border px-2 disabled:opacity-40" :disabled="suPage <= 1" @click="suPage -= 1"><ChevronRight class="h-4 w-4" /></button>
              <button type="button" class="rounded border px-2 disabled:opacity-40" :disabled="suPage >= suData.meta.last_page" @click="suPage += 1"><ChevronLeft class="h-4 w-4" /></button>
            </div>
          </div>
        </div>
      </section>

      <!-- Season positions -->
      <section class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h2 class="text-base font-bold">{{ t('accommodation.allocation.seasonPositions') }}</h2>
          <PermissionGuard permission="accommodation.manage_season_allocation">
            <button type="button" class="inline-flex items-center gap-1 rounded-lg bg-brand-primary px-3 py-1.5 text-sm font-semibold text-white disabled:opacity-50" :disabled="!canWrite" @click="openSpCreate">
              <Plus class="h-4 w-4" />{{ t('accommodation.allocation.createSeasonPosition') }}
            </button>
          </PermissionGuard>
        </div>
        <div v-if="spListState === 'loading'" class="rounded-xl border px-4 py-8 text-center text-sm">{{ t('accommodation.loading') }}</div>
        <div v-else-if="spListState === 'error'" class="rounded-xl border border-red-200 bg-red-50 px-4 py-8 text-center">
          <button type="button" class="underline" @click="() => refetchSp()">{{ t('accommodation.retry') }}</button>
        </div>
        <div v-else-if="spListState === 'empty'" class="rounded-xl border border-dashed px-4 py-8 text-center text-sm">{{ t('accommodation.allocation.emptySeasonPositions') }}</div>
        <div v-else class="overflow-hidden rounded-xl border">
          <table class="min-w-full text-sm">
            <thead class="bg-brand-bg">
              <tr>
                <th class="px-4 py-3 text-start">{{ t('accommodation.fields.positionId') }}</th>
                <th class="px-4 py-3 text-start">{{ t('accommodation.fields.unitId') }}</th>
                <th class="px-4 py-3 text-start">{{ t('accommodation.fields.status') }}</th>
                <th class="px-4 py-3 text-start">{{ t('accommodation.fields.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in seasonPositions" :key="row.id" class="border-t">
                <td class="px-4 py-3">{{ row.position_id }}</td>
                <td class="px-4 py-3">{{ row.unit_id }}</td>
                <td class="px-4 py-3">{{ row.is_active ? t('accommodation.status.active') : t('accommodation.status.inactive') }}</td>
                <td class="px-4 py-3">
                  <PermissionGuard permission="accommodation.manage_season_allocation">
                    <div class="flex gap-2">
                      <button type="button" :disabled="!canWrite" @click="openSpEdit(row)"><Pencil class="h-3.5 w-3.5" /></button>
                      <button type="button" class="text-red-700 disabled:opacity-50" :disabled="!canWrite" @click="deleteRow('position', row.id)"><Trash2 class="h-3.5 w-3.5" /></button>
                    </div>
                  </PermissionGuard>
                </td>
              </tr>
            </tbody>
          </table>
          <div v-if="spData?.meta" class="flex justify-between border-t px-4 py-2 text-sm">
            <span>{{ t('accommodation.pagination.page', { page: spData.meta.current_page, last: spData.meta.last_page }) }}</span>
            <div class="flex gap-1">
              <button type="button" class="rounded border px-2 disabled:opacity-40" :disabled="spPage <= 1" @click="spPage -= 1"><ChevronRight class="h-4 w-4" /></button>
              <button type="button" class="rounded border px-2 disabled:opacity-40" :disabled="spPage >= spData.meta.last_page" @click="spPage += 1"><ChevronLeft class="h-4 w-4" /></button>
            </div>
          </div>
        </div>
      </section>
    </template>

    <Teleport to="body">
      <div v-if="drawerSection" class="fixed inset-0 z-50">
        <div class="absolute inset-0 bg-black/30" @click="closeDrawer" />
        <aside class="app-drawer-panel absolute inset-y-0 start-0 flex w-full max-w-[480px] flex-col bg-brand-surface shadow-xl">
          <header class="border-b px-5 py-4">
            <h3 class="text-lg font-bold">{{ t('accommodation.allocation.drawerTitle') }}</h3>
          </header>
          <form
            class="flex flex-1 flex-col"
            @submit.prevent="
              drawerSection === 'facility' ? submitSf() : drawerSection === 'unit' ? submitSu() : submitSp()
            "
          >
            <div class="flex-1 space-y-4 overflow-y-auto px-5 py-5">
              <template v-if="drawerSection === 'facility'">
                <label v-if="canRead && !editingId" class="block text-sm">
                  <span class="mb-1 block text-brand-text-secondary">{{ t('accommodation.fields.facility') }}</span>
                  <AppSelect
                    :model-value="sfForm.facility_id === '' ? '' : sfForm.facility_id"
                    :options="facilityOptions"
                    @update:model-value="sfForm.facility_id = toSelectId($event)"
                  />
                </label>
                <label v-else-if="!editingId" class="block text-sm">
                  <span class="mb-1 block text-brand-text-secondary">{{ t('accommodation.fields.facilityId') }}</span>
                  <input v-model.number="sfForm.facility_id" type="number" min="1" class="w-full rounded-lg border px-3 py-2">
                </label>
                <label class="block text-sm">
                  <span class="mb-1 block text-brand-text-secondary">{{ t('accommodation.fields.audience') }}</span>
                  <AppSelect
                    :model-value="sfForm.audience"
                    :options="audienceOptions"
                    @update:model-value="sfForm.audience = $event as Audience | ''"
                  />
                </label>
                <label class="inline-flex items-center gap-2 text-sm">
                  <input v-model="sfForm.is_active" type="checkbox" class="rounded">
                  {{ t('accommodation.fields.active') }}
                </label>
              </template>
              <template v-else-if="drawerSection === 'unit'">
                <label v-if="!editingId" class="block text-sm">
                  <span class="mb-1 block">{{ t('accommodation.allocation.seasonFacility') }}</span>
                  <AppSelect
                    :model-value="suForm.season_facility_id === '' ? '' : suForm.season_facility_id"
                    :options="seasonFacilityOptions"
                    @update:model-value="suForm.season_facility_id = toSelectId($event)"
                  />
                </label>
                <label v-if="!editingId && canRead && unitOptions.length" class="block text-sm">
                  <span class="mb-1 block">{{ t('accommodation.fields.unit') }}</span>
                  <AppSelect
                    :model-value="suForm.unit_id === '' ? '' : suForm.unit_id"
                    :options="unitOptions"
                    @update:model-value="suForm.unit_id = toSelectId($event)"
                  />
                </label>
                <label v-else-if="!editingId" class="block text-sm">
                  <span class="mb-1 block">{{ t('accommodation.fields.unitId') }}</span>
                  <input v-model.number="suForm.unit_id" type="number" min="1" class="w-full rounded-lg border px-3 py-2">
                </label>
                <label class="block text-sm">
                  <span class="mb-1 block">{{ t('accommodation.allocation.modeLabel') }}</span>
                  <AppSelect
                    :model-value="suForm.allocation_mode"
                    :options="modeOptions"
                    @update:model-value="suForm.allocation_mode = $event as AllocationMode"
                  />
                </label>
                <label class="block text-sm">
                  <span class="mb-1 block">{{ t('accommodation.fields.capacity') }}</span>
                  <input v-model.number="suForm.capacity" type="number" min="0" class="w-full rounded-lg border px-3 py-2" :placeholder="t('accommodation.allocation.capacityHint')">
                </label>
                <label class="block text-sm">
                  <span class="mb-1 block">{{ t('accommodation.fields.audience') }}</span>
                  <AppSelect
                    :model-value="suForm.audience"
                    :options="audienceRequiredOptions"
                    @update:model-value="suForm.audience = $event as Audience"
                  />
                </label>
                <label class="inline-flex items-center gap-2 text-sm">
                  <input v-model="suForm.is_active" type="checkbox" class="rounded">
                  {{ t('accommodation.fields.active') }}
                </label>
              </template>
              <template v-else>
                <label v-if="!editingId" class="block text-sm">
                  <span class="mb-1 block">{{ t('accommodation.allocation.seasonUnit') }}</span>
                  <AppSelect
                    :model-value="spForm.season_unit_id === '' ? '' : spForm.season_unit_id"
                    :options="seasonUnitOptions"
                    @update:model-value="spForm.season_unit_id = toSelectId($event)"
                  />
                </label>
                <label v-if="!editingId && positionOptions.length" class="block text-sm">
                  <span class="mb-1 block">{{ t('accommodation.fields.position') }}</span>
                  <AppSelect
                    :model-value="spForm.position_id === '' ? '' : spForm.position_id"
                    :options="positionOptions"
                    @update:model-value="spForm.position_id = toSelectId($event)"
                  />
                </label>
                <label v-else-if="!editingId" class="block text-sm">
                  <span class="mb-1 block">{{ t('accommodation.fields.positionId') }}</span>
                  <input v-model.number="spForm.position_id" type="number" min="1" class="w-full rounded-lg border px-3 py-2">
                </label>
                <label class="inline-flex items-center gap-2 text-sm">
                  <input v-model="spForm.is_active" type="checkbox" class="rounded">
                  {{ t('accommodation.fields.active') }}
                </label>
              </template>
              <p v-if="formError" class="text-sm text-red-700">{{ formError }}</p>
            </div>
            <footer class="flex justify-end gap-2 border-t px-5 py-4">
              <button type="button" class="rounded-lg px-3 py-2 text-sm" @click="closeDrawer">{{ t('accommodation.cancel') }}</button>
              <button type="submit" class="rounded-lg bg-brand-primary px-3 py-2 text-sm font-semibold text-white">{{ t('accommodation.save') }}</button>
            </footer>
          </form>
        </aside>
      </div>
    </Teleport>
  </div>
</template>
