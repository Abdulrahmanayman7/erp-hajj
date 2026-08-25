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
import { useCurrentUserQuery } from '@/modules/auth/queries/useCurrentUserQuery'
import AppMobileFilters from '@/shared/components/AppMobileFilters.vue'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import { useDebouncedRef } from '@/shared/composables/useDebouncedRef'

const { t } = useI18n()
const router = useRouter()
const { data: currentUser } = useCurrentUserQuery()
const tenantTimezone = computed(() => currentUser.value?.tenant?.timezone || 'Asia/Riyadh')

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

const committedSearch = useDebouncedRef(() => filters.search ?? '')
const queryParams = computed(() => ({ ...filters, search: committedSearch.value }))
const { data, isLoading, isError, refetch, isFetching } = useAuditLogsQuery(queryParams)

const rows = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta)

const activeFilterCount = computed(() => {
  let count = 0
  if (filters.event_type) count += 1
  if (filters.actor_user_id) count += 1
  if (filters.entity_type) count += 1
  if (filters.entity_id) count += 1
  if (filters.date_from) count += 1
  if (filters.date_to) count += 1
  if (filters.correlation_id) count += 1
  return count
})

function resetFilters(): void {
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

watch(
  () => [
    committedSearch.value,
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

function openRow(id: number): void {
  void router.push(`/app/audit/${id}`)
}

function formatTime(iso: string): string {
  try {
    return new Intl.DateTimeFormat('ar-SA', {
      dateStyle: 'medium',
      timeStyle: 'short',
      timeZone: tenantTimezone.value,
    }).format(new Date(iso))
  } catch {
    return iso
  }
}
</script>

<template>
  <div class="space-y-6">
    <AppPageHeader :title="t('audit.title')" :subtitle="t('audit.subtitle')">
      <template #actions>
        <button
          type="button"
          class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-4 text-sm font-semibold text-brand-text transition hover:bg-brand-bg disabled:opacity-40 sm:w-auto"
          :disabled="isFetching"
          @click="refetch()"
        >
          <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': isFetching }" />
          <span>{{ t('audit.refresh') }}</span>
        </button>
      </template>
    </AppPageHeader>

    <AppMobileFilters
      v-model:search="filters.search"
      :search-placeholder="t('audit.filters.searchPlaceholder')"
      :active-count="activeFilterCount"
      @reset="resetFilters"
    >
      <template #desktop>
        <div
          class="grid gap-3 rounded-2xl border border-brand-border bg-brand-surface p-4 shadow-[0_1px_2px_rgba(23,32,29,0.03)] md:grid-cols-2 xl:grid-cols-4"
        >
          <label class="block text-sm md:col-span-2 xl:col-span-1">
            <span class="mb-1 block text-brand-text-secondary">{{ t('audit.filters.search') }}</span>
            <div class="relative">
              <Search
                class="pointer-events-none absolute inset-s-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-text-muted"
                :stroke-width="1.75"
              />
              <input
                v-model="filters.search"
                type="search"
                class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface pe-3 ps-10 text-sm text-brand-text outline-none transition placeholder:text-brand-text-muted focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
                :placeholder="t('audit.filters.searchPlaceholder')"
              />
            </div>
          </label>
          <label class="block text-sm">
            <span class="mb-1 block text-brand-text-secondary">{{ t('audit.filters.eventType') }}</span>
            <input
              v-model="filters.event_type"
              type="text"
              class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface px-3 text-sm text-brand-text outline-none transition focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
            />
          </label>
          <label class="block text-sm">
            <span class="mb-1 block text-brand-text-secondary">{{ t('audit.filters.entityType') }}</span>
            <input
              v-model="filters.entity_type"
              type="text"
              class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface px-3 text-sm text-brand-text outline-none transition focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
            />
          </label>
          <label class="block text-sm">
            <span class="mb-1 block text-brand-text-secondary">{{ t('audit.filters.entityId') }}</span>
            <input
              v-model="filters.entity_id"
              type="number"
              min="1"
              class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface px-3 text-sm text-brand-text outline-none transition focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
            />
          </label>
          <label class="block text-sm">
            <span class="mb-1 block text-brand-text-secondary">{{ t('audit.filters.dateFrom') }}</span>
            <input
              v-model="filters.date_from"
              type="date"
              class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface px-3 text-sm text-brand-text outline-none transition focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
            />
          </label>
          <label class="block text-sm">
            <span class="mb-1 block text-brand-text-secondary">{{ t('audit.filters.dateTo') }}</span>
            <input
              v-model="filters.date_to"
              type="date"
              class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface px-3 text-sm text-brand-text outline-none transition focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
            />
          </label>
          <label class="block text-sm">
            <span class="mb-1 block text-brand-text-secondary">{{ t('audit.filters.correlationId') }}</span>
            <input
              v-model="filters.correlation_id"
              type="text"
              class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface px-3 text-sm text-brand-text outline-none transition focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
            />
          </label>
        </div>
      </template>
      <template #filters>
        <div class="space-y-3">
          <label class="block text-sm">
            <span class="mb-1 block text-brand-text-secondary">{{ t('audit.filters.eventType') }}</span>
            <input
              v-model="filters.event_type"
              type="text"
              class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface px-3 text-sm text-brand-text outline-none transition focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
            />
          </label>
          <label class="block text-sm">
            <span class="mb-1 block text-brand-text-secondary">{{ t('audit.filters.entityType') }}</span>
            <input
              v-model="filters.entity_type"
              type="text"
              class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface px-3 text-sm text-brand-text outline-none transition focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
            />
          </label>
          <label class="block text-sm">
            <span class="mb-1 block text-brand-text-secondary">{{ t('audit.filters.entityId') }}</span>
            <input
              v-model="filters.entity_id"
              type="number"
              min="1"
              class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface px-3 text-sm text-brand-text outline-none transition focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
            />
          </label>
          <label class="block text-sm">
            <span class="mb-1 block text-brand-text-secondary">{{ t('audit.filters.dateFrom') }}</span>
            <input
              v-model="filters.date_from"
              type="date"
              class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface px-3 text-sm text-brand-text outline-none transition focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
            />
          </label>
          <label class="block text-sm">
            <span class="mb-1 block text-brand-text-secondary">{{ t('audit.filters.dateTo') }}</span>
            <input
              v-model="filters.date_to"
              type="date"
              class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface px-3 text-sm text-brand-text outline-none transition focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
            />
          </label>
          <label class="block text-sm">
            <span class="mb-1 block text-brand-text-secondary">{{ t('audit.filters.correlationId') }}</span>
            <input
              v-model="filters.correlation_id"
              type="text"
              class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface px-3 text-sm text-brand-text outline-none transition focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
            />
          </label>
        </div>
      </template>
    </AppMobileFilters>

    <div
      v-if="isLoading"
      class="space-y-3"
      aria-busy="true"
    >
      <div v-for="n in 5" :key="n" class="h-14 animate-pulse rounded-2xl bg-brand-bg" />
    </div>

    <div
      v-else-if="isError"
      class="rounded-2xl border border-red-200 bg-red-50 p-6 text-center"
    >
      <p class="text-red-800">{{ t('audit.error') }}</p>
      <button
        type="button"
        class="mt-3 rounded-xl bg-red-700 px-4 py-2 text-sm text-white"
        @click="refetch()"
      >
        {{ t('audit.retry') }}
      </button>
    </div>

    <div
      v-else-if="rows.length === 0"
      class="rounded-2xl border border-dashed border-brand-border bg-brand-surface p-10 text-center text-brand-text-secondary"
    >
      {{ t('audit.empty') }}
    </div>

    <template v-else>
      <div
        class="hidden overflow-hidden rounded-2xl border border-brand-border bg-brand-surface shadow-[0_1px_2px_rgba(23,32,29,0.03)] md:block"
      >
        <table class="min-w-full text-sm">
          <thead class="bg-[#F4F6F5] text-brand-text-muted">
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
              class="cursor-pointer border-t border-brand-border transition hover:bg-brand-primary-soft/40"
              @click="openRow(row.id)"
            >
              <td class="whitespace-nowrap px-4 py-3 text-brand-text-secondary">
                {{ formatTime(row.created_at) }}
              </td>
              <td class="px-4 py-3 font-medium text-brand-text">
                {{ auditEventLabel(row.event_type) }}
              </td>
              <td class="px-4 py-3 text-brand-text-secondary">
                {{ auditActorDisplay(row.actor_type, row.actor_label) }}
              </td>
              <td class="px-4 py-3 text-brand-text-secondary">{{ auditEntityDisplay(row) }}</td>
              <td class="px-4 py-3 text-brand-text-muted">{{ row.reason || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="space-y-3 md:hidden">
        <button
          v-for="row in rows"
          :key="row.id"
          type="button"
          class="w-full rounded-2xl border border-brand-border bg-brand-surface p-4 text-start shadow-[0_1px_2px_rgba(23,32,29,0.03)] transition active:bg-brand-bg"
          @click="openRow(row.id)"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <h3 class="truncate font-bold text-brand-text">
                {{ auditActorDisplay(row.actor_type, row.actor_label) }}
              </h3>
              <p class="mt-1 text-sm font-semibold text-brand-text-secondary">
                {{ auditEventLabel(row.event_type) }}
              </p>
            </div>
          </div>
          <dl class="mt-3 grid grid-cols-2 gap-2 text-xs text-brand-text-secondary">
            <div>
              <dt>{{ t('audit.columns.entity') }}</dt>
              <dd class="mt-0.5 font-medium text-brand-text">{{ auditEntityDisplay(row) }}</dd>
            </div>
            <div>
              <dt>{{ t('audit.columns.time') }}</dt>
              <dd class="mt-0.5 font-medium text-brand-text">{{ formatTime(row.created_at) }}</dd>
            </div>
          </dl>
        </button>
      </div>

      <div
        v-if="meta"
        class="flex items-center justify-between gap-3 text-sm text-brand-text-secondary"
      >
        <span>{{ t('audit.pagination', { page: meta.current_page, total: meta.total }) }}</span>
        <div class="flex gap-2">
          <button
            type="button"
            class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-brand-border bg-brand-surface disabled:opacity-40"
            :disabled="meta.current_page <= 1"
            @click="filters.page = Math.max(1, (filters.page ?? 1) - 1)"
          >
            <ChevronRight class="h-4 w-4" />
          </button>
          <button
            type="button"
            class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-brand-border bg-brand-surface disabled:opacity-40"
            :disabled="meta.current_page >= meta.last_page"
            @click="filters.page = (filters.page ?? 1) + 1"
          >
            <ChevronLeft class="h-4 w-4" />
          </button>
        </div>
      </div>
    </template>
  </div>
</template>
