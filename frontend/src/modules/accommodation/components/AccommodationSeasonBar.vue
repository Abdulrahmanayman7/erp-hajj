<script setup lang="ts">
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { AlertTriangle, CalendarRange } from 'lucide-vue-next'

import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { usePermissions } from '@/shared/composables/usePermissions'

import {
  useAccommodationSeasonContextQuery,
  useAccommodationSeasonsQuery,
} from '../queries/useAccommodationQueries'
import { useAccommodationSeasonStore } from '../stores/seasonContextStore'

const { t } = useI18n()
const { can } = usePermissions()
const store = useAccommodationSeasonStore()
const { selectedSeasonId, season, isClosed, canWrite, selectionMode } = storeToRefs(store)

const canListSeasons = computed(() => can('seasons.view'))
const { data: seasonsData, isError: seasonsError } = useAccommodationSeasonsQuery(canListSeasons)
const { isError: contextError, isFetching: contextLoading } = useAccommodationSeasonContextQuery(true)

const seasonOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('accommodation.season.defaultActive') },
  ...(seasonsData.value?.data ?? []).map((row) => ({
    value: row.id,
    label: `${row.name} (${row.code})`,
    hint: t(`accommodation.season.status.${row.status}`),
  })),
])

const selectedValue = computed({
  get: () => selectedSeasonId.value ?? '',
  set: (value: number | string) => {
    if (value === '' || value == null) {
      store.setSelectedSeasonId(null)
      return
    }
    store.setSelectedSeasonId(Number(value))
  },
})

watch(
  () => selectedSeasonId.value,
  () => {
    store.clearSeasonDetails()
  },
)

const statusLabel = computed(() =>
  season.value ? t(`accommodation.season.status.${season.value.status}`) : t('accommodation.season.unknown'),
)
</script>

<template>
  <section
    class="rounded-xl border border-brand-border bg-brand-surface px-4 py-3 sm:px-5"
    aria-label="season-context"
  >
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
      <div class="flex items-start gap-3">
        <div class="mt-0.5 rounded-lg bg-brand-bg p-2 text-brand-primary">
          <CalendarRange class="h-5 w-5" />
        </div>
        <div>
          <p class="text-sm font-semibold text-brand-text">
            {{ t('accommodation.season.title') }}
          </p>
          <p class="mt-0.5 text-xs text-brand-text-secondary">
            {{ season ? `${season.name} · ${statusLabel}` : t('accommodation.season.loading') }}
            <span v-if="selectionMode === 'default_active'" class="ms-1 text-brand-text-muted">
              ({{ t('accommodation.season.usingDefault') }})
            </span>
          </p>
        </div>
      </div>

      <div v-if="canListSeasons" class="w-full max-w-sm">
        <label class="mb-1 block text-xs font-medium text-brand-text-secondary">
          {{ t('accommodation.season.select') }}
        </label>
        <AppSelect v-model="selectedValue" :options="seasonOptions" />
      </div>
      <p v-else class="text-xs text-brand-text-muted">
        {{ t('accommodation.season.noListPermission') }}
      </p>
    </div>

    <div
      v-if="season && (isClosed || !canWrite)"
      class="mt-3 flex items-start gap-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-950 ring-1 ring-amber-200/80"
    >
      <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
      <p>{{ t('accommodation.season.closedNotice') }}</p>
    </div>

    <p v-if="contextError || seasonsError" class="mt-2 text-xs text-red-700">
      {{ t('accommodation.season.contextError') }}
    </p>
    <p v-if="contextLoading" class="mt-2 text-xs text-brand-text-muted">
      {{ t('accommodation.season.refreshing') }}
    </p>
  </section>
</template>
