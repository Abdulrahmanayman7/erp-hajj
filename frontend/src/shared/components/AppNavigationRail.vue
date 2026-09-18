<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { Menu } from 'lucide-vue-next'

import rafeeaLogo from '@/assets/brand/rafeea-logo.png'
import AppTooltip from '@/shared/components/AppTooltip.vue'
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
    <div class="flex flex-col items-center gap-2 px-1.5 pb-2 pt-3">
      <RouterLink
        to="/app"
        class="app-nav-rail__logo"
        :aria-label="t('nav.dashboard')"
      >
        <img
          :src="rafeeaLogo"
          alt=""
          class="h-7 w-7 object-contain"
          width="28"
          height="28"
          decoding="async"
        />
      </RouterLink>
    </div>

    <nav class="flex min-h-0 flex-1 flex-col items-center gap-1 overflow-y-auto px-1.5 py-2">
      <AppTooltip
        v-for="item in railItems"
        :key="item.key"
        :text="t(item.labelKey)"
        side="bottom"
      >
        <RouterLink
          :to="item.to"
          class="app-nav-rail__item"
          :class="{ 'app-nav-rail__item--active': isItemActive(item) }"
          :aria-current="isItemActive(item) ? 'page' : undefined"
          :aria-label="t(item.labelKey)"
        >
          <component :is="item.icon" class="h-5 w-5" :stroke-width="1.75" aria-hidden="true" />
        </RouterLink>
      </AppTooltip>
    </nav>

    <div class="flex flex-col items-center gap-2 border-t border-white/10 px-1.5 py-3">
      <AppTooltip :text="t('nav.more')" side="bottom">
        <button
          type="button"
          class="app-nav-rail__item"
          :class="{ 'app-nav-rail__item--active': moreOpen }"
          :aria-expanded="moreOpen"
          :aria-label="t('nav.more')"
          @click="openMore"
        >
          <Menu class="h-5 w-5" :stroke-width="1.75" aria-hidden="true" />
        </button>
      </AppTooltip>
    </div>
  </aside>

  <AppNavOverlay :open="moreOpen" @close="closeMore" />
</template>
