<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'

import type { DashboardKpis, DashboardResources } from '../types/dashboard'
import {
  isSafeAppHref,
  pickKpis,
  RESOURCE_ASSET_KPI_KEYS,
  RESOURCE_INVENTORY_KPI_KEYS,
  severityValueClass,
} from '../utils/dashboardDisplay'
import DashboardSectionCard from './DashboardSectionCard.vue'

const props = defineProps<{
  kpis?: DashboardKpis | null
  resources?: DashboardResources | null
}>()

const { t } = useI18n()

const inventoryEntries = computed(() =>
  pickKpis(props.kpis, RESOURCE_INVENTORY_KPI_KEYS),
)
const assetEntries = computed(() => pickKpis(props.kpis, RESOURCE_ASSET_KPI_KEYS))
const myCustodies = computed(() => props.resources?.my_custodies)

const visible = computed(
  () =>
    inventoryEntries.value.length > 0 ||
    assetEntries.value.length > 0 ||
    myCustodies.value != null,
)
</script>

<template>
  <DashboardSectionCard
    v-if="visible"
    :title="t('dashboard.resources')"
  >
    <div class="space-y-5">
      <div v-if="inventoryEntries.length > 0">
        <h4 class="mb-2 text-sm font-semibold text-brand-text">
          {{ t('dashboard.inventory') }}
        </h4>
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
          <component
            :is="isSafeAppHref(entry.kpi.href) ? RouterLink : 'div'"
            v-for="entry in inventoryEntries"
            :key="entry.key"
            :to="isSafeAppHref(entry.kpi.href) ? entry.kpi.href : undefined"
            class="rounded-xl border border-brand-border px-3 py-3 transition"
            :class="
              isSafeAppHref(entry.kpi.href)
                ? 'hover:border-brand-primary/30 hover:bg-brand-primary-soft/30'
                : ''
            "
          >
            <p class="text-xs text-brand-text-secondary">
              {{ entry.kpi.label }}
            </p>
            <p
              class="mt-1 text-xl font-bold tabular-nums"
              :class="severityValueClass(entry.kpi.severity)"
            >
              {{ entry.kpi.value }}
            </p>
          </component>
        </div>
      </div>

      <div v-if="assetEntries.length > 0">
        <h4 class="mb-2 text-sm font-semibold text-brand-text">
          {{ t('dashboard.assets') }}
        </h4>
        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
          <component
            :is="isSafeAppHref(entry.kpi.href) ? RouterLink : 'div'"
            v-for="entry in assetEntries"
            :key="entry.key"
            :to="isSafeAppHref(entry.kpi.href) ? entry.kpi.href : undefined"
            class="rounded-xl border border-brand-border px-3 py-3 transition"
            :class="
              isSafeAppHref(entry.kpi.href)
                ? 'hover:border-brand-primary/30 hover:bg-brand-primary-soft/30'
                : ''
            "
          >
            <p class="text-xs text-brand-text-secondary">
              {{ entry.kpi.label }}
            </p>
            <p
              class="mt-1 text-xl font-bold tabular-nums"
              :class="severityValueClass(entry.kpi.severity)"
            >
              {{ entry.kpi.value }}
            </p>
          </component>
        </div>
      </div>

      <component
        :is="isSafeAppHref(myCustodies.href) ? RouterLink : 'div'"
        v-if="myCustodies"
        :to="isSafeAppHref(myCustodies.href) ? myCustodies.href : undefined"
        class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-brand-border bg-[#F4F6F5]/70 p-4 transition"
        :class="
          isSafeAppHref(myCustodies.href)
            ? 'hover:border-brand-primary/30 hover:bg-brand-primary-soft/40'
            : ''
        "
      >
        <div>
          <p class="text-sm font-bold text-brand-text">
            {{ t('dashboard.myCustodies') }}
          </p>
          <p class="mt-1 text-xs text-brand-text-secondary">
            {{ t('dashboard.myCustodiesHint') }}
          </p>
        </div>
        <div class="flex gap-4 text-sm">
          <div class="text-center">
            <p class="text-xs text-brand-text-muted">
              {{ t('dashboard.activeCount') }}
            </p>
            <p class="mt-0.5 text-lg font-bold tabular-nums text-brand-primary-dark">
              {{ myCustodies.active }}
            </p>
          </div>
          <div class="text-center">
            <p class="text-xs text-brand-text-muted">
              {{ t('dashboard.overdueCount') }}
            </p>
            <p
              class="mt-0.5 text-lg font-bold tabular-nums"
              :class="myCustodies.overdue > 0 ? 'text-red-800' : 'text-brand-text'"
            >
              {{ myCustodies.overdue }}
            </p>
          </div>
        </div>
      </component>
    </div>
  </DashboardSectionCard>
</template>
