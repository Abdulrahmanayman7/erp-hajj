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
  useActivateDriverMutation,
  useCreateDriverMutation,
  useDeactivateDriverMutation,
  useDeleteDriverMutation,
  useUpdateDriverMutation,
} from '../mutations/useFleetMutations'
import { useTransportDriversQuery } from '../queries/useFleetQueries'
import type { DriverFormState, ListDriversParams, TransportExternalDriver } from '../types/fleet'
import {
  emptyDriverForm,
  resolveListState,
  validateDriverForm,
} from '../validation/fleetValidation'

const { t } = useI18n()
const toast = useToast()
const { confirm } = useConfirm()

const filters = reactive({
  search: '',
  is_active: '' as '' | '1' | '0',
  page: 1,
  per_page: 15,
})

const committedSearch = useDebouncedRef(() => filters.search)
const params = computed<ListDriversParams>(() => ({
  search: committedSearch.value.trim() || undefined,
  is_active: filters.is_active === '' ? '' : filters.is_active === '1',
  page: filters.page,
  per_page: filters.per_page,
}))

const { data, isLoading, isError, refetch } = useTransportDriversQuery(params)
const drivers = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta)
const listState = computed(() =>
  resolveListState({
    isLoading: isLoading.value,
    isError: isError.value,
    count: drivers.value.length,
  }),
)

watch(
  () => [committedSearch.value, filters.is_active],
  () => {
    filters.page = 1
  },
)

const statusOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('transportation.drivers.filters.allStatus') },
  { value: '1', label: t('transportation.drivers.filters.active') },
  { value: '0', label: t('transportation.drivers.filters.inactive') },
])

const createMutation = useCreateDriverMutation()
const updateMutation = useUpdateDriverMutation()
const deleteMutation = useDeleteDriverMutation()
const activateMutation = useActivateDriverMutation()
const deactivateMutation = useDeactivateDriverMutation()

const drawerOpen = ref(false)
const editing = ref<TransportExternalDriver | null>(null)
const form = reactive<DriverFormState>(emptyDriverForm())
const formError = ref('')
const fieldErrors = reactive<Record<string, string>>({})
const submitting = computed(
  () => createMutation.isPending.value || updateMutation.isPending.value,
)

function openCreate(): void {
  editing.value = null
  Object.assign(form, emptyDriverForm())
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  drawerOpen.value = true
}

function openEdit(driver: TransportExternalDriver): void {
  editing.value = driver
  Object.assign(form, {
    full_name: driver.full_name,
    phone: driver.phone ?? '',
    notes: driver.notes ?? '',
  })
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  drawerOpen.value = true
}

function fieldMessage(key: string): string {
  const code = fieldErrors[key]
  return code ? t(`transportation.drivers.errors.validation.${code}`) : ''
}

function apiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) return t('transportation.drivers.errors.generic')
  return error.message || t('transportation.drivers.errors.generic')
}

async function submit(): Promise<void> {
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  const local = validateDriverForm(form)
  Object.assign(fieldErrors, local)
  if (Object.keys(local).length) return

  try {
    if (editing.value) {
      await updateMutation.mutateAsync({
        id: editing.value.id,
        payload: {
          full_name: form.full_name.trim(),
          phone: form.phone.trim() || null,
          notes: form.notes.trim() || null,
        },
      })
      toast.success(t('transportation.drivers.toasts.updated'))
    } else {
      await createMutation.mutateAsync({ ...form })
      toast.success(t('transportation.drivers.toasts.created'))
    }
    drawerOpen.value = false
  } catch (error) {
    formError.value = apiMessage(error)
  }
}

