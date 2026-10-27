<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { RouterLink } from 'vue-router'
import { Building2, Tent, ExternalLink, ChevronLeft, ChevronRight, Plus, Search } from 'lucide-vue-next'

import AppMobileFilters from '@/shared/components/AppMobileFilters.vue'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'
import { useDebouncedRef } from '@/shared/composables/useDebouncedRef'

import AccommodationSeasonBar from '../components/AccommodationSeasonBar.vue'
import AccommodationSubnav from '../components/AccommodationSubnav.vue'
import { useAccommodationDialog } from '../composables/useAccommodationDialog'
import AccommodationFormDrawer from '../components/AccommodationFormDrawer.vue'
import AccommodationWorkspace from '../components/AccommodationWorkspace.vue'
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
useAccommodationDialog(drawerOpen, () => { if (!createMutation.isPending.value) drawerOpen.value = false })
</script>

<template>
  <div class="accommodation-page space-y-5">
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
    <AccommodationWorkspace />

    <div v-if="canRead && !isLoading && !isError" class="accommodation-stats">
      <div class="accommodation-stat"><span>{{ t('accommodation.experience.allPlaces') }}</span><strong>{{ data?.data.length ?? 0 }}</strong></div>
      <div class="accommodation-stat"><span>{{ t('accommodation.experience.hotels') }}</span><strong>{{ data?.data.filter(row => row.kind === 'hotel').length ?? 0 }}</strong></div>
      <div class="accommodation-stat"><span>{{ t('accommodation.experience.camps') }}</span><strong>{{ data?.data.filter(row => row.kind === 'camp').length ?? 0 }}</strong></div>
    </div>

    <AppMobileFilters
      v-model:search="filters.search"
      :search-placeholder="t('accommodation.experience.searchPlaces')"
      :active-count="Number(Boolean(filters.kind)) + Number(Boolean(filters.active))"
      @reset="filters.search = ''; filters.kind = ''; filters.active = ''"
    >
      <template #desktop>
      <div class="grid gap-3 rounded-2xl border border-brand-border bg-brand-surface p-4 shadow-[0_1px_2px_rgba(23,32,29,0.03)] sm:grid-cols-3">
        <label class="block">
          <span class="mb-1 block text-xs text-brand-text-secondary">{{ t('accommodation.filters.search') }}</span>
          <div class="relative">
            <Search class="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-text-muted" />
            <input
              v-model="filters.search"
              type="search"
              class="w-full rounded-lg border border-brand-border bg-brand-surface py-2 pe-3 ps-9 text-sm"
              :placeholder="t('accommodation.experience.searchPlaces')"
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
      </template>
      <template #filters>
        <div class="space-y-3">
          <label class="block text-sm"><span class="mb-1 block">{{ t('accommodation.filters.kind') }}</span><AppSelect v-model="filters.kind" :options="kindOptions" /></label>
          <label class="block text-sm"><span class="mb-1 block">{{ t('accommodation.filters.status') }}</span><AppSelect v-model="filters.active" :options="activeOptions" /></label>
          <p class="text-xs text-brand-text-muted">{{ t('accommodation.overview.clientFilterNote') }}</p>
        </div>
      </template>
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
    <div v-else>
      <div class="accommodation-table-surface overflow-x-auto rounded-2xl border border-brand-border bg-brand-surface shadow-[0_1px_2px_rgba(23,32,29,0.03)]">
        <table class="min-w-full border-separate border-spacing-0 text-sm">
          <thead><tr><th>{{ t('accommodation.fields.code') }}</th><th>{{ t('accommodation.fields.name') }}</th><th>{{ t('accommodation.fields.kind') }}</th><th>{{ t('accommodation.fields.status') }}</th><th>{{ t('accommodation.fields.actions') }}</th></tr></thead>
          <tbody>
            <tr v-for="row in facilities" :key="row.id" class="group">
              <td><span class="inline-flex rounded-lg border border-brand-border bg-brand-bg px-2.5 py-1 font-mono text-xs font-bold tracking-wide" dir="ltr">{{ row.code }}</span></td>
              <td class="font-semibold"><RouterLink :to="'/app/accommodation/facilities/' + row.id" class="text-brand-text hover:text-brand-primary">{{ row.name }}</RouterLink></td>
              <td><span class="inline-flex items-center gap-2"><component :is="row.kind === 'camp' ? Tent : Building2" class="h-4 w-4 text-brand-text-muted" />{{ t('accommodation.kind.' + row.kind) }}</span></td>
              <td><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="row.is_active ? 'bg-emerald-50 text-emerald-800' : 'bg-brand-bg text-brand-text-secondary'">{{ t(row.is_active ? 'accommodation.status.active' : 'accommodation.status.inactive') }}</span></td>
              <td><div class="flex items-center justify-center gap-2">
                <RouterLink :to="'/app/accommodation/facilities/' + row.id" class="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg border border-brand-border bg-brand-surface px-3 text-xs font-semibold text-brand-primary hover:border-brand-primary/30"><ExternalLink class="h-3.5 w-3.5" />{{ t('accommodation.facilities.open') }}</RouterLink>
                <PermissionGuard permission="accommodation.manage_facilities"><button type="button" class="text-xs font-semibold text-brand-text-secondary" :disabled="!canWrite || updateMutation.isPending.value" @click="toggleActive(row.id, row.is_active)">{{ t(row.is_active ? 'accommodation.facilities.deactivate' : 'accommodation.facilities.activate') }}</button></PermissionGuard>
              </div></td>
            </tr>
          </tbody>
        </table>
      </div>
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

    <AccommodationFormDrawer :open="Boolean(drawerOpen)" :submitting="createMutation.isPending.value" :disabled="!canWrite" @close="drawerOpen = false" @submit="submitCreate">
      <template #title>{{ t('accommodation.facilities.createTitle') }}</template>
      <template #subtitle>{{ t('accommodation.facilities.createSubtitle') }}</template>
      <label class="block text-sm">
        <span class="mb-1 block text-brand-text-secondary">{{ t('accommodation.fields.kind') }}</span>
        <AppSelect
          searchable
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
        <input readonly :value="t('accommodation.autoCode')" class="w-full rounded-lg border border-brand-border px-3 py-2">
      </label>
      <label class="block text-sm">
        <span class="mb-1 block text-brand-text-secondary">{{ t('accommodation.fields.name') }}</span>
        <input v-model="form.name" class="w-full rounded-lg border border-brand-border px-3 py-2">
      </label>
      <p v-if="formError" class="text-sm text-red-700">{{ formError }}</p>
      <template #submit-label>{{ t('accommodation.save') }}</template>
    </AccommodationFormDrawer>
  </div>
</template>
