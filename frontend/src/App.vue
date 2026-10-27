<script setup lang="ts">
import { computed } from 'vue'
import { sessionGeneration, sessionTransition } from '@/shared/api/sessionLifetime'
import { useRoute } from 'vue-router'

import AppLayout from './app/layouts/AppLayout.vue'
import GuestLayout from './app/layouts/GuestLayout.vue'
import PlatformLayout from './app/layouts/PlatformLayout.vue'

const route = useRoute()

const layout = computed(() => {
  if (route.meta.layout === 'guest') {
    return GuestLayout
  }
  if (route.meta.layout === 'platform') {
    return PlatformLayout
  }
  return AppLayout
})
</script>

<template>
  <!-- Guest forms own their pending auth callbacks and validation state.
       Only the authenticated tree belongs to a disposable session lifetime. -->
  <GuestLayout v-if="route.meta.layout === 'guest'">
    <RouterView />
  </GuestLayout>
  <component v-else-if="!sessionTransition" :is="layout" :key="sessionGeneration">
    <RouterView />
  </component>
</template>
