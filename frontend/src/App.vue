<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'

import AppLayout from './app/layouts/AppLayout.vue'
import GuestLayout from './app/layouts/GuestLayout.vue'

const route = useRoute()

const layout = computed(() => (route.meta.layout === 'guest' ? GuestLayout : AppLayout))
</script>

<template>
  <component :is="layout">
    <RouterView />
  </component>
</template>
