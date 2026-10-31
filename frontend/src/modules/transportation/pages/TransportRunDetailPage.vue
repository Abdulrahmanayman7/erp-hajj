<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'

import { useAccommodationSeasonStore } from '@/modules/accommodation/stores/seasonContextStore'
import { listSeasonPilgrims } from '@/modules/season-pilgrims/api/seasonPilgrimsApi'
import SeasonWorkspaceBar from '@/modules/season-pilgrims/components/SeasonWorkspaceBar.vue'
import { useSeasonsQuery } from '@/modules/season-pilgrims/queries/useSeasonsQuery'
import type { SeasonPilgrim } from '@/modules/season-pilgrims/types/seasonPilgrims'
import { listEmployees } from '@/modules/employees/api/employeesApi'
import type { Employee } from '@/modules/employees/types/employees'
import { ApiError } from '@/shared/api/http'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { useDebouncedRef } from '@/shared/composables/useDebouncedRef'
import { useToast } from '@/shared/composables/useToast'
import { employeeSelectOption } from '@/shared/lookups/selectOptions'

import { recordBreakdown } from '../api/breakdownsApi'
import { listTransportDrivers } from '../api/driversApi'
import { listSeasonVehicles } from '../api/seasonFleetApi'
import {
  useAddPassengersMutation,
  useAssignRunDriverMutation,
  useAssignRunVehicleMutation,
  useCancelPassengerMutation,
  useCancelRunMutation,
  useTransitionRunMutation,
} from '../mutations/useOperationsMutations'
import { useRunPassengersQuery, useRunQuery } from '../queries/useOperationsQueries'
import type { TransportExternalDriver, TransportSeasonVehicle } from '../types/fleet'
import { availableSeats } from '../types/operations'
import {
  mapOperationsErrorCode,
  runActionsFor,
} from '../validation/operationsValidation'

const { t } = useI18n()
const toast = useToast()
const { confirm } = useConfirm()
const route = useRoute()
const router = useRouter()
const store = useAccommodationSeasonStore()
const { selectedSeasonId } = storeToRefs(store)
const seasonsQuery = useSeasonsQuery({ per_page: 100 })
const selectedSeason = computed(
  () => (seasonsQuery.data.value?.data ?? []).find((s) => s.id === selectedSeasonId.value) ?? null,
)
const seasonWritable = computed(() => selectedSeason.value?.status !== 'closed')

const runId = computed(() => {
  const n = Number(route.params.id)
  return Number.isFinite(n) && n > 0 ? n : null
})

const { data, isLoading, isError, refetch } = useRunQuery(
  runId,
  selectedSeasonId,
  () => runId.value != null && selectedSeasonId.value != null,
)
const run = computed(() => data.value ?? null)
const seats = computed(() => (run.value ? availableSeats(run.value) : null))
const actions = computed(() => (run.value ? runActionsFor(run.value.status) : []))

const manifestSearch = ref('')
const committedSearch = useDebouncedRef(() => manifestSearch.value)
const passengerParams = computed(() => ({ page: 1, per_page: 100 }))
const {
  data: passengersData,
  isLoading: passengersLoading,
  isError: passengersError,
  refetch: refetchPassengers,
} = useRunPassengersQuery(
  runId,
  passengerParams,
  selectedSeasonId,
  () => runId.value != null && selectedSeasonId.value != null,
)

const passengers = computed(() => {
  const rows = passengersData.value?.data ?? []
  const q = committedSearch.value.trim().toLowerCase()
  if (!q) return rows.filter((p) => p.status !== 'cancelled')
  return rows.filter((p) => {
    if (p.status === 'cancelled') return false
    const name = (p.season_pilgrim?.full_name ?? '').toLowerCase()
    const ref = (p.season_pilgrim?.reference_number ?? '').toLowerCase()
    return name.includes(q) || ref.includes(q)
  })
})

const transitionMutation = useTransitionRunMutation()
const cancelRunMutation = useCancelRunMutation()
const assignVehicleMutation = useAssignRunVehicleMutation()
const assignDriverMutation = useAssignRunDriverMutation()
const addPassengersMutation = useAddPassengersMutation()
const cancelPassengerMutation = useCancelPassengerMutation()

