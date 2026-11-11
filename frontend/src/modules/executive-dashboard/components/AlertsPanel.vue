<script setup lang="ts">
import { useI18n } from 'vue-i18n'

import type { ExecutiveAlert } from '../types/executiveDashboard'

defineProps<{
  alerts: ExecutiveAlert[]
  openCount: number
  acknowledgedCount: number
  criticalCount: number
  isAcknowledging?: boolean
  isLoading?: boolean
  isError?: boolean
}>()

const emit = defineEmits<{
  (e: 'acknowledge', alertId: number): void
}>()

const { t } = useI18n()
</script>

<template>
  <section
    class="app-surface-flat space-y-3 p-4"
    :aria-label="t('executiveDashboard.a11y.alertsPanel')"
    dir="rtl"
  >
    <div class="space-y-1">
      <h2 class="text-sm font-bold text-brand-text">{{ t('executiveDashboard.alerts.title') }}</h2>
      <p class="text-xs text-brand-text-secondary">{{ t('executiveDashboard.alerts.hint') }}</p>
    </div>

    <dl class="grid grid-cols-3 gap-2.5 text-center">
      <div class="rounded-lg border border-brand-border/80 bg-[var(--surface-subtle)] p-2">
        <dt class="text-[11px] font-bold text-brand-text-muted">{{ t('executiveDashboard.alerts.open') }}</dt>
        <dd class="text-xl font-bold tabular-nums text-brand-text" data-test="alerts-open-count">
          {{ openCount }}
        </dd>
      </div>
      <div class="rounded-lg border border-brand-border/80 bg-[var(--surface-subtle)] p-2">
        <dt class="text-[11px] font-bold text-brand-text-muted">
          {{ t('executiveDashboard.alerts.acknowledged') }}
        </dt>
        <dd class="text-xl font-bold tabular-nums text-brand-text" data-test="alerts-ack-count">
          {{ acknowledgedCount }}
        </dd>
      </div>
      <div class="rounded-lg border border-brand-border/80 bg-[var(--surface-subtle)] p-2">
        <dt class="text-[11px] font-bold text-brand-text-muted">
          {{ t('executiveDashboard.alerts.critical') }}
        </dt>
        <dd class="text-xl font-bold tabular-nums text-red-700" data-test="alerts-critical-count">
          {{ criticalCount }}
        </dd>
      </div>
    </dl>

    <p v-if="isLoading" class="text-sm text-brand-text-secondary">{{ t('executiveDashboard.loading') }}</p>
    <p v-else-if="isError" class="text-sm text-red-700">{{ t('common.error') }}</p>
    <p v-else-if="alerts.length === 0" class="text-sm text-brand-text-secondary" data-test="alerts-empty">
      {{ t('executiveDashboard.alerts.empty') }}
    </p>

    <ul v-else class="space-y-2">
      <li
        v-for="alert in alerts"
        :key="alert.id"
        class="flex flex-col gap-2 rounded-lg border border-brand-border/80 bg-[var(--surface-subtle)] p-3 sm:flex-row sm:items-start sm:justify-between"
        :data-alert-id="alert.id"
      >
        <div class="min-w-0 space-y-1">
          <div class="flex flex-wrap items-center gap-2">
            <span
              class="inline-flex rounded px-1.5 py-0.5 text-[10px] font-bold"
              :class="alert.severity === 'critical' ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-800'"
              data-test="alert-severity"
            >
              {{ t(`executiveDashboard.alerts.severity.${alert.severity}`) }}
            </span>
            <span class="font-mono text-[11px] font-semibold text-brand-text-muted">{{ alert.rule_code }}</span>
            <span
              v-if="alert.status === 'acknowledged'"
              class="rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-bold text-emerald-800"
              data-test="alert-acknowledged-badge"
            >
              {{ t('executiveDashboard.alerts.acknowledged') }}
            </span>
          </div>
          <p class="text-sm font-bold text-brand-text">{{ alert.title }}</p>
          <p v-if="alert.body" class="text-xs text-brand-text-secondary">{{ alert.body }}</p>
        </div>
        <button
          v-if="alert.status === 'open'"
          type="button"
          class="shrink-0 rounded-lg border border-brand-border px-3 py-1.5 text-xs font-bold text-brand-text transition hover:border-brand-primary/30 disabled:opacity-50"
          :disabled="isAcknowledging"
          data-test="alert-acknowledge"
          @click="emit('acknowledge', alert.id)"
        >
          {{ t('executiveDashboard.alerts.acknowledge') }}
        </button>
      </li>
    </ul>
  </section>
</template>
