<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'

import { useAccommodationSeasonStore } from '@/modules/accommodation/stores/seasonContextStore'
import SeasonWorkspaceBar from '@/modules/season-pilgrims/components/SeasonWorkspaceBar.vue'
import { useSeasonsQuery } from '@/modules/season-pilgrims/queries/useSeasonsQuery'
import { ApiError } from '@/shared/api/http'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import type { AppSelectOption } from '@/shared/components/AppSelect.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { useToast } from '@/shared/composables/useToast'

import { listSeasonVehicles } from '../api/seasonFleetApi'
import {
  useCancelMovementMutation,
  useCompleteMovementMutation,
  useCreateRunMutation,
  usePlanMovementMutation,
  useStartMovementMutation,
} from '../mutations/useOperationsMutations'
import { useMovementQuery } from '../queries/useOperationsQueries'
import type { TransportSeasonVehicle } from '../types/fleet'
import {
  emptyCreateRunForm,
  mapOperationsErrorCode,
  movementActionsFor,
  validateCreateRunForm,
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

const movementId = computed(() => {
  const n = Number(route.params.id)
  return Number.isFinite(n) && n > 0 ? n : null
})

const { data, isLoading, isError, refetch } = useMovementQuery(
  movementId,
  selectedSeasonId,
  () => movementId.value != null && selectedSeasonId.value != null,
)
const movement = computed(() => data.value ?? null)
const actions = computed(() =>
  movement.value ? movementActionsFor(movement.value.status) : [],
)

const planMutation = usePlanMovementMutation()
const startMutation = useStartMovementMutation()
const completeMutation = useCompleteMovementMutation()
const cancelMutation = useCancelMovementMutation()
const createRunMutation = useCreateRunMutation()

const runDrawer = ref(false)
const runForm = reactive(emptyCreateRunForm())
const runError = ref('')
const runFieldErrors = reactive<Record<string, string>>({})

function apiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) return t('transportation.movements.errors.generic')
  const mapped = mapOperationsErrorCode(error.code)
  if (mapped !== 'generic') return t(`transportation.movements.errors.${mapped}`)
  return error.message || t('transportation.movements.errors.generic')
}

