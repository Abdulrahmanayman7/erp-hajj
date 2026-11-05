<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'

import AppDateInput from '@/shared/components/AppDateInput.vue'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'

import { downloadControlReportCsv, fetchControlReport } from '../api/demandGroupsApi'
import type { ControlReportKind } from '../types/demandGroups'

const { t } = useI18n()

const tabs: ControlReportKind[] = ['approval-latency', 'overdue', 'outcomes', 'compliance']
const active = ref<ControlReportKind>('approval-latency')

const today = new Date()
const fromDefault = new Date(today)
fromDefault.setDate(fromDefault.getDate() - 30)

const filters = reactive({
  from: fromDefault.toISOString().slice(0, 10),
  to: today.toISOString().slice(0, 10),
})

const payload = ref<Record<string, unknown> | null>(null)
const isLoading = ref(false)
const isError = ref(false)
const errorMessage = ref('')
const exportBusy = ref(false)

const rows = computed(() => {
  const list = payload.value?.rows
  return Array.isArray(list) ? (list as Array<Record<string, unknown>>) : []
})

const summaryCards = computed(() => {
  const data = payload.value
  if (!data) return [] as Array<{ key: string; label: string; value: string }>

  if (active.value === 'approval-latency') {
    return [
      { key: 'count', label: t('purchaseControlReports.summary.count'), value: formatValue(data.count) },
      { key: 'avg', label: t('purchaseControlReports.summary.avgHours'), value: formatHours(data.avg_hours) },
      { key: 'median', label: t('purchaseControlReports.summary.medianHours'), value: formatHours(data.median_hours) },
      { key: 'p95', label: t('purchaseControlReports.summary.p95Hours'), value: formatHours(data.p95_hours) },
      { key: 'inflight', label: t('purchaseControlReports.summary.inFlight'), value: formatValue(data.in_flight_count) },
    ]
  }

  if (active.value === 'outcomes') {
    return [
      {
        key: 'priced',
        label: t('purchaseControlReports.summary.approvedPricedTotal'),
        value: formatValue(data.approved_priced_total),
      },
      {
        key: 'unpriced',
        label: t('purchaseControlReports.summary.approvedUnpriced'),
        value: formatValue(data.approved_unpriced_count),
      },
    ]
  }

  return [
    { key: 'count', label: t('purchaseControlReports.summary.count'), value: formatValue(data.count) },
  ]
})

function formatValue(value: unknown): string {
  if (value === null || value === undefined || value === '') return '—'
  return String(value)
}

function formatHours(value: unknown): string {
  if (value === null || value === undefined || value === '') return '—'
  const n = Number(value)
  return Number.isFinite(n) ? n.toFixed(2) : String(value)
}

function formatDate(value: unknown): string {
  if (typeof value !== 'string' || !value) return '—'
  try {
    return new Date(value).toLocaleString('ar-SA')
  } catch {
    return value
  }
}

function outcomeStatusLabel(status: unknown): string {
  const key = String(status ?? '')
  if (key === 'under_review') return t('purchaseRequests.statuses.underReview')
  if (key) {
    const path = `purchaseRequests.statuses.${key}`
    const translated = t(path)
    return translated === path ? key : translated
  }
  return '—'
}

async function load(): Promise<void> {
  isLoading.value = true
  isError.value = false
  errorMessage.value = ''
  try {
    payload.value = await fetchControlReport(active.value, filters)
  } catch (e) {
    isError.value = true
    errorMessage.value = e instanceof Error ? e.message : t('common.error')
    payload.value = null
  } finally {
    isLoading.value = false
  }
}

watch(
  active,
  () => {
    void load()
  },
  { immediate: true },
)

