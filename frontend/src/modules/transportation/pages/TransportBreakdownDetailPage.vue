<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useQuery } from '@tanstack/vue-query'

import { useAccommodationSeasonStore } from '@/modules/accommodation/stores/seasonContextStore'
import SeasonWorkspaceBar from '@/modules/season-pilgrims/components/SeasonWorkspaceBar.vue'
import { ApiError } from '@/shared/api/http'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import type { AppSelectOption } from '@/shared/components/AppSelect.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { useToast } from '@/shared/composables/useToast'

import {
  cancelBreakdown,
  closeBreakdown,
  executeReplacement,
  getBreakdown,
  previewReplacement,
  type ReplacementPreview,
} from '../api/breakdownsApi'
import { listSeasonVehicles } from '../api/seasonFleetApi'
import type { TransportSeasonVehicle } from '../types/fleet'
import {
  emptyReplacementForm,
  mapBreakdownErrorCode,
  validateReplacementForm,
} from '../validation/breakdownValidation'

const { t } = useI18n()
const toast = useToast()
const { confirm } = useConfirm()
const route = useRoute()
const router = useRouter()
const store = useAccommodationSeasonStore()
const { selectedSeasonId } = storeToRefs(store)

const breakdownId = computed(() => {
  const n = Number(route.params.id)
  return Number.isFinite(n) && n > 0 ? n : null
})

const { data, isLoading, isError, refetch } = useQuery({
  queryKey: computed(() => ['transport-breakdown', breakdownId.value, selectedSeasonId.value]),
  queryFn: () => getBreakdown(breakdownId.value as number, selectedSeasonId.value),
  enabled: computed(() => breakdownId.value != null && selectedSeasonId.value != null),
})
const breakdown = computed(() => data.value ?? null)

const form = reactive(emptyReplacementForm())
const fieldErrors = reactive<Record<string, string>>({})
const formError = ref('')
const preview = ref<ReplacementPreview | null>(null)
const plan = ref<Array<{ run_passenger_id: number; target_run_id: number | '' }>>([])
const submitting = ref(false)

watch(
  () => form.replacement_season_vehicle_id,
  async (id) => {
    preview.value = null
    plan.value = []
    if (!id || !breakdownId.value) return
    try {
      preview.value = await previewReplacement(
        breakdownId.value,
        Number(id),
        selectedSeasonId.value,
      )
      if (preview.value.plan_required) {
        plan.value = preview.value.assigned_movable.slice(0, preview.value.capacity_gap).map((p) => ({
          run_passenger_id: p.id,
          target_run_id: '',
        }))
      }
    } catch (error) {
      formError.value = apiMessage(error)
    }
  },
)

function apiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) return t('transportation.breakdowns.errors.generic')
  const mapped = mapBreakdownErrorCode(error.code)
  return t(`transportation.breakdowns.errors.${mapped}`)
}

async function submitReplace(): Promise<void> {
  if (!breakdown.value) return
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  formError.value = ''
  const local = validateReplacementForm(form, {
    planRequired: Boolean(preview.value?.plan_required),
    planCount: plan.value.filter((p) => p.target_run_id !== '').length,
    capacityGap: preview.value?.capacity_gap ?? 0,
    requireReason: true,
  })
  Object.assign(fieldErrors, local)
  if (Object.keys(local).length) return
  if (preview.value?.boarded_block) {
    formError.value = t('transportation.breakdowns.errors.boardedBlock')
    return
  }
  if (preview.value && !preview.value.replacement.overlap_free) {
    formError.value = t('transportation.breakdowns.errors.overlap')
    return
  }

  const ok = await confirm({
    title: t('transportation.breakdowns.confirmReplaceTitle'),
    message: t('transportation.breakdowns.confirmReplaceBody'),
    confirmLabel: t('transportation.breakdowns.replace'),
    cancelLabel: t('transportation.movements.cancel'),
  })
  if (!ok) return

  submitting.value = true
  try {
    const result = await executeReplacement(
      breakdown.value.id,
      {
        replacement_season_vehicle_id: Number(form.replacement_season_vehicle_id),
        reason: form.reason.trim(),
        idempotency_key: `ui-${breakdown.value.id}-${Date.now()}`,
        reassignments: plan.value
          .filter((p) => p.target_run_id !== '')
          .map((p) => ({
            run_passenger_id: p.run_passenger_id,
            target_run_id: Number(p.target_run_id),
          })),
      },
      selectedSeasonId.value,
    )
    toast.success(t('transportation.breakdowns.toasts.replaced'))
    await refetch()
    if (result.run?.id) {
      await router.push(`/app/transport-runs/${result.run.id}`)
    }
  } catch (error) {
    formError.value = apiMessage(error)
  } finally {
    submitting.value = false
  }
}

