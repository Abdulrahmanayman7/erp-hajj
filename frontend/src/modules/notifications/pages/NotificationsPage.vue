<script setup lang="ts">
import { computed, reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { CheckCheck, ChevronLeft, ChevronRight } from 'lucide-vue-next'

import AppMobileFilters from '@/shared/components/AppMobileFilters.vue'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { useToast } from '@/shared/composables/useToast'

import {
  useMarkAllNotificationsReadMutation,
  useMarkNotificationReadMutation,
} from '../mutations/useNotificationMutations'
import { useNotificationsQuery, useUnreadCountQuery } from '../queries/useNotificationsQuery'
import type {
  ListNotificationsParams,
  Notification,
  NotificationSeverity,
} from '../types/notifications'
import { NOTIFICATION_SEVERITIES, NOTIFICATION_TYPES } from '../types/notifications'
import {
  formatNotificationAbsoluteTime,
  formatNotificationRelativeTime,
  notificationSeverityBadgeClass,
  notificationSeverityLabel,
  notificationTypeLabel,
} from '../utils/notificationLabels'
import { resolveNotificationRoute } from '../utils/resolveNotificationRoute'

const { t } = useI18n()
const router = useRouter()
const toast = useToast()

const filters = reactive({
  unread_only: false,
  type: '' as string,
  severity: '' as NotificationSeverity | '',
  page: 1,
  per_page: 15,
})

const params = computed<ListNotificationsParams>(() => ({
  unread_only: filters.unread_only || undefined,
  type: filters.type || undefined,
  severity: filters.severity || undefined,
  page: filters.page,
  per_page: filters.per_page,
}))

const { data, isLoading, isError, isFetching, refetch } = useNotificationsQuery(params)
const items = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta)

const { data: unreadData } = useUnreadCountQuery()
const unreadCount = computed(() => unreadData.value?.unread_count ?? 0)

const markRead = useMarkNotificationReadMutation()
const markAll = useMarkAllNotificationsReadMutation()

const typeOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('notifications.filters.allTypes') },
  ...NOTIFICATION_TYPES.map((type) => ({
    value: type,
    label: notificationTypeLabel(type),
  })),
])

const severityOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('notifications.filters.allSeverities') },
  ...NOTIFICATION_SEVERITIES.map((severity) => ({
    value: severity,
    label: notificationSeverityLabel(severity),
  })),
])

const activeFilterCount = computed(() => {
  let count = 0
  if (filters.unread_only) count += 1
  if (filters.type) count += 1
  if (filters.severity) count += 1
  return count
})

function resetFilters(): void {
  filters.unread_only = false
  filters.type = ''
  filters.severity = ''
}

watch(
  () => [filters.unread_only, filters.type, filters.severity],
  () => {
    filters.page = 1
  },
)

async function onItemClick(notification: Notification): Promise<void> {
  if (!notification.is_read) {
    try {
      await markRead.mutateAsync(notification.id)
    } catch {
      // Continue to navigation attempt
    }
  }
  const route = resolveNotificationRoute(notification)
  if (route) {
    await router.push(route)
  }
}

async function onMarkOne(notification: Notification, event: Event): Promise<void> {
  event.stopPropagation()
  if (notification.is_read || markRead.isPending.value) return
  try {
    await markRead.mutateAsync(notification.id)
  } catch {
    toast.error(t('notifications.errors.generic'))
  }
}

async function onMarkAll(): Promise<void> {
  if (unreadCount.value <= 0 || markAll.isPending.value) return
  try {
    await markAll.mutateAsync()
    toast.success(t('notifications.toasts.markedAllRead'))
  } catch {
    toast.error(t('notifications.errors.generic'))
  }
}
</script>