async function downloadCsv(): Promise<void> {
  exportBusy.value = true
  errorMessage.value = ''
  try {
    const { blob, filename } = await downloadControlReportCsv(active.value, filters)
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = filename ?? `${active.value}.csv`
    a.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    errorMessage.value = e instanceof Error ? e.message : t('purchaseControlReports.exportFailed')
  } finally {
    exportBusy.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <AppPageHeader
      :title="t('purchaseControlReports.title')"
      :subtitle="t('purchaseControlReports.subtitle')"
    />

    <div class="flex flex-wrap gap-2 rounded-2xl border border-brand-border bg-brand-surface p-2">
      <button
        v-for="tab in tabs"
        :key="tab"
        type="button"
        class="rounded-xl px-3 py-2 text-sm font-semibold transition"
        :class="
          active === tab
            ? 'bg-brand-primary text-white'
            : 'border border-brand-border text-brand-text hover:bg-brand-bg'
        "
        @click="active = tab"
      >
        {{ t(`purchaseControlReports.tabs.${tab}`) }}
      </button>
    </div>

    <div
      class="flex flex-col gap-3 rounded-2xl border border-brand-border bg-brand-surface p-4 sm:flex-row sm:flex-wrap sm:items-end"
    >
      <label class="min-w-[10rem] flex-1 text-sm">
        <span class="mb-1 block text-brand-text-secondary">{{ t('purchaseControlReports.from') }}</span>
        <AppDateInput v-model="filters.from" :placeholder="t('purchaseControlReports.from')" />
      </label>
      <label class="min-w-[10rem] flex-1 text-sm">
        <span class="mb-1 block text-brand-text-secondary">{{ t('purchaseControlReports.to') }}</span>
        <AppDateInput v-model="filters.to" :placeholder="t('purchaseControlReports.to')" />
      </label>
      <button
        type="button"
        class="inline-flex h-11 items-center justify-center rounded-xl border border-brand-border px-4 text-sm font-semibold"
        @click="load"
      >
        {{ t('purchaseControlReports.apply') }}
      </button>
      <PermissionGuard permission="purchase_requests.view_control_reports">
        <button
          type="button"
          class="inline-flex h-11 items-center justify-center rounded-xl bg-brand-primary px-4 text-sm font-semibold text-white disabled:opacity-50"
          :disabled="exportBusy"
          @click="downloadCsv"
        >
          {{ t('purchaseControlReports.exportCsv') }}
        </button>
      </PermissionGuard>
    </div>

    <div v-if="isLoading" class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted">
      {{ t('purchaseControlReports.loading') }}
    </div>
    <div v-else-if="isError" class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center text-sm text-red-700">
      {{ errorMessage || t('common.error') }}
    </div>
    <template v-else-if="payload">
      <p v-if="errorMessage" class="text-sm text-red-700">{{ errorMessage }}</p>

      <div
        v-if="summaryCards.length"
        class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5"
      >
        <div
          v-for="card in summaryCards"
          :key="card.key"
          class="rounded-2xl border border-brand-border bg-brand-surface p-4"
        >
          <p class="text-xs font-semibold text-brand-text-secondary">{{ card.label }}</p>
          <p class="mt-2 text-xl font-bold text-brand-text">{{ card.value }}</p>
        </div>
      </div>

      <div class="rounded-2xl border border-brand-border bg-brand-surface">
        <div v-if="rows.length === 0" class="p-10 text-center text-sm text-brand-text-muted">
          {{ t('purchaseControlReports.empty') }}
        </div>
        <div v-else class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="border-b border-brand-border bg-brand-bg/50 text-start text-brand-text-secondary">
              <tr v-if="active === 'approval-latency'">
                <th class="px-4 py-3 font-semibold">{{ t('purchaseControlReports.columns.prNumber') }}</th>
                <th class="px-4 py-3 font-semibold">{{ t('purchaseControlReports.columns.status') }}</th>
                <th class="px-4 py-3 font-semibold">{{ t('purchaseControlReports.columns.submittedAt') }}</th>
                <th class="px-4 py-3 font-semibold">{{ t('purchaseControlReports.columns.terminalAt') }}</th>
                <th class="px-4 py-3 font-semibold">{{ t('purchaseControlReports.columns.durationHours') }}</th>
              </tr>
              <tr v-else-if="active === 'overdue'">
                <th class="px-4 py-3 font-semibold">{{ t('purchaseControlReports.columns.prNumber') }}</th>
                <th class="px-4 py-3 font-semibold">{{ t('purchaseControlReports.columns.level') }}</th>
                <th class="px-4 py-3 font-semibold">{{ t('purchaseControlReports.columns.dueAt') }}</th>
                <th class="px-4 py-3 font-semibold">{{ t('purchaseControlReports.columns.hoursOverdue') }}</th>
                <th class="px-4 py-3 font-semibold">{{ t('purchaseControlReports.columns.escalatedAt') }}</th>
              </tr>
              <tr v-else-if="active === 'outcomes'">
                <th class="px-4 py-3 font-semibold">{{ t('purchaseControlReports.columns.status') }}</th>
                <th class="px-4 py-3 font-semibold">{{ t('purchaseControlReports.columns.count') }}</th>
              </tr>
              <tr v-else>
                <th class="px-4 py-3 font-semibold">{{ t('purchaseControlReports.columns.eventType') }}</th>
                <th class="px-4 py-3 font-semibold">{{ t('purchaseControlReports.columns.occurredAt') }}</th>
                <th class="px-4 py-3 font-semibold">{{ t('purchaseControlReports.columns.entityNumber') }}</th>
                <th class="px-4 py-3 font-semibold">{{ t('purchaseControlReports.columns.reason') }}</th>
                <th class="px-4 py-3 font-semibold">{{ t('purchaseControlReports.columns.errorCode') }}</th>
              </tr>
            </thead>
            <tbody>
              <template v-if="active === 'approval-latency'">
                <tr
                  v-for="(row, index) in rows"
                  :key="`latency-${index}`"
                  class="border-b border-brand-border/60 last:border-0"
                >
                  <td class="px-4 py-3">
                    <RouterLink
                      class="font-semibold text-brand-primary hover:underline"
                      :to="`/app/purchase-requests/${row.pr_id}`"
                    >
                      {{ row.pr_number }}
                    </RouterLink>
                  </td>
                  <td class="px-4 py-3">{{ outcomeStatusLabel(row.status) }}</td>
                  <td class="px-4 py-3">{{ formatDate(row.submitted_at) }}</td>
                  <td class="px-4 py-3">{{ formatDate(row.terminal_at) }}</td>
                  <td class="px-4 py-3">{{ formatHours(row.duration_hours) }}</td>
                </tr>
              </template>
              <template v-else-if="active === 'overdue'">
                <tr
                  v-for="(row, index) in rows"
                  :key="`overdue-${index}`"
                  class="border-b border-brand-border/60 last:border-0"
                >
                  <td class="px-4 py-3">
                    <RouterLink
                      class="font-semibold text-brand-primary hover:underline"
                      :to="`/app/purchase-requests/${row.pr_id}`"
                    >
                      {{ row.pr_number }}
                    </RouterLink>
                  </td>
                  <td class="px-4 py-3">{{ row.level_order }}</td>
                  <td class="px-4 py-3">{{ formatDate(row.due_at) }}</td>
                  <td class="px-4 py-3">{{ formatHours(row.hours_overdue) }}</td>
                  <td class="px-4 py-3">{{ formatDate(row.escalated_at) }}</td>
                </tr>
              </template>
              <template v-else-if="active === 'outcomes'">
                <tr
                  v-for="(row, index) in rows"
                  :key="`outcomes-${index}`"
                  class="border-b border-brand-border/60 last:border-0"
                >
                  <td class="px-4 py-3">{{ outcomeStatusLabel(row.status) }}</td>
                  <td class="px-4 py-3">{{ formatValue(row.count) }}</td>
                </tr>
              </template>
              <template v-else>
                <tr
                  v-for="(row, index) in rows"
                  :key="`compliance-${index}`"
                  class="border-b border-brand-border/60 last:border-0"
                >
                  <td class="px-4 py-3">{{ row.event_type }}</td>
                  <td class="px-4 py-3">{{ formatDate(row.occurred_at) }}</td>
                  <td class="px-4 py-3">{{ row.entity_number ?? '—' }}</td>
                  <td class="px-4 py-3">{{ row.reason ?? '—' }}</td>
                  <td class="px-4 py-3">{{ row.error_code ?? '—' }}</td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>
    </template>
    <p v-else class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted">
      {{ t('purchaseControlReports.empty') }}
    </p>
  </div>
</template>
