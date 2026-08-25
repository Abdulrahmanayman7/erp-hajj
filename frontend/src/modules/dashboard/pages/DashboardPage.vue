<script setup lang="ts">
import { computed } from 'vue'
import { useQueryClient } from '@tanstack/vue-query'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { Bell, RefreshCw } from 'lucide-vue-next'

import { useCurrentUserQuery } from '@/modules/auth/queries/useCurrentUserQuery'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import { useToast } from '@/shared/composables/useToast'

import DashboardAttention from '../components/DashboardAttention.vue'
import DashboardKpiGrid from '../components/DashboardKpiGrid.vue'
import DashboardResources from '../components/DashboardResources.vue'
import DashboardToday from '../components/DashboardToday.vue'
import DashboardWork from '../components/DashboardWork.vue'
import {
  dashboardQueryKey,
  useDashboardQuery,
} from '../queries/useDashboardQuery'
import type {
  DashboardKpis,
  DashboardResources as DashboardResourcesPayload,
  DashboardToday as DashboardTodayPayload,
  DashboardWork as DashboardWorkPayload,
} from '../types/dashboard'
import {
  asSparseRecord,
  formatDashboardDate,
  formatDashboardGeneratedAt,
  friendlyTimezone,
  hasWorkMetrics,
  isOperationallyEmpty,
  isSafeAppHref,
} from '../utils/dashboardDisplay'

const { t } = useI18n()
const queryClient = useQueryClient()
const toast = useToast()
const { data: user } = useCurrentUserQuery()
const { data, isLoading, isError, isFetching } = useDashboardQuery()

const timezone = computed(
  () => data.value?.meta?.timezone ?? user.value?.tenant?.timezone ?? 'Asia/Riyadh',
)
const todayLabel = computed(() => formatDashboardDate(timezone.value))
const generatedAtLabel = computed(() =>
  formatDashboardGeneratedAt(data.value?.meta?.generated_at, timezone.value),
)
const timezoneLabel = computed(() => friendlyTimezone(timezone.value))
const tenantName = computed(() => user.value?.tenant?.name ?? null)

const contextLine = computed(() => {
  const parts = [
    tenantName.value,
    todayLabel.value,
    t('dashboard.timezoneAt', { timezone: timezoneLabel.value }),
  ].filter(Boolean)
  return parts.join(' · ')
})

const notifications = computed(() => data.value?.notifications)
const unreadCount = computed(() => notifications.value?.unread_count ?? 0)
const notificationsHref = computed(() => {
  const href = notifications.value?.href ?? '/app/notifications'
  return isSafeAppHref(href) ? href : '/app/notifications'
})

const showEmptyModules = computed(() => {
  if (!data.value) return false
  return isOperationallyEmpty(data.value)
})

const showAttention = computed(() => Array.isArray(data.value?.attention))
const showToday = computed(() => asSparseRecord<DashboardTodayPayload>(data.value?.today) != null)
const showWork = computed(() => hasWorkMetrics(data.value?.kpis, data.value?.work))
const showResources = computed(() => {
  const kpis = asSparseRecord<DashboardKpis>(data.value?.kpis)
  const resources = asSparseRecord<DashboardResourcesPayload>(data.value?.resources)
  if (resources?.my_custodies) return true
  if (!kpis) return false
  return Boolean(
    kpis.inventory_low ||
      kpis.inventory_out ||
      kpis.assets_available ||
      kpis.assets_in_use ||
      kpis.assets_maintenance ||
      kpis.custodies_due_soon,
  )
})
const workPayload = computed(() => asSparseRecord<DashboardWorkPayload>(data.value?.work))
const todayPayload = computed(() => asSparseRecord<DashboardTodayPayload>(data.value?.today))
const resourcesPayload = computed(() => asSparseRecord<DashboardResourcesPayload>(data.value?.resources))
const kpisPayload = computed(() => asSparseRecord<DashboardKpis>(data.value?.kpis) ?? {})

