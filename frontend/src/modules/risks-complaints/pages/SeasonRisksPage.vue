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

import RiskFormDrawer from '../components/RiskFormDrawer.vue'
import { useCreateRiskMutation } from '../mutations/useRiskMutations'
import { useRisksQuery } from '../queries/useRisksQuery'
import type { ListRisksParams, RiskFormState, RiskSeverity, RiskStatus } from '../types/risks'
import { RISK_SEVERITIES, RISK_STATUSES } from '../types/risks'
import {
  emptyRiskForm,
  mapRiskErrorCode,
  resolveRisksListState,
  riskSeverityBadgeClass,
  riskStatusBadgeClass,
  validateRiskForm,
} from '../validation/riskValidation'

const { t } = useI18n()
const toast = useToast()
const router = useRouter()
const store = useAccommodationSeasonStore()
const { selectedSeasonId } = storeToRefs(store)

const filters = reactive({
  search: '',
  status: '' as RiskStatus | '',
  severity_level: '' as RiskSeverity | '',
  page: 1,
  per_page: 15,
})

const committedSearch = useDebouncedRef(() => filters.search)
const params = computed<ListRisksParams>(() => ({
  q: committedSearch.value,
  status: filters.status,
  severity_level: filters.severity_level,
  page: filters.page,
  per_page: filters.per_page,
  seasonId: selectedSeasonId.value,
}))

const { data, isLoading, isError, refetch } = useRisksQuery(
  params,
  () => selectedSeasonId.value != null,
)
const rows = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta)
const listState = computed(() =>
  resolveRisksListState({
    hasSeason: selectedSeasonId.value != null,
    isLoading: isLoading.value,
    isError: isError.value,
    count: rows.value.length,
  }),
)

watch(
  () => [selectedSeasonId.value, committedSearch.value, filters.status, filters.severity_level],
  () => {
    filters.page = 1
  },
)

const statusOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('risks.filters.allStatuses') },
  ...RISK_STATUSES.map((s) => ({ value: s, label: t(`risks.status.${s}`) })),
])
const severityOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('risks.filters.allSeverities') },
  ...RISK_SEVERITIES.map((s) => ({ value: s, label: t(`risks.severity.${s}`) })),
])

const drawerOpen = ref(false)
const form = reactive<RiskFormState>(emptyRiskForm())
const formError = ref('')
const fieldErrors = reactive<Record<string, string>>({})
const createMutation = useCreateRiskMutation()
const submitting = computed(() => createMutation.isPending.value)

function openCreate(): void {
  Object.assign(form, emptyRiskForm())
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  drawerOpen.value = true
}

function apiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) return t('risks.errors.generic')
  const mapped = mapRiskErrorCode(error.code)
  return mapped !== 'generic' ? t(`risks.errors.${mapped}`) : error.message || t('risks.errors.generic')
}

async function submit(): Promise<void> {
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  const local = validateRiskForm(form)
  Object.assign(fieldErrors, local)
  if (Object.keys(local).length) return
  try {
    const created = await createMutation.mutateAsync({
      payload: {
        title: form.title.trim(),
        description: form.description.trim() || null,
        season_pilgrim_id: form.season_pilgrim_id === '' ? null : Number(form.season_pilgrim_id),
        group_id: form.group_id === '' ? null : Number(form.group_id),
        facility_id: form.facility_id === '' ? null : Number(form.facility_id),
        transport_season_vehicle_id:
          form.transport_season_vehicle_id === '' ? null : Number(form.transport_season_vehicle_id),
        project_id: form.project_id === '' ? null : Number(form.project_id),
      },
      seasonId: selectedSeasonId.value,
    })
    toast.success(t('risks.toasts.created'))
    drawerOpen.value = false
    await router.push(`/app/risks/${created.id}`)
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
  <div class="space-y-6" data-testid="season-risks-page" dir="rtl">
    <AppPageHeader :title="t('risks.title')" :subtitle="t('risks.subtitle')">
      <template #actions>
        <PermissionGuard permission="risks.create">
          <button
            type="button"
            class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white disabled:opacity-50"
            :disabled="selectedSeasonId == null"
            data-testid="risk-create-btn"
            @click="openCreate"
          >
            <Plus class="h-4 w-4" />
            {{ t('risks.add') }}
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
          :placeholder="t('risks.searchPlaceholder')"
          data-testid="risks-search"
        />
      </label>
      <div class="w-full lg:w-48">
        <AppSelect v-model="filters.status" :options="statusOptions" />
      </div>
      <div class="w-full lg:w-48">
        <AppSelect v-model="filters.severity_level" :options="severityOptions" />
      </div>
    </div>

    <div
      v-if="listState === 'need_season'"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
      data-testid="risks-need-season"
    >
      {{ t('risks.needSeason') }}
    </div>
    <div
      v-else-if="listState === 'loading'"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
      data-testid="risks-loading"
    >
      {{ t('risks.loading') }}
    </div>
    <div
      v-else-if="listState === 'error'"
      class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center"
      data-testid="risks-error"
    >
      <p class="text-sm text-red-700">{{ t('risks.errors.load') }}</p>
      <button type="button" class="mt-3 text-sm font-semibold underline" @click="() => refetch()">
        {{ t('risks.retry') }}
      </button>
    </div>
    <div
      v-else-if="listState === 'empty'"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
      data-testid="risks-empty"
    >
      {{ t('risks.empty') }}
    </div>
    <div v-else class="overflow-hidden rounded-2xl border border-brand-border bg-brand-surface" data-testid="risks-table">
      <table class="min-w-full text-sm">
        <thead class="bg-[#F4F6F5]">
          <tr>
            <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('risks.columns.number') }}</th>
            <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('risks.columns.title') }}</th>
            <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('risks.columns.status') }}</th>
            <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('risks.columns.severity') }}</th>
            <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('risks.columns.score') }}</th>
            <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('risks.columns.owner') }}</th>
            <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('risks.columns.updated') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in rows"
            :key="row.id"
            class="cursor-pointer border-t border-brand-border/80 hover:bg-brand-bg/60"
            @click="router.push(`/app/risks/${row.id}`)"
          >
            <td class="px-5 py-3.5 font-mono text-xs" dir="ltr">{{ row.risk_number }}</td>
            <td class="px-5 py-3.5 font-semibold text-brand-text">{{ row.title }}</td>
            <td class="px-5 py-3.5">
              <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="riskStatusBadgeClass(row.status)">
                {{ t(`risks.status.${row.status}`) }}
              </span>
            </td>
            <td class="px-5 py-3.5">
              <span
                v-if="row.severity_level"
                class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                :class="riskSeverityBadgeClass(row.severity_level)"
              >
                {{ t(`risks.severity.${row.severity_level}`) }}
              </span>
              <span v-else>—</span>
            </td>
            <td class="px-5 py-3.5">{{ row.score ?? '—' }}</td>
            <td class="px-5 py-3.5">{{ row.owner_employee?.full_name ?? '—' }}</td>
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

    <RiskFormDrawer
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
