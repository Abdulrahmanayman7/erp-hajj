<script setup lang="ts">
import { computed } from 'vue'
import { useQueryClient } from '@tanstack/vue-query'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { CalendarDays, Moon, Plus, RefreshCw, Sun } from 'lucide-vue-next'

import { useCurrentUserQuery } from '@/modules/auth/queries/useCurrentUserQuery'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useQuickActions } from '@/shared/composables/useQuickActions'
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
  dashboardHour,
  formatDashboardCalendar,
  formatDashboardGeneratedAt,
  friendlyTimezone,
  hasWorkMetrics,
  isDashboardDaytime,
  isOperationallyEmpty,
  isSafeAppHref,
} from '../utils/dashboardDisplay'

const { t } = useI18n()
const queryClient = useQueryClient()
const toast = useToast()
const { can } = usePermissions()
const { actions: quickActions } = useQuickActions()
const { data: user } = useCurrentUserQuery()
const { data, isLoading, isError, isFetching } = useDashboardQuery()

const timezone = computed(
  () => data.value?.meta?.timezone ?? user.value?.tenant?.timezone ?? 'Asia/Riyadh',
)
const calendar = computed(() => formatDashboardCalendar(timezone.value))
const generatedAtLabel = computed(() =>
  formatDashboardGeneratedAt(data.value?.meta?.generated_at, timezone.value),
)
const timezoneLabel = computed(() => friendlyTimezone(timezone.value))
const tenantName = computed(() => user.value?.tenant?.name ?? null)
const canCreateTask = computed(() => can('tasks.create'))
const daytime = computed(() => isDashboardDaytime(dashboardHour(timezone.value)))

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
  const hour = dashboardHour(timezone.value)
  if (hour == null) return t('dashboard.greetingGeneric', { name: firstName.value })
  if (hour < 12) return t('dashboard.greetingMorning', { name: firstName.value })
  if (hour < 18) return t('dashboard.greetingAfternoon', { name: firstName.value })
  return t('dashboard.greetingEvening', { name: firstName.value })
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
    <header class="flex flex-col-reverse gap-3 md:flex-row md:items-stretch md:justify-between">
      <div class="flex min-w-0 items-start gap-3">
        <span
          class="mt-0.5 inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl"
          :class="daytime ? 'bg-[var(--warning-soft)] text-brand-warning' : 'bg-brand-primary-soft text-brand-primary-dark'"
          aria-hidden="true"
        >
          <Sun v-if="daytime" class="h-5 w-5" :stroke-width="1.9" />
          <Moon v-else class="h-5 w-5" :stroke-width="1.9" />
        </span>
        <div class="min-w-0 space-y-1">
          <h1 class="app-type-page">{{ greeting }}</h1>
          <p class="app-type-secondary max-w-xl">{{ t('dashboard.subtitle') }}</p>
          <p class="app-type-caption pt-0.5 md:hidden">
            {{ calendar.weekday }} · {{ calendar.gregorian }}
          </p>
        </div>
      </div>

      <div
        class="hidden shrink-0 items-center gap-3 rounded-[16px] border border-brand-border bg-brand-surface px-3.5 py-2.5 md:flex"
        :aria-label="t('dashboard.dateCardLabel')"
      >
        <span
          class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-brand-primary-soft text-brand-primary-dark"
          aria-hidden="true"
        >
          <CalendarDays class="h-5 w-5" :stroke-width="1.85" />
        </span>
        <div class="min-w-0 text-start">
          <p class="text-sm font-bold text-brand-text">{{ calendar.weekday }}</p>
          <p class="text-xs text-brand-text-secondary">{{ calendar.gregorian }}</p>
          <p v-if="calendar.hijri" class="text-[11px] text-brand-text-muted">{{ calendar.hijri }}</p>
        </div>
      </div>
    </header>

    <div class="flex flex-wrap items-center gap-2">
      <RouterLink
        v-if="notifications && unreadCount > 0"
        :to="notificationsHref"
        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-[10px] border border-brand-border bg-brand-surface px-3 text-sm font-medium text-brand-text transition hover:bg-[var(--surface-subtle)]"
      >
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

    <p v-if="metaLine" class="app-type-caption -mt-2 break-words">
      {{ metaLine }}
    </p>

    <div v-if="isLoading" class="space-y-5" aria-busy="true">
      <div class="h-[72px] animate-pulse rounded-[16px] bg-[var(--success-soft)]" />
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
      class="rounded-[16px] border border-[color-mix(in_srgb,var(--danger)_25%,var(--border))] bg-[var(--danger-soft)] p-6 text-center"
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
        <div v-if="showAttention || canCreateTask" class="flex flex-col gap-3 md:flex-row md:items-stretch">
          <div v-if="showAttention" class="min-w-0 flex-1">
            <DashboardAttention :items="data.attention ?? []" />
          </div>
          <RouterLink
            v-if="canCreateTask"
            to="/app/tasks?create=1"
            class="app-btn-primary shrink-0 md:self-center"
          >
            <Plus class="h-4 w-4" :stroke-width="2" aria-hidden="true" />
            {{ t('quickActions.addTask') }}
          </RouterLink>
        </div>

        <DashboardKpiGrid :kpis="kpisPayload" />

        <nav
          v-if="quickActions.length > 0"
          class="flex gap-2 overflow-x-auto pb-1 md:hidden"
          :aria-label="t('dashboard.shortcuts')"
        >
          <RouterLink
            v-for="action in quickActions"
            :key="action.key"
            :to="action.to"
            class="inline-flex min-h-11 shrink-0 items-center gap-2 rounded-2xl border border-brand-border bg-brand-surface px-3 text-sm font-semibold text-brand-text"
          >
            <component :is="action.icon" class="h-4 w-4 text-brand-primary-dark" :stroke-width="1.85" />
            {{ t(action.labelKey) }}
          </RouterLink>
        </nav>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 md:gap-5">
          <DashboardToday
            v-if="showToday"
            :today="todayPayload"
            :timezone="timezone"
          />
          <DashboardWork
            v-if="showWork"
            :work="workPayload"
            :kpis="kpisPayload"
          />
        </div>

        <DashboardResources
          v-if="showResources"
          :kpis="kpisPayload"
          :resources="resourcesPayload"
        />
      </template>
    </template>
  </div>
</template>
