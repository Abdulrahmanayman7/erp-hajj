<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { ChevronDown, ChevronRight, ChevronUp, Pencil, Plus, Trash2 } from 'lucide-vue-next'

import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'

import AccommodationSeasonBar from '../components/AccommodationSeasonBar.vue'
import AccommodationSubnav from '../components/AccommodationSubnav.vue'
import { useAccommodationErrors } from '../composables/useAccommodationErrors'
import {
  useCreatePositionMutation,
  useCreateStructureMutation,
  useCreateUnitMutation,
  useDeleteFacilityMutation,
  useDeletePositionMutation,
  useDeleteStructureMutation,
  useDeleteUnitMutation,
  useUpdateFacilityMutation,
  useUpdatePositionMutation,
  useUpdateStructureMutation,
  useUpdateUnitMutation,
} from '../mutations/useAccommodationMutations'
import {
  useFacilityQuery,
  usePositionsQuery,
  useStructuresQuery,
  useUnitsQuery,
} from '../queries/useAccommodationQueries'
import { useAccommodationSeasonStore } from '../stores/seasonContextStore'
import type {
  AccommodationPosition,
  AccommodationStructure,
  AccommodationUnit,
  FacilityFormState,
  PositionFormState,
  StructureFormState,
  StructureKind,
  UnitFormState,
  UnitKind,
} from '../types/accommodation'
import { ACCOMMODATION_READ_PERMISSIONS } from '../types/accommodation'
import {
  emptyFacilityForm,
  emptyPositionForm,
  emptyStructureForm,
  emptyUnitForm,
  validateFacilityForm,
  validatePositionForm,
  validateStructureForm,
  validateUnitForm,
} from '../validation/accommodationValidation'

type DrawerKind = 'facility' | 'structure' | 'unit' | 'position' | null

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { canAny } = usePermissions()
const toast = useToast()
const { confirm } = useConfirm()
const { message } = useAccommodationErrors()
const seasonStore = useAccommodationSeasonStore()
const { canWrite } = storeToRefs(seasonStore)

const facilityId = computed(() => {
  const raw = route.params.id
  const value = Number(Array.isArray(raw) ? raw[0] : raw)
  return Number.isFinite(value) && value > 0 ? value : null
})

/** Reads require view|draft + scope (PERMISSIONS.md). manage_facilities alone cannot GET. */
const canRead = computed(() => canAny([...ACCOMMODATION_READ_PERMISSIONS]))
const { data: facility, isLoading, isError, refetch } = useFacilityQuery(facilityId, canRead)
const { data: structuresData, isLoading: structuresLoading } = useStructuresQuery(facilityId, canRead)
const { data: unitsData, isLoading: unitsLoading } = useUnitsQuery(facilityId, canRead)

const structures = computed(() => structuresData.value?.data ?? [])
const units = computed(() => unitsData.value?.data ?? [])

const structureById = computed(() => {
  const map = new Map<number, AccommodationStructure>()
  for (const row of structures.value) map.set(row.id, row)
  return map
})

function structureParentLabel(row: AccommodationStructure): string {
  if (!row.parent_id) return t('accommodation.facilityDetails.noParent')
  return structureById.value.get(row.parent_id)?.name ?? `#${row.parent_id}`
}

const unitsByStructure = computed(() => {
  const map = new Map<number | 'none', AccommodationUnit[]>()
  for (const unit of units.value) {
    const key = unit.structure_id ?? 'none'
    const list = map.get(key) ?? []
    list.push(unit)
    map.set(key, list)
  }
  return map
})

const orphanUnits = computed(() => unitsByStructure.value.get('none') ?? [])

const pageReady = computed(() => {
  if (!canRead.value) return 'forbidden'
  if (isLoading.value || structuresLoading.value || unitsLoading.value) return 'loading'
  if (isError.value || !facility.value) return 'error'
  return 'ready'
})

const expandedUnitId = ref<number | null>(null)
const positionsUnitId = computed(() => expandedUnitId.value)
const { data: positionsData, isLoading: positionsLoading } = usePositionsQuery(positionsUnitId, canRead)
const positions = computed(() => positionsData.value?.data ?? [])

