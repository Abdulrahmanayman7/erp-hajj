<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { ChevronLeft, ChevronRight, Plus } from 'lucide-vue-next'

import { useAccommodationSeasonStore } from '@/modules/accommodation/stores/seasonContextStore'
import { listEmployees } from '@/modules/employees/api/employeesApi'
import type { Employee } from '@/modules/employees/types/employees'
import SeasonWorkspaceBar from '@/modules/season-pilgrims/components/SeasonWorkspaceBar.vue'
import { useSeasonsQuery } from '@/modules/season-pilgrims/queries/useSeasonsQuery'
import { ApiError } from '@/shared/api/http'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { employeeSelectOption } from '@/shared/lookups/selectOptions'
import { useToast } from '@/shared/composables/useToast'

import {
  useCreateSeasonProjectMutation,
  useUpdateSeasonProjectMutation,
} from '../mutations/useSeasonProjectsMutations'
import { useSeasonProjectsQuery } from '../queries/useSeasonProjectsQuery'
import type {
  ListSeasonProjectsParams,
  SeasonProject,
  SeasonProjectStatus,
} from '../types/seasonProjects'
import { SEASON_PROJECT_STATUSES } from '../types/seasonProjects'
import {
  emptyProjectForm,
  formatMoneyDisplay,
  mapSeasonProjectErrorCode,
  resolveListState,
  validateProjectForm,
} from '../validation/seasonProjectsValidation'

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
  status: '' as SeasonProjectStatus | '',
  owner_employee_id: '' as number | '',
  q: '',
  page: 1,
  per_page: 15,
})

const params = computed<ListSeasonProjectsParams>(() => ({
  status: filters.status,
  owner_employee_id: filters.owner_employee_id,
  q: filters.q.trim() || undefined,
  page: filters.page,
  per_page: filters.per_page,
  seasonId: selectedSeasonId.value,
}))

const { data, isLoading, isError, refetch } = useSeasonProjectsQuery(
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
  () => [selectedSeasonId.value, filters.status, filters.owner_employee_id, filters.q],
  () => {
    filters.page = 1
  },
)

const statusOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('seasonProjects.filters.allStatus') },
  ...SEASON_PROJECT_STATUSES.map((s) => ({
    value: s,
    label: t(`seasonProjects.status.${s}`),
  })),
])

const fetchActiveEmployees = (params: { search?: string; page: number; per_page: number }) =>
  listEmployees({ ...params, status: 'active' })

const drawerOpen = ref(false)
const editing = ref<SeasonProject | null>(null)
const form = reactive(emptyProjectForm())
const formError = ref('')
const fieldErrors = reactive<Record<string, string>>({})
const createMutation = useCreateSeasonProjectMutation()
const updateMutation = useUpdateSeasonProjectMutation()
const submitting = computed(
  () => createMutation.isPending.value || updateMutation.isPending.value,
)

function openCreate(): void {
  editing.value = null
  Object.assign(form, emptyProjectForm())
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  drawerOpen.value = true
}

function openEdit(row: SeasonProject): void {
  editing.value = row
  Object.assign(form, {
    title: row.title,
    description: row.description ?? '',
    owner_employee_id: row.owner_employee_id ?? '',
  })
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  drawerOpen.value = true
}

function apiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) return t('seasonProjects.errors.generic')
  const mapped = mapSeasonProjectErrorCode(error.code)
  if (mapped !== 'generic') return t(`seasonProjects.errors.${mapped}`)
  return error.message || t('seasonProjects.errors.generic')
}

async function submitForm(): Promise<void> {
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  formError.value = ''
  const local = validateProjectForm(form)
  if (Object.keys(local).length) {
    Object.assign(
      fieldErrors,
      Object.fromEntries(Object.entries(local).map(([k, v]) => [k, t(`seasonProjects.errors.${v}`)])),
    )
    return
  }
  const payload = {
    title: form.title.trim(),
    description: form.description.trim() || null,
    owner_employee_id: form.owner_employee_id === '' ? null : Number(form.owner_employee_id),
  }
  try {
    if (editing.value) {
      await updateMutation.mutateAsync({
        id: editing.value.id,
        payload,
        seasonId: selectedSeasonId.value,
      })
      toast.success(t('seasonProjects.toasts.updated'))
    } else {
      const created = await createMutation.mutateAsync({
        payload,
        seasonId: selectedSeasonId.value,
      })
      toast.success(t('seasonProjects.toasts.created'))
      drawerOpen.value = false
      await router.push(`/app/season-projects/${created.id}`)
      return
    }
    drawerOpen.value = false
  } catch (error) {
    if (error instanceof ApiError && error.errors) {
      Object.entries(error.errors).forEach(([key, messages]) => {
        if (Array.isArray(messages) && messages[0]) fieldErrors[key] = String(messages[0])
      })
    }
    formError.value = apiMessage(error)
  }
}

