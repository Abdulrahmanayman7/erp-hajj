<script setup lang="ts">
defineProps<{
  title: string
  subtitle?: string
  meta?: string
}>()
</script>

<template>
  <header class="app-page-header flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between sm:gap-4">
    <div class="min-w-0">
      <h1 class="text-[1.5rem] font-bold leading-tight text-brand-text sm:text-[1.75rem]">
        {{ title }}
      </h1>
      <p
        v-if="subtitle"
        class="mt-1.5 text-sm leading-relaxed text-brand-text-secondary"
      >
        {{ subtitle }}
      </p>
      <p
        v-if="meta"
        class="mt-2"
      >
        <span class="inline-flex items-center rounded-full bg-brand-primary-soft px-2.5 py-0.5 text-xs font-semibold text-brand-primary-dark">
          {{ meta }}
        </span>
      </p>
    </div>

    <div
      v-if="$slots.actions"
      class="app-page-header__actions flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:items-center sm:justify-end"
    >
      <slot name="actions" />
    </div>
  </header>
</template>
