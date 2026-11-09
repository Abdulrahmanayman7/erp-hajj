<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RefreshCw } from 'lucide-vue-next'

import { useSeasonsQuery } from '@/modules/season-pilgrims/queries/useSeasonsQuery'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { usePermissions } from '@/shared/composables/usePermissions'

import OperationalKpiCardGrid from '../components/OperationalKpiCardGrid.vue'
import { useOperationalKpisQuery } from '../queries/useOperationalKpisQuery'

const { t } = useI18n()
const { can } = usePermissions()

const canPickSeasons = computed(() => can('seasons.view'))
const seasonsQuery = useSeasonsQuery({ per_page: 100 })
const seasons = computed(() => seasonsQuery.data.value?.data ?? [])
const hasNoSeasons = computed(
  () => canPickSeasons.value && !seasonsQuery.isLoading.value && seasons.value.length === 0,
)

const pickerSeasonId = ref<number | ''>('')
/** Confirmed season used for API fetch — never silently equals Active without confirm (D05-C3). */
const confirmedSeasonId = ref<number | null>(null)

const activeSeason = computed(() => seasons.value.find((s) => s.status === 'active') ?? null)
const showActiveSuggestion = computed(
  () =>
    activeSeason.value != null &&
    confirmedSeasonId.value == null &&
    (pickerSeasonId.value === '' || pickerSeasonId.value === activeSeason.value.id),
)

const seasonOptions = computed<AppSelectOption[]>(() =>
  seasons.value.map((s) => ({
    value: s.id,
    label:
      s.status === 'closed'
        ? `${s.name} — ${t('operationalKpis.closed')}`
        : s.status === 'active'
          ? `${s.name} — ${t('operationalKpis.active')}`
          : `${s.name} — ${t('operationalKpis.draft')}`,
  })),
)

watch(
  seasons,
  (list) => {
    if (list.length === 1 && confirmedSeasonId.value == null && pickerSeasonId.value === '') {
      pickerSeasonId.value = list[0]!.id
    }
  },
  { immediate: true },
)

function confirmSeason(): void {
  const id = pickerSeasonId.value === '' ? activeSeason.value?.id : Number(pickerSeasonId.value)
  if (id == null || Number.isNaN(id)) return
  pickerSeasonId.value = id
  confirmedSeasonId.value = id
}

function confirmSuggestedActive(): void {
  if (!activeSeason.value) return
  pickerSeasonId.value = activeSeason.value.id
  confirmedSeasonId.value = activeSeason.value.id
}

function clearConfirmation(): void {
  confirmedSeasonId.value = null
}

const kpisQuery = useOperationalKpisQuery(
  confirmedSeasonId,
  () => confirmedSeasonId.value != null,
)

const isKpisFetching = computed(() => kpisQuery.isFetching.value)
const payload = computed(() => kpisQuery.data.value?.data ?? null)
const meta = computed(() => kpisQuery.data.value?.meta)
const confirmedSeasonMeta = computed(() => payload.value?.season ?? null)

const pageState = computed(() => {
  if (!canPickSeasons.value) return 'need_seasons_permission' as const
  if (seasonsQuery.isLoading.value) return 'loading_seasons' as const
  if (seasonsQuery.isError.value) return 'seasons_error' as const
  if (hasNoSeasons.value) return 'no_seasons' as const
  if (confirmedSeasonId.value == null) return 'need_confirm' as const
  if (kpisQuery.isLoading.value) return 'loading' as const
  if (kpisQuery.isError.value) return 'error' as const
  if (payload.value?.response_state === 'no_domain') return 'no_domain' as const
  if ((payload.value?.kpis.length ?? 0) === 0) return 'empty' as const
  return 'ready' as const
})
</script>

