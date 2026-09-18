<script setup lang="ts">
defineProps<{
  title: string
}>()
</script>

<template>
  <section class="app-surface-flat p-4 md:p-5">
    <h3 class="app-type-section mb-3">
      {{ title }}
    </h3>
    <slot />
  </section>
</template>