function toggleUnit(unitId: number): void {
  expandedUnitId.value = expandedUnitId.value === unitId ? null : unitId
}

const drawerKind = ref<DrawerKind>(null)
const editingStructureId = ref<number | null>(null)
const editingUnitId = ref<number | null>(null)
const editingPositionId = ref<number | null>(null)
const formError = ref('')

const facilityForm = reactive<FacilityFormState>(emptyFacilityForm())
const structureForm = reactive<StructureFormState>(emptyStructureForm())
const unitForm = reactive<UnitFormState>(emptyUnitForm())
const positionForm = reactive<PositionFormState>(emptyPositionForm())

const updateFacilityMutation = useUpdateFacilityMutation()
const deleteFacilityMutation = useDeleteFacilityMutation()
const createStructureMutation = useCreateStructureMutation()
const updateStructureMutation = useUpdateStructureMutation()
const deleteStructureMutation = useDeleteStructureMutation()
const createUnitMutation = useCreateUnitMutation()
const updateUnitMutation = useUpdateUnitMutation()
const deleteUnitMutation = useDeleteUnitMutation()
const createPositionMutation = useCreatePositionMutation()
const updatePositionMutation = useUpdatePositionMutation()
const deletePositionMutation = useDeletePositionMutation()

const parentStructureOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('accommodation.facilityDetails.noParent') },
  ...structures.value
    .filter((row) => row.id !== editingStructureId.value)
    .map((row) => ({ value: row.id, label: row.name, hint: t(`accommodation.structureKind.${row.kind}`) })),
])

const unitStructureOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('accommodation.facilityDetails.noStructure') },
  ...structures.value.map((row) => ({ value: row.id, label: row.name })),
])

function closeDrawer(): void {
  drawerKind.value = null
  formError.value = ''
}

function openFacilityEdit(): void {
  if (!facility.value) return
  Object.assign(facilityForm, {
    kind: facility.value.kind,
    code: facility.value.code,
    name: facility.value.name,
    is_active: facility.value.is_active,
  })
  formError.value = ''
  drawerKind.value = 'facility'
}

function openStructureCreate(): void {
  Object.assign(structureForm, emptyStructureForm())
  editingStructureId.value = null
  formError.value = ''
  drawerKind.value = 'structure'
}

function openStructureEdit(row: AccommodationStructure): void {
  Object.assign(structureForm, {
    kind: row.kind,
    name: row.name,
    parent_id: row.parent_id ?? '',
  })
  editingStructureId.value = row.id
  formError.value = ''
  drawerKind.value = 'structure'
}

function openUnitCreate(structureId: number | null = null): void {
  Object.assign(unitForm, emptyUnitForm())
  if (structureId != null) unitForm.structure_id = structureId
  editingUnitId.value = null
  formError.value = ''
  drawerKind.value = 'unit'
}

function openUnitEdit(row: AccommodationUnit): void {
  Object.assign(unitForm, {
    unit_kind: row.unit_kind,
    code: row.code,
    structure_id: row.structure_id ?? '',
  })
  editingUnitId.value = row.id
  formError.value = ''
  drawerKind.value = 'unit'
}

function openPositionCreate(): void {
  if (!expandedUnitId.value) return
  Object.assign(positionForm, emptyPositionForm())
  editingPositionId.value = null
  formError.value = ''
  drawerKind.value = 'position'
}

function openPositionEdit(row: AccommodationPosition): void {
  Object.assign(positionForm, { code: row.code })
  editingPositionId.value = row.id
  formError.value = ''
  drawerKind.value = 'position'
}

async function submitFacility(): Promise<void> {
  if (!facilityId.value) return
  const errors = validateFacilityForm(facilityForm)
  if (Object.keys(errors).length) {
    formError.value = t('accommodation.errors.validation')
    return
  }
  try {
    await updateFacilityMutation.mutateAsync({
      id: facilityId.value,
      payload: {
        code: facilityForm.code.trim(),
        name: facilityForm.name.trim(),
        is_active: facilityForm.is_active,
      },
    })
    toast.success(t('accommodation.facilities.updated'))
    closeDrawer()
  } catch (error) {
    formError.value = message(error)
  }
}

