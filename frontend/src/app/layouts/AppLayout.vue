<script setup lang="ts">
import AppConfirmDialog from '@/shared/components/AppConfirmDialog.vue'
import AppToastHost from '@/shared/components/AppToastHost.vue'

import AppFooter from './AppFooter.vue'
import AppSidebar from './AppSidebar.vue'
import AppTopbar from './AppTopbar.vue'
</script>

<template>
  <div class="flex h-dvh overflow-hidden gap-3 bg-brand-bg p-3 text-brand-text lg:gap-4 lg:p-4">
    <!-- First in RTL flex = right side -->
    <AppSidebar />

    <div class="flex min-h-0 min-w-0 flex-1 flex-col gap-3 lg:gap-4">
      <AppTopbar />

      <main class="min-h-0 flex-1 overflow-auto">
        <div class="mx-auto w-full max-w-[1440px] px-3 py-4 sm:px-4 lg:px-6 lg:py-6">
          <slot />
        </div>
      </main>

      <AppFooter />
    </div>

    <AppToastHost />
    <AppConfirmDialog />
  </div>
</template>
