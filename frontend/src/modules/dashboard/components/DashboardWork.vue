<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'

import type { DashboardWork } from '../types/dashboard'
import { isSafeAppHref } from '../utils/dashboardDisplay'
import DashboardSectionCard from './DashboardSectionCard.vue'

const props = defineProps<{
  work?: DashboardWork | null
}>()

const { t } = useI18n()

const myTasks = computed(() => props.work?.my_tasks)
const visible = computed(() => myTasks.value != null)
</script>

<template>
  <DashboardSectionCard
    v-if="visible && myTasks"
    :title="t('dashboard.work')"
  >
    <component
      :is="isSafeAppHref(myTasks.href) ? RouterLink : 'div'"
      :to="isSafeAppHref(myTasks.href) ? myTasks.href : undefined"
      class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-brand-border bg-[#F4F6F5]/70 p-4 transition"
      :class="
        isSafeAppHref(myTasks.href)
          ? 'hover:border-brand-primary/30 hover:bg-brand-primary-soft/40'
          : ''
      "
    >
      <div>
        <p class="text-sm font-bold text-brand-text">
          {{ t('dashboard.myTasks') }}
        </p>
        <p class="mt-1 text-xs text-brand-text-secondary">
          {{ t('dashboard.myTasksHint') }}
        </p>
      </div>
      <div class="flex gap-4 text-sm">
        <div class="text-center">
          <p class="text-xs text-brand-text-muted">
            {{ t('dashboard.openCount') }}
          </p>
          <p class="mt-0.5 text-lg font-bold tabular-nums text-brand-primary-dark">
            {{ myTasks.open }}
          </p>
        </div>
        <div class="text-center">
          <p class="text-xs text-brand-text-muted">
            {{ t('dashboard.overdueCount') }}
          </p>
          <p
            class="mt-0.5 text-lg font-bold tabular-nums"
            :class="myTasks.overdue > 0 ? 'text-red-800' : 'text-brand-text'"
          >
            {{ myTasks.overdue }}
          </p>
        </div>
      </div>
    </component>
  </DashboardSectionCard>
</template>