function openDetail(row: SeasonProject): void {
  void router.push(`/app/season-projects/${row.id}`)
}

function delayLabel(row: SeasonProject): string {
  if (!row.delay_summary) return t('seasonProjects.delay.none')
  return row.delay_summary
}
</script>

<template>
  <div class="space-y-4">
    <AppPageHeader
      :title="t('seasonProjects.title')"
      :subtitle="t('seasonProjects.subtitle')"
      :meta="meta ? t('seasonProjects.total', { count: meta.total }) : undefined"
    >
      <template #actions>
        <PermissionGuard permission="season_projects.create">
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-lg bg-brand-primary px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
            :disabled="!seasonWritable || selectedSeasonId == null"
            @click="openCreate"
          >
            <Plus class="h-4 w-4" />
            {{ t('seasonProjects.add') }}
          </button>
        </PermissionGuard>
      </template>
    </AppPageHeader>

    <SeasonWorkspaceBar />

    <section class="rounded-xl border border-brand-border bg-brand-surface p-4">
      <div class="grid gap-3 md:grid-cols-3">
        <label class="block text-sm">
          <span class="mb-1 block text-brand-muted">{{ t('seasonProjects.columns.status') }}</span>
          <AppSelect v-model="filters.status" :options="statusOptions" />
        </label>
        <label class="block text-sm">
          <span class="mb-1 block text-brand-muted">{{ t('seasonProjects.columns.owner') }}</span>
          <AppRemoteSelect
            v-model="filters.owner_employee_id"
            :query-key="['season-projects-filter-owners']"
            :fetcher="fetchActiveEmployees"
            :map-option="(row: Employee) => employeeSelectOption(row)"
            :placeholder="t('seasonProjects.filters.ownerPlaceholder')"
            clearable
          />
        </label>
        <label class="block text-sm">
          <span class="mb-1 block text-brand-muted">{{ t('common.all') }}</span>
          <input
            v-model="filters.q"
            type="search"
            class="w-full rounded-lg border border-brand-border bg-white px-3 py-2"
            :placeholder="t('seasonProjects.searchPlaceholder')"
          >
        </label>
      </div>
    </section>

    <section
      v-if="listState === 'need_season'"
      class="rounded-xl border border-dashed border-brand-border bg-brand-surface px-4 py-10 text-center text-brand-muted"
    >
      {{ t('seasonProjects.needSeason') }}
    </section>
    <section
      v-else-if="listState === 'loading'"
      class="rounded-xl border border-brand-border bg-brand-surface px-4 py-10 text-center"
    >
      {{ t('seasonProjects.loading') }}
    </section>
    <section
      v-else-if="listState === 'error'"
      class="rounded-xl border border-red-200 bg-red-50 px-4 py-10 text-center"
    >
      <p class="mb-3">{{ t('seasonProjects.errors.generic') }}</p>
      <button type="button" class="rounded-lg border px-3 py-1.5 text-sm" @click="() => refetch()">
        {{ t('seasonProjects.retry') }}
      </button>
    </section>
    <section
      v-else-if="listState === 'empty'"
      class="rounded-xl border border-dashed border-brand-border bg-brand-surface px-4 py-10 text-center text-brand-muted"
    >
      {{ t('seasonProjects.empty') }}
    </section>
    <section v-else class="overflow-x-auto rounded-xl border border-brand-border bg-brand-surface">
      <table class="min-w-full text-sm">
        <thead class="bg-brand-primary-soft/40 text-start">
          <tr>
            <th class="px-3 py-2 font-semibold">{{ t('seasonProjects.columns.number') }}</th>
            <th class="px-3 py-2 font-semibold">{{ t('seasonProjects.columns.title') }}</th>
            <th class="px-3 py-2 font-semibold">{{ t('seasonProjects.columns.status') }}</th>
            <th class="px-3 py-2 font-semibold">{{ t('seasonProjects.columns.owner') }}</th>
            <th class="px-3 py-2 font-semibold">{{ t('seasonProjects.columns.progress') }}</th>
            <th class="px-3 py-2 font-semibold">{{ t('seasonProjects.columns.delay') }}</th>
            <th class="px-3 py-2 font-semibold">{{ t('seasonProjects.columns.budget') }}</th>
            <th class="px-3 py-2 font-semibold" />
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in rows"
            :key="row.id"
            class="border-t border-brand-border/70 hover:bg-brand-primary-soft/20"
          >
            <td class="px-3 py-2 font-mono text-xs">{{ row.number }}</td>
            <td class="px-3 py-2">{{ row.title }}</td>
            <td class="px-3 py-2">
              <span class="rounded-full bg-brand-primary-soft px-2 py-0.5 text-xs font-semibold">
                {{ t(`seasonProjects.status.${row.status}`) }}
              </span>
            </td>
            <td class="px-3 py-2">
              {{ row.owner_employee?.full_name ?? '—' }}
            </td>
            <td class="px-3 py-2">{{ row.progress_percent }}%</td>
            <td class="px-3 py-2">{{ delayLabel(row) }}</td>
            <td class="px-3 py-2">
              <span v-if="row.budget_amount != null">
                {{ formatMoneyDisplay(row.budget_amount) }} {{ row.currency_code }}
              </span>
              <span v-else>—</span>
            </td>
            <td class="px-3 py-2">
              <div class="flex flex-wrap gap-2">
                <PermissionGuard permission="season_projects.view">
                  <button type="button" class="text-brand-primary underline" @click="openDetail(row)">
                    {{ t('seasonProjects.open') }}
                  </button>
                </PermissionGuard>
                <PermissionGuard permission="season_projects.update">
                  <button
                    v-if="row.status === 'draft' || row.status === 'active'"
                    type="button"
                    class="text-brand-muted underline"
                    @click="openEdit(row)"
                  >
                    {{ t('seasonProjects.edit') }}
                  </button>
                </PermissionGuard>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      <div
        v-if="meta && meta.last_page > 1"
        class="flex items-center justify-between border-t border-brand-border px-3 py-2"
      >
        <button
          type="button"
          class="inline-flex items-center gap-1 rounded border px-2 py-1 disabled:opacity-40"
          :disabled="filters.page <= 1"
          @click="filters.page -= 1"
        >
          <ChevronRight class="h-4 w-4" />
          {{ t('seasonProjects.prev') }}
        </button>
        <span class="text-xs text-brand-muted">
          {{ t('common.pageOf', { page: meta.current_page, total: meta.last_page }) }}
        </span>
        <button
          type="button"
          class="inline-flex items-center gap-1 rounded border px-2 py-1 disabled:opacity-40"
          :disabled="filters.page >= meta.last_page"
          @click="filters.page += 1"
        >
          {{ t('seasonProjects.next') }}
          <ChevronLeft class="h-4 w-4" />
        </button>
      </div>
    </section>

    <div
      v-if="drawerOpen"
      class="fixed inset-0 z-40 flex justify-end bg-black/30"
      role="dialog"
      aria-modal="true"
      @click.self="drawerOpen = false"
    >
      <div class="flex h-full w-full max-w-md flex-col bg-white shadow-xl">
        <header class="border-b border-brand-border px-4 py-3">
          <h2 class="text-lg font-semibold">
            {{ editing ? t('seasonProjects.editTitle') : t('seasonProjects.createTitle') }}
          </h2>
          <p class="text-sm text-brand-muted">
            {{ editing ? t('seasonProjects.editSubtitle') : t('seasonProjects.createSubtitle') }}
          </p>
        </header>
        <form class="flex flex-1 flex-col gap-3 overflow-y-auto p-4" @submit.prevent="submitForm">
          <label class="block text-sm">
            <span class="mb-1 block">{{ t('seasonProjects.fields.title') }}</span>
            <input
              v-model="form.title"
              required
              class="w-full rounded-lg border border-brand-border px-3 py-2"
            >
            <span v-if="fieldErrors.title" class="mt-1 block text-xs text-red-600">{{ fieldErrors.title }}</span>
          </label>
          <label class="block text-sm">
            <span class="mb-1 block">{{ t('seasonProjects.fields.description') }}</span>
            <textarea
              v-model="form.description"
              rows="3"
              class="w-full rounded-lg border border-brand-border px-3 py-2"
            />
          </label>
          <label class="block text-sm">
            <span class="mb-1 block">{{ t('seasonProjects.fields.owner') }}</span>
            <AppRemoteSelect
              v-model="form.owner_employee_id"
              :query-key="['season-projects-form-owners']"
              :fetcher="fetchActiveEmployees"
              :map-option="(row: Employee) => employeeSelectOption(row)"
              :selected-option="
                editing?.owner_employee ? employeeSelectOption(editing.owner_employee) : null
              "
              clearable
            />
            <span class="mt-1 block text-xs text-brand-muted">{{ t('seasonProjects.fields.ownerHint') }}</span>
            <span v-if="fieldErrors.owner_employee_id" class="mt-1 block text-xs text-red-600">
              {{ fieldErrors.owner_employee_id }}
            </span>
          </label>
          <p v-if="formError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ formError }}</p>
          <div class="mt-auto flex gap-2 border-t border-brand-border pt-3">
            <button
              type="button"
              class="rounded-lg border px-3 py-2 text-sm"
              @click="drawerOpen = false"
            >
              {{ t('seasonProjects.cancel') }}
            </button>
            <button
              type="submit"
              class="rounded-lg bg-brand-primary px-3 py-2 text-sm font-semibold text-white disabled:opacity-50"
              :disabled="submitting"
            >
              {{ submitting ? t('seasonProjects.saving') : (editing ? t('seasonProjects.editCta') : t('seasonProjects.createCta')) }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
