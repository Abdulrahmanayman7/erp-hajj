<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { ChevronLeft, ChevronRight, Pencil, Plus, Users } from 'lucide-vue-next'

import { useAccommodationSeasonStore } from '@/modules/accommodation/stores/seasonContextStore'
import { listEmployees } from '@/modules/employees/api/employeesApi'
import type { Employee } from '@/modules/employees/types/employees'
import SeasonWorkspaceBar from '@/modules/season-pilgrims/components/SeasonWorkspaceBar.vue'
import { useSeasonsQuery } from '@/modules/season-pilgrims/queries/useSeasonsQuery'
import { ApiError } from '@/shared/api/http'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { useToast } from '@/shared/composables/useToast'
import { employeeSelectOption } from '@/shared/lookups/selectOptions'

import {
  useActivateSeasonVehicleMutation,
  useCreateSeasonVehicleMutation,
  useDeactivateSeasonVehicleMutation,
  useDeleteSeasonVehicleMutation,
  useSetDefaultCrewMutation,
  useUpdateSeasonVehicleMutation,
} from '../mutations/useFleetMutations'
import {
  useSeasonFleetQuery,
  useTransportDriversQuery,
  useTransportVehiclesQuery,
} from '../queries/useFleetQueries'
import type {
  DefaultCrewFormState,
  ListSeasonVehiclesParams,
  SeasonVehicleFormState,
  ServiceLevel,
  TransportSeasonVehicle,
} from '../types/fleet'
import { SERVICE_LEVELS } from '../types/fleet'
import {
  defaultCrewFormFromRow,
  emptyDefaultCrewForm,
  emptySeasonVehicleForm,
  mapTransportErrorCode,
  resolveListState,
  toDefaultCrewPayload,
  validateDefaultCrewForm,
  validateSeasonVehicleForm,
} from '../validation/fleetValidation'

const { t } = useI18n()
const toast = useToast()
const { confirm } = useConfirm()
const store = useAccommodationSeasonStore()
const { selectedSeasonId } = storeToRefs(store)

const seasonsQuery = useSeasonsQuery({ per_page: 100 })
const selectedSeason = computed(
  () => (seasonsQuery.data.value?.data ?? []).find((s) => s.id === selectedSeasonId.value) ?? null,
)
const seasonWritable = computed(() => selectedSeason.value?.status !== 'closed')

const filters = reactive({
  is_active: '' as '' | '1' | '0',
  page: 1,
  per_page: 15,
})

const params = computed<ListSeasonVehiclesParams>(() => ({
  is_active: filters.is_active === '' ? '' : filters.is_active === '1',
  page: filters.page,
  per_page: filters.per_page,
  seasonId: selectedSeasonId.value,
}))

const { data, isLoading, isError, refetch } = useSeasonFleetQuery(
  params,
  () => selectedSeasonId.value != null,
)
const rows = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta)
const listState = computed(() =>
  resolveListState({
    hasSeason: selectedSeasonId.value != null,
    isLoading: isLoading.value,
    isError: isError.value,
    count: rows.value.length,
  }),
)

watch(
  () => [selectedSeasonId.value, filters.is_active],
  () => {
    filters.page = 1
  },
)

const statusOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('transportation.vehicles.filters.allStatus') },
  { value: '1', label: t('transportation.seasonFleet.status.active') },
  { value: '0', label: t('transportation.seasonFleet.status.inactive') },
])
const serviceOptions = computed<AppSelectOption[]>(() =>
  SERVICE_LEVELS.map((level) => ({
    value: level,
    label: t(`transportation.seasonFleet.service.${level}`),
  })),
)

const vehiclesQuery = useTransportVehiclesQuery(
  computed(() => ({ is_active: true, per_page: 100 })),
)
const vehicleOptions = computed<AppSelectOption[]>(() => {
  const linked = new Set(rows.value.map((r) => r.transport_vehicle_id))
  return (vehiclesQuery.data.value?.data ?? [])
    .filter((v) => !linked.has(v.id) || editing.value?.transport_vehicle_id === v.id)
    .map((v) => ({
      value: v.id,
      label: `${v.vehicle_number} · ${v.plate_number}`,
      hint: t(`transportation.vehicles.class.${v.vehicle_class}`),
    }))
})

