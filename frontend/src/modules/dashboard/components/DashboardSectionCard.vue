<script setup lang="ts">
defineProps<{
  title: string
}>()
</script>

<template>
  <section class="rounded-2xl border border-brand-border bg-brand-surface p-5 shadow-[0_10px_28px_-24px_rgba(23,32,29,0.18)] sm:p-6">
    <h3 class="mb-4 text-base font-bold text-brand-text">
      {{ title }}
    </h3>
    <slot />
  </section>
</template>
