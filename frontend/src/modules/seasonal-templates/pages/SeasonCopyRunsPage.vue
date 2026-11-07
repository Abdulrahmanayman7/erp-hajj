<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'

import { useSeasonsQuery } from '@/modules/season-pilgrims/queries/useSeasonsQuery'
import AppMobileFilters from '@/shared/components/AppMobileFilters.vue'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'

import CopyRunDetailDrawer from '../components/CopyRunDetailDrawer.vue'
import { useSeasonCopyRunsQuery } from '../queries/useSeasonCopyQuery'
import type { CopyRunStatus, ListSeasonCopyRunsParams } from '../types/seasonCopy'
import { runStatusBadgeClass } from '../validation/seasonCopyValidation'
import { resolveListState } from '../validation/seasonTemplateValidation'

const { t } = useI18n()

const filters = reactive({
  status: '' as CopyRunStatus | '',
  target_season_id: '' as number | '',
  page: 1,
  per_page: 15,
})

const params = computed<ListSeasonCopyRunsParams>(() => ({
  status: filters.status,
  target_season_id: filters.target_season_id,
  page: filters.page,
  per_page: filters.per_page,
}))

const { data, isLoading, isError, refetch } = useSeasonCopyRunsQuery(params)
const rows = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta)
const listState = computed(() =>
  resolveListState({ isLoading: isLoading.value, isError: isError.value, count: rows.value.length }),
)

watch(
  () => [filters.status, filters.target_season_id],
  () => {
    filters.page = 1
  },
)

const seasonsQuery = useSeasonsQuery({ per_page: 100 })
const seasonNames = computed(
  () => Object.fromEntries((seasonsQuery.data.value?.data ?? []).map((s) => [s.id, s.name])) as Record<number, string>,
)
const seasonFilterOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('seasonalTemplates.runs.allSeasons') },
  ...(seasonsQuery.data.value?.data ?? []).map((s) => ({ value: s.id, label: `${s.name} (${s.code})` })),
])
const statusOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('seasonalTemplates.runs.allStatuses') },
  ...(['succeeded', 'failed'] as const).map((s) => ({ value: s, label: t(`seasonalTemplates.runs.status.${s}`) })),
])

const activeFilterCount = computed(() => (filters.status ? 1 : 0) + (filters.target_season_id !== '' ? 1 : 0))

function resetFilters(): void {
  filters.status = ''
  filters.target_season_id = ''
}

function setSeason(value: string | number | null): void {
  filters.target_season_id = value === '' || value == null ? '' : Number(value)
}

function seasonName(id: number): string {
  return seasonNames.value[id] ?? `#${id}`
}

const selectedRunId = ref<number | null>(null)

function formatWhen(iso: string | null): string {
  if (!iso) return '—'
  try {
    return new Intl.DateTimeFormat('ar-SA', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso))
  } catch {
    return iso
  }
}
</script>

<template>
  <div class="space-y-6" data-testid="season-copy-runs-page" dir="rtl">
    <AppPageHeader :title="t('seasonalTemplates.runs.title')" :subtitle="t('seasonalTemplates.runs.subtitle')" />

    <AppMobileFilters :show-search="false" :active-count="activeFilterCount" @reset="resetFilters">
      <template #desktop>
        <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-brand-border bg-brand-surface p-4 shadow-[0_1px_2px_rgba(23,32,29,0.03)]">
          <div class="w-full sm:w-52"><AppSelect v-model="filters.status" :options="statusOptions" /></div>
          <div class="w-full sm:w-64">
            <AppSelect :model-value="filters.target_season_id" :options="seasonFilterOptions" searchable @update:model-value="setSeason" />
          </div>
        </div>
      </template>
      <template #filters>
        <div class="space-y-3">
          <AppSelect v-model="filters.status" :options="statusOptions" />
          <AppSelect :model-value="filters.target_season_id" :options="seasonFilterOptions" searchable @update:model-value="setSeason" />
        </div>
      </template>
    </AppMobileFilters>

    <div v-if="listState === 'loading'" class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted" data-testid="runs-loading">
      {{ t('seasonalTemplates.common.loading') }}
    </div>
    <div v-else-if="listState === 'error'" class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center" data-testid="runs-error">
      <p class="text-sm text-red-700">{{ t('seasonalTemplates.errors.load') }}</p>
      <button type="button" class="mt-3 text-sm font-semibold underline" @click="() => refetch()">
        {{ t('seasonalTemplates.common.retry') }}
      </button>
    </div>
    <div v-else-if="listState === 'empty'" class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted" data-testid="runs-empty">
      {{ t('seasonalTemplates.runs.empty') }}
    </div>
    <div v-else class="overflow-hidden rounded-2xl border border-brand-border bg-brand-surface" data-testid="runs-table">
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-[#F4F6F5]">
            <tr>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('seasonalTemplates.runs.columns.number') }}</th>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('seasonalTemplates.runs.columns.status') }}</th>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('seasonalTemplates.runs.columns.mode') }}</th>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('seasonalTemplates.runs.columns.target') }}</th>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('seasonalTemplates.runs.columns.counts') }}</th>
              <th class="px-5 py-3.5 text-start text-xs font-bold text-brand-text-muted">{{ t('seasonalTemplates.runs.columns.started') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in rows"
              :key="row.id"
              class="cursor-pointer border-t border-brand-border/80 hover:bg-brand-bg/60"
              :data-testid="`run-row-${row.id}`"
              @click="selectedRunId = row.id"
            >
              <td class="px-5 py-3.5 text-start font-mono text-xs" dir="ltr">{{ row.run_number }}</td>
              <td class="px-5 py-3.5 text-start">
                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="runStatusBadgeClass(row.status)">
                  {{ t(`seasonalTemplates.runs.status.${row.status}`) }}
                </span>
              </td>
              <td class="px-5 py-3.5 text-start">{{ t(`seasonalTemplates.copy.mode.${row.mode}`) }}</td>
              <td class="px-5 py-3.5 text-start">{{ seasonName(row.target_season_id) }}</td>
              <td class="px-5 py-3.5 text-start text-xs text-brand-text-secondary">
                <template v-if="row.counts">
                  {{ t('seasonalTemplates.runs.countsSummary', row.counts) }}
                </template>
                <template v-else>—</template>
              </td>
              <td class="px-5 py-3.5 text-start text-xs text-brand-text-muted">{{ formatWhen(row.started_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="meta && meta.last_page > 1" class="flex items-center justify-between border-t border-brand-border px-5 py-3 text-sm">
        <button type="button" class="inline-flex h-9 items-center gap-1 rounded-lg px-3 disabled:opacity-40" :disabled="filters.page <= 1" :aria-label="t('common.prev')" @click="filters.page -= 1">
          <ChevronRight class="h-4 w-4" />
        </button>
        <span class="text-brand-text-muted">{{ filters.page }} / {{ meta.last_page }}</span>
        <button type="button" class="inline-flex h-9 items-center gap-1 rounded-lg px-3 disabled:opacity-40" :disabled="filters.page >= meta.last_page" :aria-label="t('common.next')" @click="filters.page += 1">
          <ChevronLeft class="h-4 w-4" />
        </button>
      </div>
    </div>

    <CopyRunDetailDrawer :run-id="selectedRunId" :season-names="seasonNames" @close="selectedRunId = null" />
  </div>
</template>
