<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { RouterLink } from 'vue-router'
import { Building2, Tent, ArrowUpLeft, ChevronLeft, ChevronRight, Plus, Search } from 'lucide-vue-next'

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
      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <article v-for="row in facilities" :key="row.id" class="group flex flex-col rounded-2xl border border-brand-border bg-brand-surface p-5 transition hover:border-brand-primary/30 hover:shadow-md">
          <div class="mb-6 flex items-start justify-between gap-3">
            <div class="rounded-2xl p-3" :class="row.kind === 'camp' ? 'bg-brand-gold-soft text-amber-800' : 'bg-brand-primary-soft text-brand-primary'">
              <component :is="row.kind === 'camp' ? Tent : Building2" class="h-7 w-7" :stroke-width="1.5" />
            </div>
            <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="row.is_active ? 'bg-emerald-50 text-emerald-800' : 'bg-brand-bg text-brand-text-secondary'">{{ t(row.is_active ? 'accommodation.status.active' : 'accommodation.status.inactive') }}</span>
          </div>
          <p class="mb-1 text-xs text-brand-text-muted">{{ t('accommodation.kind.' + row.kind) }} · {{ row.code }}</p>
          <RouterLink :to="'/app/accommodation/facilities/' + row.id" class="text-lg font-bold text-brand-text hover:text-brand-primary">{{ row.name }}</RouterLink>
          <div class="mt-6 flex items-center justify-between gap-3 border-t border-brand-border pt-4">
            <RouterLink :to="'/app/accommodation/facilities/' + row.id" class="inline-flex items-center gap-2 text-sm font-bold text-brand-primary">{{ t('accommodation.facilities.open') }}<ArrowUpLeft class="h-4 w-4" /></RouterLink>
            <PermissionGuard permission="accommodation.manage_facilities">
              <button type="button" class="text-xs text-brand-text-secondary hover:underline disabled:opacity-50" :disabled="!canWrite || updateMutation.isPending.value" @click="toggleActive(row.id, row.is_active)">{{ t(row.is_active ? 'accommodation.facilities.deactivate' : 'accommodation.facilities.activate') }}</button>
            </PermissionGuard>
          </div>
        </article>
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

    <Teleport to="body">
      <div v-if="drawerOpen" class="fixed inset-0 z-50" role="presentation">
        <div class="absolute inset-0 bg-black/30" @click="drawerOpen = false" />
        <aside role="dialog" aria-modal="true" :aria-label="t('accommodation.experience.eyebrow')" class="accommodation-drawer app-drawer-panel absolute inset-y-0 start-0 flex w-full max-w-[480px] flex-col bg-brand-surface shadow-xl">
          <header class="relative pe-14 border-b border-brand-border px-5 py-4">
            <h3 class="text-lg font-bold">{{ t('accommodation.facilities.createTitle') }}</h3>
            <p class="mt-1 text-sm text-brand-text-secondary">{{ t('accommodation.facilities.createSubtitle') }}</p>
          <button type="button" class="absolute end-3 top-3 flex h-9 w-9 items-center justify-center rounded-xl text-2xl text-brand-text-secondary hover:bg-brand-primary-soft" :aria-label="t('accommodation.cancel')" @click="drawerOpen = false"><span aria-hidden="true">×</span></button>
          </header>
          <form class="flex flex-1 flex-col" @submit.prevent="submitCreate">
            <div class="flex-1 space-y-4 overflow-y-auto px-5 py-5">
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
