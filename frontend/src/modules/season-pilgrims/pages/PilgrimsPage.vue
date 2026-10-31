<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { ChevronLeft, ChevronRight, Pencil, Plus, Search, UserPlus, UserRound } from 'lucide-vue-next'
import { storeToRefs } from 'pinia'

import { useAccommodationSeasonStore } from '@/modules/accommodation/stores/seasonContextStore'
import { ApiError } from '@/shared/api/http'
import AppMobileFilters from '@/shared/components/AppMobileFilters.vue'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useDebouncedRef } from '@/shared/composables/useDebouncedRef'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'

import EnrollPilgrimDrawer from '../components/EnrollPilgrimDrawer.vue'
import PilgrimFormDrawer from '../components/PilgrimFormDrawer.vue'
import {
  useCreatePilgrimMutation,
  useUpdatePilgrimMutation,
} from '../mutations/usePilgrimMutations'
import { usePilgrimsQuery, useSeasonPilgrimsQuery } from '../queries/usePilgrimsQuery'
import { useSeasonsQuery } from '../queries/useSeasonsQuery'
import type { Pilgrim, PilgrimFormState } from '../types/pilgrims'
import type { SeasonPilgrim } from '../types/seasonPilgrims'
import {
  emptyPilgrimForm,
  resolvePilgrimsListState,
  validatePilgrimForm,
} from '../validation/pilgrimValidation'

const { t } = useI18n()
const { can } = usePermissions()
const toast = useToast()
const router = useRouter()
const seasonStore = useAccommodationSeasonStore()
const { selectedSeasonId } = storeToRefs(seasonStore)

const filters = reactive({
  search: '',
  season_id: '' as number | '',
  page: 1,
  per_page: 15,
})

const committedSearch = useDebouncedRef(() => filters.search)
const seasonMode = computed(() => filters.season_id !== '' && can('season_pilgrims.view'))

const pilgrimParams = computed(() => ({
  search: committedSearch.value || undefined,
  page: filters.page,
  per_page: filters.per_page,
}))

const seasonPilgrimParams = computed(() => ({
  search: committedSearch.value || undefined,
  page: filters.page,
  per_page: filters.per_page,
  seasonId: filters.season_id === '' ? null : Number(filters.season_id),
}))

const pilgrimsQuery = usePilgrimsQuery(pilgrimParams)
const seasonPilgrimsQuery = useSeasonPilgrimsQuery(seasonPilgrimParams, seasonMode)
const seasonsQuery = useSeasonsQuery({ per_page: 100 })

const createMutation = useCreatePilgrimMutation()
const updateMutation = useUpdatePilgrimMutation()

const activeQuery = computed(() => (seasonMode.value ? seasonPilgrimsQuery : pilgrimsQuery))
const rows = computed(() => activeQuery.value.data.value?.data ?? [])
const meta = computed(() => activeQuery.value.data.value?.meta)
const listState = computed(() =>
  resolvePilgrimsListState({
    isLoading: activeQuery.value.isLoading.value,
    isError: activeQuery.value.isError.value,
    count: rows.value.length,
  }),
)

const seasonOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('pilgrims.filters.allSeasons') },
  ...(seasonsQuery.data.value?.data ?? []).map((season) => ({
    value: season.id,
    label: `${season.name} (${season.code})`,
    hint: t(`seasons.status.${season.status}`),
  })),
])

const drawerOpen = ref(false)
const enrollOpen = ref(false)
const enrollPreset = ref<Pilgrim | null>(null)
const editing = ref<Pilgrim | null>(null)
const formError = ref('')
const fieldErrors = reactive<Record<string, string>>({})
const form = reactive<PilgrimFormState>(emptyPilgrimForm())
const isFormSubmitting = computed(
  () => createMutation.isPending.value || updateMutation.isPending.value,
)

const enrollSeasonId = computed(() => {
  if (filters.season_id !== '') return Number(filters.season_id)
  return selectedSeasonId.value
})
const enrollSeason = computed(
  () =>
    (seasonsQuery.data.value?.data ?? []).find((season) => season.id === enrollSeasonId.value) ??
    null,
)
const enrollWritable = computed(() => enrollSeason.value != null && enrollSeason.value.status !== 'closed')