const createMutation = useCreateSeasonVehicleMutation()
const updateMutation = useUpdateSeasonVehicleMutation()
const deleteMutation = useDeleteSeasonVehicleMutation()
const activateMutation = useActivateSeasonVehicleMutation()
const deactivateMutation = useDeactivateSeasonVehicleMutation()
const crewMutation = useSetDefaultCrewMutation()

const drawerOpen = ref(false)
const crewOpen = ref(false)
const editing = ref<TransportSeasonVehicle | null>(null)
const crewRow = ref<TransportSeasonVehicle | null>(null)
const form = reactive<SeasonVehicleFormState>(emptySeasonVehicleForm())
const crewForm = reactive<DefaultCrewFormState>(emptyDefaultCrewForm())
const formError = ref('')
const crewError = ref('')
const fieldErrors = reactive<Record<string, string>>({})
const crewFieldErrors = reactive<Record<string, string>>({})
const submitting = computed(
  () => createMutation.isPending.value || updateMutation.isPending.value,
)
const crewSubmitting = computed(() => crewMutation.isPending.value)

const driversQuery = useTransportDriversQuery(computed(() => ({ is_active: true, per_page: 100 })))
const externalDriverOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('transportation.seasonFleet.fields.noExternal') },
  ...(driversQuery.data.value?.data ?? []).map((d) => ({
    value: d.id,
    label: d.full_name,
    hint: d.phone ?? undefined,
  })),
])
const driverKindOptions = computed<AppSelectOption[]>(() => [
  { value: 'none', label: t('transportation.seasonFleet.driverKind.none') },
  { value: 'employee', label: t('transportation.seasonFleet.driverKind.employee') },
  { value: 'external', label: t('transportation.seasonFleet.driverKind.external') },
])
const emptyEmployee = computed<AppSelectOption>(() => ({
  value: '',
  label: t('transportation.seasonFleet.fields.noEmployee'),
}))
const fetchEmployees = (params: { search?: string; page: number; per_page: number }) =>
  listEmployees({ ...params, status: 'active' })

function crewDriverLabel(row: TransportSeasonVehicle): string {
  if (row.default_driver_employee) return row.default_driver_employee.full_name
  if (row.default_external_driver) return row.default_external_driver.full_name
  return '—'
}

function openCrew(row: TransportSeasonVehicle): void {
  if (!seasonWritable.value) return
  crewRow.value = row
  Object.assign(crewForm, defaultCrewFormFromRow(row))
  crewError.value = ''
  Object.keys(crewFieldErrors).forEach((k) => delete crewFieldErrors[k])
  crewOpen.value = true
}

async function submitCrew(): Promise<void> {
  if (!seasonWritable.value || !selectedSeasonId.value || !crewRow.value) {
    crewError.value = t('transportation.seasonFleet.errors.seasonClosed')
    return
  }
  Object.keys(crewFieldErrors).forEach((k) => delete crewFieldErrors[k])
  const local = validateDefaultCrewForm(crewForm)
  Object.assign(crewFieldErrors, local)
  if (Object.keys(local).length) return

  try {
    await crewMutation.mutateAsync({
      id: crewRow.value.id,
      seasonId: selectedSeasonId.value,
      payload: toDefaultCrewPayload(crewForm),
    })
    toast.success(t('transportation.seasonFleet.toasts.crewUpdated'))
    crewOpen.value = false
  } catch (error) {
    crewError.value = apiMessage(error)
  }
}

function openCreate(): void {
  if (!seasonWritable.value || !selectedSeasonId.value) return
  editing.value = null
  Object.assign(form, emptySeasonVehicleForm())
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  drawerOpen.value = true
}

function openEdit(row: TransportSeasonVehicle): void {
  if (!seasonWritable.value) return
  editing.value = row
  Object.assign(form, {
    transport_vehicle_id: row.transport_vehicle_id,
    operational_capacity: String(row.operational_capacity),
    service_level: row.service_level,
    notes: row.notes ?? '',
  })
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  drawerOpen.value = true
}

