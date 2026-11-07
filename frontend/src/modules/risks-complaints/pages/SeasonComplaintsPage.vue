<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { ChevronLeft, ChevronRight, Plus, Search } from 'lucide-vue-next'

import { useAccommodationSeasonStore } from '@/modules/accommodation/stores/seasonContextStore'
import SeasonWorkspaceBar from '@/modules/season-pilgrims/components/SeasonWorkspaceBar.vue'
import { ApiError } from '@/shared/api/http'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useDebouncedRef } from '@/shared/composables/useDebouncedRef'
import { useToast } from '@/shared/composables/useToast'

import ComplaintFormDrawer from '../components/ComplaintFormDrawer.vue'
import { useCreateComplaintMutation } from '../mutations/useComplaintMutations'
import { useComplaintsQuery } from '../queries/useComplaintsQuery'
import type {
  ComplaintCategory,
  ComplaintFormState,
  ComplaintStatus,
  ListComplaintsParams,
} from '../types/complaints'
import { COMPLAINT_CATEGORIES, COMPLAINT_STATUSES } from '../types/complaints'
import {
  complaintStatusBadgeClass,
  emptyComplaintForm,
  mapComplaintErrorCode,
  resolveComplaintsListState,
  validateComplaintForm,
} from '../validation/complaintValidation'

const { t } = useI18n()
const toast = useToast()
const router = useRouter()
const store = useAccommodationSeasonStore()
const { selectedSeasonId } = storeToRefs(store)

const filters = reactive({
  search: '',
  status: '' as ComplaintStatus | '',
  category_code: '' as ComplaintCategory | '',
  page: 1,
  per_page: 15,
})

const committedSearch = useDebouncedRef(() => filters.search)
const params = computed<ListComplaintsParams>(() => ({
  q: committedSearch.value,
  status: filters.status,
  category_code: filters.category_code,
  page: filters.page,
  per_page: filters.per_page,
  seasonId: selectedSeasonId.value,
}))

const { data, isLoading, isError, refetch } = useComplaintsQuery(
  params,
  () => selectedSeasonId.value != null,
)
const rows = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta)
const listState = computed(() =>
  resolveComplaintsListState({
    hasSeason: selectedSeasonId.value != null,
    isLoading: isLoading.value,
    isError: isError.value,
    count: rows.value.length,
  }),
)

watch(
  () => [selectedSeasonId.value, committedSearch.value, filters.status, filters.category_code],
  () => {
    filters.page = 1
  },
)

const statusOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('complaints.filters.allStatuses') },
  ...COMPLAINT_STATUSES.map((s) => ({ value: s, label: t(`complaints.status.${s}`) })),
])
const categoryOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('complaints.filters.allCategories') },
  ...COMPLAINT_CATEGORIES.map((c) => ({ value: c, label: t(`complaints.category.${c}`) })),
])

const drawerOpen = ref(false)
const form = reactive<ComplaintFormState>(emptyComplaintForm())
const formError = ref('')
const fieldErrors = reactive<Record<string, string>>({})
const createMutation = useCreateComplaintMutation()
const submitting = computed(() => createMutation.isPending.value)

function openCreate(): void {
  Object.assign(form, emptyComplaintForm())
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  drawerOpen.value = true
}

function apiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) return t('complaints.errors.generic')
  const mapped = mapComplaintErrorCode(error.code)
  return mapped !== 'generic'
    ? t(`complaints.errors.${mapped}`)
    : error.message || t('complaints.errors.generic')
}

async function submit(): Promise<void> {
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  const local = validateComplaintForm(form)
  Object.assign(fieldErrors, local)
  if (Object.keys(local).length) return
  try {
    const created = await createMutation.mutateAsync({
      payload: {
        title: form.title.trim(),
        body: form.body.trim(),
        category_code: form.category_code,
        season_pilgrim_id: form.season_pilgrim_id === '' ? null : Number(form.season_pilgrim_id),
        group_id: form.group_id === '' ? null : Number(form.group_id),
        facility_id: form.facility_id === '' ? null : Number(form.facility_id),
        transport_season_vehicle_id:
          form.transport_season_vehicle_id === '' ? null : Number(form.transport_season_vehicle_id),
        project_id: form.project_id === '' ? null : Number(form.project_id),
      },
      seasonId: selectedSeasonId.value,
    })
    toast.success(t('complaints.toasts.created'))
    drawerOpen.value = false
    await router.push(`/app/complaints/${created.id}`)
  } catch (error) {
    formError.value = apiMessage(error)
  }
}

function formatWhen(iso: string | null): string {
  if (!iso) return '—'
  try {
    return new Intl.DateTimeFormat('ar-SA', { dateStyle: 'medium' }).format(new Date(iso))
  } catch {
    return iso
  }
}
</script>