async function toggleActive(driver: TransportExternalDriver): Promise<void> {
  try {
    if (driver.is_active) {
      await deactivateMutation.mutateAsync(driver.id)
      toast.success(t('transportation.drivers.toasts.deactivated'))
    } else {
      await activateMutation.mutateAsync(driver.id)
      toast.success(t('transportation.drivers.toasts.activated'))
    }
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

async function onDelete(driver: TransportExternalDriver): Promise<void> {
  const ok = await confirm({
    title: t('transportation.drivers.confirmDeleteTitle'),
    message: t('transportation.drivers.confirmDeleteBody'),
    confirmLabel: t('transportation.drivers.delete'),
    cancelLabel: t('transportation.drivers.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deleteMutation.mutateAsync(driver.id)
    toast.success(t('transportation.drivers.toasts.deleted'))
  } catch (error) {
    toast.error(apiMessage(error))
  }
}
</script>

<template>
  <div class="space-y-6">
    <AppPageHeader
      :title="t('transportation.drivers.title')"
      :subtitle="t('transportation.drivers.subtitle')"
      :meta="
        listState === 'ready' && meta
          ? t('transportation.drivers.total', { count: meta.total })
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
            {{ t('transportation.drivers.add') }}
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
          :placeholder="t('transportation.drivers.searchPlaceholder')"
        />
      </div>
      <div class="w-full sm:w-44">
        <AppSelect v-model="filters.is_active" :options="statusOptions" />
      </div>
    </div>

    <div
      v-if="listState === 'loading'"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('transportation.drivers.loading') }}
    </div>
    <div
      v-else-if="listState === 'error'"
      class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center"
    >
      <p class="text-sm text-red-700">{{ t('transportation.drivers.errors.load') }}</p>
      <button type="button" class="mt-3 text-sm font-semibold underline" @click="() => refetch()">
        {{ t('transportation.drivers.retry') }}
      </button>
    </div>
    <div
      v-else-if="listState === 'empty'"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('transportation.drivers.empty') }}
    </div>
    <div v-else class="overflow-hidden rounded-2xl border border-brand-border bg-brand-surface">
      <table class="min-w-full text-sm">
        <thead class="bg-[#F4F6F5]">
          <tr>
            <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">
              {{ t('transportation.drivers.columns.name') }}
            </th>
            <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">
              {{ t('transportation.drivers.columns.phone') }}
            </th>
            <th class="px-5 py-3.5 text-center text-xs font-bold text-brand-text-muted">
              {{ t('transportation.drivers.columns.status') }}
            </th>
            <th class="px-5 py-3.5 text-center text-xs font-bold text-brand-text-muted">
              {{ t('transportation.drivers.columns.actions') }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="driver in drivers" :key="driver.id" class="border-t border-brand-border/80">
            <td class="px-5 py-3.5 font-semibold">{{ driver.full_name }}</td>
            <td class="px-5 py-3.5" dir="ltr">{{ driver.phone || '—' }}</td>
            <td class="px-5 py-3.5 text-center">
              <span
                class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                :class="
                  driver.is_active
                    ? 'bg-emerald-50 text-emerald-800'
                    : 'bg-neutral-100 text-neutral-600'
                "
              >
                {{
                  driver.is_active
                    ? t('transportation.drivers.status.active')
                    : t('transportation.drivers.status.inactive')
                }}
              </span>
            </td>
            <td class="px-5 py-3.5">
              <div class="flex flex-wrap justify-center gap-1">
                <PermissionGuard permission="transportation.manage_registry">
                  <button
                    type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg hover:bg-brand-bg"
                    @click="openEdit(driver)"
                  >
                    <Pencil class="h-4 w-4" />
                  </button>
                  <button
                    type="button"
                    class="inline-flex h-9 items-center rounded-lg px-2 text-xs font-semibold hover:bg-brand-bg"
                    @click="toggleActive(driver)"
                  >
                    {{
                      driver.is_active
                        ? t('transportation.drivers.deactivate')
                        : t('transportation.drivers.activate')
                    }}
                  </button>
                  <button
                    type="button"
                    class="inline-flex h-9 items-center rounded-lg px-2 text-xs font-semibold text-red-700 hover:bg-red-50"
                    @click="onDelete(driver)"
                  >
                    {{ t('transportation.drivers.delete') }}
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
                  ? t('transportation.drivers.editTitle')
                  : t('transportation.drivers.createTitle')
              }}
            </h3>
          </header>
          <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
            <p v-if="formError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">
              {{ formError }}
            </p>
            <div>
              <label class="mb-1 block text-sm font-medium">{{
                t('transportation.drivers.fields.fullName')
              }}</label>
              <input
                v-model="form.full_name"
                type="text"
                class="h-11 w-full rounded-xl border border-brand-border px-3 text-sm"
              />
              <p v-if="fieldMessage('full_name')" class="mt-1 text-xs text-red-600">
                {{ fieldMessage('full_name') }}
              </p>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium">{{
                t('transportation.drivers.fields.phone')
              }}</label>
              <input
                v-model="form.phone"
                type="text"
                dir="ltr"
                class="h-11 w-full rounded-xl border border-brand-border px-3 text-sm"
              />
              <p v-if="fieldMessage('phone')" class="mt-1 text-xs text-red-600">
                {{ fieldMessage('phone') }}
              </p>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium">{{
                t('transportation.drivers.fields.notes')
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
              {{ t('transportation.drivers.cancel') }}
            </button>
            <button
              type="button"
              class="h-11 flex-1 rounded-xl bg-brand-primary-dark text-sm font-semibold text-white disabled:opacity-50"
              :disabled="submitting"
              @click="submit"
            >
              {{ t('transportation.drivers.save') }}
            </button>
          </footer>
        </aside>
      </div>
    </Teleport>
  </div>
</template>
