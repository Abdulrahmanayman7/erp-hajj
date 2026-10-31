<script setup lang="ts">
import { computed } from 'vue'
import { sessionGeneration, sessionTransition } from '@/shared/api/sessionLifetime'
import { useRoute } from 'vue-router'

import AppLayout from './app/layouts/AppLayout.vue'
import GuestLayout from './app/layouts/GuestLayout.vue'
import PlatformLayout from './app/layouts/PlatformLayout.vue'

const route = useRoute()

const GUEST_PATHS = new Set([
  '/login',
  '/forgot-password',
  '/reset-password',
  '/access-blocked',
  '/setup',
])

/**
 * Guest chrome must win even while the router is still on START_LOCATION (`/` /
 * empty matched) during the first `/login` navigation. Clearing an anonymous
 * `/me` 401 advances `sessionGeneration` and remounts the authenticated shell
 * mid-guard — without this fallback `/login` paints AppLayout with an empty main.
 */
const isGuestChrome = computed(() => {
  if (route.meta.layout === 'guest') return true
  if (typeof window === 'undefined') return false
  return GUEST_PATHS.has(window.location.pathname)
})

const layout = computed(() => {
  if (isGuestChrome.value) {
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
  <GuestLayout v-if="isGuestChrome">
    <RouterView />
  </GuestLayout>
  <component v-else-if="!sessionTransition" :is="layout" :key="sessionGeneration">
    <RouterView />
  </component>
</template>
