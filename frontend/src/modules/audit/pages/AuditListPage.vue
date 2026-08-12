<script setup lang="ts">
import { computed, reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { ChevronLeft, ChevronRight, RefreshCw, Search } from 'lucide-vue-next'

import { useAuditLogsQuery } from '../queries/useAuditQuery'
import {
  auditActorDisplay,
  auditEntityDisplay,
  auditEventLabel,
} from '../utils/auditDisplay'
import type { ListAuditLogsParams } from '../types/audit'

const { t } = useI18n()
const router = useRouter()

const filters = reactive<ListAuditLogsParams>({
  search: '',
  event_type: '',
  actor_user_id: '',
  entity_type: '',
  entity_id: '',
  date_from: '',
  date_to: '',
  correlation_id: '',
  page: 1,
  per_page: 20,
})

const queryParams = computed(() => ({ ...filters }))
const { data, isLoading, isError, refetch, isFetching } = useAuditLogsQuery(queryParams)

const rows = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta)

watch(
  () => [
    filters.search,
    filters.event_type,
    filters.actor_user_id,
    filters.entity_type,
    filters.entity_id,
    filters.date_from,
    filters.date_to,
    filters.correlation_id,
  ],
  () => {
    filters.page = 1
  },
)

function clearFilters(): void {
  filters.search = ''
  filters.event_type = ''
  filters.actor_user_id = ''
  filters.entity_type = ''
  filters.entity_id = ''
  filters.date_from = ''
  filters.date_to = ''
  filters.correlation_id = ''
  filters.page = 1
}

function openRow(id: number): void {
  void router.push(`/app/audit/${id}`)
}

function formatTime(iso: string): string {
  try {
    return new Intl.DateTimeFormat('ar-SA', {
      dateStyle: 'medium',
      timeStyle: 'short',
      timeZone: 'Asia/Riyadh',
    }).format(new Date(iso))
  } catch {
    return iso
  }
}
</script>

