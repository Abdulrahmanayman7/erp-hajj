<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ChevronLeft, ChevronRight, Link2, Pencil, Plus, Search } from 'lucide-vue-next'

import { ApiError } from '@/shared/api/http'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { useDebouncedRef } from '@/shared/composables/useDebouncedRef'
import { useToast } from '@/shared/composables/useToast'

import VehicleLinksDrawer from '../components/VehicleLinksDrawer.vue'
import {
  useActivateVehicleMutation,
  useCreateVehicleMutation,
  useDeactivateVehicleMutation,
  useDeleteVehicleMutation,
  useUpdateVehicleMutation,
} from '../mutations/useFleetMutations'
import { useTransportVehiclesQuery } from '../queries/useFleetQueries'
import type { ListVehiclesParams, TransportVehicle, VehicleClass, VehicleFormState } from '../types/fleet'
import { VEHICLE_CLASSES } from '../types/fleet'
import {
  emptyVehicleForm,
  hasGpsBackendContract,
  resolveListState,
  validateVehicleForm,
} from '../validation/fleetValidation'

const { t } = useI18n()
const toast = useToast()
const { confirm } = useConfirm()

const filters = reactive({
  search: '',
  is_active: '' as '' | '1' | '0',
  vehicle_class: '' as VehicleClass | '',
  page: 1,
  per_page: 15,
})

const committedSearch = useDebouncedRef(() => filters.search)
const params = computed<ListVehiclesParams>(() => ({
  search: committedSearch.value.trim() || undefined,
  is_active: filters.is_active === '' ? '' : filters.is_active === '1',
  vehicle_class: filters.vehicle_class,
  page: filters.page,
  per_page: filters.per_page,
}))

const { data, isLoading, isError, refetch } = useTransportVehiclesQuery(params)
const vehicles = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta)
const listState = computed(() =>
  resolveListState({
    isLoading: isLoading.value,
    isError: isError.value,
    count: vehicles.value.length,
  }),
)

watch(
  () => [committedSearch.value, filters.is_active, filters.vehicle_class],
  () => {
    filters.page = 1
  },
)

const statusOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('transportation.vehicles.filters.allStatus') },
  { value: '1', label: t('transportation.vehicles.filters.active') },
  { value: '0', label: t('transportation.vehicles.filters.inactive') },
])
const classFilterOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('transportation.vehicles.filters.allClasses') },
  ...VEHICLE_CLASSES.map((c) => ({ value: c, label: t(`transportation.vehicles.class.${c}`) })),
])
const classFormOptions = computed<AppSelectOption[]>(() =>
  VEHICLE_CLASSES.map((c) => ({ value: c, label: t(`transportation.vehicles.class.${c}`) })),
)

const createMutation = useCreateVehicleMutation()
const updateMutation = useUpdateVehicleMutation()
const deleteMutation = useDeleteVehicleMutation()
const activateMutation = useActivateVehicleMutation()
const deactivateMutation = useDeactivateVehicleMutation()

const drawerOpen = ref(false)
const linksOpen = ref(false)
const linksVehicleId = ref<number | null>(null)
const editing = ref<TransportVehicle | null>(null)
const form = reactive<VehicleFormState>(emptyVehicleForm())
const formError = ref('')
const fieldErrors = reactive<Record<string, string>>({})
const submitting = computed(
  () => createMutation.isPending.value || updateMutation.isPending.value,
)
const showGpsGap = computed(() => !hasGpsBackendContract())

function openLinks(vehicle: TransportVehicle): void {
  linksVehicleId.value = vehicle.id
  linksOpen.value = true
}

function openCreate(): void {
  editing.value = null
  Object.assign(form, emptyVehicleForm())
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  drawerOpen.value = true
}

function openEdit(vehicle: TransportVehicle): void {
  editing.value = vehicle
  Object.assign(form, {
    plate_number: vehicle.plate_number,
    vehicle_class: vehicle.vehicle_class,
    notes: vehicle.notes ?? '',
  })
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  drawerOpen.value = true
}

function fieldMessage(key: string): string {
  const code = fieldErrors[key]
  return code ? t(`transportation.vehicles.errors.validation.${code}`) : ''
}

function apiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) return t('transportation.vehicles.errors.generic')
  return error.message || t('transportation.vehicles.errors.generic')
}

