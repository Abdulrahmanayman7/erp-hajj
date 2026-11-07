<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { storeToRefs } from 'pinia'

import { useAccommodationSeasonStore } from '@/modules/accommodation/stores/seasonContextStore'
import { ApiError } from '@/shared/api/http'

import { getComplaintTimeline } from '../api/complaintsApi'
import { getRiskTimeline, type OperationalTimelineEntry } from '../api/risksApi'

const props = defineProps<{
  entity: 'risk' | 'complaint'
  entityId: number
}>()

const { t } = useI18n()
const seasonStore = useAccommodationSeasonStore()
const { selectedSeasonId } = storeToRefs(seasonStore)

const entries = ref<OperationalTimelineEntry[]>([])
const loading = ref(false)
const error = ref('')

const title = computed(() => t('risks.timeline.title'))

async function load(): Promise<void> {
  if (props.entityId <= 0 || selectedSeasonId.value == null) {
    entries.value = []
    return
  }
  loading.value = true
  error.value = ''
  try {
    entries.value =
      props.entity === 'risk'
        ? await getRiskTimeline(props.entityId, selectedSeasonId.value)
        : await getComplaintTimeline(props.entityId, selectedSeasonId.value)
  } catch (err) {
    entries.value = []
    error.value =
      err instanceof ApiError ? err.message || t('risks.timeline.error') : t('risks.timeline.error')
  } finally {
    loading.value = false
  }
}

watch(
  () => [props.entity, props.entityId, selectedSeasonId.value] as const,
  () => {
    void load()
  },
  { immediate: true },
)

function formatWhen(iso: string | null): string {
  if (!iso) return '—'
  try {
    return new Intl.DateTimeFormat('ar-SA', { dateStyle: 'medium', timeStyle: 'short' }).format(
      new Date(iso),
    )
  } catch {
    return iso
  }
}

function eventLabel(code: string): string {
  const key = `audit.events.${code}`
  const translated = t(key)
  return translated === key ? code : translated
}
</script>

<template>
  <section class="space-y-3 rounded-2xl border border-brand-border bg-brand-surface p-4" data-testid="operational-timeline">
    <h3 class="text-base font-bold text-brand-text">{{ title }}</h3>

    <div v-if="loading" class="text-sm text-brand-text-muted">{{ t('risks.timeline.loading') }}</div>
    <div v-else-if="error" class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">
      {{ error }}
    </div>
    <p v-else-if="entries.length === 0" class="text-sm text-brand-text-muted">
      {{ t('risks.timeline.empty') }}
    </p>
    <ol v-else class="space-y-3">
      <li
        v-for="entry in entries"
        :key="entry.id"
        class="rounded-xl border border-brand-border bg-brand-bg p-3"
        :data-testid="`timeline-entry-${entry.id}`"
      >
        <div class="flex flex-wrap items-center justify-between gap-2">
          <p class="text-sm font-semibold text-brand-text">{{ eventLabel(entry.event_type) }}</p>
          <time class="text-xs text-brand-text-muted">{{ formatWhen(entry.created_at) }}</time>
        </div>
        <p class="mt-1 text-xs text-brand-text-secondary">
          {{ entry.actor_label ?? '—' }}
        </p>
      </li>
    </ol>
  </section>
</template>
