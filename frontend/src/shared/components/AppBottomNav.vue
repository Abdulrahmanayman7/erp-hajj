<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'
import { Bell, Home, ListTodo, Menu, Plus } from 'lucide-vue-next'

import { useUnreadCountQuery } from '@/modules/notifications/queries/useNotificationsQuery'
import { isAppNavItemActive, useAppNavigation } from '@/shared/composables/useAppNavigation'
import { usePermissions } from '@/shared/composables/usePermissions'

import AppMobileMoreSheet from './AppMobileMoreSheet.vue'
import QuickActionSheet from './QuickActionSheet.vue'

const { t } = useI18n()
const route = useRoute()
const { can } = usePermissions()
const { flatItems } = useAppNavigation()
const { data: unread } = useUnreadCountQuery()
const moreOpen = ref(false)
const quickOpen = ref(false)

const unreadCount = computed(() => unread.value?.unread_count ?? 0)

const primaryTabs = computed(() => {
  const tabs: Array<{
    key: string
    labelKey: string
    to: string
    icon: typeof Home
    match: 'exact' | 'prefix'
    badge?: number
  }> = [{ key: 'home', labelKey: 'nav.home', to: '/app', icon: Home, match: 'exact' }]

  if (can('tasks.view')) {
    tabs.push({
      key: 'tasks',
      labelKey: 'nav.tasks',
      to: '/app/tasks',
      icon: ListTodo,
      match: 'prefix',
    })
  }

  return tabs
})

const notificationTab = computed(() => ({
  key: 'notifications',
  labelKey: 'nav.notifications',
  to: '/app/notifications',
  icon: Bell,
  match: 'prefix' as const,
  badge: unreadCount.value,
}))

const moreItems = computed(() => {
  const primaryKeys = new Set([
    ...primaryTabs.value.map((tab) => tab.key),
    'dashboard',
    'notifications',
  ])
  return flatItems.value.filter((item) => !primaryKeys.has(item.key) && item.key !== 'dashboard')
})

function isTabActive(tab: { to: string; match: 'exact' | 'prefix' }): boolean {
  return isAppNavItemActive(route.path, {
    key: tab.to,
    labelKey: '',
    to: tab.to,
    icon: Home,
    match: tab.match,
  })
}
</script>

<template>
  <nav class="app-bottom-nav md:hidden" aria-label="التنقل السفلي">
    <div class="app-bottom-nav__inner app-bottom-nav__inner--with-fab">
      <RouterLink
        v-for="tab in primaryTabs"
        :key="tab.key"
        :to="tab.to"
        class="app-bottom-nav__item"
        :class="{ 'app-bottom-nav__item--active': isTabActive(tab) }"
      >
        <component :is="tab.icon" class="h-5 w-5" :stroke-width="1.9" aria-hidden="true" />
        <span class="app-bottom-nav__label">{{ t(tab.labelKey) }}</span>
      </RouterLink>

      <button
        type="button"
        class="app-bottom-nav__fab"
        :aria-label="t('quickActions.title')"
        :aria-expanded="quickOpen"
        @click="quickOpen = true"
      >
        <Plus class="h-5 w-5" :stroke-width="2.25" aria-hidden="true" />
      </button>

      <RouterLink
        :to="notificationTab.to"
        class="app-bottom-nav__item"
        :class="{ 'app-bottom-nav__item--active': isTabActive(notificationTab) }"
      >
        <span class="relative inline-flex">
          <Bell class="h-5 w-5" :stroke-width="1.9" aria-hidden="true" />
          <span
            v-if="notificationTab.badge > 0"
            class="absolute -top-1.5 -end-2 inline-flex min-w-4 items-center justify-center rounded-full bg-brand-danger px-1 text-[10px] font-bold leading-4 text-white"
          >
            {{ notificationTab.badge > 99 ? '99+' : notificationTab.badge }}
          </span>
        </span>
        <span class="app-bottom-nav__label">{{ t(notificationTab.labelKey) }}</span>
      </RouterLink>

      <button
        type="button"
        class="app-bottom-nav__item"
        :class="{ 'app-bottom-nav__item--active': moreOpen }"
        :aria-expanded="moreOpen"
        aria-controls="app-mobile-more-sheet"
        @click="moreOpen = true"
      >
        <Menu class="h-5 w-5" :stroke-width="1.9" aria-hidden="true" />
        <span class="app-bottom-nav__label">{{ t('nav.more') }}</span>
      </button>
    </div>
  </nav>

  <AppMobileMoreSheet :open="moreOpen" :items="moreItems" @close="moreOpen = false" />
  <QuickActionSheet :open="quickOpen" @close="quickOpen = false" />
</template>
