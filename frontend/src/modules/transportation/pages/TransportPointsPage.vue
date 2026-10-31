<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ChevronLeft, ChevronRight, Pencil, Plus, Search } from 'lucide-vue-next'

import { ApiError } from '@/shared/api/http'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { useDebouncedRef } from '@/shared/composables/useDebouncedRef'
import { useToast } from '@/shared/composables/useToast'

import {
  useActivatePointMutation,
  useCreatePointMutation,
  useDeactivatePointMutation,
  useDeletePointMutation,
  useUpdatePointMutation,
} from '../mutations/useFleetMutations'
import { useTransportPointsQuery } from '../queries/useFleetQueries'
import type { ListPointsParams, PointFormState, TransportPoint, TransportPointType } from '../types/points'
import { POINT_TYPES } from '../types/points'
import {
  emptyPointForm,
  mapTransportErrorCode,
  resolveListState,
  validatePointForm,
} from '../validation/fleetValidation'

const { t } = useI18n()
const toast = useToast()
const { confirm } = useConfirm()

const filters = reactive({
  search: '',
  is_active: '' as '' | '1' | '0',
  point_type: '' as TransportPointType | '',
  page: 1,
  per_page: 15,
})

const committedSearch = useDebouncedRef(() => filters.search)
const params = computed<ListPointsParams>(() => ({
  search: committedSearch.value.trim() || undefined,
  is_active: filters.is_active === '' ? '' : filters.is_active === '1',
  point_type: filters.point_type,
  page: filters.page,
  per_page: filters.per_page,
}))

const { data, isLoading, isError, refetch } = useTransportPointsQuery(params)
const points = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta)
const listState = computed(() =>
  resolveListState({
    isLoading: isLoading.value,
    isError: isError.value,
    count: points.value.length,
  }),
)

watch(
  () => [committedSearch.value, filters.is_active, filters.point_type],
  () => {
    filters.page = 1
  },
)

const statusOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('transportation.points.filters.allStatus') },
  { value: '1', label: t('transportation.points.filters.active') },
  { value: '0', label: t('transportation.points.filters.inactive') },
])
const typeFilterOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('transportation.points.filters.allTypes') },
  ...POINT_TYPES.map((type) => ({ value: type, label: t(`transportation.points.type.${type}`) })),
])
const typeFormOptions = computed<AppSelectOption[]>(() =>
  POINT_TYPES.map((type) => ({ value: type, label: t(`transportation.points.type.${type}`) })),
)

const createMutation = useCreatePointMutation()
const updateMutation = useUpdatePointMutation()
const deleteMutation = useDeletePointMutation()
const activateMutation = useActivatePointMutation()
const deactivateMutation = useDeactivatePointMutation()

const drawerOpen = ref(false)
const editing = ref<TransportPoint | null>(null)
const form = reactive<PointFormState>(emptyPointForm())
const formError = ref('')
const fieldErrors = reactive<Record<string, string>>({})
const submitting = computed(
  () => createMutation.isPending.value || updateMutation.isPending.value,
)

function openCreate(): void {
  editing.value = null
  Object.assign(form, emptyPointForm())
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  drawerOpen.value = true
}

function openEdit(point: TransportPoint): void {
  editing.value = point
  Object.assign(form, {
    code: point.code,
    name: point.name,
    point_type: point.point_type,
    notes: point.notes ?? '',
  })
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  drawerOpen.value = true
}

function fieldMessage(key: string): string {
  const code = fieldErrors[key]
  return code ? t(`transportation.points.errors.validation.${code}`) : ''
}

function apiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) return t('transportation.points.errors.generic')
  const mapped = mapTransportErrorCode(error.code)
  if (mapped === 'pointCodeTaken' || mapped === 'pointInUse') {
    return t(`transportation.points.errors.${mapped}`)
  }
  return error.message || t('transportation.points.errors.generic')
}

async function submit(): Promise<void> {
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  const local = validatePointForm(form)
  Object.assign(fieldErrors, local)
  if (Object.keys(local).length) return

  try {
    if (editing.value) {
      await updateMutation.mutateAsync({
        id: editing.value.id,
        payload: {
          code: form.code.trim(),
          name: form.name.trim(),
          point_type: form.point_type,
          notes: form.notes.trim() || null,
        },
      })
      toast.success(t('transportation.points.toasts.updated'))
    } else {
      await createMutation.mutateAsync({ ...form })
      toast.success(t('transportation.points.toasts.created'))
    }
    drawerOpen.value = false
  } catch (error) {
    formError.value = apiMessage(error)
  }
}

