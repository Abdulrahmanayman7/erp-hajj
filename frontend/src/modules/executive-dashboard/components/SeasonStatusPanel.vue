<script setup lang="ts">
import { useI18n } from 'vue-i18n'

import type { OperationalKpiSeason } from '@/modules/operational-kpis/types/operationalKpis'

defineProps<{
  season: OperationalKpiSeason
}>()

const { t } = useI18n()
</script>

<template>
  <section
    class="app-surface-flat space-y-3 p-4"
    aria-label="season status panel"
    dir="rtl"
  >
    <h2 class="text-sm font-bold text-brand-text">{{ t('executiveDashboard.panels.seasonTitle') }}</h2>
    <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <div class="space-y-1">
        <dt class="text-[11px] font-bold text-brand-text-muted">
          {{ t('executiveDashboard.panels.seasonId') }}
        </dt>
        <dd class="font-mono text-sm font-semibold text-brand-text">{{ season.id }}</dd>
      </div>
      <div class="space-y-1 sm:col-span-1 lg:col-span-2">
        <dt class="text-[11px] font-bold text-brand-text-muted">
          {{ t('executiveDashboard.panels.seasonName') }}
        </dt>
        <dd class="text-sm font-bold text-brand-text">{{ season.name }}</dd>
      </div>
      <div class="space-y-1">
        <dt class="text-[11px] font-bold text-brand-text-muted">
          {{ t('executiveDashboard.panels.seasonStatus') }}
        </dt>
        <dd>
          <span
            class="inline-flex rounded px-2 py-0.5 text-xs font-bold"
            :class="
              season.status === 'closed'
                ? 'bg-amber-50 text-amber-800'
                : season.status === 'active'
                  ? 'bg-emerald-50 text-emerald-800'
                  : 'bg-[var(--surface-subtle)] text-brand-text-secondary'
            "
          >
            {{ season.status_label }}
          </span>
        </dd>
      </div>
    </dl>
  </section>
</template>