function openEnroll(pilgrim?: Pilgrim | null): void {
  if (filters.season_id !== '' && selectedSeasonId.value !== Number(filters.season_id)) {
    seasonStore.setSelectedSeasonId(Number(filters.season_id))
  }
  enrollPreset.value = pilgrim ?? null
  enrollOpen.value = true
}

watch(
  () => [committedSearch.value, filters.season_id],
  () => {
    filters.page = 1
  },
)

function resetFilters(): void {
  filters.season_id = ''
}

function genderLabel(value: string | null | undefined): string {
  if (value === 'male' || value === 'female') return t(`pilgrims.gender.${value}`)
  return t('pilgrims.gender.none')
}

function asPilgrim(row: Pilgrim | SeasonPilgrim): Pilgrim | null {
  if ('full_name' in row && 'gender' in row) return row as Pilgrim
  const sp = row as SeasonPilgrim
  if (!sp.pilgrim) return null
  return {
    id: sp.pilgrim.id,
    full_name: sp.pilgrim.full_name,
    gender: null,
    nationality: null,
    phone: null,
    notes: null,
    created_at: null,
    updated_at: null,
  }
}

function rowName(row: Pilgrim | SeasonPilgrim): string {
  if ('full_name' in row && typeof (row as Pilgrim).full_name === 'string' && !('reference_number' in row)) {
    return (row as Pilgrim).full_name
  }
  return (row as SeasonPilgrim).pilgrim?.full_name ?? '—'
}

function rowPilgrimId(row: Pilgrim | SeasonPilgrim): number | null {
  if ('reference_number' in row) return (row as SeasonPilgrim).pilgrim?.id ?? null
  return (row as Pilgrim).id
}

function openCreate(): void {
  editing.value = null
  Object.assign(form, emptyPilgrimForm())
  formError.value = ''
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key])
  drawerOpen.value = true
}

function openEdit(pilgrim: Pilgrim): void {
  editing.value = pilgrim
  Object.assign(form, {
    full_name: pilgrim.full_name,
    gender: pilgrim.gender ?? '',
    nationality: pilgrim.nationality ?? '',
    phone: pilgrim.phone ?? '',
    notes: pilgrim.notes ?? '',
  })
  formError.value = ''
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key])
  drawerOpen.value = true
}

async function openEditFromRow(row: Pilgrim | SeasonPilgrim): Promise<void> {
  const pilgrim = asPilgrim(row)
  if (!pilgrim) return
  if ('reference_number' in row) {
    await router.push(`/app/pilgrims/${pilgrim.id}`)
    return
  }
  openEdit(pilgrim)
}

function goProfile(row: Pilgrim | SeasonPilgrim): void {
  const id = rowPilgrimId(row)
  if (id == null) return
  void router.push(`/app/pilgrims/${id}`)
}

function applyLaravelErrors(error: ApiError): void {
  if (!error.errors) return
  Object.entries(error.errors).forEach(([key, messages]) => {
    fieldErrors[key] = 'format'
    if (Array.isArray(messages) && messages[0]) {
      fieldErrors[key] = 'format'
    }
  })
  // Prefer server messages on formError when present
  const first = Object.values(error.errors)[0]?.[0]
  if (first) formError.value = first
}

async function submitForm(): Promise<void> {
  Object.keys(fieldErrors).forEach((key) => delete fieldErrors[key])
  formError.value = ''
  const local = validatePilgrimForm(form)
  Object.assign(fieldErrors, local)
  if (Object.keys(local).length > 0) return

  const payload = {
    full_name: form.full_name.trim(),
    gender: form.gender === '' ? null : form.gender,
    nationality: form.nationality.trim() ? form.nationality.trim().toUpperCase() : null,
    phone: form.phone.trim() || null,
    notes: form.notes.trim() || null,
  }

  try {
    if (editing.value) {
      await updateMutation.mutateAsync({ id: editing.value.id, payload })
      toast.success(t('pilgrims.toasts.updated'))
    } else {
      await createMutation.mutateAsync(payload)
      toast.success(t('pilgrims.toasts.created'))
    }
    drawerOpen.value = false
  } catch (error) {
    if (error instanceof ApiError) {
      if (error.status === 422) {
        applyLaravelErrors(error)
        return
      }
      formError.value = error.message || t('pilgrims.errors.generic')
      return
    }
    formError.value = t('pilgrims.errors.generic')
  }
}

