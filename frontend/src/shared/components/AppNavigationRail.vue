<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { Menu } from 'lucide-vue-next'

import rafeeaLogo from '@/assets/brand/rafeea-logo.png'
import AppNavOverlay from '@/shared/components/AppNavOverlay.vue'
import {
  type AppNavItem,
  useAppNavigation,
} from '@/shared/composables/useAppNavigation'

const { t } = useI18n()
const { flatItems, isItemActive } = useAppNavigation()
const moreOpen = ref(false)

/** Primary destinations for the compact rail (permission-filtered via flatItems). */
const PRIMARY_KEYS = [
  'dashboard',
  'tasks',
  'meetings',
  'employees',
  'documents',
  'contracts',
] as const

const railItems = computed(() => {
  const byKey = new Map(flatItems.value.map((item) => [item.key, item]))
  const items: AppNavItem[] = []
  for (const key of PRIMARY_KEYS) {
    const item = byKey.get(key)
    if (item) items.push(item)
  }
  return items
})

function railLabel(item: AppNavItem): string {
  return item.key === 'dashboard' ? t('nav.home') : t(item.labelKey)
}

function openMore(): void {
  moreOpen.value = true
}

function closeMore(): void {
  moreOpen.value = false
}
</script>

<template>
  <aside
    class="app-nav-rail hidden h-full min-h-0 shrink-0 flex-col self-stretch md:flex xl:hidden"
    :aria-label="t('shell.navRail')"
  >
    <div class="flex flex-col items-center gap-2 px-2 pb-3 pt-4">
      <RouterLink
        to="/app"
        class="app-nav-rail__logo"
        :aria-label="t('nav.dashboard')"
      >
        <span class="app-brand-mark app-brand-mark--md">
          <img
            :src="rafeeaLogo"
            alt=""
            class="app-brand-mark__img"
            width="48"
            height="48"
            decoding="async"
          />
        </span>
      </RouterLink>
    </div>

    <nav class="flex min-h-0 flex-1 flex-col items-stretch gap-1 overflow-y-auto px-2 py-1">
      <RouterLink
        v-for="item in railItems"
        :key="item.key"
        :to="item.to"
        class="app-nav-rail__item"
        :class="{ 'app-nav-rail__item--active': isItemActive(item) }"
        :aria-current="isItemActive(item) ? 'page' : undefined"
        :title="railLabel(item)"
      >
        <component :is="item.icon" class="h-5 w-5 shrink-0" :stroke-width="1.75" aria-hidden="true" />
        <span class="app-nav-rail__label">{{ railLabel(item) }}</span>
      </RouterLink>
    </nav>

    <div class="flex flex-col items-stretch gap-2 border-t border-white/10 px-2 py-3">
      <button
        type="button"
        class="app-nav-rail__item"
        :class="{ 'app-nav-rail__item--active': moreOpen }"
        :aria-expanded="moreOpen"
        @click="openMore"
      >
        <Menu class="h-5 w-5 shrink-0" :stroke-width="1.75" aria-hidden="true" />
        <span class="app-nav-rail__label">{{ t('nav.more') }}</span>
      </button>
    </div>
  </aside>

  <AppNavOverlay :open="moreOpen" @close="closeMore" />
</template>
