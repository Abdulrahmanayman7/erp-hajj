<script setup lang="ts">
import AppBottomNav from '@/shared/components/AppBottomNav.vue'
import AppConfirmDialog from '@/shared/components/AppConfirmDialog.vue'
import AppToastHost from '@/shared/components/AppToastHost.vue'

import AppFooter from './AppFooter.vue'
import AppSidebar from './AppSidebar.vue'
import AppTopbar from './AppTopbar.vue'
</script>

<template>
  <div class="app-shell flex h-dvh overflow-hidden bg-brand-bg text-brand-text md:gap-3 md:p-3 lg:gap-4 lg:p-4">
    <!-- Desktop sidebar only — mobile uses bottom nav -->
    <div class="hidden h-full shrink-0 overflow-visible md:block">
      <AppSidebar />
    </div>

    <div class="flex min-h-0 min-w-0 flex-1 flex-col md:gap-3 lg:gap-4">
      <AppTopbar />

      <main class="app-shell-main min-h-0 flex-1 overflow-auto">
        <div class="app-page-container mx-auto w-full max-w-[1440px]">
          <slot />
        </div>
      </main>

      <div class="hidden md:block">
        <AppFooter />
      </div>
    </div>

    <AppBottomNav />
    <AppToastHost />
    <AppConfirmDialog />
  </div>
</template>
