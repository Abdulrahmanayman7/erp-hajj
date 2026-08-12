<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRouter } from 'vue-router'
import { Bell, CheckCheck, X } from 'lucide-vue-next'

import {
  useMarkAllNotificationsReadMutation,
  useMarkNotificationReadMutation,
} from '../mutations/useNotificationMutations'
import {
  useRecentNotificationsQuery,
  useUnreadCountQuery,
} from '../queries/useNotificationsQuery'
import type { Notification } from '../types/notifications'
import {
  formatNotificationAbsoluteTime,
  formatNotificationRelativeTime,
  notificationSeverityBadgeClass,
  notificationSeverityLabel,
} from '../utils/notificationLabels'
import { resolveNotificationRoute } from '../utils/resolveNotificationRoute'

const { t } = useI18n()
const router = useRouter()

const open = ref(false)
const root = ref<HTMLElement | null>(null)
const isMobile = ref(false)

const { data: unreadData } = useUnreadCountQuery()
const unreadCount = computed(() => unreadData.value?.unread_count ?? 0)
const badgeLabel = computed(() => {
  const count = unreadCount.value
  if (count <= 0) return ''
  return count > 99 ? '99+' : String(count)
})

const { data, isLoading, isError, refetch } = useRecentNotificationsQuery(open)
const items = computed(() => data.value?.data ?? [])

const markRead = useMarkNotificationReadMutation()
const markAll = useMarkAllNotificationsReadMutation()

function updateIsMobile(): void {
  isMobile.value = window.matchMedia('(max-width: 639px)').matches
}

function toggle(): void {
  open.value = !open.value
}

function close(): void {
  open.value = false
}

async function onItemClick(notification: Notification): Promise<void> {
  if (!notification.is_read) {
    try {
      await markRead.mutateAsync(notification.id)
    } catch {
      // Still allow navigation if mark-read fails
    }
  }
  close()
  const route = resolveNotificationRoute(notification)
  if (route) {
    await router.push(route)
  }
}

async function onMarkAll(): Promise<void> {
  if (unreadCount.value <= 0 || markAll.isPending.value) return
  try {
    await markAll.mutateAsync()
  } catch {
    // Keep panel open; list will refresh on next poll
  }
}

function onDocumentClick(event: MouseEvent): void {
  if (!open.value || !root.value || isMobile.value) return
  if (!root.value.contains(event.target as Node)) {
    close()
  }
}

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape' && open.value) {
    close()
  }
}

watch(open, (value) => {
  if (typeof document === 'undefined') return
  if (value && isMobile.value) {
    document.body.style.overflow = 'hidden'
  } else {
    document.body.style.overflow = ''
  }
})

onMounted(() => {
  updateIsMobile()
  window.addEventListener('resize', updateIsMobile)
  document.addEventListener('click', onDocumentClick)
  document.addEventListener('keydown', onKeydown)
})

onUnmounted(() => {
  window.removeEventListener('resize', updateIsMobile)
  document.removeEventListener('click', onDocumentClick)
  document.removeEventListener('keydown', onKeydown)
  document.body.style.overflow = ''
})
</script>