<template>
  <div class="space-y-6" data-testid="season-complaints-page" dir="rtl">
    <AppPageHeader :title="t('complaints.title')" :subtitle="t('complaints.subtitle')">
      <template #actions>
        <PermissionGuard permission="complaints.create">
          <button
            type="button"
            class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white disabled:opacity-50"
            :disabled="selectedSeasonId == null"
            data-testid="complaint-create-btn"
            @click="openCreate"
          >
            <Plus class="h-4 w-4" />
            {{ t('complaints.add') }}
          </button>
        </PermissionGuard>
      </template>
    </AppPageHeader>

    <SeasonWorkspaceBar />

    <div class="flex flex-col gap-3 rounded-2xl border border-brand-border bg-brand-surface p-4 lg:flex-row lg:items-end">
      <label class="relative flex-1">
        <Search class="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-text-muted" />
        <input
          v-model="filters.search"
          type="search"
          class="h-11 w-full rounded-xl border border-brand-border bg-brand-bg pe-3 ps-10 text-sm outline-none focus:border-brand-primary"
          :placeholder="t('complaints.searchPlaceholder')"
          data-testid="complaints-search"
        />
      </label>
      <div class="w-full lg:w-48">
        <AppSelect v-model="filters.status" :options="statusOptions" />
      </div>
      <div class="w-full lg:w-48">
        <AppSelect v-model="filters.category_code" :options="categoryOptions" />
      </div>
    </div>

    <div
      v-if="listState === 'need_season'"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
      data-testid="complaints-need-season"
    >
      {{ t('complaints.needSeason') }}
    </div>
    <div
      v-else-if="listState === 'loading'"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
      data-testid="complaints-loading"
    >
      {{ t('complaints.loading') }}
    </div>
    <div
      v-else-if="listState === 'error'"
      class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center"
      data-testid="complaints-error"
    >
      <p class="text-sm text-red-700">{{ t('complaints.errors.load') }}</p>
      <button type="button" class="mt-3 text-sm font-semibold underline" @click="() => refetch()">
        {{ t('complaints.retry') }}
      </button>
    </div>
    <div
      v-else-if="listState === 'empty'"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
      data-testid="complaints-empty"
    >
      {{ t('complaints.empty') }}
    </div>
    <div v-else class="overflow-hidden rounded-2xl border border-brand-border bg-brand-surface" data-testid="complaints-table">
      <table class="min-w-full text-sm">
        <thead class="bg-[#F4F6F5]">
          <tr>
            <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('complaints.columns.number') }}</th>
            <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('complaints.columns.title') }}</th>
            <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('complaints.columns.category') }}</th>
            <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('complaints.columns.status') }}</th>
            <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('complaints.columns.assignee') }}</th>
            <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('complaints.columns.escalation') }}</th>
            <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('complaints.columns.updated') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in rows"
            :key="row.id"
            class="cursor-pointer border-t border-brand-border/80 hover:bg-brand-bg/60"
            @click="router.push(`/app/complaints/${row.id}`)"
          >
            <td class="px-5 py-3.5 font-mono text-xs" dir="ltr">{{ row.complaint_number }}</td>
            <td class="px-5 py-3.5 font-semibold">{{ row.title }}</td>
            <td class="px-5 py-3.5">{{ t(`complaints.category.${row.category_code}`, row.category_code) }}</td>
            <td class="px-5 py-3.5">
              <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="complaintStatusBadgeClass(row.status)">
                {{ t(`complaints.status.${row.status}`) }}
              </span>
            </td>
            <td class="px-5 py-3.5">{{ row.assignee_employee?.full_name ?? '—' }}</td>
            <td class="px-5 py-3.5">{{ row.escalation_level }}</td>
            <td class="px-5 py-3.5 text-xs text-brand-text-muted">{{ formatWhen(row.updated_at) }}</td>
          </tr>
        </tbody>
      </table>
      <div
        v-if="meta && meta.last_page > 1"
        class="flex items-center justify-between border-t border-brand-border px-5 py-3 text-sm"
      >
        <button type="button" class="inline-flex h-9 items-center gap-1 rounded-lg px-3 disabled:opacity-40" :disabled="filters.page <= 1" @click="filters.page -= 1">
          <ChevronRight class="h-4 w-4" />
        </button>
        <span class="text-brand-text-muted">{{ filters.page }} / {{ meta.last_page }}</span>
        <button type="button" class="inline-flex h-9 items-center gap-1 rounded-lg px-3 disabled:opacity-40" :disabled="filters.page >= meta.last_page" @click="filters.page += 1">
          <ChevronLeft class="h-4 w-4" />
        </button>
      </div>
    </div>

    <ComplaintFormDrawer
      :open="drawerOpen"
      :editing="null"
      :form="form"
      :form-error="formError"
      :field-errors="fieldErrors"
      :submitting="submitting"
      @close="drawerOpen = false"
      @submit="submit"
      @update:form="Object.assign(form, $event)"
    />
  </div>
</template>