async function submit(): Promise<void> {
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  const local = validateVehicleForm(form)
  Object.assign(fieldErrors, local)
  if (Object.keys(local).length) return

  try {
    if (editing.value) {
      await updateMutation.mutateAsync({
        id: editing.value.id,
        payload: {
          plate_number: form.plate_number.trim(),
          vehicle_class: form.vehicle_class,
          notes: form.notes.trim() || null,
        },
      })
      toast.success(t('transportation.vehicles.toasts.updated'))
    } else {
      await createMutation.mutateAsync({ ...form })
      toast.success(t('transportation.vehicles.toasts.created'))
    }
    drawerOpen.value = false
  } catch (error) {
    formError.value = apiMessage(error)
  }
}

async function toggleActive(vehicle: TransportVehicle): Promise<void> {
  try {
    if (vehicle.is_active) {
      await deactivateMutation.mutateAsync(vehicle.id)
      toast.success(t('transportation.vehicles.toasts.deactivated'))
    } else {
      await activateMutation.mutateAsync(vehicle.id)
      toast.success(t('transportation.vehicles.toasts.activated'))
    }
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

async function onDelete(vehicle: TransportVehicle): Promise<void> {
  const ok = await confirm({
    title: t('transportation.vehicles.confirmDeleteTitle'),
    message: t('transportation.vehicles.confirmDeleteBody'),
    confirmLabel: t('transportation.vehicles.delete'),
    cancelLabel: t('transportation.vehicles.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deleteMutation.mutateAsync(vehicle.id)
    toast.success(t('transportation.vehicles.toasts.deleted'))
  } catch (error) {
    toast.error(apiMessage(error))
  }
}
</script>

<template>
  <div class="space-y-6">
    <AppPageHeader
      :title="t('transportation.vehicles.title')"
      :subtitle="t('transportation.vehicles.subtitle')"
      :meta="
        listState === 'ready' && meta
          ? t('transportation.vehicles.total', { count: meta.total })
          : undefined
      "
    >
      <template #actions>
        <PermissionGuard permission="transportation.manage_registry">
          <button
            type="button"
            class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white"
            @click="openCreate"
          >
            <Plus class="h-4 w-4" />
            {{ t('transportation.vehicles.add') }}
          </button>
        </PermissionGuard>
      </template>
    </AppPageHeader>

    <div
      v-if="showGpsGap"
      class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-950"
      role="note"
    >
      <p class="font-semibold">{{ t('transportation.vehicles.gpsNoticeTitle') }}</p>
      <p class="mt-1 text-sky-900/90">{{ t('transportation.vehicles.gpsNoticeBody') }}</p>
    </div>

    <div class="flex flex-col gap-3 rounded-2xl border border-brand-border bg-brand-surface p-4 sm:flex-row sm:items-end">
      <div class="relative min-w-0 flex-1">
        <Search class="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-text-muted" />
        <input
          v-model="filters.search"
          type="search"
          class="h-11 w-full rounded-xl border border-brand-border bg-white pe-3 ps-10 text-sm"
          :placeholder="t('transportation.vehicles.searchPlaceholder')"
        />
      </div>
      <div class="w-full sm:w-44">
        <AppSelect v-model="filters.is_active" :options="statusOptions" />
      </div>
      <div class="w-full sm:w-44">
        <AppSelect v-model="filters.vehicle_class" :options="classFilterOptions" />
      </div>
    </div>

    <div
      v-if="listState === 'loading'"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('transportation.vehicles.loading') }}
    </div>
    <div
      v-else-if="listState === 'error'"
      class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center"
    >
      <p class="text-sm text-red-700">{{ t('transportation.vehicles.errors.load') }}</p>
      <button type="button" class="mt-3 text-sm font-semibold underline" @click="() => refetch()">
        {{ t('transportation.vehicles.retry') }}
      </button>
    </div>
    <div
      v-else-if="listState === 'empty'"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('transportation.vehicles.empty') }}
    </div>
    <div v-else class="overflow-hidden rounded-2xl border border-brand-border bg-brand-surface">
      <table class="min-w-full text-sm">
        <thead class="bg-[#F4F6F5]">
          <tr>
            <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">
              {{ t('transportation.vehicles.columns.number') }}
            </th>
            <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">
              {{ t('transportation.vehicles.columns.plate') }}
            </th>
            <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">
              {{ t('transportation.vehicles.columns.class') }}
            </th>
            <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">
              {{ t('transportation.vehicles.columns.asset') }}
            </th>
            <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">
              {{ t('transportation.vehicles.columns.contract') }}
            </th>
            <th class="px-5 py-3.5 text-center text-xs font-bold text-brand-text-muted">
              {{ t('transportation.vehicles.columns.status') }}
            </th>
            <th class="px-5 py-3.5 text-center text-xs font-bold text-brand-text-muted">
              {{ t('transportation.vehicles.columns.actions') }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="vehicle in vehicles" :key="vehicle.id" class="border-t border-brand-border/80">
            <td class="px-5 py-3.5 font-mono text-xs" dir="ltr">{{ vehicle.vehicle_number }}</td>
            <td class="px-5 py-3.5 font-semibold" dir="ltr">{{ vehicle.plate_number }}</td>
            <td class="px-5 py-3.5">{{ t(`transportation.vehicles.class.${vehicle.vehicle_class}`) }}</td>
            <td class="px-5 py-3.5 text-xs">
              {{ vehicle.asset_id != null ? `#${vehicle.asset_id}` : '—' }}
            </td>
            <td class="px-5 py-3.5 text-xs">
              {{ vehicle.contract_id != null ? `#${vehicle.contract_id}` : '—' }}
            </td>
            <td class="px-5 py-3.5 text-center">
              <span
                class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                :class="
                  vehicle.is_active
                    ? 'bg-emerald-50 text-emerald-800'
                    : 'bg-neutral-100 text-neutral-600'
                "
              >
                {{
                  vehicle.is_active
                    ? t('transportation.vehicles.status.active')
                    : t('transportation.vehicles.status.inactive')
                }}
              </span>
            </td>
            <td class="px-5 py-3.5">
              <div class="flex flex-wrap justify-center gap-1">
                <PermissionGuard permission="transportation.manage_registry">
                  <button
                    type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg hover:bg-brand-bg"
                    :title="t('transportation.vehicles.links')"
                    @click="openLinks(vehicle)"
                  >
                    <Link2 class="h-4 w-4" />
                  </button>
                  <button
                    type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg hover:bg-brand-bg"
                    @click="openEdit(vehicle)"
                  >
                    <Pencil class="h-4 w-4" />
                  </button>
                  <button
                    type="button"
                    class="inline-flex h-9 items-center rounded-lg px-2 text-xs font-semibold hover:bg-brand-bg"
                    @click="toggleActive(vehicle)"
                  >
                    {{
                      vehicle.is_active
                        ? t('transportation.vehicles.deactivate')
                        : t('transportation.vehicles.activate')
                    }}
                  </button>
                  <button
                    type="button"
                    class="inline-flex h-9 items-center rounded-lg px-2 text-xs font-semibold text-red-700 hover:bg-red-50"
                    @click="onDelete(vehicle)"
                  >
                    {{ t('transportation.vehicles.delete') }}
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

    <VehicleLinksDrawer v-model:open="linksOpen" :vehicle-id="linksVehicleId" />

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
                  ? t('transportation.vehicles.editTitle')
                  : t('transportation.vehicles.createTitle')
              }}
            </h3>
          </header>
          <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
            <p v-if="formError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">
              {{ formError }}
            </p>
            <div>
              <label class="mb-1 block text-sm font-medium">{{
                t('transportation.vehicles.fields.plate')
              }}</label>
              <input
                v-model="form.plate_number"
                type="text"
                dir="ltr"
                class="h-11 w-full rounded-xl border border-brand-border px-3 text-sm"
              />
              <p v-if="fieldMessage('plate_number')" class="mt-1 text-xs text-red-600">
                {{ fieldMessage('plate_number') }}
              </p>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium">{{
                t('transportation.vehicles.fields.class')
              }}</label>
              <AppSelect v-model="form.vehicle_class" :options="classFormOptions" />
              <p v-if="fieldMessage('vehicle_class')" class="mt-1 text-xs text-red-600">
                {{ fieldMessage('vehicle_class') }}
              </p>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium">{{
                t('transportation.vehicles.fields.notes')
              }}</label>
              <textarea
                v-model="form.notes"
                rows="3"
                class="w-full rounded-xl border border-brand-border px-3 py-2 text-sm"
              />
              <p v-if="fieldMessage('notes')" class="mt-1 text-xs text-red-600">
                {{ fieldMessage('notes') }}
              </p>
            </div>
          </div>
          <footer class="flex gap-2 border-t border-brand-border px-5 py-4">
            <button
              type="button"
              class="h-11 flex-1 rounded-xl border border-brand-border text-sm font-semibold"
              @click="drawerOpen = false"
            >
              {{ t('transportation.vehicles.cancel') }}
            </button>
            <button
              type="button"
              class="h-11 flex-1 rounded-xl bg-brand-primary-dark text-sm font-semibold text-white disabled:opacity-50"
              :disabled="submitting"
              @click="submit"
            >
              {{ t('transportation.vehicles.save') }}
            </button>
          </footer>
        </aside>
      </div>
    </Teleport>
  </div>
</template>