const vehicleDrawer = ref(false)
const driverDrawer = ref(false)
const assignDrawer = ref(false)
const vehicleId = ref<string | number | ''>('')
const vehicleReason = ref('')
const driverKind = ref<'employee' | 'external'>('employee')
const driverEmployeeId = ref<string | number | ''>('')
const driverExternalId = ref<string | number | ''>('')
const driverReason = ref('')
const pilgrimSearch = ref('')
const selectedPilgrimIds = ref<number[]>([])
const assignError = ref('')
const panelError = ref('')

watch(driverKind, () => {
  driverEmployeeId.value = ''
  driverExternalId.value = ''
})

function apiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) return t('transportation.runs.errors.generic')
  const mapped = mapOperationsErrorCode(error.code)
  if (mapped !== 'generic') return t(`transportation.runs.errors.${mapped}`)
  return error.message || t('transportation.runs.errors.generic')
}

async function doTransition(
  action: 'start-boarding' | 'depart' | 'arrive' | 'complete' | 'cancel',
): Promise<void> {
  if (!run.value) return
  try {
    if (action === 'cancel') {
      const reason = window.prompt(t('transportation.runs.cancelReasonPrompt'))
      if (!reason || reason.trim().length < 3) return
      await cancelRunMutation.mutateAsync({
        id: run.value.id,
        reason: reason.trim(),
        seasonId: selectedSeasonId.value,
      })
      toast.success(t('transportation.runs.toasts.cancelled'))
      return
    }
    const ok = await confirm({
      title: t(`transportation.runs.confirm.${action}Title`),
      message: t(`transportation.runs.confirm.${action}Body`),
      confirmLabel: t(`transportation.runs.actions.${action}`),
      cancelLabel: t('transportation.movements.cancel'),
    })
    if (!ok) return
    await transitionMutation.mutateAsync({
      id: run.value.id,
      action,
      seasonId: selectedSeasonId.value,
    })
    toast.success(t(`transportation.runs.toasts.${action}`))
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

async function submitVehicle(): Promise<void> {
  if (!run.value || !vehicleId.value) return
  panelError.value = ''
  const needsReason = run.value.status !== 'planned'
  if (needsReason && vehicleReason.value.trim().length < 3) {
    panelError.value = t('transportation.runs.errors.validation.reasonRequired')
    return
  }
  try {
    await assignVehicleMutation.mutateAsync({
      id: run.value.id,
      transport_season_vehicle_id: Number(vehicleId.value),
      reason: needsReason ? vehicleReason.value.trim() : null,
      seasonId: selectedSeasonId.value,
    })
    toast.success(t('transportation.runs.toasts.vehicleAssigned'))
    vehicleDrawer.value = false
  } catch (error) {
    panelError.value = apiMessage(error)
  }
}

async function submitDriver(): Promise<void> {
  if (!run.value) return
  panelError.value = ''
  const needsReason = run.value.status !== 'planned'
  if (needsReason && driverReason.value.trim().length < 3) {
    panelError.value = t('transportation.runs.errors.validation.reasonRequired')
    return
  }
  try {
    await assignDriverMutation.mutateAsync({
      id: run.value.id,
      driver_employee_id:
        driverKind.value === 'employee' && driverEmployeeId.value
          ? Number(driverEmployeeId.value)
          : null,
      external_driver_id:
        driverKind.value === 'external' && driverExternalId.value
          ? Number(driverExternalId.value)
          : null,
      reason: needsReason ? driverReason.value.trim() : null,
      seasonId: selectedSeasonId.value,
    })
    toast.success(t('transportation.runs.toasts.driverAssigned'))
    driverDrawer.value = false
  } catch (error) {
    panelError.value = apiMessage(error)
  }
}

async function searchPilgrims(): Promise<void> {
  // selection list refreshed via remote select below; keep for button affordance
}

async function submitPassengers(): Promise<void> {
  if (!run.value || selectedPilgrimIds.value.length === 0) return
  assignError.value = ''
  try {
    await addPassengersMutation.mutateAsync({
      runId: run.value.id,
      season_pilgrim_ids: [...selectedPilgrimIds.value],
      seasonId: selectedSeasonId.value,
    })
    toast.success(t('transportation.runs.toasts.passengersAdded'))
    selectedPilgrimIds.value = []
    assignDrawer.value = false
    await refetchPassengers()
    await refetch()
  } catch (error) {
    assignError.value = apiMessage(error)
  }
}

async function removePassenger(id: number): Promise<void> {
  const reason = window.prompt(t('transportation.runs.cancelPassengerPrompt'))
  if (!reason || reason.trim().length < 3) return
  try {
    await cancelPassengerMutation.mutateAsync({
      passengerId: id,
      reason: reason.trim(),
      seasonId: selectedSeasonId.value,
    })
    toast.success(t('transportation.runs.toasts.passengerCancelled'))
    await refetchPassengers()
    await refetch()
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

function printManifest(): void {
  window.print()
}

const driverKindOptions = computed<AppSelectOption[]>(() => [
  { value: 'employee', label: t('transportation.runs.fields.driverEmployee') },
  { value: 'external', label: t('transportation.runs.fields.driverExternal') },
])

const fetchFleet = (p: { search?: string; page: number; per_page: number }) =>
  listSeasonVehicles({ ...p, is_active: true, seasonId: selectedSeasonId.value })
const mapFleet = (row: TransportSeasonVehicle): AppSelectOption => ({
  value: row.id,
  label: row.vehicle?.plate_number ?? `#${row.id}`,
  hint: String(row.operational_capacity),
})
const fetchEmployees = (p: { search?: string; page: number; per_page: number }) =>
  listEmployees(p)
const mapEmployee = (row: Employee): AppSelectOption => employeeSelectOption(row)
const fetchDrivers = (p: { search?: string; page: number; per_page: number }) =>
  listTransportDrivers({ ...p, is_active: true })
const mapDriver = (row: TransportExternalDriver): AppSelectOption => ({
  value: row.id,
  label: row.full_name,
  hint: row.phone ?? undefined,
})

const pilgrimPick = ref<string | number | ''>('')
watch(pilgrimPick, (v) => {
  if (v === '' || v == null) return
  const id = Number(v)
  if (!selectedPilgrimIds.value.includes(id)) selectedPilgrimIds.value.push(id)
  pilgrimPick.value = ''
})

const fetchPilgrims = (p: { search?: string; page: number; per_page: number }) =>
  listSeasonPilgrims({ ...p, status: 'active' }, selectedSeasonId.value ?? undefined)
const mapPilgrim = (row: SeasonPilgrim): AppSelectOption => ({
  value: row.id,
  label: row.pilgrim?.full_name ?? row.reference_number ?? `#${row.id}`,
  hint: row.reference_number ?? undefined,
})

function driverLabel(): string {
  const d = run.value?.current_driver
  if (!d) return '—'
  return d.employee?.full_name ?? d.external_driver?.full_name ?? '—'
}

async function onRecordBreakdown(): Promise<void> {
  if (!run.value) return
  const reason = window.prompt(t('transportation.breakdowns.fields.recordReason') + ' (3+):')
  if (!reason || reason.trim().length < 3) return
  try {
    const bd = await recordBreakdown(run.value.id, { reason: reason.trim() }, selectedSeasonId.value)
    toast.success(t('transportation.breakdowns.toasts.recorded'))
    await router.push(`/app/transport-breakdowns/${bd.id}`)
  } catch (error) {
    toast.error(apiMessage(error))
  }
}
</script>

<template>
  <div class="space-y-6">
    <AppPageHeader
      :title="run?.run_number ?? t('transportation.runs.detailTitle')"
      :subtitle="t('transportation.runs.detailSubtitle')"
    >
      <template #actions>
        <button
          type="button"
          class="h-11 rounded-xl border border-brand-border px-4 text-sm font-semibold"
          @click="
            run?.transport_movement_id
              ? router.push(`/app/transport-movements/${run.transport_movement_id}`)
              : router.push('/app/transport-movements')
          "
        >
          {{ t('transportation.runs.backToMovement') }}
        </button>
      </template>
    </AppPageHeader>

    <SeasonWorkspaceBar />

    <div
      v-if="selectedSeasonId == null"
      class="rounded-2xl border border-brand-border p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('transportation.movements.needSeason') }}
    </div>
    <div
      v-else-if="isLoading"
      class="rounded-2xl border border-brand-border p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('transportation.runs.loading') }}
    </div>
    <div v-else-if="isError || !run" class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center">
      <p class="text-sm text-red-700">{{ t('transportation.runs.errors.load') }}</p>
      <button type="button" class="mt-3 text-sm font-semibold underline" @click="() => refetch()">
        {{ t('transportation.movements.retry') }}
      </button>
    </div>
    <template v-else>
      <div class="grid gap-4 rounded-2xl border border-brand-border bg-brand-surface p-5 md:grid-cols-3">
        <div>
          <p class="text-xs text-brand-text-muted">{{ t('transportation.runs.columns.status') }}</p>
          <p class="font-semibold">{{ t(`transportation.runs.status.${run.status}`) }}</p>
        </div>
        <div>
          <p class="text-xs text-brand-text-muted">{{ t('transportation.runs.columns.vehicle') }}</p>
          <p class="font-semibold" dir="ltr">{{ run.current_vehicle?.plate_number ?? '—' }}</p>
        </div>
        <div>
          <p class="text-xs text-brand-text-muted">{{ t('transportation.runs.columns.driver') }}</p>
          <p class="font-semibold">{{ driverLabel() }}</p>
        </div>
        <div>
          <p class="text-xs text-brand-text-muted">{{ t('transportation.runs.columns.capacity') }}</p>
          <p class="font-semibold">
            {{ run.occupied_count ?? 0 }} / {{ run.effective_capacity ?? '—' }}
            <span v-if="seats != null" class="text-xs text-brand-text-muted">
              ({{ t('transportation.runs.availableSeats', { count: seats }) }})
            </span>
          </p>
        </div>
        <div>
          <p class="text-xs text-brand-text-muted">{{ t('transportation.movements.fields.origin') }}</p>
          <p class="font-semibold">{{ run.movement?.origin?.name ?? '—' }}</p>
        </div>
        <div>
          <p class="text-xs text-brand-text-muted">{{ t('transportation.movements.fields.destination') }}</p>
          <p class="font-semibold">{{ run.movement?.destination?.name ?? '—' }}</p>
        </div>
      </div>

      <div class="flex flex-wrap gap-2">
        <PermissionGuard
          v-for="action in actions"
          :key="action"
          permission="transportation.operate_runs"
        >
          <button
            type="button"
            class="h-11 rounded-xl px-4 text-sm font-semibold text-white disabled:opacity-50"
            :class="action === 'cancel' ? 'bg-red-700' : 'bg-brand-primary-dark'"
            :disabled="!seasonWritable"
            @click="doTransition(action)"
          >
            {{ t(`transportation.runs.actions.${action}`) }}
          </button>
        </PermissionGuard>
        <PermissionGuard permission="transportation.operate_runs">
          <button
            type="button"
            class="h-11 rounded-xl border border-brand-border px-4 text-sm font-semibold disabled:opacity-50"
            :disabled="!seasonWritable || run.status === 'completed' || run.status === 'cancelled'"
            @click="vehicleDrawer = true; panelError = ''; vehicleId = ''; vehicleReason = ''"
          >
            {{ t('transportation.runs.assignVehicle') }}
          </button>
          <button
            type="button"
            class="h-11 rounded-xl border border-brand-border px-4 text-sm font-semibold disabled:opacity-50"
            :disabled="!seasonWritable || run.status === 'completed' || run.status === 'cancelled'"
            @click="driverDrawer = true; panelError = ''; driverEmployeeId = ''; driverExternalId = ''; driverReason = ''"
          >
            {{ t('transportation.runs.assignDriver') }}
          </button>
          <button
            type="button"
            class="h-11 rounded-xl border border-amber-300 bg-amber-50 px-4 text-sm font-semibold text-amber-950 disabled:opacity-50"
            :disabled="!seasonWritable || run.status === 'completed' || run.status === 'cancelled'"
            @click="onRecordBreakdown"
          >
            {{ t('transportation.breakdowns.title') }}
          </button>
        </PermissionGuard>
      </div>

      <p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
        {{ t('transportation.runs.substitutionNotice') }}
      </p>

      <!-- Manifest -->
      <div class="rounded-2xl border border-brand-border bg-brand-surface print:border-0">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-brand-border px-5 py-4">
          <h3 class="font-bold">{{ t('transportation.runs.manifestTitle') }}</h3>
          <div class="flex flex-wrap gap-2">
            <PermissionGuard permission="transportation.manage_manifest">
              <button
                type="button"
                class="h-10 rounded-xl bg-brand-primary-dark px-3 text-sm font-semibold text-white disabled:opacity-50 print:hidden"
                :disabled="!seasonWritable || seats === 0 || run.status === 'completed' || run.status === 'cancelled'"
                @click="assignDrawer = true; assignError = ''; selectedPilgrimIds = []"
              >
                {{ t('transportation.runs.assignPassengers') }}
              </button>
            </PermissionGuard>
            <button
              type="button"
              class="h-10 rounded-xl border border-brand-border px-3 text-sm font-semibold print:hidden"
              @click="printManifest"
            >
              {{ t('transportation.runs.printManifest') }}
            </button>
          </div>
        </div>
        <div class="border-b border-brand-border px-5 py-3 print:hidden">
          <input
            v-model="manifestSearch"
            type="search"
            class="h-11 w-full rounded-xl border border-brand-border px-3 text-sm"
            :placeholder="t('transportation.runs.manifestSearch')"
          />
        </div>
        <div v-if="passengersLoading" class="p-8 text-center text-sm text-brand-text-muted">
          {{ t('transportation.runs.loading') }}
        </div>
        <div v-else-if="passengersError" class="p-8 text-center">
          <p class="text-sm text-red-700">{{ t('transportation.runs.errors.loadPassengers') }}</p>
          <button type="button" class="mt-2 text-sm font-semibold underline" @click="() => refetchPassengers()">
            {{ t('transportation.movements.retry') }}
          </button>
        </div>
        <div v-else-if="!passengers.length" class="p-8 text-center text-sm text-brand-text-muted">
          {{ t('transportation.runs.manifestEmpty') }}
        </div>
        <table v-else class="min-w-full text-sm">
          <thead class="bg-[#F4F6F5]">
            <tr>
              <th class="px-5 py-3 text-start text-xs font-bold text-brand-text-muted">
                {{ t('transportation.runs.manifest.columns.name') }}
              </th>
              <th class="px-5 py-3 text-start text-xs font-bold text-brand-text-muted">
                {{ t('transportation.runs.manifest.columns.reference') }}
              </th>
              <th class="px-5 py-3 text-start text-xs font-bold text-brand-text-muted">
                {{ t('transportation.runs.manifest.columns.status') }}
              </th>
              <th class="px-5 py-3 text-center text-xs font-bold text-brand-text-muted print:hidden">
                {{ t('transportation.movements.columns.actions') }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in passengers" :key="p.id" class="border-t border-brand-border/80">
              <td class="px-5 py-3 font-semibold">{{ p.season_pilgrim?.full_name ?? '—' }}</td>
              <td class="px-5 py-3 font-mono text-xs" dir="ltr">
                {{ p.season_pilgrim?.reference_number ?? '—' }}
              </td>
              <td class="px-5 py-3">{{ t(`transportation.runs.passengerStatus.${p.status}`) }}</td>
              <td class="px-5 py-3 text-center print:hidden">
                <PermissionGuard permission="transportation.manage_manifest">
                  <button
                    v-if="p.status === 'assigned'"
                    type="button"
                    class="text-xs font-semibold text-red-700 disabled:opacity-50"
                    :disabled="!seasonWritable"
                    @click="removePassenger(p.id)"
                  >
                    {{ t('transportation.runs.removePassenger') }}
                  </button>
                </PermissionGuard>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <!-- Vehicle drawer -->
    <Teleport to="body">
      <div v-if="vehicleDrawer" class="fixed inset-0 z-50 print:hidden">
        <div class="absolute inset-0 bg-[rgba(15,23,20,0.32)]" @click="vehicleDrawer = false" />
        <aside class="absolute inset-y-0 start-0 flex h-dvh w-full max-w-[440px] flex-col bg-brand-surface" role="dialog">
          <header class="border-b border-brand-border px-5 py-4">
            <h3 class="text-lg font-bold">{{ t('transportation.runs.assignVehicle') }}</h3>
          </header>
          <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
            <p v-if="panelError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ panelError }}</p>
            <AppRemoteSelect
              v-model="vehicleId"
              :query-key="['run-assign-fleet', selectedSeasonId]"
              :fetcher="fetchFleet"
              :map-option="mapFleet"
              searchable
            />
            <div v-if="run && run.status !== 'planned'">
              <label class="mb-1 block text-sm font-medium">{{ t('transportation.runs.fields.reason') }}</label>
              <textarea v-model="vehicleReason" rows="3" class="w-full rounded-xl border border-brand-border px-3 py-2 text-sm" />
            </div>
          </div>
          <footer class="flex gap-2 border-t border-brand-border px-5 py-4">
            <button type="button" class="h-11 flex-1 rounded-xl border border-brand-border text-sm font-semibold" @click="vehicleDrawer = false">
              {{ t('transportation.movements.cancel') }}
            </button>
            <button type="button" class="h-11 flex-1 rounded-xl bg-brand-primary-dark text-sm font-semibold text-white" @click="submitVehicle">
              {{ t('transportation.movements.save') }}
            </button>
          </footer>
        </aside>
      </div>
    </Teleport>

    <!-- Driver drawer -->
    <Teleport to="body">
      <div v-if="driverDrawer" class="fixed inset-0 z-50 print:hidden">
        <div class="absolute inset-0 bg-[rgba(15,23,20,0.32)]" @click="driverDrawer = false" />
        <aside class="absolute inset-y-0 start-0 flex h-dvh w-full max-w-[440px] flex-col bg-brand-surface" role="dialog">
          <header class="border-b border-brand-border px-5 py-4">
            <h3 class="text-lg font-bold">{{ t('transportation.runs.assignDriver') }}</h3>
          </header>
          <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
            <p v-if="panelError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ panelError }}</p>
            <AppSelect v-model="driverKind" :options="driverKindOptions" />
            <AppRemoteSelect
              v-if="driverKind === 'employee'"
              v-model="driverEmployeeId"
              :query-key="['run-assign-employees']"
              :fetcher="fetchEmployees"
              :map-option="mapEmployee"
              searchable
            />
            <AppRemoteSelect
              v-else
              v-model="driverExternalId"
              :query-key="['run-assign-external-drivers']"
              :fetcher="fetchDrivers"
              :map-option="mapDriver"
              searchable
            />
            <div v-if="run && run.status !== 'planned'">
              <label class="mb-1 block text-sm font-medium">{{ t('transportation.runs.fields.reason') }}</label>
              <textarea v-model="driverReason" rows="3" class="w-full rounded-xl border border-brand-border px-3 py-2 text-sm" />
            </div>
          </div>
          <footer class="flex gap-2 border-t border-brand-border px-5 py-4">
            <button type="button" class="h-11 flex-1 rounded-xl border border-brand-border text-sm font-semibold" @click="driverDrawer = false">
              {{ t('transportation.movements.cancel') }}
            </button>
            <button type="button" class="h-11 flex-1 rounded-xl bg-brand-primary-dark text-sm font-semibold text-white" @click="submitDriver">
              {{ t('transportation.movements.save') }}
            </button>
          </footer>
        </aside>
      </div>
    </Teleport>

    <!-- Passengers drawer -->
    <Teleport to="body">
      <div v-if="assignDrawer" class="fixed inset-0 z-50 print:hidden">
        <div class="absolute inset-0 bg-[rgba(15,23,20,0.32)]" @click="assignDrawer = false" />
        <aside class="absolute inset-y-0 start-0 flex h-dvh w-full max-w-[440px] flex-col bg-brand-surface" role="dialog">
          <header class="border-b border-brand-border px-5 py-4">
            <h3 class="text-lg font-bold">{{ t('transportation.runs.assignPassengers') }}</h3>
          </header>
          <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
            <p v-if="assignError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ assignError }}</p>
            <p class="text-xs text-brand-text-muted">
              {{ t('transportation.runs.availableSeats', { count: seats ?? '—' }) }}
            </p>
            <AppRemoteSelect
              v-model="pilgrimPick"
              :query-key="['run-assign-pilgrims', selectedSeasonId, pilgrimSearch]"
              :fetcher="fetchPilgrims"
              :map-option="mapPilgrim"
              searchable
            />
            <ul v-if="selectedPilgrimIds.length" class="space-y-1 text-sm">
              <li v-for="id in selectedPilgrimIds" :key="id" class="flex justify-between rounded-lg bg-brand-bg px-3 py-2">
                <span dir="ltr">#{{ id }}</span>
                <button type="button" class="text-xs text-red-700" @click="selectedPilgrimIds = selectedPilgrimIds.filter((x) => x !== id)">
                  {{ t('transportation.runs.removeSelection') }}
                </button>
              </li>
            </ul>
            <button type="button" class="hidden" @click="searchPilgrims" />
          </div>
          <footer class="flex gap-2 border-t border-brand-border px-5 py-4">
            <button type="button" class="h-11 flex-1 rounded-xl border border-brand-border text-sm font-semibold" @click="assignDrawer = false">
              {{ t('transportation.movements.cancel') }}
            </button>
            <button
              type="button"
              class="h-11 flex-1 rounded-xl bg-brand-primary-dark text-sm font-semibold text-white disabled:opacity-50"
              :disabled="!selectedPilgrimIds.length || addPassengersMutation.isPending.value"
              @click="submitPassengers"
            >
              {{ t('transportation.movements.save') }}
            </button>
          </footer>
        </aside>
      </div>
    </Teleport>
  </div>
</template>
