<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { MapPin, RefreshCw } from 'lucide-vue-next'

import OperationalKpiCardGrid from '@/modules/operational-kpis/components/OperationalKpiCardGrid.vue'
import { useSeasonsQuery } from '@/modules/season-pilgrims/queries/useSeasonsQuery'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { usePermissions } from '@/shared/composables/usePermissions'

import AccommodationCountsPanel from '../components/AccommodationCountsPanel.vue'
import AlertsPanel from '../components/AlertsPanel.vue'
import ProjectProgressPanel from '../components/ProjectProgressPanel.vue'
import ReportsPanel from '../components/ReportsPanel.vue'
import SeasonStatusPanel from '../components/SeasonStatusPanel.vue'
import {
  useAcknowledgeExecutiveAlertMutation,
  useExecutiveAlertsQuery,
} from '../queries/useExecutiveAlertsQuery'
import { useExecutiveDashboardQuery } from '../queries/useExecutiveDashboardQuery'
import {
  ACCOMMODATION_PANEL_IDS,
  PROJECT_PANEL_IDS,
  pickKpisById,
} from '../utils/executiveDashboardPanels'
import {
  canPickExecutiveSeasons,
  shouldShowExecutiveAlertsPanel,
  shouldShowExecutiveGpsPanel,
  shouldShowExecutiveReportsPanel,
} from '../utils/executiveDashboardVisibility'

const { t } = useI18n()
const { can } = usePermissions()

const canPickSeasons = computed(() => canPickExecutiveSeasons(can))
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
const showGps = computed(() => shouldShowExecutiveGpsPanel(payload.value?.gps))

const seasonPanel = computed(() => payload.value?.panels?.season ?? payload.value?.season ?? null)
const accommodationPanelKpis = computed(() => {
  const fromPanels = payload.value?.panels?.accommodation?.kpis
  if (fromPanels && fromPanels.length > 0) return fromPanels
  return pickKpisById(payload.value?.kpis ?? [], ACCOMMODATION_PANEL_IDS)
})
const projectPanelKpis = computed(() => {
  const fromPanels = payload.value?.panels?.projects?.kpis
  if (fromPanels && fromPanels.length > 0) return fromPanels
  return pickKpisById(payload.value?.kpis ?? [], PROJECT_PANEL_IDS)
})

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

/** The alerts panel exists only when the API returned it (executive_alerts.view). */
const alertsSummary = computed(() => payload.value?.panels?.alerts ?? null)
const alertsQuery = useExecutiveAlertsQuery(
  confirmedSeasonId,
  () => confirmedSeasonId.value != null && shouldShowExecutiveAlertsPanel(alertsSummary.value),
)
const acknowledgeMutation = useAcknowledgeExecutiveAlertMutation()
const alertRows = computed(() => alertsQuery.data.value ?? alertsSummary.value?.items ?? [])

const showA3Panels = computed(
  () =>
    pageState.value === 'ready' ||
    pageState.value === 'no_domain' ||
    pageState.value === 'empty',
)

/** Reports panel is permission-gated on the client; API still enforces dual page + executive_reports.view. */
const showReports = computed(() =>
  shouldShowExecutiveReportsPanel(can, confirmedSeasonId.value, pageState.value),
)
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
      :aria-label="t('executiveDashboard.a11y.seasonPicker')"
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
      :aria-label="t('executiveDashboard.a11y.manualSeason')"
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
      <span v-if="meta?.generated_at"
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

    <div v-if="showA3Panels && seasonPanel" class="space-y-4">
      <SeasonStatusPanel :season="seasonPanel" />
      <AccommodationCountsPanel :kpis="accommodationPanelKpis" />
      <ProjectProgressPanel :kpis="projectPanelKpis" />
      <AlertsPanel
        v-if="alertsSummary"
        :alerts="alertRows"
        :open-count="alertsSummary.open_count"
        :acknowledged-count="alertsSummary.acknowledged_count"
        :critical-count="alertsSummary.critical_count"
        :is-acknowledging="acknowledgeMutation.isPending.value"
        :is-loading="alertsQuery.isLoading.value"
        :is-error="alertsQuery.isError.value"
        @acknowledge="acknowledgeMutation.mutate($event)"
      />
      <ReportsPanel v-if="showReports && confirmedSeasonId != null" :season-id="confirmedSeasonId" />
    </div>

    <section
      v-if="showGps && payload?.gps"
      class="app-surface-flat space-y-3 p-4"
      :aria-label="t('executiveDashboard.a11y.gpsPanel')"
    >
      <div class="flex items-start gap-3">
        <MapPin class="mt-0.5 h-5 w-5 shrink-0 text-brand-text-muted" aria-hidden="true" />
        <div class="min-w-0 flex-1 space-y-2">
          <div class="flex flex-wrap items-center gap-2">
            <h2 class="text-sm font-bold text-brand-text">{{ t('executiveDashboard.gpsTitle') }}</h2>
            <span
              class="inline-flex rounded px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide"
              :class="{
                'bg-emerald-50 text-emerald-800': payload.gps.freshness_class === 'real',
                'bg-amber-50 text-amber-800': payload.gps.freshness_class === 'stale',
                'bg-[var(--surface-subtle)] text-brand-text-muted': payload.gps.freshness_class === 'unavailable',
              }"
              data-test="gps-freshness-chip"
            >
              {{ t(`executiveDashboard.gpsFreshness.${payload.gps.freshness_class}`) }}
            </span>
          </div>
          <p
            v-if="payload.gps.markers.length === 0"
            class="text-sm text-brand-text-secondary"
            data-test="gps-unavailable"
          >
            {{ t('executiveDashboard.gpsUnavailable') }}
          </p>
          <ul v-else class="space-y-2" data-test="gps-markers">
            <li
              v-for="marker in payload.gps.markers"
              :key="marker.transport_season_vehicle_id"
              class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-brand-border/80 bg-[var(--surface-subtle)] px-3 py-2 text-sm"
            >
              <span class="font-bold text-brand-text">
                {{ t('executiveDashboard.gpsMarkerVehicle') }} #{{ marker.transport_season_vehicle_id }}
              </span>
              <span class="font-mono text-xs text-brand-text-secondary">
                {{ marker.lat.toFixed(5) }}, {{ marker.lng.toFixed(5) }}
              </span>
              <span
                class="inline-flex rounded px-1.5 py-0.5 text-[10px] font-bold uppercase"
                :class="{
                  'bg-emerald-50 text-emerald-800': marker.freshness_class === 'real',
                  'bg-amber-50 text-amber-800': marker.freshness_class === 'stale',
                  'bg-[var(--surface-subtle)] text-brand-text-muted': marker.freshness_class === 'unavailable',
                }"
                data-test="gps-marker-freshness"
              >
                {{ t(`executiveDashboard.gpsFreshness.${marker.freshness_class}`) }}
              </span>
            </li>
          </ul>
        </div>
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