function fieldMessage(key: string): string {
  const code = fieldErrors[key]
  return code ? t(`transportation.seasonFleet.errors.validation.${code}`) : ''
}

function apiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) return t('transportation.seasonFleet.errors.generic')
  const mapped = mapTransportErrorCode(error.code)
  if (mapped !== 'generic') return t(`transportation.seasonFleet.errors.${mapped}`)
  return error.message || t('transportation.seasonFleet.errors.generic')
}

async function submit(): Promise<void> {
  if (!seasonWritable.value || !selectedSeasonId.value) {
    formError.value = t('transportation.seasonFleet.errors.seasonClosed')
    return
  }
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  const local = validateSeasonVehicleForm(form, !editing.value)
  Object.assign(fieldErrors, local)
  if (Object.keys(local).length) return

  try {
    if (editing.value) {
      await updateMutation.mutateAsync({
        id: editing.value.id,
        seasonId: selectedSeasonId.value,
        payload: {
          operational_capacity: Number(form.operational_capacity),
          service_level: form.service_level as ServiceLevel,
          notes: form.notes.trim() || null,
        },
      })
      toast.success(t('transportation.seasonFleet.toasts.updated'))
    } else {
      await createMutation.mutateAsync({
        payload: { ...form },
        seasonId: selectedSeasonId.value,
      })
      toast.success(t('transportation.seasonFleet.toasts.created'))
    }
    drawerOpen.value = false
  } catch (error) {
    formError.value = apiMessage(error)
  }
}

