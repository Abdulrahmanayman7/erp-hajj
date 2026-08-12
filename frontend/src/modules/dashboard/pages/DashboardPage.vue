<script setup lang="ts">
import { computed } from 'vue'
import { useQueryClient } from '@tanstack/vue-query'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { Bell, RefreshCw } from 'lucide-vue-next'

import { useCurrentUserQuery } from '@/modules/auth/queries/useCurrentUserQuery'

import DashboardAttention from '../components/DashboardAttention.vue'
import DashboardKpiGrid from '../components/DashboardKpiGrid.vue'
import DashboardResources from '../components/DashboardResources.vue'
import DashboardToday from '../components/DashboardToday.vue'
import DashboardWork from '../components/DashboardWork.vue'
import {
  dashboardQueryKey,
  useDashboardQuery,
} from '../queries/useDashboardQuery'
import {
  formatDashboardDate,
  isOperationallyEmpty,
  isSafeAppHref,
} from '../utils/dashboardDisplay'

const { t } = useI18n()
const queryClient = useQueryClient()
const { data: user } = useCurrentUserQuery()
const { data, isLoading, isError, isFetching } = useDashboardQuery()

const timezone = computed(
  () => data.value?.meta?.timezone ?? user.value?.tenant?.timezone ?? 'Asia/Riyadh',
)
const todayLabel = computed(() => formatDashboardDate(timezone.value))
const tenantName = computed(() => user.value?.tenant?.name ?? null)

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

const showAttention = computed(() => data.value?.attention !== undefined)
const showToday = computed(() => data.value?.today != null)
const showWork = computed(() => data.value?.work?.my_tasks != null)
const showResources = computed(() => {
  const kpis = data.value?.kpis
  const resources = data.value?.resources
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

async function onRefresh(): Promise<void> {
  await queryClient.invalidateQueries({ queryKey: dashboardQueryKey })
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h2 class="text-[1.75rem] font-bold text-brand-text">
          {{ t('dashboard.title') }}
        </h2>
        <p class="mt-1.5 text-sm text-brand-text-secondary">
          {{ t('dashboard.subtitle') }}
        </p>
        <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-brand-text-muted">
          <span v-if="tenantName">{{ tenantName }}</span>
          <span>{{ todayLabel }}</span>
        </div>
      </div>

      <div class="flex flex-wrap items-center gap-3">
        <RouterLink
          v-if="notifications && unreadCount > 0"
          :to="notificationsHref"
          class="inline-flex items-center gap-2 rounded-full border border-brand-border bg-brand-surface px-3 py-1.5 text-xs font-semibold text-brand-text transition hover:border-brand-primary/30 hover:bg-brand-primary-soft/50"
        >
          <Bell class="h-3.5 w-3.5 text-brand-primary" :stroke-width="1.75" />
          <span>{{ t('dashboard.unreadChip', { count: unreadCount }) }}</span>
        </RouterLink>

        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-3.5 py-2 text-sm font-semibold text-brand-text transition hover:border-brand-primary/30 hover:bg-brand-primary-soft/40 disabled:opacity-60"
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
    </div>

    <div
      v-if="isLoading"
      class="space-y-6"
      aria-busy="true"
    >
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <div
          v-for="n in 6"
          :key="n"
          class="h-28 animate-pulse rounded-2xl border border-brand-border bg-[#F4F6F5]"
        />
      </div>
      <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <div class="h-48 animate-pulse rounded-2xl border border-brand-border bg-[#F4F6F5]" />
        <div class="h-48 animate-pulse rounded-2xl border border-brand-border bg-[#F4F6F5]" />
      </div>
    </div>

    <div
      v-else-if="isError"
      class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center"
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
        class="rounded-2xl border border-brand-border bg-brand-surface p-8 text-center text-sm text-brand-text-secondary"
      >
        {{ t('dashboard.noModules') }}
      </div>

      <template v-else>
        <DashboardKpiGrid :kpis="data.kpis" />

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
          <DashboardAttention
            v-if="showAttention"
            :items="data.attention"
          />
          <DashboardToday
            v-if="showToday"
            :today="data.today"
            :timezone="timezone"
          />
        </div>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
          <DashboardWork
            v-if="showWork"
            :work="data.work"
          />
          <DashboardResources
            v-if="showResources"
            :kpis="data.kpis"
            :resources="data.resources"
          />
        </div>
      </template>
    </template>
  </div>
</template>