async function onRefresh(): Promise<void> {
  try {
    await queryClient.refetchQueries({ queryKey: dashboardQueryKey })
  } catch {
    toast.error(t('dashboard.refreshError'))
  }
}
</script>

<template>
  <div class="mx-auto max-w-[1280px] min-w-0 space-y-5 overflow-x-hidden">
    <AppPageHeader
      :title="t('dashboard.title')"
      :subtitle="t('dashboard.subtitle')"
    >
      <template #actions>
        <RouterLink
          v-if="notifications && unreadCount > 0"
          :to="notificationsHref"
          class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-3 text-sm font-semibold text-brand-text transition hover:border-brand-primary/30 hover:bg-brand-primary-soft/50 sm:h-auto sm:w-auto sm:rounded-full sm:py-1.5 sm:text-xs"
        >
          <Bell class="h-3.5 w-3.5 text-brand-primary" :stroke-width="1.75" />
          <span>{{ t('dashboard.unreadChip', { count: unreadCount }) }}</span>
        </RouterLink>

        <div
          class="flex w-full items-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-2 py-1.5 sm:w-auto"
        >
          <span class="hidden min-w-0 truncate text-xs text-brand-text-muted sm:inline">
            {{ t('dashboard.lastUpdated', { time: generatedAtLabel }) }}
          </span>
          <button
            type="button"
            class="inline-flex h-11 flex-1 items-center justify-center gap-1.5 rounded-lg px-3 text-sm font-semibold text-brand-text transition hover:bg-brand-bg disabled:opacity-60 sm:h-8 sm:flex-none sm:px-2"
            :disabled="isFetching"
            :aria-label="t('dashboard.refresh')"
            @click="onRefresh"
          >
            <RefreshCw
              class="h-4 w-4"
              :class="isFetching ? 'animate-spin' : ''"
              :stroke-width="1.75"
            />
            <span>{{ t('dashboard.refresh') }}</span>
          </button>
        </div>
      </template>
    </AppPageHeader>

    <p class="-mt-2 break-words text-xs leading-5 text-brand-text-muted">
      {{ contextLine }}
    </p>

    <div
      v-if="isLoading"
      class="space-y-6"
      aria-busy="true"
    >
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <div
          v-for="n in 6"
          :key="n"
          class="h-32 animate-pulse rounded-2xl border border-brand-border bg-brand-bg"
        />
      </div>
      <div class="grid grid-cols-1 gap-4">
        <div v-for="n in 4" :key="n" class="h-52 animate-pulse rounded-2xl border border-brand-border bg-brand-bg" />
      </div>
    </div>

    <div
      v-else-if="isError"
      class="rounded-2xl border border-red-200 bg-red-50 p-6 text-center sm:p-10"
      role="alert"
    >
      <p class="text-sm text-red-700">
        {{ t('dashboard.loadError') }}
      </p>
      <button
        type="button"
        class="mt-3 text-sm font-semibold text-red-800 underline"
        @click="onRefresh"
      >
        {{ t('dashboard.retry') }}
      </button>
    </div>

    <template v-else-if="data">
      <div
        v-if="showEmptyModules"
        class="rounded-2xl border border-brand-border bg-brand-surface p-6 text-center text-sm text-brand-text-secondary sm:p-8"
      >
        {{ t('dashboard.noModules') }}
      </div>

      <template v-else>
        <!-- Mobile priority: attention → KPIs → today → work → resources -->
        <DashboardAttention
          v-if="showAttention"
          :items="data.attention ?? []"
        />

        <DashboardKpiGrid :kpis="kpisPayload" />

        <DashboardToday
          v-if="showToday"
          :today="todayPayload"
          :timezone="timezone"
        />

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
          <DashboardWork
            v-if="showWork"
            :work="workPayload"
            :kpis="kpisPayload"
          />
          <DashboardResources
            v-if="showResources"
            :kpis="kpisPayload"
            :resources="resourcesPayload"
          />
        </div>
      </template>
    </template>
  </div>
</template>