<template>
  <div class="space-y-6">
    <AppPageHeader
      :title="t('notifications.title')"
      :subtitle="t('notifications.subtitle')"
      :meta="unreadCount > 0 ? t('notifications.unreadCount', { count: unreadCount }) : undefined"
    >
      <template #actions>
        <button
          type="button"
          class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-4 text-sm font-semibold text-brand-primary-dark transition hover:bg-brand-bg disabled:cursor-not-allowed disabled:opacity-40 sm:w-auto"
          :disabled="unreadCount <= 0 || markAll.isPending.value"
          @click="onMarkAll"
        >
          <CheckCheck class="h-4 w-4" :stroke-width="2" />
          <span>{{ t('notifications.markAllRead') }}</span>
        </button>
      </template>
    </AppPageHeader>

    <AppMobileFilters
      search=""
      search-placeholder=""
      :show-search="false"
      :active-count="activeFilterCount"
      @reset="resetFilters"
    >
      <template #desktop>
        <div
          class="flex flex-wrap items-center gap-3 rounded-2xl border border-brand-border bg-brand-surface p-4 shadow-[0_1px_2px_rgba(23,32,29,0.03)]"
        >
          <label
            class="inline-flex h-11 cursor-pointer items-center gap-2 rounded-xl border border-brand-border px-3 text-sm font-semibold text-brand-text-secondary"
          >
            <input v-model="filters.unread_only" type="checkbox" class="h-4 w-4 accent-[#064E3B]" />
            {{ t('notifications.filters.unreadOnly') }}
          </label>
          <AppSelect v-model="filters.type" :options="typeOptions" class="min-w-[12rem]" />
          <AppSelect v-model="filters.severity" :options="severityOptions" class="min-w-[10rem]" />
        </div>
      </template>
      <template #filters>
        <div class="space-y-3">
          <label
            class="inline-flex h-11 w-full cursor-pointer items-center gap-2 rounded-xl border border-brand-border px-3 text-sm font-semibold text-brand-text"
          >
            <input v-model="filters.unread_only" type="checkbox" class="h-4 w-4 accent-[#064E3B]" />
            {{ t('notifications.filters.unreadOnly') }}
          </label>
          <AppSelect v-model="filters.type" :options="typeOptions" />
          <AppSelect v-model="filters.severity" :options="severityOptions" />
        </div>
      </template>
    </AppMobileFilters>

    <div v-if="isLoading" class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-secondary">
      {{ t('notifications.loading') }}
    </div>
    <div
      v-else-if="isError"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm"
    >
      <p class="text-brand-text-secondary">{{ t('notifications.errors.load') }}</p>
      <button
        type="button"
        class="mt-3 font-semibold text-brand-primary-dark"
        @click="() => refetch()"
      >
        {{ t('notifications.retry') }}
      </button>
    </div>
    <div
      v-else-if="!items.length"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-secondary"
    >
      {{ t('notifications.empty') }}
    </div>
    <template v-else>
      <!-- Desktop table -->
      <div class="hidden overflow-hidden rounded-2xl border border-brand-border bg-brand-surface md:block">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="bg-brand-bg text-brand-text-secondary">
              <th class="px-4 py-3 text-start font-semibold">{{ t('notifications.columns.title') }}</th>
              <th class="px-4 py-3 text-start font-semibold">{{ t('notifications.columns.type') }}</th>
              <th class="px-4 py-3 text-start font-semibold">{{ t('notifications.columns.severity') }}</th>
              <th class="px-4 py-3 text-start font-semibold">{{ t('notifications.columns.time') }}</th>
              <th class="px-4 py-3 text-start font-semibold">{{ t('notifications.columns.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="item in items"
              :key="item.id"
              class="cursor-pointer border-t border-brand-border transition hover:bg-brand-bg/70"
              @click="onItemClick(item)"
            >
              <td class="max-w-md px-4 py-3">
                <div class="flex items-start gap-2">
                  <span
                    class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
                    :class="item.is_read ? 'bg-transparent' : 'bg-[#C6A15B]'"
                    aria-hidden="true"
                  />
                  <div class="min-w-0">
                    <p
                      class="truncate"
                      :class="item.is_read ? 'font-medium text-brand-text-secondary' : 'font-bold text-brand-text'"
                    >
                      {{ item.title }}
                    </p>
                    <p class="mt-0.5 line-clamp-1 text-xs text-brand-text-secondary">{{ item.body }}</p>
                  </div>
                </div>
              </td>
              <td class="px-4 py-3 text-brand-text-secondary">
                {{ notificationTypeLabel(item.type) }}
              </td>
              <td class="px-4 py-3">
                <span
                  class="rounded-full px-2 py-1 text-xs font-semibold"
                  :class="notificationSeverityBadgeClass(item.severity)"
                >
                  {{ notificationSeverityLabel(item.severity) }}
                </span>
              </td>
              <td
                class="whitespace-nowrap px-4 py-3 text-brand-text-muted"
                :title="formatNotificationAbsoluteTime(item.created_at)"
              >
                {{ formatNotificationRelativeTime(item.created_at) }}
              </td>
              <td class="px-4 py-3">
                <button
                  v-if="!item.is_read"
                  type="button"
                  class="rounded-lg px-2 py-1 text-xs font-semibold text-brand-primary-dark hover:bg-brand-bg"
                  @click="onMarkOne(item, $event)"
                >
                  {{ t('notifications.markRead') }}
                </button>
                <span v-else class="text-xs text-brand-text-muted">{{ t('notifications.read') }}</span>
              </td>
            </tr>
          </tbody>
        </table>
        <div
          v-if="meta && meta.last_page > 1"
          class="flex items-center justify-between gap-3 border-t border-brand-border bg-[#F7F8F6] px-5 py-3 text-sm"
        >
          <button
            type="button"
            class="inline-flex h-9 items-center gap-1 rounded-lg border border-brand-border bg-brand-surface px-3 font-semibold text-brand-text transition hover:bg-brand-bg disabled:cursor-not-allowed disabled:opacity-40"
            :disabled="filters.page <= 1 || isFetching"
            @click="filters.page -= 1"
          >
            <ChevronRight class="h-4 w-4" :stroke-width="2" />
            <span>{{ t('notifications.prev') }}</span>
          </button>
          <span class="text-xs font-semibold text-brand-text-muted">
            {{ filters.page }} / {{ meta.last_page }}
          </span>
          <button
            type="button"
            class="inline-flex h-9 items-center gap-1 rounded-lg border border-brand-border bg-brand-surface px-3 font-semibold text-brand-text transition hover:bg-brand-bg disabled:cursor-not-allowed disabled:opacity-40"
            :disabled="filters.page >= meta.last_page || isFetching"
            @click="filters.page += 1"
          >
            <span>{{ t('notifications.next') }}</span>
            <ChevronLeft class="h-4 w-4" :stroke-width="2" />
          </button>
        </div>
      </div>

      <!-- Mobile cards -->
      <div class="space-y-3 md:hidden">
        <button
          v-for="item in items"
          :key="item.id"
          type="button"
          class="flex w-full min-h-[4.5rem] flex-col gap-2 rounded-2xl border border-brand-border bg-brand-surface p-4 text-start transition active:bg-brand-bg"
          @click="onItemClick(item)"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="flex min-w-0 items-start gap-2">
              <span
                class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full"
                :class="item.is_read ? 'bg-transparent' : 'bg-[#C6A15B]'"
                aria-hidden="true"
              />
              <p
                class="line-clamp-2 text-sm"
                :class="item.is_read ? 'font-medium text-brand-text-secondary' : 'font-bold text-brand-text'"
              >
                {{ item.title }}
              </p>
            </div>
            <span
              class="shrink-0 rounded-full px-2 py-1 text-[10px] font-semibold"
              :class="notificationSeverityBadgeClass(item.severity)"
            >
              {{ notificationSeverityLabel(item.severity) }}
            </span>
          </div>
          <p class="line-clamp-2 ps-4 text-sm text-brand-text-secondary">{{ item.body }}</p>
          <div class="flex items-center justify-between gap-2 ps-4">
            <span
              class="text-xs text-brand-text-muted"
              :title="formatNotificationAbsoluteTime(item.created_at)"
            >
              {{ formatNotificationRelativeTime(item.created_at) }}
            </span>
            <span class="text-xs text-brand-text-muted">{{ notificationTypeLabel(item.type) }}</span>
          </div>
        </button>

        <div
          v-if="meta && meta.last_page > 1"
          class="flex items-center justify-between gap-3 pt-1"
        >
          <button
            type="button"
            class="inline-flex h-11 items-center gap-1 rounded-xl border border-brand-border bg-brand-surface px-3 text-sm font-semibold disabled:opacity-40"
            :disabled="filters.page <= 1 || isFetching"
            @click="filters.page -= 1"
          >
            <ChevronRight class="h-4 w-4" />
            {{ t('notifications.prev') }}
          </button>
          <span class="text-sm text-brand-text-muted">{{ filters.page }} / {{ meta.last_page }}</span>
          <button
            type="button"
            class="inline-flex h-11 items-center gap-1 rounded-xl border border-brand-border bg-brand-surface px-3 text-sm font-semibold disabled:opacity-40"
            :disabled="filters.page >= meta.last_page || isFetching"
            @click="filters.page += 1"
          >
            {{ t('notifications.next') }}
            <ChevronLeft class="h-4 w-4" />
          </button>
        </div>
      </div>
    </template>
  </div>
</template>
