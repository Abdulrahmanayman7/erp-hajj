<script setup lang="ts">
import { computed, reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'

import { useAccommodationSeasonStore } from '@/modules/accommodation/stores/seasonContextStore'
import SeasonWorkspaceBar from '@/modules/season-pilgrims/components/SeasonWorkspaceBar.vue'
import { ApiError } from '@/shared/api/http'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { useToast } from '@/shared/composables/useToast'

import {
  type BreakdownStatus,
  closeBreakdown,
  listBreakdowns,
} from '../api/breakdownsApi'
import { mapBreakdownErrorCode } from '../validation/breakdownValidation'
import { resolveListState } from '../validation/fleetValidation'
import { useQuery } from '@tanstack/vue-query'

const { t } = useI18n()
const toast = useToast()
const router = useRouter()
const store = useAccommodationSeasonStore()
const { selectedSeasonId } = storeToRefs(store)

const filters = reactive({
  status: '' as BreakdownStatus | '',
  page: 1,
  per_page: 15,
})

const params = computed(() => ({
  status: filters.status,
  page: filters.page,
  per_page: filters.per_page,
}))

const { data, isLoading, isError, refetch } = useQuery({
  queryKey: computed(() => ['transport-breakdowns', selectedSeasonId.value, params.value]),
  queryFn: () => listBreakdowns(params.value, selectedSeasonId.value),
  enabled: computed(() => selectedSeasonId.value != null),
})

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
  () => [selectedSeasonId.value, filters.status],
  () => {
    filters.page = 1
  },
)

const statusOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('transportation.breakdowns.filters.allStatus') },
  ...(['open', 'replaced', 'closed', 'cancelled'] as BreakdownStatus[]).map((s) => ({
    value: s,
    label: t(`transportation.breakdowns.status.${s}`),
  })),
])

async function onClose(id: number): Promise<void> {
  const reason = window.prompt(t('transportation.breakdowns.closePrompt'))
  if (!reason || reason.trim().length < 3) return
  try {
    await closeBreakdown(id, reason.trim(), selectedSeasonId.value)
    toast.success(t('transportation.breakdowns.toasts.closed'))
    await refetch()
  } catch (error) {
    const mapped =
      error instanceof ApiError ? mapBreakdownErrorCode(error.code) : 'generic'
    toast.error(t(`transportation.breakdowns.errors.${mapped}`))
  }
}
</script>

<template>
  <div class="space-y-6">
    <AppPageHeader
      :title="t('transportation.breakdowns.title')"
      :subtitle="t('transportation.breakdowns.subtitle')"
    />
    <SeasonWorkspaceBar />

    <div class="w-full max-w-xs">
      <AppSelect v-model="filters.status" :options="statusOptions" />
    </div>

    <div
      v-if="listState === 'need_season'"
      class="rounded-2xl border border-brand-border p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('transportation.movements.needSeason') }}
    </div>
    <div
      v-else-if="listState === 'loading'"
      class="rounded-2xl border border-brand-border p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('transportation.breakdowns.loading') }}
    </div>
    <div v-else-if="listState === 'error'" class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center">
      <p class="text-sm text-red-700">{{ t('transportation.breakdowns.errors.load') }}</p>
      <button type="button" class="mt-3 text-sm font-semibold underline" @click="() => refetch()">
        {{ t('transportation.movements.retry') }}
      </button>
    </div>
    <div
      v-else-if="listState === 'empty'"
      class="rounded-2xl border border-brand-border p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('transportation.breakdowns.empty') }}
    </div>
    <div v-else class="overflow-hidden rounded-2xl border border-brand-border bg-brand-surface">
      <table class="min-w-full text-sm">
        <thead class="bg-[#F4F6F5]">
          <tr>
            <th class="px-5 py-3 text-start text-xs font-bold text-brand-text-muted">#</th>
            <th class="px-5 py-3 text-start text-xs font-bold text-brand-text-muted">
              {{ t('transportation.breakdowns.columns.run') }}
            </th>
            <th class="px-5 py-3 text-start text-xs font-bold text-brand-text-muted">
              {{ t('transportation.breakdowns.columns.vehicle') }}
            </th>
            <th class="px-5 py-3 text-start text-xs font-bold text-brand-text-muted">
              {{ t('transportation.breakdowns.columns.status') }}
            </th>
            <th class="px-5 py-3 text-start text-xs font-bold text-brand-text-muted">
              {{ t('transportation.breakdowns.columns.reason') }}
            </th>
            <th class="px-5 py-3 text-center text-xs font-bold text-brand-text-muted">
              {{ t('transportation.movements.columns.actions') }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in rows"
            :key="row.id"
            class="cursor-pointer border-t border-brand-border/80 hover:bg-brand-bg/60"
            @click="router.push(`/app/transport-breakdowns/${row.id}`)"
          >
            <td class="px-5 py-3 font-mono text-xs" dir="ltr">{{ row.id }}</td>
            <td class="px-5 py-3 font-mono text-xs" dir="ltr">{{ row.run?.run_number ?? row.vehicle_run_id }}</td>
            <td class="px-5 py-3" dir="ltr">{{ row.vehicle?.plate_number ?? '—' }}</td>
            <td class="px-5 py-3">{{ t(`transportation.breakdowns.status.${row.status}`) }}</td>
            <td class="px-5 py-3 text-xs">{{ row.reason }}</td>
            <td class="px-5 py-3 text-center" @click.stop>
              <button
                v-if="row.status === 'open'"
                type="button"
                class="text-xs font-semibold hover:underline"
                @click="onClose(row.id)"
              >
                {{ t('transportation.breakdowns.close') }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
      <div
        v-if="meta && meta.last_page > 1"
        class="flex items-center justify-between border-t border-brand-border px-5 py-3 text-sm"
      >
        <button type="button" class="inline-flex h-9 items-center px-3 disabled:opacity-40" :disabled="filters.page <= 1" @click="filters.page -= 1">
          <ChevronRight class="h-4 w-4" />
        </button>
        <span class="text-brand-text-muted">{{ filters.page }} / {{ meta.last_page }}</span>
        <button type="button" class="inline-flex h-9 items-center px-3 disabled:opacity-40" :disabled="filters.page >= meta.last_page" @click="filters.page += 1">
          <ChevronLeft class="h-4 w-4" />
        </button>
      </div>
    </div>
  </div>
</template>