async function toggleActive(point: TransportPoint): Promise<void> {
  try {
    if (point.is_active) {
      await deactivateMutation.mutateAsync(point.id)
      toast.success(t('transportation.points.toasts.deactivated'))
    } else {
      await activateMutation.mutateAsync(point.id)
      toast.success(t('transportation.points.toasts.activated'))
    }
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

async function onDelete(point: TransportPoint): Promise<void> {
  const ok = await confirm({
    title: t('transportation.points.confirmDeleteTitle'),
    message: t('transportation.points.confirmDeleteBody'),
    confirmLabel: t('transportation.points.delete'),
    cancelLabel: t('transportation.points.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deleteMutation.mutateAsync(point.id)
    toast.success(t('transportation.points.toasts.deleted'))
  } catch (error) {
    toast.error(apiMessage(error))
  }
}
</script>

<template>
  <div class="space-y-6">
    <AppPageHeader
      :title="t('transportation.points.title')"
      :subtitle="t('transportation.points.subtitle')"
      :meta="
        listState === 'ready' && meta
          ? t('transportation.points.total', { count: meta.total })
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
            {{ t('transportation.points.add') }}
          </button>
        </PermissionGuard>
      </template>
    </AppPageHeader>

    <div class="flex flex-col gap-3 rounded-2xl border border-brand-border bg-brand-surface p-4 sm:flex-row sm:items-end">
      <div class="relative min-w-0 flex-1">
        <Search class="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-text-muted" />
        <input
          v-model="filters.search"
          type="search"
          class="h-11 w-full rounded-xl border border-brand-border bg-white pe-3 ps-10 text-sm"
          :placeholder="t('transportation.points.searchPlaceholder')"
        />
      </div>
      <div class="w-full sm:w-44">
        <AppSelect v-model="filters.is_active" :options="statusOptions" />
      </div>
      <div class="w-full sm:w-48">
        <AppSelect v-model="filters.point_type" :options="typeFilterOptions" />
      </div>
    </div>

    <div
      v-if="listState === 'loading'"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('transportation.points.loading') }}
    </div>
    <div
      v-else-if="listState === 'error'"
      class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center"
    >
      <p class="text-sm text-red-700">{{ t('transportation.points.errors.load') }}</p>
      <button type="button" class="mt-3 text-sm font-semibold underline" @click="() => refetch()">
        {{ t('transportation.points.retry') }}
      </button>
    </div>
    <div
      v-else-if="listState === 'empty'"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('transportation.points.empty') }}
    </div>
    <div v-else class="overflow-hidden rounded-2xl border border-brand-border bg-brand-surface">
      <table class="min-w-full text-sm">
        <thead class="bg-[#F4F6F5]">
          <tr>
            <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">
              {{ t('transportation.points.columns.code') }}
            </th>
            <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">
              {{ t('transportation.points.columns.name') }}
            </th>
            <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">
              {{ t('transportation.points.columns.type') }}
            </th>
            <th class="px-5 py-3.5 text-center text-xs font-bold text-brand-text-muted">
              {{ t('transportation.points.columns.status') }}
            </th>
            <th class="px-5 py-3.5 text-center text-xs font-bold text-brand-text-muted">
              {{ t('transportation.points.columns.actions') }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="point in points" :key="point.id" class="border-t border-brand-border/80">
            <td class="px-5 py-3.5 font-mono text-xs" dir="ltr">{{ point.code }}</td>
            <td class="px-5 py-3.5 font-semibold">{{ point.name }}</td>
            <td class="px-5 py-3.5">{{ t(`transportation.points.type.${point.point_type}`) }}</td>
            <td class="px-5 py-3.5 text-center">
              <span
                class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                :class="
                  point.is_active
                    ? 'bg-emerald-50 text-emerald-800'
                    : 'bg-neutral-100 text-neutral-600'
                "
              >
                {{
                  point.is_active
                    ? t('transportation.points.status.active')
                    : t('transportation.points.status.inactive')
                }}
              </span>
            </td>
            <td class="px-5 py-3.5">
              <div class="flex flex-wrap justify-center gap-1">
                <PermissionGuard permission="transportation.manage_registry">
                  <button
                    type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg hover:bg-brand-bg"
                    @click="openEdit(point)"
                  >
                    <Pencil class="h-4 w-4" />
                  </button>
                  <button
                    type="button"
                    class="inline-flex h-9 items-center rounded-lg px-2 text-xs font-semibold hover:bg-brand-bg"
                    @click="toggleActive(point)"
                  >
                    {{
                      point.is_active
                        ? t('transportation.points.deactivate')
                        : t('transportation.points.activate')
                    }}
                  </button>
                  <button
                    type="button"
                    class="inline-flex h-9 items-center rounded-lg px-2 text-xs font-semibold text-red-700 hover:bg-red-50"
                    @click="onDelete(point)"
                  >
                    {{ t('transportation.points.delete') }}
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
                  ? t('transportation.points.editTitle')
                  : t('transportation.points.createTitle')
              }}
            </h3>
          </header>
          <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
            <p v-if="formError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">
              {{ formError }}
            </p>
            <div>
              <label class="mb-1 block text-sm font-medium">{{
                t('transportation.points.fields.code')
              }}</label>
              <input
                v-model="form.code"
                type="text"
                dir="ltr"
                class="h-11 w-full rounded-xl border border-brand-border px-3 text-sm"
              />
              <p v-if="fieldMessage('code')" class="mt-1 text-xs text-red-600">
                {{ fieldMessage('code') }}
              </p>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium">{{
                t('transportation.points.fields.name')
              }}</label>
              <input
                v-model="form.name"
                type="text"
                class="h-11 w-full rounded-xl border border-brand-border px-3 text-sm"
              />
              <p v-if="fieldMessage('name')" class="mt-1 text-xs text-red-600">
                {{ fieldMessage('name') }}
              </p>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium">{{
                t('transportation.points.fields.type')
              }}</label>
              <AppSelect v-model="form.point_type" :options="typeFormOptions" />
              <p v-if="fieldMessage('point_type')" class="mt-1 text-xs text-red-600">
                {{ fieldMessage('point_type') }}
              </p>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium">{{
                t('transportation.points.fields.notes')
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
              {{ t('transportation.points.cancel') }}
            </button>
            <button
              type="button"
              class="h-11 flex-1 rounded-xl bg-brand-primary-dark text-sm font-semibold text-white disabled:opacity-50"
              :disabled="submitting"
              @click="submit"
            >
              {{ t('transportation.points.save') }}
            </button>
          </footer>
        </aside>
      </div>
    </Teleport>
  </div>
</template>
