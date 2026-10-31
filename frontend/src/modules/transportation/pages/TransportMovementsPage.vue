<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { ChevronLeft, ChevronRight, Plus } from 'lucide-vue-next'

import { useAccommodationSeasonStore } from '@/modules/accommodation/stores/seasonContextStore'
import SeasonWorkspaceBar from '@/modules/season-pilgrims/components/SeasonWorkspaceBar.vue'
import { useSeasonsQuery } from '@/modules/season-pilgrims/queries/useSeasonsQuery'
import { ApiError } from '@/shared/api/http'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useToast } from '@/shared/composables/useToast'

import { listTransportPoints } from '../api/pointsApi'
import {
  useCreateMovementMutation,
  useUpdateMovementMutation,
} from '../mutations/useOperationsMutations'
import { useMovementsQuery } from '../queries/useOperationsQueries'
import type { ListMovementsParams, MovementStatus, TransportMovement } from '../types/operations'
import { MOVEMENT_STATUSES } from '../types/operations'
import type { TransportPoint } from '../types/points'
import {
  emptyMovementForm,
  validateMovementForm,
} from '../validation/operationsValidation'
import { mapOperationsErrorCode } from '../validation/operationsValidation'
import { resolveListState } from '../validation/fleetValidation'

const { t } = useI18n()
const toast = useToast()
const router = useRouter()
const store = useAccommodationSeasonStore()
const { selectedSeasonId } = storeToRefs(store)
const seasonsQuery = useSeasonsQuery({ per_page: 100 })
const selectedSeason = computed(
  () => (seasonsQuery.data.value?.data ?? []).find((s) => s.id === selectedSeasonId.value) ?? null,
)
const seasonWritable = computed(() => selectedSeason.value?.status !== 'closed')

const filters = reactive({
  status: '' as MovementStatus | '',
  page: 1,
  per_page: 15,
})

const params = computed<ListMovementsParams>(() => ({
  status: filters.status,
  page: filters.page,
  per_page: filters.per_page,
  seasonId: selectedSeasonId.value,
}))

const { data, isLoading, isError, refetch } = useMovementsQuery(
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
  () => [selectedSeasonId.value, filters.status],
  () => {
    filters.page = 1
  },
)

const statusOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('transportation.movements.filters.allStatus') },
  ...MOVEMENT_STATUSES.map((s) => ({
    value: s,
    label: t(`transportation.movements.status.${s}`),
  })),
])

const drawerOpen = ref(false)
const editing = ref<TransportMovement | null>(null)
const form = reactive(emptyMovementForm())
const formError = ref('')
const fieldErrors = reactive<Record<string, string>>({})
const createMutation = useCreateMovementMutation()
const updateMutation = useUpdateMovementMutation()
const submitting = computed(
  () => createMutation.isPending.value || updateMutation.isPending.value,
)

function openCreate(): void {
  editing.value = null
  Object.assign(form, emptyMovementForm())
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  drawerOpen.value = true
}

function openEdit(row: TransportMovement): void {
  editing.value = row
  Object.assign(form, {
    origin_point_id: row.origin_point_id,
    destination_point_id: row.destination_point_id,
    planned_departure_at: toLocalInput(row.planned_departure_at),
    planned_arrival_at: toLocalInput(row.planned_arrival_at),
    notes: row.notes ?? '',
  })
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  drawerOpen.value = true
}

function toLocalInput(iso: string | null): string {
  if (!iso) return ''
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return iso.slice(0, 16)
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

function toApiDateTime(local: string): string {
  // Backend accepts datetime; keep seconds.
  return local.length === 16 ? `${local}:00` : local
}

function fieldMessage(key: string): string {
  const code = fieldErrors[key]
  return code ? t(`transportation.movements.errors.validation.${code}`) : ''
}

function apiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) return t('transportation.movements.errors.generic')
  const mapped = mapOperationsErrorCode(error.code)
  if (mapped !== 'generic') return t(`transportation.movements.errors.${mapped}`)
  return error.message || t('transportation.movements.errors.generic')
}

async function submit(): Promise<void> {
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  const local = validateMovementForm(form)
  Object.assign(fieldErrors, local)
  if (Object.keys(local).length) return
  const payload = {
    ...form,
    planned_departure_at: toApiDateTime(form.planned_departure_at),
    planned_arrival_at: toApiDateTime(form.planned_arrival_at),
  }
  try {
    if (editing.value) {
      await updateMutation.mutateAsync({
        id: editing.value.id,
        payload,
        seasonId: selectedSeasonId.value,
      })
      toast.success(t('transportation.movements.toasts.updated'))
    } else {
      await createMutation.mutateAsync({ payload, seasonId: selectedSeasonId.value })
      toast.success(t('transportation.movements.toasts.created'))
    }
    drawerOpen.value = false
  } catch (error) {
    formError.value = apiMessage(error)
  }
}

function formatWhen(iso: string | null): string {
  if (!iso) return '—'
  try {
    return new Intl.DateTimeFormat('ar-SA', {
      dateStyle: 'medium',
      timeStyle: 'short',
    }).format(new Date(iso))
  } catch {
    return iso
  }
}

const fetchPoints = (p: { search?: string; page: number; per_page: number }) =>
  listTransportPoints({ ...p, is_active: true })
const mapPoint = (row: TransportPoint): AppSelectOption => ({
  value: row.id,
  label: row.name,
  hint: row.code,
})
</script>

