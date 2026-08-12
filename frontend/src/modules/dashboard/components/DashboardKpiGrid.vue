<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'

import type { DashboardKpis } from '../types/dashboard'
import {
  getTopKpis,
  isSafeAppHref,
  severityCardClass,
  severityValueClass,
} from '../utils/dashboardDisplay'

const props = defineProps<{
  kpis?: DashboardKpis | null
}>()

const entries = computed(() => getTopKpis(props.kpis))
</script>

<template>
  <div
    v-if="entries.length > 0"
    class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3"
  >
    <component
      :is="isSafeAppHref(entry.kpi.href) ? RouterLink : 'div'"
      v-for="entry in entries"
      :key="entry.key"
      :to="isSafeAppHref(entry.kpi.href) ? entry.kpi.href : undefined"
      class="rounded-2xl border p-5 transition"
      :class="[
        severityCardClass(entry.kpi.severity),
        isSafeAppHref(entry.kpi.href)
          ? 'hover:border-brand-primary/30 hover:shadow-[0_10px_28px_-20px_rgba(6,78,59,0.35)]'
          : '',
      ]"
    >
      <p class="text-sm font-medium text-brand-text-secondary">
        {{ entry.kpi.label }}
      </p>
      <p
        class="mt-3 text-3xl font-bold tabular-nums tracking-tight"
        :class="severityValueClass(entry.kpi.severity)"
      >
        {{ entry.kpi.value }}
      </p>
    </component>
  </div>
</template>