<template>
  <div ref="root" class="relative">
    <button
      type="button"
      class="relative inline-flex h-10 w-10 items-center justify-center rounded-xl text-brand-text-secondary transition hover:bg-brand-bg hover:text-brand-primary"
      :aria-label="t('nav.notifications')"
      :aria-expanded="open"
      aria-haspopup="dialog"
      @click.stop="toggle"
    >
      <Bell class="h-5 w-5" :stroke-width="1.75" />
      <span
        v-if="badgeLabel"
        class="absolute -top-0.5 inset-e-0.5 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-[#C6A15B] px-1 text-[10px] font-bold leading-none text-[#033D30] ring-2 ring-brand-surface"
        aria-live="polite"
      >
        <span class="sr-only">{{ t('notifications.unreadCount', { count: unreadCount }) }}</span>
        <span aria-hidden="true">{{ badgeLabel }}</span>
      </span>
    </button>

    <!-- Desktop popover -->
    <div
      v-if="open && !isMobile"
      class="absolute inset-e-0 top-full z-50 mt-2 w-[min(100vw-1.5rem,22rem)] overflow-hidden rounded-2xl border border-brand-border bg-brand-surface shadow-[0_16px_40px_-18px_rgba(23,32,29,0.4)]"
      role="dialog"
      :aria-label="t('nav.notifications')"
    >
      <div class="flex items-center justify-between gap-2 border-b border-brand-border px-4 py-3">
        <h3 class="text-sm font-bold text-brand-text">{{ t('nav.notifications') }}</h3>
        <button
          type="button"
          class="inline-flex h-9 items-center gap-1.5 rounded-lg px-2 text-xs font-semibold text-brand-primary-dark transition hover:bg-brand-bg disabled:cursor-not-allowed disabled:opacity-40"
          :disabled="unreadCount <= 0 || markAll.isPending.value"
          @click="onMarkAll"
        >
          <CheckCheck class="h-3.5 w-3.5" :stroke-width="2" />
          {{ t('notifications.markAllRead') }}
        </button>
      </div>

      <div class="max-h-[min(70vh,24rem)] overflow-y-auto">
        <div v-if="isLoading" class="px-4 py-10 text-center text-sm text-brand-text-secondary">
          {{ t('notifications.loading') }}
        </div>
        <div v-else-if="isError" class="px-4 py-10 text-center text-sm">
          <p class="text-brand-text-secondary">{{ t('notifications.errors.load') }}</p>
          <button
            type="button"
            class="mt-3 text-sm font-semibold text-brand-primary-dark"
            @click="() => refetch()"
          >
            {{ t('notifications.retry') }}
          </button>
        </div>
        <div v-else-if="!items.length" class="px-4 py-10 text-center text-sm text-brand-text-secondary">
          {{ t('notifications.empty') }}
        </div>
        <ul v-else class="divide-y divide-brand-border" role="list">
          <li v-for="item in items" :key="item.id">
            <button
              type="button"
              class="flex w-full gap-3 px-4 py-3.5 text-start transition hover:bg-brand-bg"
              @click="onItemClick(item)"
            >
              <span
                class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
                :class="item.is_read ? 'bg-transparent' : 'bg-[#C6A15B]'"
                aria-hidden="true"
              />
              <span class="min-w-0 flex-1">
                <span class="flex flex-wrap items-center gap-2">
                  <span
                    class="line-clamp-1 text-sm"
                    :class="item.is_read ? 'font-medium text-brand-text-secondary' : 'font-bold text-brand-text'"
                  >
                    {{ item.title }}
                  </span>
                  <span
                    class="rounded-full px-1.5 py-0.5 text-[10px] font-semibold"
                    :class="notificationSeverityBadgeClass(item.severity)"
                  >
                    {{ notificationSeverityLabel(item.severity) }}
                  </span>
                </span>
                <span class="mt-0.5 line-clamp-2 text-xs text-brand-text-secondary">{{ item.body }}</span>
                <span
                  class="mt-1 block text-[11px] text-brand-text-muted"
                  :title="formatNotificationAbsoluteTime(item.created_at)"
                >
                  {{ formatNotificationRelativeTime(item.created_at) }}
                </span>
              </span>
            </button>
          </li>
        </ul>
      </div>

      <div class="border-t border-brand-border px-4 py-2.5">
        <RouterLink
          to="/app/notifications"
          class="block text-center text-sm font-semibold text-brand-primary-dark transition hover:text-brand-primary"
          @click="close"
        >
          {{ t('notifications.viewAll') }}
        </RouterLink>
      </div>
    </div>

    <!-- Mobile sheet -->
    <Teleport to="body">
      <div
        v-if="open && isMobile"
        class="fixed inset-0 z-[200] flex flex-col bg-[rgba(15,23,20,0.4)]"
        role="dialog"
        aria-modal="true"
        :aria-label="t('nav.notifications')"
      >
        <button type="button" class="min-h-12 flex-1" :aria-label="t('notifications.close')" @click="close" />
        <div class="flex max-h-[88vh] flex-col overflow-hidden rounded-t-3xl bg-brand-surface shadow-2xl">
          <div class="flex items-center justify-between gap-3 border-b border-brand-border px-4 py-4">
            <h3 class="text-base font-bold text-brand-text">{{ t('nav.notifications') }}</h3>
            <div class="flex items-center gap-1">
              <button
                type="button"
                class="inline-flex h-11 items-center gap-1.5 rounded-xl px-3 text-sm font-semibold text-brand-primary-dark transition hover:bg-brand-bg disabled:opacity-40"
                :disabled="unreadCount <= 0 || markAll.isPending.value"
                @click="onMarkAll"
              >
                <CheckCheck class="h-4 w-4" :stroke-width="2" />
                {{ t('notifications.markAllRead') }}
              </button>
              <button
                type="button"
                class="inline-flex h-11 w-11 items-center justify-center rounded-xl text-brand-text-secondary hover:bg-brand-bg"
                :aria-label="t('notifications.close')"
                @click="close"
              >
                <X class="h-5 w-5" :stroke-width="1.75" />
              </button>
            </div>
          </div>

          <div class="flex-1 overflow-y-auto">
            <div v-if="isLoading" class="px-4 py-12 text-center text-sm text-brand-text-secondary">
              {{ t('notifications.loading') }}
            </div>
            <div v-else-if="isError" class="px-4 py-12 text-center text-sm">
              <p class="text-brand-text-secondary">{{ t('notifications.errors.load') }}</p>
              <button
                type="button"
                class="mt-3 h-11 px-4 text-sm font-semibold text-brand-primary-dark"
                @click="() => refetch()"
              >
                {{ t('notifications.retry') }}
              </button>
            </div>
            <div v-else-if="!items.length" class="px-4 py-12 text-center text-sm text-brand-text-secondary">
              {{ t('notifications.empty') }}
            </div>
            <ul v-else class="divide-y divide-brand-border" role="list">
              <li v-for="item in items" :key="item.id">
                <button
                  type="button"
                  class="flex w-full min-h-[4.5rem] gap-3 px-4 py-4 text-start transition active:bg-brand-bg"
                  @click="onItemClick(item)"
                >
                  <span
                    class="mt-2 h-2.5 w-2.5 shrink-0 rounded-full"
                    :class="item.is_read ? 'bg-transparent' : 'bg-[#C6A15B]'"
                    aria-hidden="true"
                  />
                  <span class="min-w-0 flex-1">
                    <span class="flex flex-wrap items-center gap-2">
                      <span
                        class="line-clamp-2 text-sm"
                        :class="item.is_read ? 'font-medium text-brand-text-secondary' : 'font-bold text-brand-text'"
                      >
                        {{ item.title }}
                      </span>
                      <span
                        class="rounded-full px-1.5 py-0.5 text-[10px] font-semibold"
                        :class="notificationSeverityBadgeClass(item.severity)"
                      >
                        {{ notificationSeverityLabel(item.severity) }}
                      </span>
                    </span>
                    <span class="mt-1 line-clamp-2 text-sm text-brand-text-secondary">{{ item.body }}</span>
                    <span
                      class="mt-1.5 block text-xs text-brand-text-muted"
                      :title="formatNotificationAbsoluteTime(item.created_at)"
                    >
                      {{ formatNotificationRelativeTime(item.created_at) }}
                    </span>
                  </span>
                </button>
              </li>
            </ul>
          </div>

          <div class="border-t border-brand-border p-4">
            <RouterLink
              to="/app/notifications"
              class="flex h-12 items-center justify-center rounded-xl bg-brand-primary-dark text-sm font-semibold text-white"
              @click="close"
            >
              {{ t('notifications.viewAll') }}
            </RouterLink>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