function refetch(): void {
  void activeQuery.value.refetch()
}
</script>

<template>
  <div class="space-y-6">
    <AppPageHeader
      :title="t('pilgrims.title')"
      :subtitle="t('pilgrims.subtitle')"
      :meta="meta ? t('pilgrims.total', { count: meta.total }) : undefined"
    >
      <template #actions>
        <PermissionGuard permission="season_pilgrims.register">
          <button
            type="button"
            class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-4 text-sm font-semibold text-brand-text sm:w-auto"
            @click="openEnroll(null)"
          >
            <UserPlus class="h-4 w-4" />
            <span>{{ t('pilgrims.enroll.cta') }}</span>
          </button>
        </PermissionGuard>
        <PermissionGuard permission="pilgrims.create">
          <button
            type="button"
            class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white transition hover:bg-brand-primary sm:w-auto"
            @click="openCreate"
          >
            <Plus class="h-4 w-4" :stroke-width="2.25" />
            <span>{{ t('pilgrims.add') }}</span>
          </button>
        </PermissionGuard>
      </template>
    </AppPageHeader>

    <AppMobileFilters
      v-model:search="filters.search"
      :search-placeholder="t('pilgrims.searchPlaceholder')"
      :active-count="filters.season_id !== '' ? 1 : 0"
      @reset="resetFilters"
    >
      <template #desktop>
        <div
          class="flex flex-wrap items-center gap-3 rounded-2xl border border-brand-border bg-brand-surface p-4 shadow-[0_1px_2px_rgba(23,32,29,0.03)]"
        >
          <div class="relative min-w-48 flex-1">
            <Search
              class="pointer-events-none absolute inset-s-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-text-muted"
              :stroke-width="1.75"
              aria-hidden="true"
            />
            <input
              v-model="filters.search"
              type="search"
              class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface pe-3 ps-10 text-sm text-brand-text outline-none transition placeholder:text-brand-text-muted focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
              :placeholder="t('pilgrims.searchPlaceholder')"
            />
          </div>
          <AppSelect
            v-model="filters.season_id"
            :options="seasonOptions"
            searchable
            :disabled="!can('season_pilgrims.view') && !can('seasons.view')"
          />
        </div>
        <p
          v-if="filters.season_id !== '' && !can('season_pilgrims.view')"
          class="mt-2 text-xs text-amber-800"
        >
          {{ t('pilgrims.filters.seasonHint') }}
        </p>
      </template>
      <template #filters>
        <AppSelect v-model="filters.season_id" :options="seasonOptions" searchable />
      </template>
    </AppMobileFilters>

    <div
      v-if="listState === 'loading'"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('pilgrims.loading') }}
    </div>
    <div
      v-else-if="listState === 'error'"
      class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center"
    >
      <p class="text-sm text-red-700">{{ t('pilgrims.errors.load') }}</p>
      <button
        type="button"
        class="mt-3 text-sm font-semibold text-brand-primary-dark underline"
        @click="refetch"
      >
        {{ t('pilgrims.retry') }}
      </button>
    </div>
    <div
      v-else-if="listState === 'empty'"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center"
    >
      <p class="text-sm text-brand-text-muted">{{ t('pilgrims.empty') }}</p>
      <PermissionGuard permission="pilgrims.create">
        <button
          type="button"
          class="mt-4 inline-flex h-10 items-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white"
          @click="openCreate"
        >
          <Plus class="h-4 w-4" />
          {{ t('pilgrims.add') }}
        </button>
      </PermissionGuard>
    </div>
    <template v-else>
      <div
        class="hidden overflow-hidden rounded-2xl border border-brand-border bg-brand-surface shadow-[0_1px_2px_rgba(23,32,29,0.03)] lg:block"
      >
        <div class="overflow-x-auto">
          <table class="min-w-full border-separate border-spacing-0 text-sm">
            <thead>
              <tr class="bg-[#F4F6F5]">
                <th
                  class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-start text-xs font-bold tracking-wide text-brand-text-muted"
                >
                  {{ t('pilgrims.columns.fullName') }}
                </th>
                <th
                  v-if="!seasonMode"
                  class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text-muted"
                >
                  {{ t('pilgrims.columns.gender') }}
                </th>
                <th
                  v-if="!seasonMode"
                  class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text-muted"
                >
                  {{ t('pilgrims.columns.nationality') }}
                </th>
                <th
                  v-if="!seasonMode"
                  class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text-muted"
                >
                  {{ t('pilgrims.columns.phone') }}
                </th>
                <th
                  v-if="!seasonMode"
                  class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text-muted"
                >
                  {{ t('pilgrims.columns.documents') }}
                </th>
                <th
                  v-if="seasonMode"
                  class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text-muted"
                >
                  {{ t('pilgrims.columns.reference') }}
                </th>
                <th
                  v-if="seasonMode"
                  class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text-muted"
                >
                  {{ t('pilgrims.columns.seasonStatus') }}
                </th>
                <th
                  v-if="seasonMode"
                  class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text-muted"
                >
                  {{ t('pilgrims.columns.group') }}
                </th>
                <th
                  class="w-40 whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text-muted"
                >
                  {{ t('pilgrims.columns.actions') }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(row, index) in rows"
                :key="('reference_number' in row ? `sp-${row.id}` : `p-${row.id}`)"
                class="group transition-colors duration-150"
                :class="index % 2 === 1 ? 'bg-[#FAFBFA]' : 'bg-brand-surface'"
              >
                <td
                  class="border-b border-brand-border/80 px-5 py-3.5 font-semibold text-brand-text group-hover:bg-[#EEF2F0]"
                >
                  {{ rowName(row) }}
                </td>
                <td
                  v-if="!seasonMode"
                  class="border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text-secondary group-hover:bg-[#EEF2F0]"
                >
                  {{ genderLabel((row as Pilgrim).gender) }}
                </td>
                <td
                  v-if="!seasonMode"
                  class="border-b border-brand-border/80 px-5 py-3.5 text-center font-mono text-xs text-brand-text-secondary group-hover:bg-[#EEF2F0]"
                  dir="ltr"
                >
                  {{ (row as Pilgrim).nationality ?? '—' }}
                </td>
                <td
                  v-if="!seasonMode"
                  class="border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text-secondary group-hover:bg-[#EEF2F0]"
                  dir="ltr"
                >
                  {{ (row as Pilgrim).phone ?? '—' }}
                </td>
                <td
                  v-if="!seasonMode"
                  class="border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text-secondary group-hover:bg-[#EEF2F0]"
                >
                  {{
                    (row as Pilgrim).has_identity_documents
                      ? t('pilgrims.docs.yes')
                      : t('pilgrims.docs.no')
                  }}
                </td>
                <td
                  v-if="seasonMode"
                  class="border-b border-brand-border/80 px-5 py-3.5 text-center font-mono text-xs group-hover:bg-[#EEF2F0]"
                  dir="ltr"
                >
                  {{ (row as SeasonPilgrim).reference_number }}
                </td>
                <td
                  v-if="seasonMode"
                  class="border-b border-brand-border/80 px-5 py-3.5 text-center group-hover:bg-[#EEF2F0]"
                >
                  {{ t(`pilgrims.seasonStatus.${(row as SeasonPilgrim).status}`) }}
                </td>
                <td
                  v-if="seasonMode"
                  class="border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text-secondary group-hover:bg-[#EEF2F0]"
                >
                  {{ (row as SeasonPilgrim).pilgrim_group?.name ?? '—' }}
                </td>
                <td
                  class="border-b border-brand-border/80 px-5 py-3.5 text-center group-hover:bg-[#EEF2F0]"
                >
                  <div class="flex items-center justify-center gap-1">
                    <button
                      type="button"
                      class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-brand-text-secondary hover:bg-brand-bg hover:text-brand-text"
                      :title="t('pilgrims.openProfile')"
                      @click="goProfile(row)"
                    >
                      <UserRound class="h-4 w-4" />
                    </button>
                    <PermissionGuard permission="pilgrims.update">
                      <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-brand-text-secondary hover:bg-brand-bg hover:text-brand-text"
                        :title="t('pilgrims.edit')"
                        @click="openEditFromRow(row)"
                      >
                        <Pencil class="h-4 w-4" />
                      </button>
                    </PermissionGuard>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="space-y-3 lg:hidden">
        <article
          v-for="row in rows"
          :key="('reference_number' in row ? `msp-${row.id}` : `mp-${row.id}`)"
          class="rounded-2xl border border-brand-border bg-brand-surface p-4"
        >
          <h3 class="font-semibold text-brand-text">{{ rowName(row) }}</h3>
          <p v-if="seasonMode" class="mt-1 text-xs text-brand-text-muted" dir="ltr">
            {{ (row as SeasonPilgrim).reference_number }} ·
            {{ t(`pilgrims.seasonStatus.${(row as SeasonPilgrim).status}`) }}
          </p>
          <p v-else class="mt-1 text-xs text-brand-text-muted">
            {{ genderLabel((row as Pilgrim).gender) }} ·
            {{ (row as Pilgrim).nationality ?? '—' }}
          </p>
          <div class="mt-3 flex gap-2">
            <button
              type="button"
              class="inline-flex h-9 flex-1 items-center justify-center rounded-xl border border-brand-border text-sm font-semibold"
              @click="goProfile(row)"
            >
              {{ t('pilgrims.openProfile') }}
            </button>
            <PermissionGuard permission="pilgrims.update">
              <button
                type="button"
                class="inline-flex h-9 flex-1 items-center justify-center rounded-xl bg-brand-primary-dark text-sm font-semibold text-white"
                @click="openEditFromRow(row)"
              >
                {{ t('pilgrims.edit') }}
              </button>
            </PermissionGuard>
          </div>
        </article>
      </div>

      <div
        v-if="meta && meta.last_page > 1"
        class="flex items-center justify-between gap-3 rounded-2xl border border-brand-border bg-brand-surface px-4 py-3"
      >
        <button
          type="button"
          class="inline-flex h-10 items-center gap-1 rounded-xl border border-brand-border px-3 text-sm font-semibold disabled:opacity-40"
          :disabled="filters.page <= 1"
          @click="filters.page -= 1"
        >
          <ChevronRight class="h-4 w-4" />
          {{ t('pilgrims.prev') }}
        </button>
        <span class="text-sm text-brand-text-muted">{{ filters.page }} / {{ meta.last_page }}</span>
        <button
          type="button"
          class="inline-flex h-10 items-center gap-1 rounded-xl border border-brand-border px-3 text-sm font-semibold disabled:opacity-40"
          :disabled="filters.page >= meta.last_page"
          @click="filters.page += 1"
        >
          {{ t('pilgrims.next') }}
          <ChevronLeft class="h-4 w-4" />
        </button>
      </div>
    </template>

    <PilgrimFormDrawer
      :open="drawerOpen"
      :editing="editing"
      :form="form"
      :form-error="formError"
      :field-errors="fieldErrors"
      :submitting="isFormSubmitting"
      @close="drawerOpen = false"
      @update:form="Object.assign(form, $event)"
      @submit="submitForm"
    />

    <EnrollPilgrimDrawer
      :open="enrollOpen"
      :season-id="enrollSeasonId"
      :season-writable="enrollWritable"
      :preset-pilgrim="enrollPreset"
      @close="enrollOpen = false"
      @enrolled="() => refetch()"
    />
  </div>
</template>
