<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'
import { AlertTriangle, CalendarRange } from 'lucide-vue-next'

import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useAccommodationSeasonStore } from '@/modules/accommodation/stores/seasonContextStore'

import { useSeasonsQuery } from '../queries/useSeasonsQuery'

const { t } = useI18n()
const { can } = usePermissions()
const store = useAccommodationSeasonStore()
const { selectedSeasonId } = storeToRefs(store)

const canList = computed(() => can('seasons.view'))
const { data: seasonsData } = useSeasonsQuery(
  computed(() => ({ per_page: 100 })),
)

const seasons = computed(() => seasonsData.value?.data ?? [])
const selectedSeason = computed(
  () => seasons.value.find((row) => row.id === selectedSeasonId.value) ?? null,
)
const isClosed = computed(() => selectedSeason.value?.status === 'closed')
const isWritable = computed(
  () => selectedSeason.value != null && selectedSeason.value.status !== 'closed',
)

const seasonOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('seasonOps.bar.selectPlaceholder') },
  ...seasons.value.map((row) => ({
    value: row.id,
    label: `${row.name} (${row.code})`,
    hint: t(`seasons.status.${row.status}`),
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

defineExpose({ selectedSeason, isClosed, isWritable, selectedSeasonId })
</script>

<template>
  <section
    class="rounded-xl border border-brand-border bg-brand-surface px-4 py-3 sm:px-5"
    aria-label="season-workspace"
  >
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
      <div class="flex items-start gap-3">
        <div class="mt-0.5 rounded-lg bg-brand-bg p-2 text-brand-primary">
          <CalendarRange class="h-5 w-5" />
        </div>
        <div>
          <p class="text-sm font-semibold text-brand-text">{{ t('seasonOps.bar.title') }}</p>
          <p class="mt-0.5 text-xs text-brand-text-secondary">
            <template v-if="selectedSeason">
              {{ selectedSeason.name }} · {{ t(`seasons.status.${selectedSeason.status}`) }}
            </template>
            <template v-else>{{ t('seasonOps.bar.noneSelected') }}</template>
          </p>
        </div>
      </div>

      <div v-if="canList" class="w-full max-w-sm">
        <label class="mb-1 block text-xs font-medium text-brand-text-secondary">
          {{ t('seasonOps.bar.select') }}
        </label>
        <AppSelect v-model="selectedValue" :options="seasonOptions" searchable />
      </div>
      <p v-else class="text-xs text-brand-text-muted">{{ t('seasonOps.bar.noListPermission') }}</p>
    </div>

    <div
      v-if="isClosed"
      class="mt-3 flex items-start gap-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-950 ring-1 ring-amber-200/80"
    >
      <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
      <p>{{ t('seasonOps.bar.closedNotice') }}</p>
    </div>
  </section>
</template>
