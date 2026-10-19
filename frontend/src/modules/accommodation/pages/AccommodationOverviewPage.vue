<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { RouterLink } from 'vue-router'
import { ChevronLeft, ChevronRight, Plus, Search } from 'lucide-vue-next'

import AppMobileFilters from '@/shared/components/AppMobileFilters.vue'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'
import { useDebouncedRef } from '@/shared/composables/useDebouncedRef'

import AccommodationSeasonBar from '../components/AccommodationSeasonBar.vue'
import AccommodationSubnav from '../components/AccommodationSubnav.vue'
import { useAccommodationErrors } from '../composables/useAccommodationErrors'
import {
  useCreateFacilityMutation,
  useUpdateFacilityMutation,
} from '../mutations/useAccommodationMutations'
import { useFacilitiesQuery } from '../queries/useAccommodationQueries'
import { useAccommodationSeasonStore } from '../stores/seasonContextStore'
import type { FacilityFormState, FacilityKind } from '../types/accommodation'
import { ACCOMMODATION_READ_PERMISSIONS } from '../types/accommodation'
import {
  emptyFacilityForm,
  filterFacilitiesClientSide,
  resolveListState,
  validateFacilityForm,
} from '../validation/accommodationValidation'

const { t } = useI18n()
const { canAny } = usePermissions()
const toast = useToast()
const { message } = useAccommodationErrors()
const seasonStore = useAccommodationSeasonStore()
const { canWrite } = storeToRefs(seasonStore)

const page = ref(1)
const canRead = computed(() => canAny([...ACCOMMODATION_READ_PERMISSIONS]))
const { data, isLoading, isError, refetch } = useFacilitiesQuery(page, canRead)

const filters = reactive({
  search: '',
  kind: '' as FacilityKind | '',
  active: '' as '' | '1' | '0',
})
const committedSearch = useDebouncedRef(() => filters.search)

const facilities = computed(() =>
  filterFacilitiesClientSide(data.value?.data ?? [], {
    search: committedSearch.value,
    kind: filters.kind,
    active: filters.active,
  }),
)
const meta = computed(() => data.value?.meta)
const listState = computed(() =>
  resolveListState({ isLoading: isLoading.value, isError: isError.value, count: facilities.value.length }),
)

const kindOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('accommodation.filters.allKinds') },
  { value: 'hotel', label: t('accommodation.kind.hotel') },
  { value: 'camp', label: t('accommodation.kind.camp') },
])
const activeOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('accommodation.filters.allStatuses') },
  { value: '1', label: t('accommodation.status.active') },
  { value: '0', label: t('accommodation.status.inactive') },
])

const drawerOpen = ref(false)
const form = reactive<FacilityFormState>(emptyFacilityForm())
const formError = ref('')
const fieldErrors = reactive<Record<string, string>>({})
const createMutation = useCreateFacilityMutation()
const updateMutation = useUpdateFacilityMutation()

function openCreate(): void {
  Object.assign(form, emptyFacilityForm())
  formError.value = ''
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key])
  drawerOpen.value = true
}

async function submitCreate(): Promise<void> {
  const errors = validateFacilityForm(form)
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key])
  Object.assign(fieldErrors, errors)
  if (Object.keys(errors).length) {
    formError.value = t('accommodation.errors.validation')
    return
  }
  try {
    await createMutation.mutateAsync({
      kind: form.kind,
      code: form.code.trim(),
      name: form.name.trim(),
    })
    toast.success(t('accommodation.facilities.created'))
    drawerOpen.value = false
  } catch (error) {
    formError.value = message(error)
  }
}

async function toggleActive(id: number, isActive: boolean): Promise<void> {
  if (!canWrite.value) return
  try {
    await updateMutation.mutateAsync({ id, payload: { is_active: !isActive } })
    toast.success(t('accommodation.facilities.updated'))
  } catch (error) {
    toast.error(message(error))
  }
}

watch(
  () => [committedSearch.value, filters.kind, filters.active],
  () => {
    page.value = 1
  },
)
</script>