<template>
  <div class="space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ t('audit.title') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ t('audit.subtitle') }}</p>
      </div>
      <button
        type="button"
        class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
        :disabled="isFetching"
        @click="refetch()"
      >
        <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': isFetching }" />
        {{ t('audit.refresh') }}
      </button>
    </header>

    <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
      <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        <label class="block text-sm">
          <span class="mb-1 block text-slate-600">{{ t('audit.filters.search') }}</span>
          <div class="relative">
            <Search class="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
              v-model="filters.search"
              type="search"
              class="w-full rounded-lg border border-slate-200 py-2 pe-3 ps-9 text-sm"
              :placeholder="t('audit.filters.searchPlaceholder')"
            />
          </div>
        </label>
        <label class="block text-sm">
          <span class="mb-1 block text-slate-600">{{ t('audit.filters.eventType') }}</span>
          <input v-model="filters.event_type" type="text" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
        </label>
        <label class="block text-sm">
          <span class="mb-1 block text-slate-600">{{ t('audit.filters.entityType') }}</span>
          <input v-model="filters.entity_type" type="text" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
        </label>
        <label class="block text-sm">
          <span class="mb-1 block text-slate-600">{{ t('audit.filters.entityId') }}</span>
          <input v-model="filters.entity_id" type="number" min="1" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
        </label>
        <label class="block text-sm">
          <span class="mb-1 block text-slate-600">{{ t('audit.filters.dateFrom') }}</span>
          <input v-model="filters.date_from" type="date" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
        </label>
        <label class="block text-sm">
          <span class="mb-1 block text-slate-600">{{ t('audit.filters.dateTo') }}</span>
          <input v-model="filters.date_to" type="date" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
        </label>
        <label class="block text-sm">
          <span class="mb-1 block text-slate-600">{{ t('audit.filters.correlationId') }}</span>
          <input v-model="filters.correlation_id" type="text" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
        </label>
        <div class="flex items-end">
          <button type="button" class="rounded-lg px-3 py-2 text-sm text-emerald-800 hover:bg-emerald-50" @click="clearFilters">
            {{ t('audit.filters.clear') }}
          </button>
        </div>
      </div>
    </section>

    <section v-if="isLoading" class="space-y-3" aria-busy="true">
      <div v-for="n in 5" :key="n" class="h-14 animate-pulse rounded-xl bg-slate-100" />
    </section>

    <section v-else-if="isError" class="rounded-xl border border-red-200 bg-red-50 p-6 text-center">
      <p class="text-red-800">{{ t('audit.error') }}</p>
      <button type="button" class="mt-3 rounded-lg bg-red-700 px-4 py-2 text-sm text-white" @click="refetch()">
        {{ t('audit.retry') }}
      </button>
    </section>

    <section v-else-if="rows.length === 0" class="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-600">
      {{ t('audit.empty') }}
    </section>

    <section v-else class="space-y-3">
      <div class="hidden overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm md:block">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50 text-slate-600">
            <tr>
              <th class="px-4 py-3 text-start font-medium">{{ t('audit.columns.time') }}</th>
              <th class="px-4 py-3 text-start font-medium">{{ t('audit.columns.event') }}</th>
              <th class="px-4 py-3 text-start font-medium">{{ t('audit.columns.actor') }}</th>
              <th class="px-4 py-3 text-start font-medium">{{ t('audit.columns.entity') }}</th>
              <th class="px-4 py-3 text-start font-medium">{{ t('audit.columns.description') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in rows"
              :key="row.id"
              class="cursor-pointer border-t border-slate-100 hover:bg-emerald-50/40"
              @click="openRow(row.id)"
            >
              <td class="px-4 py-3 whitespace-nowrap text-slate-700">{{ formatTime(row.created_at) }}</td>
              <td class="px-4 py-3 font-medium text-slate-900">{{ auditEventLabel(row.event_type) }}</td>
              <td class="px-4 py-3 text-slate-700">{{ auditActorDisplay(row.actor_type, row.actor_label) }}</td>
              <td class="px-4 py-3 text-slate-700">{{ auditEntityDisplay(row) }}</td>
              <td class="px-4 py-3 text-slate-600">{{ row.reason || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="space-y-3 md:hidden">
        <button
          v-for="row in rows"
          :key="row.id"
          type="button"
          class="w-full rounded-xl border border-slate-200 bg-white p-4 text-start shadow-sm"
          @click="openRow(row.id)"
        >
          <div class="font-semibold text-slate-900">{{ auditEventLabel(row.event_type) }}</div>
          <div class="mt-1 text-xs text-slate-500">{{ formatTime(row.created_at) }}</div>
          <div class="mt-2 text-sm text-slate-700">{{ auditActorDisplay(row.actor_type, row.actor_label) }}</div>
          <div class="mt-1 text-sm text-slate-600">{{ auditEntityDisplay(row) }}</div>
        </button>
      </div>

      <div v-if="meta" class="flex items-center justify-between gap-3 text-sm text-slate-600">
        <span>{{ t('audit.pagination', { page: meta.current_page, total: meta.total }) }}</span>
        <div class="flex gap-2">
          <button
            type="button"
            class="rounded-lg border border-slate-200 bg-white p-2 disabled:opacity-40"
            :disabled="meta.current_page <= 1"
            @click="filters.page = Math.max(1, (filters.page ?? 1) - 1)"
          >
            <ChevronRight class="h-4 w-4" />
          </button>
          <button
            type="button"
            class="rounded-lg border border-slate-200 bg-white p-2 disabled:opacity-40"
            :disabled="meta.current_page >= meta.last_page"
            @click="filters.page = (filters.page ?? 1) + 1"
          >
            <ChevronLeft class="h-4 w-4" />
          </button>
        </div>
      </div>
    </section>
  </div>
</template>