async function onClose(): Promise<void> {
  if (!breakdown.value) return
  const reason = window.prompt(t('transportation.breakdowns.closePrompt'))
  if (!reason || reason.trim().length < 3) return
  try {
    await closeBreakdown(breakdown.value.id, reason.trim(), selectedSeasonId.value)
    toast.success(t('transportation.breakdowns.toasts.closed'))
    await refetch()
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

async function onCancel(): Promise<void> {
  if (!breakdown.value) return
  const reason = window.prompt(t('transportation.breakdowns.cancelPrompt'))
  if (!reason || reason.trim().length < 3) return
  try {
    await cancelBreakdown(breakdown.value.id, reason.trim(), selectedSeasonId.value)
    toast.success(t('transportation.breakdowns.toasts.cancelled'))
    await refetch()
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

const fetchFleet = (p: { search?: string; page: number; per_page: number }) =>
  listSeasonVehicles({ ...p, is_active: true, seasonId: selectedSeasonId.value })
const mapFleet = (row: TransportSeasonVehicle): AppSelectOption => ({
  value: row.id,
  label: row.vehicle?.plate_number ?? `#${row.id}`,
  hint: String(row.operational_capacity),
})

function targetOptions(): AppSelectOption[] {
  return (preview.value?.eligible_target_runs ?? []).map((r) => ({
    value: r.id,
    label: `${r.movement_number ?? ''} / ${r.run_number}`,
    hint: `${r.available_seats} · ${r.plate_number ?? ''}`,
  }))
}
</script>

<template>
  <div class="space-y-6">
    <AppPageHeader
      :title="t('transportation.breakdowns.detailTitle')"
      :subtitle="t('transportation.breakdowns.detailSubtitle')"
    >
      <template #actions>
        <button
          type="button"
          class="h-11 rounded-xl border border-brand-border px-4 text-sm font-semibold"
          @click="router.push('/app/transport-breakdowns')"
        >
          {{ t('transportation.movements.back') }}
        </button>
      </template>
    </AppPageHeader>
    <SeasonWorkspaceBar />

    <div v-if="isLoading" class="rounded-2xl border border-brand-border p-10 text-center text-sm text-brand-text-muted">
      {{ t('transportation.breakdowns.loading') }}
    </div>
    <div v-else-if="isError || !breakdown" class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center">
      <p class="text-sm text-red-700">{{ t('transportation.breakdowns.errors.load') }}</p>
      <button type="button" class="mt-3 text-sm font-semibold underline" @click="() => refetch()">
        {{ t('transportation.movements.retry') }}
      </button>
    </div>
    <template v-else>
      <div class="grid gap-4 rounded-2xl border border-brand-border bg-brand-surface p-5 md:grid-cols-2">
        <div>
          <p class="text-xs text-brand-text-muted">{{ t('transportation.breakdowns.columns.status') }}</p>
          <p class="font-semibold">{{ t(`transportation.breakdowns.status.${breakdown.status}`) }}</p>
        </div>
        <div>
          <p class="text-xs text-brand-text-muted">{{ t('transportation.breakdowns.columns.vehicle') }}</p>
          <p class="font-semibold" dir="ltr">{{ breakdown.vehicle?.plate_number ?? '—' }}</p>
        </div>
        <div>
          <p class="text-xs text-brand-text-muted">{{ t('transportation.breakdowns.columns.run') }}</p>
          <button
            type="button"
            class="font-semibold underline"
            @click="router.push(`/app/transport-runs/${breakdown.vehicle_run_id}`)"
          >
            {{ breakdown.run?.run_number ?? breakdown.vehicle_run_id }}
          </button>
        </div>
        <div>
          <p class="text-xs text-brand-text-muted">{{ t('transportation.breakdowns.columns.reason') }}</p>
          <p class="font-semibold">{{ breakdown.reason }}</p>
        </div>
      </div>

      <div v-if="breakdown.status === 'open'" class="flex flex-wrap gap-2">
        <PermissionGuard permission="transportation.operate_runs">
          <button type="button" class="h-11 rounded-xl border border-brand-border px-4 text-sm font-semibold" @click="onClose">
            {{ t('transportation.breakdowns.close') }}
          </button>
          <button type="button" class="h-11 rounded-xl border border-red-200 px-4 text-sm font-semibold text-red-700" @click="onCancel">
            {{ t('transportation.breakdowns.cancelRecord') }}
          </button>
        </PermissionGuard>
      </div>

      <PermissionGuard v-if="breakdown.status === 'open'" permission="transportation.operate_runs">
        <div class="space-y-4 rounded-2xl border border-brand-border bg-brand-surface p-5">
          <h3 class="font-bold">{{ t('transportation.breakdowns.replaceSection') }}</h3>
          <p v-if="formError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ formError }}</p>
          <div>
            <label class="mb-1 block text-sm font-medium">{{ t('transportation.breakdowns.fields.replacement') }}</label>
            <AppRemoteSelect
              v-model="form.replacement_season_vehicle_id"
              :query-key="['breakdown-replacement-fleet', selectedSeasonId]"
              :fetcher="fetchFleet"
              :map-option="mapFleet"
              searchable
            />
            <p v-if="fieldErrors.replacement_season_vehicle_id" class="mt-1 text-xs text-red-600">
              {{ t('transportation.breakdowns.errors.validation.required') }}
            </p>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium">{{ t('transportation.breakdowns.fields.reason') }}</label>
            <textarea v-model="form.reason" rows="3" class="w-full rounded-xl border border-brand-border px-3 py-2 text-sm" />
            <p v-if="fieldErrors.reason" class="mt-1 text-xs text-red-600">
              {{ t('transportation.breakdowns.errors.validation.min') }}
            </p>
          </div>

          <div v-if="preview" class="rounded-xl border border-brand-border bg-brand-bg p-4 text-sm">
            <p>
              {{ t('transportation.breakdowns.preview.occupied', { count: preview.occupied_count }) }}
              ·
              {{ t('transportation.breakdowns.preview.capacity', { count: preview.replacement.effective_capacity }) }}
              ·
              {{ t('transportation.breakdowns.preview.gap', { count: preview.capacity_gap }) }}
            </p>
            <p v-if="preview.plan_required" class="mt-2 font-semibold text-amber-800">
              {{ t('transportation.breakdowns.preview.planNeeded') }}
            </p>
          </div>

          <div v-if="plan.length" class="space-y-3">
            <h4 class="text-sm font-bold">{{ t('transportation.breakdowns.planTitle') }}</h4>
            <p v-if="fieldErrors.plan" class="text-xs text-red-600">
              {{ t('transportation.breakdowns.errors.validation.plan') }}
            </p>
            <div v-for="(row, idx) in plan" :key="row.run_passenger_id" class="grid gap-2 md:grid-cols-2">
              <div class="rounded-lg bg-brand-bg px-3 py-2 text-sm">
                {{
                  t('transportation.breakdowns.fields.passengerRow', {
                    id: row.run_passenger_id,
                    pilgrim:
                      preview?.assigned_movable.find((p) => p.id === row.run_passenger_id)?.season_pilgrim_id ??
                      '—',
                  })
                }}
              </div>
              <select
                v-model="plan[idx].target_run_id"
                class="h-11 rounded-xl border border-brand-border px-3 text-sm"
              >
                <option value="">{{ t('transportation.breakdowns.fields.pickTarget') }}</option>
                <option v-for="opt in targetOptions()" :key="opt.value" :value="opt.value">
                  {{ opt.label }} ({{ opt.hint }})
                </option>
              </select>
            </div>
          </div>

          <button
            type="button"
            class="h-11 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white disabled:opacity-50"
            :disabled="submitting"
            @click="submitReplace"
          >
            {{ t('transportation.breakdowns.replace') }}
          </button>
        </div>
      </PermissionGuard>

      <div v-if="breakdown.replacement" class="rounded-2xl border border-brand-border bg-brand-surface p-5 text-sm">
        <h3 class="mb-2 font-bold">{{ t('transportation.breakdowns.replacementResult') }}</h3>
        <p>
          {{ t('transportation.breakdowns.preview.retained', { count: breakdown.replacement.passengers_retained }) }}
          ·
          {{ t('transportation.breakdowns.preview.reassigned', { count: breakdown.replacement.passengers_reassigned }) }}
        </p>
      </div>
    </template>
  </div>
</template>