async function runAction(action: 'plan' | 'start' | 'complete' | 'cancel'): Promise<void> {
  if (!movement.value) return
  try {
    if (action === 'cancel') {
      const reason = window.prompt(t('transportation.movements.cancelReasonPrompt'))
      if (!reason || reason.trim().length < 3) return
      await cancelMutation.mutateAsync({
        id: movement.value.id,
        reason: reason.trim(),
        seasonId: selectedSeasonId.value,
      })
      toast.success(t('transportation.movements.toasts.cancelled'))
      return
    }
    const ok = await confirm({
      title: t(`transportation.movements.confirm.${action}Title`),
      message: t(`transportation.movements.confirm.${action}Body`),
      confirmLabel: t(`transportation.movements.actions.${action}`),
      cancelLabel: t('transportation.movements.cancel'),
    })
    if (!ok) return
    const id = movement.value.id
    const seasonId = selectedSeasonId.value
    if (action === 'plan') await planMutation.mutateAsync({ id, seasonId })
    if (action === 'start') await startMutation.mutateAsync({ id, seasonId })
    if (action === 'complete') await completeMutation.mutateAsync({ id, seasonId })
    toast.success(t(`transportation.movements.toasts.${action}`))
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

function openCreateRun(): void {
  Object.assign(runForm, emptyCreateRunForm())
  runError.value = ''
  Object.keys(runFieldErrors).forEach((k) => delete runFieldErrors[k])
  runDrawer.value = true
}

async function submitRun(): Promise<void> {
  if (!movement.value) return
  Object.keys(runFieldErrors).forEach((k) => delete runFieldErrors[k])
  const local = validateCreateRunForm(runForm)
  Object.assign(runFieldErrors, local)
  if (Object.keys(local).length) return
  try {
    const run = await createRunMutation.mutateAsync({
      movementId: movement.value.id,
      payload: { ...runForm },
      seasonId: selectedSeasonId.value,
    })
    toast.success(t('transportation.runs.toasts.created'))
    runDrawer.value = false
    await router.push(`/app/transport-runs/${run.id}`)
  } catch (error) {
    runError.value = apiMessage(error)
  }
}

function formatWhen(iso: string | null): string {
  if (!iso) return '—'
  try {
    return new Intl.DateTimeFormat('ar-SA', { dateStyle: 'medium', timeStyle: 'short' }).format(
      new Date(iso),
    )
  } catch {
    return iso
  }
}

const fetchFleet = (p: { search?: string; page: number; per_page: number }) =>
  listSeasonVehicles({ ...p, is_active: true, seasonId: selectedSeasonId.value })
const mapFleet = (row: TransportSeasonVehicle): AppSelectOption => ({
  value: row.id,
  label: row.vehicle?.plate_number ?? `#${row.id}`,
  hint: `${row.vehicle?.vehicle_number ?? ''} · ${row.operational_capacity}`,
})
</script>

<template>
  <div class="space-y-6">
    <AppPageHeader
      :title="movement?.movement_number ?? t('transportation.movements.detailTitle')"
      :subtitle="t('transportation.movements.detailSubtitle')"
    >
      <template #actions>
        <button
          type="button"
          class="h-11 rounded-xl border border-brand-border px-4 text-sm font-semibold"
          @click="router.push('/app/transport-movements')"
        >
          {{ t('transportation.movements.back') }}
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
      {{ t('transportation.movements.loading') }}
    </div>
    <div v-else-if="isError || !movement" class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center">
      <p class="text-sm text-red-700">{{ t('transportation.movements.errors.load') }}</p>
      <button type="button" class="mt-3 text-sm font-semibold underline" @click="() => refetch()">
        {{ t('transportation.movements.retry') }}
      </button>
    </div>
    <template v-else>
      <div class="grid gap-4 rounded-2xl border border-brand-border bg-brand-surface p-5 md:grid-cols-2">
        <div>
          <p class="text-xs text-brand-text-muted">{{ t('transportation.movements.fields.origin') }}</p>
          <p class="font-semibold">{{ movement.origin?.name ?? '—' }}</p>
        </div>
        <div>
          <p class="text-xs text-brand-text-muted">{{ t('transportation.movements.fields.destination') }}</p>
          <p class="font-semibold">{{ movement.destination?.name ?? '—' }}</p>
        </div>
        <div>
          <p class="text-xs text-brand-text-muted">{{ t('transportation.movements.fields.departure') }}</p>
          <p class="font-semibold">{{ formatWhen(movement.planned_departure_at) }}</p>
        </div>
        <div>
          <p class="text-xs text-brand-text-muted">{{ t('transportation.movements.fields.arrival') }}</p>
          <p class="font-semibold">{{ formatWhen(movement.planned_arrival_at) }}</p>
        </div>
        <div>
          <p class="text-xs text-brand-text-muted">{{ t('transportation.movements.columns.status') }}</p>
          <p class="font-semibold">{{ t(`transportation.movements.status.${movement.status}`) }}</p>
        </div>
        <div>
          <p class="text-xs text-brand-text-muted">{{ t('transportation.movements.fields.notes') }}</p>
          <p class="font-semibold">{{ movement.notes || '—' }}</p>
        </div>
      </div>

      <div class="flex flex-wrap gap-2">
        <PermissionGuard
          v-for="action in actions"
          :key="action"
          :permission="
            action === 'start' || action === 'complete'
              ? 'transportation.operate_runs'
              : 'transportation.manage_movements'
          "
        >
          <button
            type="button"
            class="h-11 rounded-xl px-4 text-sm font-semibold text-white disabled:opacity-50"
            :class="action === 'cancel' ? 'bg-red-700' : 'bg-brand-primary-dark'"
            :disabled="!seasonWritable"
            @click="runAction(action)"
          >
            {{ t(`transportation.movements.actions.${action}`) }}
          </button>
        </PermissionGuard>
      </div>

      <div class="rounded-2xl border border-brand-border bg-brand-surface">
        <div class="flex items-center justify-between border-b border-brand-border px-5 py-4">
          <h3 class="font-bold">{{ t('transportation.runs.sectionTitle') }}</h3>
          <PermissionGuard permission="transportation.manage_movements">
            <button
              type="button"
              class="h-10 rounded-xl bg-brand-primary-dark px-3 text-sm font-semibold text-white disabled:opacity-50"
              :disabled="!seasonWritable || movement.status === 'cancelled' || movement.status === 'completed'"
              @click="openCreateRun"
            >
              {{ t('transportation.runs.add') }}
            </button>
          </PermissionGuard>
        </div>
        <div v-if="!(movement.runs && movement.runs.length)" class="p-8 text-center text-sm text-brand-text-muted">
          {{ t('transportation.runs.empty') }}
        </div>
        <table v-else class="min-w-full text-sm">
          <thead class="bg-[#F4F6F5]">
            <tr>
              <th class="px-5 py-3 text-start text-xs font-bold text-brand-text-muted">
                {{ t('transportation.runs.columns.number') }}
              </th>
              <th class="px-5 py-3 text-start text-xs font-bold text-brand-text-muted">
                {{ t('transportation.runs.columns.vehicle') }}
              </th>
              <th class="px-5 py-3 text-start text-xs font-bold text-brand-text-muted">
                {{ t('transportation.runs.columns.capacity') }}
              </th>
              <th class="px-5 py-3 text-start text-xs font-bold text-brand-text-muted">
                {{ t('transportation.runs.columns.status') }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="run in movement.runs"
              :key="run.id"
              class="cursor-pointer border-t border-brand-border/80 hover:bg-brand-bg/60"
              @click="router.push(`/app/transport-runs/${run.id}`)"
            >
              <td class="px-5 py-3 font-mono text-xs" dir="ltr">{{ run.run_number }}</td>
              <td class="px-5 py-3" dir="ltr">
                {{ run.current_vehicle?.plate_number ?? '—' }}
              </td>
              <td class="px-5 py-3 text-xs">
                {{ run.occupied_count ?? 0 }} / {{ run.effective_capacity ?? '—' }}
              </td>
              <td class="px-5 py-3">
                {{ t(`transportation.runs.status.${run.status}`) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <Teleport to="body">
      <div v-if="runDrawer" class="fixed inset-0 z-50">
        <div class="absolute inset-0 bg-[rgba(15,23,20,0.32)]" @click="runDrawer = false" />
        <aside class="absolute inset-y-0 start-0 flex h-dvh w-full max-w-[440px] flex-col bg-brand-surface" role="dialog">
          <header class="border-b border-brand-border px-5 py-4">
            <h3 class="text-lg font-bold">{{ t('transportation.runs.createTitle') }}</h3>
          </header>
          <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
            <p v-if="runError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ runError }}</p>
            <div>
              <label class="mb-1 block text-sm font-medium">{{ t('transportation.runs.fields.vehicle') }}</label>
              <AppRemoteSelect
                v-model="runForm.transport_season_vehicle_id"
                :query-key="['transport-run-season-vehicles', selectedSeasonId]"
                :fetcher="fetchFleet"
                :map-option="mapFleet"
                searchable
              />
              <p v-if="runFieldErrors.transport_season_vehicle_id" class="mt-1 text-xs text-red-600">
                {{ t('transportation.runs.errors.validation.required') }}
              </p>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium">{{ t('transportation.runs.fields.capacityOverride') }}</label>
              <input
                v-model="runForm.capacity_override"
                type="text"
                inputmode="numeric"
                class="h-11 w-full rounded-xl border border-brand-border px-3 text-sm"
              />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium">{{ t('transportation.runs.fields.notes') }}</label>
              <textarea v-model="runForm.notes" rows="3" class="w-full rounded-xl border border-brand-border px-3 py-2 text-sm" />
            </div>
          </div>
          <footer class="flex gap-2 border-t border-brand-border px-5 py-4">
            <button type="button" class="h-11 flex-1 rounded-xl border border-brand-border text-sm font-semibold" @click="runDrawer = false">
              {{ t('transportation.movements.cancel') }}
            </button>
            <button
              type="button"
              class="h-11 flex-1 rounded-xl bg-brand-primary-dark text-sm font-semibold text-white disabled:opacity-50"
              :disabled="createRunMutation.isPending.value"
              @click="submitRun"
            >
              {{ t('transportation.movements.save') }}
            </button>
          </footer>
        </aside>
      </div>
    </Teleport>
  </div>
</template>
