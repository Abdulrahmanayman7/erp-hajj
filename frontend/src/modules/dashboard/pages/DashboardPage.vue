<script setup lang="ts">
import { computed } from 'vue'
import { useQueryClient } from '@tanstack/vue-query'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { Bell, RefreshCw } from 'lucide-vue-next'

import { useCurrentUserQuery } from '@/modules/auth/queries/useCurrentUserQuery'
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

const metaLine = computed(() => {
  const parts = [
    tenantName.value,
    t('dashboard.timezoneAt', { timezone: timezoneLabel.value }),
  ].filter(Boolean)
  if (generatedAtLabel.value) {
    parts.push(t('dashboard.lastUpdated', { time: generatedAtLabel.value }))
  }
  return parts.join(' · ')
})

const firstName = computed(() => {
  const name = user.value?.name?.trim()
  if (!name) return t('auth.userFallback')
  return name.split(/\s+/)[0] ?? name
})

const greeting = computed(() => {
  try {
    const hour = Number(
      new Intl.DateTimeFormat('en-GB', {
        hour: 'numeric',
        hour12: false,
        timeZone: timezone.value,
      }).format(new Date()),
    )
    if (hour < 12) return t('dashboard.greetingMorning', { name: firstName.value })
    if (hour < 18) return t('dashboard.greetingAfternoon', { name: firstName.value })
    return t('dashboard.greetingEvening', { name: firstName.value })
  } catch {
    return t('dashboard.greetingGeneric', { name: firstName.value })
  }
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
  <div class="mx-auto max-w-[1280px] min-w-0 space-y-5 md:space-y-6">
    <!-- Lightweight page intro — no marketing card -->
    <header class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
      <div class="min-w-0 space-y-1">
        <h1 class="app-type-page">{{ greeting }}</h1>
        <p class="app-type-secondary max-w-xl">{{ t('dashboard.subtitle') }}</p>
        <p class="app-type-caption pt-0.5">{{ todayLabel }}</p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <RouterLink
          v-if="notifications && unreadCount > 0"
          :to="notificationsHref"
          class="inline-flex min-h-11 items-center justify-center gap-2 rounded-[10px] border border-brand-border bg-brand-surface px-3 text-sm font-medium text-brand-text transition hover:bg-[var(--surface-subtle)]"
        >
          <Bell class="h-3.5 w-3.5 text-brand-primary" :stroke-width="1.75" aria-hidden="true" />
          <span>{{ t('dashboard.unreadChip', { count: unreadCount }) }}</span>
        </RouterLink>
        <button
          type="button"
          class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-[10px] border border-brand-border bg-brand-surface px-3 text-sm font-medium text-brand-text-secondary transition hover:bg-[var(--surface-subtle)] hover:text-brand-text disabled:opacity-60"
          :disabled="isFetching"
          :aria-label="t('dashboard.refresh')"
          @click="onRefresh"
        >
          <RefreshCw
            class="h-4 w-4"
            :class="isFetching ? 'animate-spin' : ''"
            :stroke-width="1.75"
            aria-hidden="true"
          />
          <span class="hidden sm:inline">{{ t('dashboard.refresh') }}</span>
        </button>
      </div>
    </header>

    <p v-if="metaLine" class="app-type-caption -mt-2 break-words">
      {{ metaLine }}
    </p>

    <div v-if="isLoading" class="space-y-5" aria-busy="true">
      <div class="h-[72px] animate-pulse rounded-[14px] bg-[var(--success-soft)]" />
      <div class="grid grid-cols-2 gap-2.5 xl:grid-cols-3">
        <div
          v-for="n in 6"
          :key="n"
          class="h-24 animate-pulse rounded-[14px] border border-brand-border bg-brand-surface"
        />
      </div>
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div
          v-for="n in 2"
          :key="n"
          class="h-40 animate-pulse rounded-[14px] border border-brand-border bg-brand-surface"
        />
      </div>
    </div>

    <div
      v-else-if="isError"
      class="rounded-[14px] border border-[color-mix(in_srgb,var(--danger)_25%,var(--border))] bg-[var(--danger-soft)] p-6 text-center"
      role="alert"
    >
      <p class="text-sm text-red-800">{{ t('dashboard.loadError') }}</p>
      <button
        type="button"
        class="mt-3 inline-flex min-h-11 items-center justify-center px-3 text-sm font-semibold text-red-900 underline"
        @click="onRefresh"
      >
        {{ t('dashboard.retry') }}
      </button>
    </div>

    <template v-else-if="data">
      <div
        v-if="showEmptyModules"
        class="app-surface-flat p-8 text-center text-sm text-brand-text-secondary"
      >
        {{ t('dashboard.noModules') }}
      </div>

      <template v-else>
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

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 md:gap-5">
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
