<script setup lang="ts">
import { computed } from 'vue'
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
  <component :is="layout">
    <RouterView />
  </component>
</template>
