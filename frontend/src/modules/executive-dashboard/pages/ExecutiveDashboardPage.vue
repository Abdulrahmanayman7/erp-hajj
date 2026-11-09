<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { MapPin, RefreshCw } from 'lucide-vue-next'

import OperationalKpiCardGrid from '@/modules/operational-kpis/components/OperationalKpiCardGrid.vue'
import { useSeasonsQuery } from '@/modules/season-pilgrims/queries/useSeasonsQuery'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { usePermissions } from '@/shared/composables/usePermissions'

import { useExecutiveDashboardQuery } from '../queries/useExecutiveDashboardQuery'

const { t } = useI18n()
const { can } = usePermissions()

const canPickSeasons = computed(() => can('seasons.view'))
const seasonsQuery = useSeasonsQuery({ per_page: 100 })
const seasons = computed(() => seasonsQuery.data.value?.data ?? [])
const hasNoSeasons = computed(
  () => canPickSeasons.value && !seasonsQuery.isLoading.value && seasons.value.length === 0,
)

const pickerSeasonId = ref<number | ''>('')
const manualSeasonId = ref('')
/** Confirmed season used for API fetch — never silently equals Active without confirm (S10-T01). */
const confirmedSeasonId = ref<number | null>(null)

const activeSeason = computed(() => seasons.value.find((s) => s.status === 'active') ?? null)
const showActiveSuggestion = computed(
  () =>
    canPickSeasons.value &&
    activeSeason.value != null &&
    confirmedSeasonId.value == null &&
    (pickerSeasonId.value === '' || pickerSeasonId.value === activeSeason.value.id),
)