<template>
  <div class="space-y-6">
    <AppPageHeader
      :title="t('transportation.movements.title')"
      :subtitle="t('transportation.movements.subtitle')"
    >
      <template #actions>
        <PermissionGuard permission="transportation.manage_movements">
          <button
            type="button"
            class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white disabled:opacity-50"
            :disabled="!seasonWritable || selectedSeasonId == null"
            @click="openCreate"
          >
            <Plus class="h-4 w-4" />
            {{ t('transportation.movements.add') }}
          </button>
        </PermissionGuard>
      </template>
    </AppPageHeader>

    <SeasonWorkspaceBar />

    <div class="flex flex-col gap-3 rounded-2xl border border-brand-border bg-brand-surface p-4 sm:flex-row sm:items-end">
      <div class="w-full sm:w-56">
        <AppSelect v-model="filters.status" :options="statusOptions" />
      </div>
    </div>

    <div
      v-if="listState === 'need_season'"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('transportation.movements.needSeason') }}
    </div>
    <div
      v-else-if="listState === 'loading'"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('transportation.movements.loading') }}
    </div>
    <div
      v-else-if="listState === 'error'"
      class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center"
    >
      <p class="text-sm text-red-700">{{ t('transportation.movements.errors.load') }}</p>
      <button type="button" class="mt-3 text-sm font-semibold underline" @click="() => refetch()">
        {{ t('transportation.movements.retry') }}
      </button>
    </div>
    <div
      v-else-if="listState === 'empty'"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('transportation.movements.empty') }}
    </div>
    <div v-else class="overflow-hidden rounded-2xl border border-brand-border bg-brand-surface">
      <table class="min-w-full text-sm">
        <thead class="bg-[#F4F6F5]">
          <tr>
            <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">
              {{ t('transportation.movements.columns.number') }}
            </th>
            <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">
              {{ t('transportation.movements.columns.route') }}
            </th>
            <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">
              {{ t('transportation.movements.columns.departure') }}
            </th>
            <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">
              {{ t('transportation.movements.columns.status') }}
            </th>
            <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">
              {{ t('transportation.movements.columns.runs') }}
            </th>
            <th class="px-5 py-3.5 text-center text-xs font-bold text-brand-text-muted">
              {{ t('transportation.movements.columns.actions') }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in rows"
            :key="row.id"
            class="cursor-pointer border-t border-brand-border/80 hover:bg-brand-bg/60"
            @click="router.push(`/app/transport-movements/${row.id}`)"
          >
            <td class="px-5 py-3.5 font-mono text-xs" dir="ltr">{{ row.movement_number }}</td>
            <td class="px-5 py-3.5">
              {{ row.origin?.name ?? '—' }}
              <span class="text-brand-text-muted">←</span>
              {{ row.destination?.name ?? '—' }}
            </td>
            <td class="px-5 py-3.5 text-xs">{{ formatWhen(row.planned_departure_at) }}</td>
            <td class="px-5 py-3.5">
              <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800">
                {{ t(`transportation.movements.status.${row.status}`) }}
              </span>
            </td>
            <td class="px-5 py-3.5 text-xs">{{ row.runs_count ?? 0 }}</td>
            <td class="px-5 py-3.5 text-center" @click.stop>
              <PermissionGuard permission="transportation.manage_movements">
                <button
                  v-if="row.status === 'draft'"
                  type="button"
                  class="rounded-lg px-2 py-1 text-xs font-semibold hover:bg-brand-bg"
                  :disabled="!seasonWritable"
                  @click="openEdit(row)"
                >
                  {{ t('transportation.movements.edit') }}
                </button>
              </PermissionGuard>
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
                  ? t('transportation.movements.editTitle')
                  : t('transportation.movements.createTitle')
              }}
            </h3>
          </header>
          <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
            <p v-if="formError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">
              {{ formError }}
            </p>
            <div>
              <label class="mb-1 block text-sm font-medium">{{
                t('transportation.movements.fields.origin')
              }}</label>
              <AppRemoteSelect
                v-model="form.origin_point_id"
                :query-key="['transport-movement-origin-points']"
                :fetcher="fetchPoints"
                :map-option="mapPoint"
                searchable
              />
              <p v-if="fieldMessage('origin_point_id')" class="mt-1 text-xs text-red-600">
                {{ fieldMessage('origin_point_id') }}
              </p>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium">{{
                t('transportation.movements.fields.destination')
              }}</label>
              <AppRemoteSelect
                v-model="form.destination_point_id"
                :query-key="['transport-movement-destination-points']"
                :fetcher="fetchPoints"
                :map-option="mapPoint"
                searchable
              />
              <p v-if="fieldMessage('destination_point_id')" class="mt-1 text-xs text-red-600">
                {{ fieldMessage('destination_point_id') }}
              </p>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium">{{
                t('transportation.movements.fields.departure')
              }}</label>
              <input
                v-model="form.planned_departure_at"
                type="datetime-local"
                class="h-11 w-full rounded-xl border border-brand-border px-3 text-sm"
              />
              <p v-if="fieldMessage('planned_departure_at')" class="mt-1 text-xs text-red-600">
                {{ fieldMessage('planned_departure_at') }}
              </p>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium">{{
                t('transportation.movements.fields.arrival')
              }}</label>
              <input
                v-model="form.planned_arrival_at"
                type="datetime-local"
                class="h-11 w-full rounded-xl border border-brand-border px-3 text-sm"
              />
              <p v-if="fieldMessage('planned_arrival_at')" class="mt-1 text-xs text-red-600">
                {{ fieldMessage('planned_arrival_at') }}
              </p>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium">{{
                t('transportation.movements.fields.notes')
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
              {{ t('transportation.movements.cancel') }}
            </button>
            <button
              type="button"
              class="h-11 flex-1 rounded-xl bg-brand-primary-dark text-sm font-semibold text-white disabled:opacity-50"
              :disabled="submitting"
              @click="submit"
            >
              {{ t('transportation.movements.save') }}
            </button>
          </footer>
        </aside>
      </div>
    </Teleport>
  </div>
</template>