async function toggleActive(row: TransportSeasonVehicle): Promise<void> {
  if (!seasonWritable.value || !selectedSeasonId.value) return
  try {
    if (row.is_active) {
      await deactivateMutation.mutateAsync({ id: row.id, seasonId: selectedSeasonId.value })
      toast.success(t('transportation.seasonFleet.toasts.deactivated'))
    } else {
      await activateMutation.mutateAsync({ id: row.id, seasonId: selectedSeasonId.value })
      toast.success(t('transportation.seasonFleet.toasts.activated'))
    }
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

async function onUnlink(row: TransportSeasonVehicle): Promise<void> {
  if (!seasonWritable.value || !selectedSeasonId.value) return
  const ok = await confirm({
    title: t('transportation.seasonFleet.confirmUnlinkTitle'),
    message: t('transportation.seasonFleet.confirmUnlinkBody'),
    confirmLabel: t('transportation.seasonFleet.unlink'),
    cancelLabel: t('transportation.seasonFleet.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deleteMutation.mutateAsync({ id: row.id, seasonId: selectedSeasonId.value })
    toast.success(t('transportation.seasonFleet.toasts.deleted'))
  } catch (error) {
    toast.error(apiMessage(error))
  }
}
</script>

<template>
  <div class="space-y-6">
    <AppPageHeader
      :title="t('transportation.seasonFleet.title')"
      :subtitle="t('transportation.seasonFleet.subtitle')"
      :meta="
        listState === 'ready' && meta
          ? t('transportation.seasonFleet.total', { count: meta.total })
          : undefined
      "
    >
      <template #actions>
        <PermissionGuard permission="transportation.manage_season_fleet">
          <button
            type="button"
            class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white disabled:opacity-50"
            :disabled="!seasonWritable || !selectedSeasonId"
            @click="openCreate"
          >
            <Plus class="h-4 w-4" />
            {{ t('transportation.seasonFleet.add') }}
          </button>
        </PermissionGuard>
      </template>
    </AppPageHeader>

    <SeasonWorkspaceBar />

    <div
      v-if="selectedSeason && !seasonWritable"
      class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
    >
      {{ t('transportation.seasonFleet.closedNotice') }}
    </div>

    <div
      v-if="selectedSeasonId"
      class="flex flex-col gap-3 rounded-2xl border border-brand-border bg-brand-surface p-4 sm:flex-row sm:items-end"
    >
      <div class="w-full sm:w-44">
        <AppSelect v-model="filters.is_active" :options="statusOptions" />
      </div>
    </div>

    <div
      v-if="listState === 'need_season'"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('transportation.seasonFleet.needSeason') }}
    </div>
    <div
      v-else-if="listState === 'loading'"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('transportation.seasonFleet.loading') }}
    </div>
    <div
      v-else-if="listState === 'error'"
      class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center"
    >
      <p class="text-sm text-red-700">{{ t('transportation.seasonFleet.errors.load') }}</p>
      <button type="button" class="mt-3 text-sm font-semibold underline" @click="() => refetch()">
        {{ t('transportation.seasonFleet.retry') }}
      </button>
    </div>
    <div
      v-else-if="listState === 'empty'"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('transportation.seasonFleet.empty') }}
    </div>
    <div v-else class="overflow-hidden rounded-2xl border border-brand-border bg-brand-surface">
      <table class="min-w-full text-sm">
        <thead class="bg-[#F4F6F5]">
          <tr>
            <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">
              {{ t('transportation.seasonFleet.columns.vehicle') }}
            </th>
            <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">
              {{ t('transportation.seasonFleet.columns.plate') }}
            </th>
            <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">
              {{ t('transportation.seasonFleet.columns.class') }}
            </th>
            <th class="px-5 py-3.5 text-center text-xs font-bold text-brand-text-muted">
              {{ t('transportation.seasonFleet.columns.capacity') }}
            </th>
            <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">
              {{ t('transportation.seasonFleet.columns.service') }}
            </th>
            <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">
              {{ t('transportation.seasonFleet.columns.driver') }}
            </th>
            <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">
              {{ t('transportation.seasonFleet.columns.supervisor') }}
            </th>
            <th class="px-5 py-3.5 text-center text-xs font-bold text-brand-text-muted">
              {{ t('transportation.seasonFleet.columns.status') }}
            </th>
            <th class="px-5 py-3.5 text-center text-xs font-bold text-brand-text-muted">
              {{ t('transportation.seasonFleet.columns.actions') }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id" class="border-t border-brand-border/80">
            <td class="px-5 py-3.5 font-mono text-xs" dir="ltr">
              {{ row.vehicle?.vehicle_number ?? '—' }}
            </td>
            <td class="px-5 py-3.5 font-semibold" dir="ltr">
              {{ row.vehicle?.plate_number ?? '—' }}
            </td>
            <td class="px-5 py-3.5">
              <template v-if="row.vehicle">
                {{ t(`transportation.vehicles.class.${row.vehicle.vehicle_class}`) }}
              </template>
              <template v-else>—</template>
            </td>
            <td class="px-5 py-3.5 text-center">{{ row.operational_capacity }}</td>
            <td class="px-5 py-3.5">
              {{ t(`transportation.seasonFleet.service.${row.service_level}`) }}
            </td>
            <td class="px-5 py-3.5 text-sm">{{ crewDriverLabel(row) }}</td>
            <td class="px-5 py-3.5 text-sm">
              {{ row.default_supervisor_employee?.full_name ?? '—' }}
            </td>
            <td class="px-5 py-3.5 text-center">
              <span
                class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                :class="
                  row.is_active
                    ? 'bg-emerald-50 text-emerald-800'
                    : 'bg-neutral-100 text-neutral-600'
                "
              >
                {{
                  row.is_active
                    ? t('transportation.seasonFleet.status.active')
                    : t('transportation.seasonFleet.status.inactive')
                }}
              </span>
            </td>
            <td class="px-5 py-3.5">
              <div class="flex flex-wrap justify-center gap-1">
                <PermissionGuard permission="transportation.manage_season_fleet">
                  <button
                    type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg hover:bg-brand-bg disabled:opacity-40"
                    :disabled="!seasonWritable"
                    :title="t('transportation.seasonFleet.crew')"
                    @click="openCrew(row)"
                  >
                    <Users class="h-4 w-4" />
                  </button>
                  <button
                    type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg hover:bg-brand-bg disabled:opacity-40"
                    :disabled="!seasonWritable"
                    @click="openEdit(row)"
                  >
                    <Pencil class="h-4 w-4" />
                  </button>
                  <button
                    type="button"
                    class="inline-flex h-9 items-center rounded-lg px-2 text-xs font-semibold hover:bg-brand-bg disabled:opacity-40"
                    :disabled="!seasonWritable"
                    @click="toggleActive(row)"
                  >
                    {{
                      row.is_active
                        ? t('transportation.seasonFleet.deactivate')
                        : t('transportation.seasonFleet.activate')
                    }}
                  </button>
                  <button
                    type="button"
                    class="inline-flex h-9 items-center rounded-lg px-2 text-xs font-semibold text-red-700 hover:bg-red-50 disabled:opacity-40"
                    :disabled="!seasonWritable"
                    @click="onUnlink(row)"
                  >
                    {{ t('transportation.seasonFleet.unlink') }}
                  </button>
                </PermissionGuard>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <div
        v-if="meta && meta.last_page > 1"
        class="flex items-center justify-between border-t border-brand-border px-5 py-3 text-sm"
      >
        <button
          type="button"
          class="inline-flex h-9 items-center gap-1 rounded-lg px-3 disabled:opacity-40"
          :disabled="filters.page <= 1"
          @click="filters.page -= 1"
        >
          <ChevronRight class="h-4 w-4" />
        </button>
        <span class="text-brand-text-muted">{{ filters.page }} / {{ meta.last_page }}</span>
        <button
          type="button"
          class="inline-flex h-9 items-center gap-1 rounded-lg px-3 disabled:opacity-40"
          :disabled="filters.page >= meta.last_page"
          @click="filters.page += 1"
        >
          <ChevronLeft class="h-4 w-4" />
        </button>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="drawerOpen" class="fixed inset-0 z-50">
        <div class="absolute inset-0 bg-[rgba(15,23,20,0.32)]" @click="drawerOpen = false" />
        <aside
          class="absolute inset-y-0 start-0 flex h-dvh w-full max-w-[440px] flex-col bg-brand-surface"
          role="dialog"
          aria-modal="true"
        >
          <header class="border-b border-brand-border px-5 py-4">
            <h3 class="text-lg font-bold">
              {{
                editing
                  ? t('transportation.seasonFleet.editTitle')
                  : t('transportation.seasonFleet.createTitle')
              }}
            </h3>
          </header>
          <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
            <p v-if="formError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">
              {{ formError }}
            </p>
            <div v-if="!editing">
              <label class="mb-1 block text-sm font-medium">{{
                t('transportation.seasonFleet.fields.vehicle')
              }}</label>
              <AppSelect
                v-model="form.transport_vehicle_id"
                :options="vehicleOptions"
                searchable
              />
              <p v-if="fieldMessage('transport_vehicle_id')" class="mt-1 text-xs text-red-600">
                {{ fieldMessage('transport_vehicle_id') }}
              </p>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium">{{
                t('transportation.seasonFleet.fields.capacity')
              }}</label>
              <input
                v-model="form.operational_capacity"
                type="text"
                inputmode="numeric"
                class="h-11 w-full rounded-xl border border-brand-border px-3 text-sm"
              />
              <p v-if="fieldMessage('operational_capacity')" class="mt-1 text-xs text-red-600">
                {{ fieldMessage('operational_capacity') }}
              </p>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium">{{
                t('transportation.seasonFleet.fields.serviceLevel')
              }}</label>
              <AppSelect v-model="form.service_level" :options="serviceOptions" />
              <p v-if="fieldMessage('service_level')" class="mt-1 text-xs text-red-600">
                {{ fieldMessage('service_level') }}
              </p>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium">{{
                t('transportation.seasonFleet.fields.notes')
              }}</label>
              <textarea
                v-model="form.notes"
                rows="3"
                class="w-full rounded-xl border border-brand-border px-3 py-2 text-sm"
              />
            </div>
          </div>
          <footer class="flex gap-2 border-t border-brand-border px-5 py-4">
            <button
              type="button"
              class="h-11 flex-1 rounded-xl border border-brand-border text-sm font-semibold"
              @click="drawerOpen = false"
            >
              {{ t('transportation.seasonFleet.cancel') }}
            </button>
            <button
              type="button"
              class="h-11 flex-1 rounded-xl bg-brand-primary-dark text-sm font-semibold text-white disabled:opacity-50"
              :disabled="submitting"
              @click="submit"
            >
              {{ t('transportation.seasonFleet.save') }}
            </button>
          </footer>
        </aside>
      </div>
    </Teleport>

    <Teleport to="body">
      <div v-if="crewOpen" class="fixed inset-0 z-50">
        <div class="absolute inset-0 bg-[rgba(15,23,20,0.32)]" @click="crewOpen = false" />
        <aside
          class="absolute inset-y-0 start-0 flex h-dvh w-full max-w-[440px] flex-col bg-brand-surface"
          role="dialog"
          aria-modal="true"
        >
          <header class="border-b border-brand-border px-5 py-4">
            <h3 class="text-lg font-bold">{{ t('transportation.seasonFleet.crewTitle') }}</h3>
            <p v-if="crewRow?.vehicle" class="mt-1 text-xs text-brand-text-muted" dir="ltr">
              {{ crewRow.vehicle.vehicle_number }} · {{ crewRow.vehicle.plate_number }}
            </p>
          </header>
          <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
            <p v-if="crewError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">
              {{ crewError }}
            </p>
            <div>
              <label class="mb-1 block text-sm font-medium">{{
                t('transportation.seasonFleet.fields.driverKind')
              }}</label>
              <AppSelect v-model="crewForm.driver_kind" :options="driverKindOptions" />
            </div>
            <div v-if="crewForm.driver_kind === 'employee'">
              <label class="mb-1 block text-sm font-medium">{{
                t('transportation.seasonFleet.fields.driverEmployee')
              }}</label>
              <AppRemoteSelect
                v-model="crewForm.default_driver_employee_id"
                :query-key="['transport-default-driver-employees']"
                :fetcher="fetchEmployees"
                :map-option="(row: Employee) => employeeSelectOption(row)"
                :empty-option="emptyEmployee"
                :selected-option="
                  crewRow?.default_driver_employee
                    ? employeeSelectOption(crewRow.default_driver_employee)
                    : null
                "
                clearable
                searchable
              />
              <p v-if="crewFieldErrors.driver" class="mt-1 text-xs text-red-600">
                {{ t(`transportation.seasonFleet.errors.validation.${crewFieldErrors.driver}`) }}
              </p>
            </div>
            <div v-else-if="crewForm.driver_kind === 'external'">
              <label class="mb-1 block text-sm font-medium">{{
                t('transportation.seasonFleet.fields.externalDriver')
              }}</label>
              <AppSelect
                v-model="crewForm.default_external_driver_id"
                :options="externalDriverOptions"
                searchable
              />
              <p v-if="crewFieldErrors.driver" class="mt-1 text-xs text-red-600">
                {{ t(`transportation.seasonFleet.errors.validation.${crewFieldErrors.driver}`) }}
              </p>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium">{{
                t('transportation.seasonFleet.fields.supervisor')
              }}</label>
              <AppRemoteSelect
                v-model="crewForm.default_supervisor_employee_id"
                :query-key="['transport-default-supervisor-employees']"
                :fetcher="fetchEmployees"
                :map-option="(row: Employee) => employeeSelectOption(row)"
                :empty-option="emptyEmployee"
                :selected-option="
                  crewRow?.default_supervisor_employee
                    ? employeeSelectOption(crewRow.default_supervisor_employee)
                    : null
                "
                clearable
                searchable
              />
            </div>
          </div>
          <footer class="flex gap-2 border-t border-brand-border px-5 py-4">
            <button
              type="button"
              class="h-11 flex-1 rounded-xl border border-brand-border text-sm font-semibold"
              @click="crewOpen = false"
            >
              {{ t('transportation.seasonFleet.cancel') }}
            </button>
            <button
              type="button"
              class="h-11 flex-1 rounded-xl bg-brand-primary-dark text-sm font-semibold text-white disabled:opacity-50"
              :disabled="crewSubmitting || !seasonWritable"
              @click="submitCrew"
            >
              {{ t('transportation.seasonFleet.crewSave') }}
            </button>
          </footer>
        </aside>
      </div>
    </Teleport>
  </div>
</template>