const seasonOptions = computed<AppSelectOption[]>(() =>
  seasons.value.map((s) => ({
    value: s.id,
    label:
      s.status === 'closed'
        ? `${s.name} — ${t('executiveDashboard.closed')}`
        : s.status === 'active'
          ? `${s.name} — ${t('executiveDashboard.active')}`
          : `${s.name} — ${t('executiveDashboard.draft')}`,
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
  if (canPickSeasons.value) {
    const id = pickerSeasonId.value === '' ? activeSeason.value?.id : Number(pickerSeasonId.value)
    if (id == null || Number.isNaN(id)) return
    pickerSeasonId.value = id
    confirmedSeasonId.value = id
    return
  }

  const parsed = Number(manualSeasonId.value.trim())
  if (!Number.isInteger(parsed) || parsed < 1) return
  confirmedSeasonId.value = parsed
}

function confirmSuggestedActive(): void {
  if (!activeSeason.value) return
  pickerSeasonId.value = activeSeason.value.id
  confirmedSeasonId.value = activeSeason.value.id
}

function clearConfirmation(): void {
  confirmedSeasonId.value = null
}

const dashboardQuery = useExecutiveDashboardQuery(
  confirmedSeasonId,
  () => confirmedSeasonId.value != null,
)

const isFetching = computed(() => dashboardQuery.isFetching.value)
const payload = computed(() => dashboardQuery.data.value?.data ?? null)
const meta = computed(() => dashboardQuery.data.value?.meta)
const confirmedSeasonMeta = computed(() => payload.value?.season ?? null)
const showGps = computed(() => payload.value?.gps != null)

const pageState = computed(() => {
  if (canPickSeasons.value) {
    if (seasonsQuery.isLoading.value) return 'loading_seasons' as const
    if (seasonsQuery.isError.value) return 'seasons_error' as const
    if (hasNoSeasons.value) return 'no_seasons' as const
  }
  if (confirmedSeasonId.value == null) return 'need_confirm' as const
  if (dashboardQuery.isLoading.value) return 'loading' as const
  if (dashboardQuery.isError.value) return 'error' as const
  if (payload.value?.response_state === 'no_domain') return 'no_domain' as const
  if ((payload.value?.kpis.length ?? 0) === 0) return 'empty' as const
  return 'ready' as const
})
</script>

<template>
  <div class="space-y-6">
    <AppPageHeader :title="t('executiveDashboard.title')" :subtitle="t('executiveDashboard.subtitle')">
      <template #actions>
        <button
          v-if="confirmedSeasonId != null"
          type="button"
          class="inline-flex items-center gap-2 rounded-lg border border-brand-border bg-brand-surface px-3 py-2 text-sm font-semibold text-brand-text transition hover:border-brand-primary/30"
          :disabled="isFetching"
          @click="dashboardQuery.refetch()"
        >
          <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': isFetching }" />
          {{ t('executiveDashboard.refresh') }}
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
            t('executiveDashboard.seasonLabel')
          }}</label>
          <AppSelect
            v-model="pickerSeasonId"
            :options="seasonOptions"
            :placeholder="t('executiveDashboard.pickSeason')"
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
          {{ t('executiveDashboard.confirmSeason') }}
        </button>
      </div>

      <div
        v-if="showActiveSuggestion && activeSeason"
        class="flex flex-col gap-2 rounded-lg border border-brand-primary/20 bg-[var(--surface-subtle)] p-3 sm:flex-row sm:items-center sm:justify-between"
      >
        <p class="text-sm text-brand-text">
          {{ t('executiveDashboard.suggestedActive') }}:
          <span class="font-bold">{{ activeSeason.name }}</span>
        </p>
        <button
          type="button"
          class="text-sm font-bold text-brand-primary underline-offset-2 hover:underline"
          @click="confirmSuggestedActive"
        >
          {{ t('executiveDashboard.confirmSeason') }}
        </button>
      </div>
    </section>

    <section
      v-else
      class="app-surface-flat space-y-3 p-4"
      aria-label="manual season id"
    >
      <p class="text-sm text-brand-text-secondary">{{ t('executiveDashboard.manualSeasonHint') }}</p>
      <div class="flex flex-col gap-3 md:flex-row md:items-end">
        <div class="min-w-0 flex-1 space-y-1.5">
          <label class="text-xs font-bold text-brand-text-secondary" for="exec-manual-season">{{
            t('executiveDashboard.manualSeasonId')
          }}</label>
          <input
            id="exec-manual-season"
            v-model="manualSeasonId"
            type="number"
            min="1"
            inputmode="numeric"
            class="w-full rounded-lg border border-brand-border bg-brand-surface px-3 py-2.5 text-sm text-brand-text"
            @input="clearConfirmation"
          />
        </div>
        <button
          type="button"
          class="inline-flex items-center justify-center rounded-lg bg-brand-primary px-4 py-2.5 text-sm font-bold text-white transition hover:opacity-95 disabled:opacity-50"
          :disabled="!manualSeasonId.trim()"
          @click="confirmSeason"
        >
          {{ t('executiveDashboard.confirmSeason') }}
        </button>
      </div>
    </section>

    <p v-if="confirmedSeasonMeta" class="text-xs text-brand-text-muted px-1">
      <span class="font-semibold text-brand-text">{{ confirmedSeasonMeta.name }}</span>
      <span
        v-if="confirmedSeasonMeta.status === 'closed'"
        class="ms-2 rounded bg-amber-50 px-1.5 py-0.5 text-[11px] font-bold text-amber-800"
        >{{ confirmedSeasonMeta.status_label || t('executiveDashboard.closed') }}</span
      >
      <span v-if="meta?.generated_at" class="ms-3"
        >{{ t('executiveDashboard.generatedAt') }}: {{ meta.generated_at }}</span
      >
      <span v-if="meta?.timezone" class="ms-2"
        >({{ t('executiveDashboard.timezone') }}: {{ meta.timezone }})</span
      >
      <button
        type="button"
        class="ms-3 text-brand-primary font-bold underline-offset-2 hover:underline"
        @click="clearConfirmation"
      >
        {{ t('executiveDashboard.changeSeason') }}
      </button>
    </p>

    <section
      v-if="showGps && payload?.gps"
      class="app-surface-flat flex items-start gap-3 p-4"
      aria-label="gps unavailable"
    >
      <MapPin class="mt-0.5 h-5 w-5 shrink-0 text-brand-text-muted" aria-hidden="true" />
      <div class="space-y-1">
        <h2 class="text-sm font-bold text-brand-text">{{ t('executiveDashboard.gpsTitle') }}</h2>
        <p class="text-sm text-brand-text-secondary">{{ t('executiveDashboard.gpsUnavailable') }}</p>
        <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-text-muted">
          {{ payload.gps.freshness_class }}
        </p>
      </div>
    </section>

    <div
      v-if="pageState === 'no_seasons'"
      class="app-surface-flat p-6 text-center text-sm font-semibold text-brand-text"
    >
      {{ t('executiveDashboard.noSeasons') }}
    </div>
    <div
      v-else-if="pageState === 'need_confirm'"
      class="app-surface-flat p-6 text-center text-sm text-brand-text-secondary"
    >
      {{ t('executiveDashboard.needSeason') }}
    </div>
    <div
      v-else-if="pageState === 'loading' || pageState === 'loading_seasons'"
      class="app-surface-flat p-6 text-center text-sm text-brand-text-secondary"
    >
      {{ t('executiveDashboard.loading') }}
    </div>
    <div
      v-else-if="pageState === 'error' || pageState === 'seasons_error'"
      class="app-surface-flat space-y-3 p-6 text-center"
    >
      <p class="text-sm text-red-700">{{ t('common.error') }}</p>
      <button
        type="button"
        class="rounded-lg border border-brand-border px-4 py-2 text-sm font-bold"
        @click="pageState === 'seasons_error' ? seasonsQuery.refetch() : dashboardQuery.refetch()"
      >
        {{ t('executiveDashboard.retry') }}
      </button>
    </div>
    <div
      v-else-if="pageState === 'no_domain'"
      class="app-surface-flat p-6 text-center text-sm text-brand-text-secondary"
    >
      {{ t('executiveDashboard.noDomain') }}
    </div>
    <div
      v-else-if="pageState === 'empty'"
      class="app-surface-flat p-6 text-center text-sm text-brand-text-secondary"
    >
      {{ t('executiveDashboard.empty') }}
    </div>
    <OperationalKpiCardGrid v-else-if="pageState === 'ready' && payload" :kpis="payload.kpis" />
  </div>
</template>