<template>
  <div class="space-y-5">
    <AppPageHeader
      :title="t('accommodation.overview.title')"
      :subtitle="t('accommodation.overview.subtitle')"
    >
      <template #actions>
        <PermissionGuard permission="accommodation.manage_facilities">
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-lg bg-brand-primary px-3 py-2 text-sm font-semibold text-white disabled:opacity-50"
            :disabled="!canWrite"
            @click="openCreate"
          >
            <Plus class="h-4 w-4" />
            {{ t('accommodation.facilities.create') }}
          </button>
        </PermissionGuard>
      </template>
    </AppPageHeader>

    <AccommodationSubnav />
    <AccommodationSeasonBar />

    <p class="rounded-lg bg-sky-50 px-3 py-2 text-sm text-sky-950 ring-1 ring-sky-200/70">
      {{ t('accommodation.overview.scopeHint') }}
    </p>

    <AppMobileFilters>
      <div class="grid gap-3 sm:grid-cols-3">
        <label class="block">
          <span class="mb-1 block text-xs text-brand-text-secondary">{{ t('accommodation.filters.search') }}</span>
          <div class="relative">
            <Search class="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-text-muted" />
            <input
              v-model="filters.search"
              type="search"
              class="w-full rounded-lg border border-brand-border bg-brand-surface py-2 pe-3 ps-9 text-sm"
              :placeholder="t('accommodation.filters.searchPlaceholder')"
            >
          </div>
        </label>
        <label class="block">
          <span class="mb-1 block text-xs text-brand-text-secondary">{{ t('accommodation.filters.kind') }}</span>
          <AppSelect v-model="filters.kind" :options="kindOptions" />
        </label>
        <label class="block">
          <span class="mb-1 block text-xs text-brand-text-secondary">{{ t('accommodation.filters.status') }}</span>
          <AppSelect v-model="filters.active" :options="activeOptions" />
        </label>
      </div>
      <p class="mt-2 text-xs text-brand-text-muted">{{ t('accommodation.overview.clientFilterNote') }}</p>
    </AppMobileFilters>

    <div v-if="!canRead" class="rounded-xl border border-dashed border-brand-border px-4 py-10 text-center text-sm text-brand-text-secondary">
      {{ t('accommodation.overview.manageOnlyEmpty') }}
    </div>

    <div v-else-if="listState === 'loading'" class="rounded-xl border border-brand-border px-4 py-10 text-center text-sm text-brand-text-muted">
      {{ t('accommodation.loading') }}
    </div>
    <div v-else-if="listState === 'error'" class="rounded-xl border border-red-200 bg-red-50 px-4 py-8 text-center">
      <p class="text-sm text-red-800">{{ t('accommodation.errors.load') }}</p>
      <button type="button" class="mt-3 text-sm font-semibold text-red-900 underline" @click="() => refetch()">
        {{ t('accommodation.retry') }}
      </button>
    </div>
    <div v-else-if="listState === 'empty'" class="rounded-xl border border-dashed border-brand-border px-4 py-10 text-center text-sm text-brand-text-secondary">
      {{ t('accommodation.overview.empty') }}
    </div>
    <div v-else class="overflow-hidden rounded-xl border border-brand-border bg-brand-surface">
      <table class="min-w-full text-sm">
        <thead class="bg-brand-bg text-brand-text-secondary">
          <tr>
            <th class="px-4 py-3 text-start font-medium">{{ t('accommodation.fields.code') }}</th>
            <th class="px-4 py-3 text-start font-medium">{{ t('accommodation.fields.name') }}</th>
            <th class="px-4 py-3 text-start font-medium">{{ t('accommodation.fields.kind') }}</th>
            <th class="px-4 py-3 text-start font-medium">{{ t('accommodation.fields.status') }}</th>
            <th class="px-4 py-3 text-start font-medium">{{ t('accommodation.fields.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in facilities" :key="row.id" class="border-t border-brand-border">
            <td class="px-4 py-3 font-medium text-brand-text">{{ row.code }}</td>
            <td class="px-4 py-3">{{ row.name }}</td>
            <td class="px-4 py-3">{{ t(`accommodation.kind.${row.kind}`) }}</td>
            <td class="px-4 py-3">
              <span
                class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold ring-1"
                :class="row.is_active ? 'bg-emerald-50 text-emerald-900 ring-emerald-200' : 'bg-slate-100 text-slate-700 ring-slate-200'"
              >
                {{ row.is_active ? t('accommodation.status.active') : t('accommodation.status.inactive') }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex flex-wrap gap-2">
                <RouterLink
                  :to="`/app/accommodation/facilities/${row.id}`"
                  class="text-sm font-semibold text-brand-primary hover:underline"
                >
                  {{ t('accommodation.facilities.open') }}
                </RouterLink>
                <PermissionGuard permission="accommodation.manage_facilities">
                  <button
                    type="button"
                    class="text-sm text-brand-text-secondary hover:text-brand-text disabled:opacity-50"
                    :disabled="!canWrite"
                    @click="toggleActive(row.id, row.is_active)"
                  >
                    {{ row.is_active ? t('accommodation.facilities.deactivate') : t('accommodation.facilities.activate') }}
                  </button>
                </PermissionGuard>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="meta" class="flex items-center justify-between border-t border-brand-border px-4 py-3 text-sm">
        <span class="text-brand-text-muted">
          {{ t('accommodation.pagination.page', { page: meta.current_page, last: meta.last_page }) }}
        </span>
        <div class="flex gap-2">
          <button
            type="button"
            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-brand-border disabled:opacity-40"
            :disabled="page <= 1"
            @click="page -= 1"
          >
            <ChevronRight class="h-4 w-4" />
          </button>
          <button
            type="button"
            class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-brand-border disabled:opacity-40"
            :disabled="page >= meta.last_page"
            @click="page += 1"
          >
            <ChevronLeft class="h-4 w-4" />
          </button>
        </div>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="drawerOpen" class="fixed inset-0 z-50" role="presentation">
        <div class="absolute inset-0 bg-black/30" @click="drawerOpen = false" />
        <aside class="app-drawer-panel absolute inset-y-0 start-0 flex w-full max-w-[480px] flex-col bg-brand-surface shadow-xl">
          <header class="border-b border-brand-border px-5 py-4">
            <h3 class="text-lg font-bold">{{ t('accommodation.facilities.createTitle') }}</h3>
            <p class="mt-1 text-sm text-brand-text-secondary">{{ t('accommodation.facilities.createSubtitle') }}</p>
          </header>
          <form class="flex flex-1 flex-col" @submit.prevent="submitCreate">
            <div class="flex-1 space-y-4 overflow-y-auto px-5 py-5">
              <label class="block text-sm">
                <span class="mb-1 block text-brand-text-secondary">{{ t('accommodation.fields.kind') }}</span>
                <AppSelect
                  :model-value="form.kind"
                  :options="[
                    { value: 'hotel', label: t('accommodation.kind.hotel') },
                    { value: 'camp', label: t('accommodation.kind.camp') },
                  ]"
                  @update:model-value="form.kind = $event as FacilityKind"
                />
              </label>
              <label class="block text-sm">
                <span class="mb-1 block text-brand-text-secondary">{{ t('accommodation.fields.code') }}</span>
                <input v-model="form.code" class="w-full rounded-lg border border-brand-border px-3 py-2">
              </label>
              <label class="block text-sm">
                <span class="mb-1 block text-brand-text-secondary">{{ t('accommodation.fields.name') }}</span>
                <input v-model="form.name" class="w-full rounded-lg border border-brand-border px-3 py-2">
              </label>
              <p v-if="formError" class="text-sm text-red-700">{{ formError }}</p>
            </div>
            <footer class="flex justify-end gap-2 border-t border-brand-border px-5 py-4">
              <button type="button" class="rounded-lg px-3 py-2 text-sm" @click="drawerOpen = false">
                {{ t('accommodation.cancel') }}
              </button>
              <button
                type="submit"
                class="rounded-lg bg-brand-primary px-3 py-2 text-sm font-semibold text-white disabled:opacity-50"
                :disabled="createMutation.isPending.value"
              >
                {{ t('accommodation.save') }}
              </button>
            </footer>
          </form>
        </aside>
      </div>
    </Teleport>
  </div>
</template>