async function deleteFacility(): Promise<void> {
  if (!facilityId.value || !canWrite.value) return
  const ok = await confirm({
    title: t('accommodation.facilityDetails.confirmDelete.title'),
    message: t('accommodation.facilityDetails.confirmDelete.body'),
    confirmLabel: t('accommodation.delete'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deleteFacilityMutation.mutateAsync(facilityId.value)
    toast.success(t('accommodation.facilities.deleted'))
    router.push('/app/accommodation')
  } catch (error) {
    toast.error(message(error))
  }
}

async function submitStructure(): Promise<void> {
  if (!facilityId.value) return
  const errors = validateStructureForm(structureForm)
  if (Object.keys(errors).length) {
    formError.value = t('accommodation.errors.validation')
    return
  }
  const payload = {
    kind: structureForm.kind,
    name: structureForm.name.trim(),
    parent_id: structureForm.parent_id === '' ? null : structureForm.parent_id,
  }
  try {
    if (editingStructureId.value) {
      await updateStructureMutation.mutateAsync({ id: editingStructureId.value, payload })
      toast.success(t('accommodation.structures.updated'))
    } else {
      await createStructureMutation.mutateAsync({ facilityId: facilityId.value, payload })
      toast.success(t('accommodation.structures.created'))
    }
    closeDrawer()
  } catch (error) {
    formError.value = message(error)
  }
}

async function deleteStructure(row: AccommodationStructure): Promise<void> {
  if (!canWrite.value) return
  const ok = await confirm({
    title: t('accommodation.structures.confirmDelete.title'),
    message: t('accommodation.structures.confirmDelete.body'),
    confirmLabel: t('accommodation.delete'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deleteStructureMutation.mutateAsync(row.id)
    toast.success(t('accommodation.structures.deleted'))
  } catch (error) {
    toast.error(message(error))
  }
}

async function submitUnit(): Promise<void> {
  if (!facilityId.value) return
  const errors = validateUnitForm(unitForm)
  if (Object.keys(errors).length) {
    formError.value = t('accommodation.errors.validation')
    return
  }
  const payload = {
    unit_kind: unitForm.unit_kind,
    code: unitForm.code.trim(),
    structure_id: unitForm.structure_id === '' ? null : unitForm.structure_id,
  }
  try {
    if (editingUnitId.value) {
      await updateUnitMutation.mutateAsync({ id: editingUnitId.value, payload })
      toast.success(t('accommodation.units.updated'))
    } else {
      await createUnitMutation.mutateAsync({ facilityId: facilityId.value, payload })
      toast.success(t('accommodation.units.created'))
    }
    closeDrawer()
  } catch (error) {
    formError.value = message(error)
  }
}

async function deleteUnit(row: AccommodationUnit): Promise<void> {
  if (!canWrite.value) return
  const ok = await confirm({
    title: t('accommodation.units.confirmDelete.title'),
    message: t('accommodation.units.confirmDelete.body'),
    confirmLabel: t('accommodation.delete'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deleteUnitMutation.mutateAsync(row.id)
    if (expandedUnitId.value === row.id) expandedUnitId.value = null
    toast.success(t('accommodation.units.deleted'))
  } catch (error) {
    toast.error(message(error))
  }
}

async function submitPosition(): Promise<void> {
  if (!expandedUnitId.value) return
  const errors = validatePositionForm(positionForm)
  if (Object.keys(errors).length) {
    formError.value = t('accommodation.errors.validation')
    return
  }
  const payload = { code: positionForm.code.trim() }
  try {
    if (editingPositionId.value) {
      await updatePositionMutation.mutateAsync({ id: editingPositionId.value, payload })
      toast.success(t('accommodation.positions.updated'))
    } else {
      await createPositionMutation.mutateAsync({ unitId: expandedUnitId.value, payload })
      toast.success(t('accommodation.positions.created'))
    }
    closeDrawer()
  } catch (error) {
    formError.value = message(error)
  }
}

async function deletePosition(row: AccommodationPosition): Promise<void> {
  if (!canWrite.value) return
  const ok = await confirm({
    title: t('accommodation.positions.confirmDelete.title'),
    message: t('accommodation.positions.confirmDelete.body'),
    confirmLabel: t('accommodation.delete'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deletePositionMutation.mutateAsync(row.id)
    toast.success(t('accommodation.positions.deleted'))
  } catch (error) {
    toast.error(message(error))
  }
}

watch(facilityId, () => {
  expandedUnitId.value = null
})
</script>

<template>
  <div class="space-y-5">
    <AppPageHeader
      :title="facility?.name ?? t('accommodation.facilityDetails.title')"
      :subtitle="t('accommodation.facilityDetails.subtitle')"
    >
      <template #actions>
        <RouterLink
          to="/app/accommodation"
          class="inline-flex items-center gap-1 rounded-lg border border-brand-border px-3 py-2 text-sm font-medium text-brand-text-secondary hover:text-brand-text"
        >
          <ChevronRight class="h-4 w-4" />
          {{ t('accommodation.facilityDetails.back') }}
        </RouterLink>
        <PermissionGuard permission="accommodation.manage_facilities">
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-lg border border-brand-border px-3 py-2 text-sm font-semibold disabled:opacity-50"
            :disabled="!canWrite || pageReady !== 'ready'"
            @click="openFacilityEdit"
          >
            <Pencil class="h-4 w-4" />
            {{ t('accommodation.facilityDetails.editFacility') }}
          </button>
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-lg bg-red-700 px-3 py-2 text-sm font-semibold text-white disabled:opacity-50"
            :disabled="!canWrite || pageReady !== 'ready'"
            @click="deleteFacility"
          >
            <Trash2 class="h-4 w-4" />
            {{ t('accommodation.facilityDetails.deleteFacility') }}
          </button>
        </PermissionGuard>
      </template>
    </AppPageHeader>

    <AccommodationSubnav />
    <AccommodationSeasonBar />

    <div v-if="!canRead" class="rounded-xl border border-dashed border-brand-border px-4 py-10 text-center text-sm text-brand-text-secondary">
      {{ t('accommodation.errors.forbidden') }}
    </div>
    <div v-else-if="pageReady === 'loading'" class="rounded-xl border border-brand-border px-4 py-10 text-center text-sm text-brand-text-muted">
      {{ t('accommodation.loading') }}
    </div>
    <div v-else-if="pageReady === 'error'" class="rounded-xl border border-red-200 bg-red-50 px-4 py-8 text-center">
      <p class="text-sm text-red-800">{{ t('accommodation.errors.load') }}</p>
      <button type="button" class="mt-3 text-sm font-semibold text-red-900 underline" @click="() => refetch()">
        {{ t('accommodation.retry') }}
      </button>
    </div>
    <template v-else>
      <section class="rounded-xl border border-brand-border bg-brand-surface p-4">
        <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <div>
            <dt class="text-xs text-brand-text-secondary">{{ t('accommodation.fields.code') }}</dt>
            <dd class="font-medium">{{ facility!.code }}</dd>
          </div>
          <div>
            <dt class="text-xs text-brand-text-secondary">{{ t('accommodation.fields.kind') }}</dt>
            <dd>{{ t(`accommodation.kind.${facility!.kind}`) }}</dd>
          </div>
          <div>
            <dt class="text-xs text-brand-text-secondary">{{ t('accommodation.fields.status') }}</dt>
            <dd>
              {{ facility!.is_active ? t('accommodation.status.active') : t('accommodation.status.inactive') }}
            </dd>
          </div>
        </dl>
      </section>

      <section class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h2 class="text-base font-bold text-brand-text">{{ t('accommodation.facilityDetails.structures') }}</h2>
          <PermissionGuard permission="accommodation.manage_facilities">
            <button
              type="button"
              class="inline-flex items-center gap-1 rounded-lg bg-brand-primary px-3 py-1.5 text-sm font-semibold text-white disabled:opacity-50"
              :disabled="!canWrite"
              @click="openStructureCreate"
            >
              <Plus class="h-4 w-4" />
              {{ t('accommodation.structures.create') }}
            </button>
          </PermissionGuard>
        </div>
        <div v-if="structures.length === 0" class="rounded-xl border border-dashed border-brand-border px-4 py-8 text-center text-sm text-brand-text-secondary">
          {{ t('accommodation.facilityDetails.structuresEmpty') }}
        </div>
        <div v-else class="overflow-hidden rounded-xl border border-brand-border">
          <table class="min-w-full text-sm">
            <thead class="bg-brand-bg text-brand-text-secondary">
              <tr>
                <th class="px-4 py-3 text-start font-medium">{{ t('accommodation.fields.name') }}</th>
                <th class="px-4 py-3 text-start font-medium">{{ t('accommodation.fields.kind') }}</th>
                <th class="px-4 py-3 text-start font-medium">{{ t('accommodation.facilityDetails.parentStructure') }}</th>
                <th class="px-4 py-3 text-start font-medium">{{ t('accommodation.fields.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="structure in structures" :key="structure.id">
                <tr class="border-t border-brand-border">
                  <td class="px-4 py-3 font-medium">{{ structure.name }}</td>
                  <td class="px-4 py-3">{{ t(`accommodation.structureKind.${structure.kind}`) }}</td>
                  <td class="px-4 py-3">{{ structureParentLabel(structure) }}</td>
                  <td class="px-4 py-3">
                    <PermissionGuard permission="accommodation.manage_facilities">
                      <div class="flex flex-wrap gap-2">
                        <button
                          type="button"
                          class="text-sm font-semibold text-brand-primary disabled:opacity-50"
                          :disabled="!canWrite"
                          @click="openUnitCreate(structure.id)"
                        >
                          {{ t('accommodation.units.addUnderStructure') }}
                        </button>
                        <button type="button" class="text-sm text-brand-text-secondary" :disabled="!canWrite" @click="openStructureEdit(structure)">
                          {{ t('accommodation.edit') }}
                        </button>
                        <button type="button" class="text-sm text-red-700 disabled:opacity-50" :disabled="!canWrite" @click="deleteStructure(structure)">
                          {{ t('accommodation.delete') }}
                        </button>
                      </div>
                    </PermissionGuard>
                  </td>
                </tr>
                <tr v-for="unit in (unitsByStructure.get(structure.id) ?? [])" :key="unit.id" class="border-t border-brand-border bg-brand-bg/40">
                  <td colspan="4" class="px-4 py-2">
                    <div class="flex flex-wrap items-center justify-between gap-2 ps-4">
                      <button
                        type="button"
                        class="inline-flex items-center gap-2 text-start font-medium text-brand-text"
                        @click="toggleUnit(unit.id)"
                      >
                        <ChevronDown v-if="expandedUnitId !== unit.id" class="h-4 w-4" />
                        <ChevronUp v-else class="h-4 w-4" />
                        {{ unit.code }}
                        <span class="text-xs font-normal text-brand-text-muted">({{ t(`accommodation.unitKind.${unit.unit_kind}`) }})</span>
                      </button>
                      <PermissionGuard permission="accommodation.manage_facilities">
                        <div class="flex gap-2">
                          <button type="button" class="text-xs text-brand-text-secondary" :disabled="!canWrite" @click="openUnitEdit(unit)">
                            {{ t('accommodation.edit') }}
                          </button>
                          <button type="button" class="text-xs text-red-700 disabled:opacity-50" :disabled="!canWrite" @click="deleteUnit(unit)">
                            {{ t('accommodation.delete') }}
                          </button>
                        </div>
                      </PermissionGuard>
                    </div>
                    <div v-if="expandedUnitId === unit.id" class="mt-3 space-y-2 border-s-2 border-brand-primary/30 ps-4">
                      <div class="flex items-center justify-between">
                        <p class="text-xs font-semibold text-brand-text-secondary">{{ t('accommodation.positions.title') }}</p>
                        <PermissionGuard permission="accommodation.manage_facilities">
                          <button
                            type="button"
                            class="text-xs font-semibold text-brand-primary disabled:opacity-50"
                            :disabled="!canWrite"
                            @click="openPositionCreate"
                          >
                            {{ t('accommodation.positions.create') }}
                          </button>
                        </PermissionGuard>
                      </div>
                      <p v-if="positionsLoading" class="text-xs text-brand-text-muted">{{ t('accommodation.loading') }}</p>
                      <ul v-else-if="positions.length === 0" class="text-xs text-brand-text-muted">
                        {{ t('accommodation.positions.empty') }}
                      </ul>
                      <ul v-else class="space-y-1">
                        <li
                          v-for="position in positions"
                          :key="position.id"
                          class="flex items-center justify-between rounded-lg bg-brand-surface px-3 py-2 text-sm ring-1 ring-brand-border"
                        >
                          <span>{{ position.code }}</span>
                          <PermissionGuard permission="accommodation.manage_facilities">
                            <div class="flex gap-2">
                              <button type="button" class="text-xs" :disabled="!canWrite" @click="openPositionEdit(position)">
                                {{ t('accommodation.edit') }}
                              </button>
                              <button type="button" class="text-xs text-red-700 disabled:opacity-50" :disabled="!canWrite" @click="deletePosition(position)">
                                {{ t('accommodation.delete') }}
                              </button>
                            </div>
                          </PermissionGuard>
                        </li>
                      </ul>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </section>

      <section v-if="orphanUnits.length" class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h2 class="text-base font-bold text-brand-text">{{ t('accommodation.facilityDetails.unitsWithoutStructure') }}</h2>
          <PermissionGuard permission="accommodation.manage_facilities">
            <button
              type="button"
              class="inline-flex items-center gap-1 rounded-lg border border-brand-border px-3 py-1.5 text-sm font-semibold disabled:opacity-50"
              :disabled="!canWrite"
              @click="openUnitCreate(null)"
            >
              <Plus class="h-4 w-4" />
              {{ t('accommodation.units.create') }}
            </button>
          </PermissionGuard>
        </div>
        <div class="overflow-hidden rounded-xl border border-brand-border">
          <table class="min-w-full text-sm">
            <tbody>
              <tr v-for="unit in orphanUnits" :key="unit.id" class="border-t border-brand-border first:border-t-0">
                <td class="px-4 py-3">
                  <div class="flex flex-wrap items-center justify-between gap-2">
                    <button type="button" class="inline-flex items-center gap-2 font-medium" @click="toggleUnit(unit.id)">
                      <ChevronDown v-if="expandedUnitId !== unit.id" class="h-4 w-4" />
                      <ChevronUp v-else class="h-4 w-4" />
                      {{ unit.code }}
                    </button>
                    <PermissionGuard permission="accommodation.manage_facilities">
                      <div class="flex gap-2">
                        <button type="button" class="text-sm" :disabled="!canWrite" @click="openUnitEdit(unit)">{{ t('accommodation.edit') }}</button>
                        <button type="button" class="text-sm text-red-700 disabled:opacity-50" :disabled="!canWrite" @click="deleteUnit(unit)">{{ t('accommodation.delete') }}</button>
                      </div>
                    </PermissionGuard>
                  </div>
                  <div v-if="expandedUnitId === unit.id" class="mt-2 ps-6 text-xs text-brand-text-muted">
                    {{ t('accommodation.positions.expandHint') }}
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>

    <Teleport to="body">
      <div v-if="drawerKind" class="fixed inset-0 z-50" role="presentation">
        <div class="absolute inset-0 bg-black/30" @click="closeDrawer" />
        <aside class="app-drawer-panel absolute inset-y-0 start-0 flex w-full max-w-[480px] flex-col bg-brand-surface shadow-xl">
          <header class="border-b border-brand-border px-5 py-4">
            <h3 class="text-lg font-bold">
              {{
                drawerKind === 'facility'
                  ? t('accommodation.facilityDetails.editFacility')
                  : drawerKind === 'structure'
                    ? editingStructureId
                      ? t('accommodation.structures.editTitle')
                      : t('accommodation.structures.createTitle')
                    : drawerKind === 'unit'
                      ? editingUnitId
                        ? t('accommodation.units.editTitle')
                        : t('accommodation.units.createTitle')
                      : editingPositionId
                        ? t('accommodation.positions.editTitle')
                        : t('accommodation.positions.createTitle')
              }}
            </h3>
          </header>
          <form class="flex flex-1 flex-col" @submit.prevent="
            drawerKind === 'facility' ? submitFacility()
            : drawerKind === 'structure' ? submitStructure()
            : drawerKind === 'unit' ? submitUnit()
            : submitPosition()
          ">
            <div class="flex-1 space-y-4 overflow-y-auto px-5 py-5">
              <template v-if="drawerKind === 'facility'">
                <label class="block text-sm">
                  <span class="mb-1 block text-brand-text-secondary">{{ t('accommodation.fields.code') }}</span>
                  <input v-model="facilityForm.code" class="w-full rounded-lg border border-brand-border px-3 py-2">
                </label>
                <label class="block text-sm">
                  <span class="mb-1 block text-brand-text-secondary">{{ t('accommodation.fields.name') }}</span>
                  <input v-model="facilityForm.name" class="w-full rounded-lg border border-brand-border px-3 py-2">
                </label>
                <label class="inline-flex items-center gap-2 text-sm">
                  <input v-model="facilityForm.is_active" type="checkbox" class="rounded border-brand-border">
                  {{ t('accommodation.fields.active') }}
                </label>
              </template>
              <template v-else-if="drawerKind === 'structure'">
                <label class="block text-sm">
                  <span class="mb-1 block text-brand-text-secondary">{{ t('accommodation.fields.kind') }}</span>
                  <AppSelect
                    :model-value="structureForm.kind"
                    :options="[
                      { value: 'building', label: t('accommodation.structureKind.building') },
                      { value: 'floor', label: t('accommodation.structureKind.floor') },
                    ]"
                    @update:model-value="structureForm.kind = $event as StructureKind"
                  />
                </label>
                <label class="block text-sm">
                  <span class="mb-1 block text-brand-text-secondary">{{ t('accommodation.fields.name') }}</span>
                  <input v-model="structureForm.name" class="w-full rounded-lg border border-brand-border px-3 py-2">
                </label>
                <label class="block text-sm">
                  <span class="mb-1 block text-brand-text-secondary">{{ t('accommodation.facilityDetails.parentStructure') }}</span>
                  <AppSelect
                    :model-value="structureForm.parent_id === '' ? '' : structureForm.parent_id"
                    :options="parentStructureOptions"
                    @update:model-value="structureForm.parent_id = $event === '' ? '' : Number($event)"
                  />
                </label>
              </template>
              <template v-else-if="drawerKind === 'unit'">
                <label class="block text-sm">
                  <span class="mb-1 block text-brand-text-secondary">{{ t('accommodation.fields.kind') }}</span>
                  <AppSelect
                    :model-value="unitForm.unit_kind"
                    :options="[
                      { value: 'room', label: t('accommodation.unitKind.room') },
                      { value: 'tent', label: t('accommodation.unitKind.tent') },
                    ]"
                    @update:model-value="unitForm.unit_kind = $event as UnitKind"
                  />
                </label>
                <label class="block text-sm">
                  <span class="mb-1 block text-brand-text-secondary">{{ t('accommodation.fields.code') }}</span>
                  <input v-model="unitForm.code" class="w-full rounded-lg border border-brand-border px-3 py-2">
                </label>
                <label class="block text-sm">
                  <span class="mb-1 block text-brand-text-secondary">{{ t('accommodation.facilityDetails.structure') }}</span>
                  <AppSelect
                    :model-value="unitForm.structure_id === '' ? '' : unitForm.structure_id"
                    :options="unitStructureOptions"
                    @update:model-value="unitForm.structure_id = $event === '' ? '' : Number($event)"
                  />
                </label>
              </template>
              <template v-else>
                <label class="block text-sm">
                  <span class="mb-1 block text-brand-text-secondary">{{ t('accommodation.fields.code') }}</span>
                  <input v-model="positionForm.code" class="w-full rounded-lg border border-brand-border px-3 py-2">
                </label>
              </template>
              <p v-if="formError" class="text-sm text-red-700">{{ formError }}</p>
            </div>
            <footer class="flex justify-end gap-2 border-t border-brand-border px-5 py-4">
              <button type="button" class="rounded-lg px-3 py-2 text-sm" @click="closeDrawer">{{ t('accommodation.cancel') }}</button>
              <button type="submit" class="rounded-lg bg-brand-primary px-3 py-2 text-sm font-semibold text-white">{{ t('accommodation.save') }}</button>
            </footer>
          </form>
        </aside>
      </div>
    </Teleport>
  </div>
</template>