<template>
  <div class="space-y-6">
    <AppPageHeader :title="t('operationalKpis.title')" :subtitle="t('operationalKpis.subtitle')">
      <template #actions>
        <button
          v-if="confirmedSeasonId != null"
          type="button"
          class="inline-flex items-center gap-2 rounded-lg border border-brand-border bg-brand-surface px-3 py-2 text-sm font-semibold text-brand-text transition hover:border-brand-primary/30"
          :disabled="isKpisFetching"
          @click="kpisQuery.refetch()"
        >
          <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': isKpisFetching }" />
          {{ t('operationalKpis.refresh') }}
        </button>
      </template>
    </AppPageHeader>

    <section
      v-if="canPickSeasons"
      class="app-surface-flat space-y-3 p-4"
      aria-label="season picker"
    >
      <div class="flex flex-col gap-3 md:flex-row md:items-end">
        <div class="min-w-0 flex-1 space-y-1.5">
          <label class="text-xs font-bold text-brand-text-secondary">{{
            t('operationalKpis.seasonLabel')
          }}</label>
          <AppSelect
            v-model="pickerSeasonId"
            :options="seasonOptions"
            :placeholder="t('operationalKpis.pickSeason')"
            :disabled="seasonsQuery.isLoading.value || hasNoSeasons"
            @update:model-value="clearConfirmation"
          />
        </div>
        <button
          type="button"
          class="inline-flex items-center justify-center rounded-lg bg-brand-primary px-4 py-2.5 text-sm font-bold text-white transition hover:opacity-95 disabled:opacity-50"
          :disabled="pickerSeasonId === '' && !activeSeason"
          @click="confirmSeason"
        >
          {{ t('operationalKpis.confirmSeason') }}
        </button>
      </div>

      <div
        v-if="showActiveSuggestion && activeSeason"
        class="flex flex-col gap-2 rounded-lg border border-brand-primary/20 bg-[var(--surface-subtle)] p-3 sm:flex-row sm:items-center sm:justify-between"
      >
        <p class="text-sm text-brand-text">
          {{ t('operationalKpis.suggestedActive') }}:
          <span class="font-bold">{{ activeSeason.name }}</span>
        </p>
        <button
          type="button"
          class="text-sm font-bold text-brand-primary underline-offset-2 hover:underline"
          @click="confirmSuggestedActive"
        >
          {{ t('operationalKpis.confirmSeason') }}
        </button>
      </div>

      <p
        v-if="confirmedSeasonMeta"
        class="text-xs text-brand-text-muted"
      >
        <span class="font-semibold text-brand-text">{{ confirmedSeasonMeta.name }}</span>
        <span
          v-if="confirmedSeasonMeta.status === 'closed'"
          class="ms-2 rounded bg-amber-50 px-1.5 py-0.5 text-[11px] font-bold text-amber-800"
          >{{ confirmedSeasonMeta.status_label || t('operationalKpis.closed') }}</span
        >
        <span v-if="meta?.generated_at" class="ms-3"
          >{{ t('operationalKpis.generatedAt') }}: {{ meta.generated_at }}</span
        >
        <span v-if="meta?.timezone" class="ms-2"
          >({{ t('operationalKpis.timezone') }}: {{ meta.timezone }})</span
        >
      </p>
    </section>

    <div
      v-if="pageState === 'need_seasons_permission'"
      class="app-surface-flat p-6 text-center text-sm text-brand-text-secondary"
    >
      {{ t('operationalKpis.needSeasonsPermission') }}
    </div>
    <div
      v-else-if="pageState === 'no_seasons'"
      class="app-surface-flat p-6 text-center text-sm font-semibold text-brand-text"
    >
      {{ t('operationalKpis.noSeasons') }}
    </div>
    <div
      v-else-if="pageState === 'need_confirm'"
      class="app-surface-flat p-6 text-center text-sm text-brand-text-secondary"
    >
      {{ t('operationalKpis.needSeason') }}
    </div>
    <div
      v-else-if="pageState === 'loading' || pageState === 'loading_seasons'"
      class="app-surface-flat p-6 text-center text-sm text-brand-text-secondary"
    >
      {{ t('operationalKpis.loading') }}
    </div>
    <div
      v-else-if="pageState === 'error' || pageState === 'seasons_error'"
      class="app-surface-flat space-y-3 p-6 text-center"
    >
      <p class="text-sm text-red-700">{{ t('operationalKpis.retry') }}</p>
      <button
        type="button"
        class="rounded-lg border border-brand-border px-4 py-2 text-sm font-bold"
        @click="pageState === 'seasons_error' ? seasonsQuery.refetch() : kpisQuery.refetch()"
      >
        {{ t('operationalKpis.retry') }}
      </button>
    </div>
    <div
      v-else-if="pageState === 'no_domain'"
      class="app-surface-flat p-6 text-center text-sm text-brand-text-secondary"
    >
      {{ t('operationalKpis.noDomain') }}
    </div>
    <div
      v-else-if="pageState === 'empty'"
      class="app-surface-flat p-6 text-center text-sm text-brand-text-secondary"
    >
      {{ t('operationalKpis.empty') }}
    </div>
    <OperationalKpiCardGrid v-else-if="pageState === 'ready' && payload" :kpis="payload.kpis" />
  </div>
</template>
