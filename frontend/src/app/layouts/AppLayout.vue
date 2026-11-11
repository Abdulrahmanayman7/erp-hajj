<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import AppBottomNav from '@/shared/components/AppBottomNav.vue'
import AppConfirmDialog from '@/shared/components/AppConfirmDialog.vue'
import AppMobileMoreSheet from '@/shared/components/AppMobileMoreSheet.vue'
import AppNavigationRail from '@/shared/components/AppNavigationRail.vue'
import AppPullToRefresh from '@/shared/components/AppPullToRefresh.vue'
import AppToastHost from '@/shared/components/AppToastHost.vue'
import { useAppNavigation } from '@/shared/composables/useAppNavigation'
import { useMobileMore } from '@/shared/composables/useMobileMore'

import AppFooter from './AppFooter.vue'
import AppSidebar from './AppSidebar.vue'
import AppTopbar from './AppTopbar.vue'

const { t } = useI18n()
const { moreOpen, closeMore } = useMobileMore()
const { flatItems } = useAppNavigation()
const moreItems = computed(() => flatItems.value)
const mainEl = ref<HTMLElement | null>(null)
</script>

<template>
  <div
    class="app-shell flex h-full min-h-0 overflow-hidden text-brand-text md:gap-3 md:p-3 xl:gap-4 xl:p-4"
  >
    <a
      href="#app-main-content"
      class="sr-only focus:not-sr-only focus:absolute focus:start-3 focus:top-3 focus:z-[600] focus:rounded-lg focus:bg-brand-surface focus:px-3 focus:py-2 focus:text-sm focus:font-bold focus:text-brand-primary focus:shadow-lg focus:outline-none focus:ring-2 focus:ring-brand-primary/40"
      data-test="skip-to-content"
    >
      {{ t('common.skipToContent') }}
    </a>

    <!-- Desktop ≥1280: full sidebar -->
    <div class="hidden h-full min-h-0 shrink-0 self-stretch overflow-visible xl:block">
      <AppSidebar />
    </div>

    <!-- Tablet 768–1279: compact navigation rail -->
    <AppNavigationRail />

    <div class="flex h-full min-h-0 min-w-0 flex-1 flex-col self-stretch md:gap-3 xl:gap-4">
      <AppTopbar />

      <main
        id="app-main-content"
        ref="mainEl"
        tabindex="-1"
        class="app-shell-main min-h-0 flex-1 overflow-x-hidden overflow-y-auto overscroll-y-contain"
      >
        <AppPullToRefresh :scroller="mainEl" />
        <div class="app-page-container mx-auto w-full min-w-0 max-w-[1440px]">
          <slot />
        </div>
      </main>

      <div class="hidden shrink-0 xl:block">
        <AppFooter />
      </div>
    </div>

    <AppBottomNav />
    <AppMobileMoreSheet :open="moreOpen" :items="moreItems" @close="closeMore" />
    <AppToastHost />
    <AppConfirmDialog />
  </div>
</template>
