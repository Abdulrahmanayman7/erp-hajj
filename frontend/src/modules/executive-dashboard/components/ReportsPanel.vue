<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { Download } from 'lucide-vue-next'

import {
  downloadExecutiveReportCsv,
  EXECUTIVE_REPORT_IDS,
  type ExecutiveReportId,
} from '../api/executiveReportsApi'

const props = defineProps<{
  seasonId: number
}>()

const { t } = useI18n()
const downloading = ref<ExecutiveReportId | null>(null)
const error = ref(false)

async function onDownload(report: ExecutiveReportId): Promise<void> {
  downloading.value = report
  error.value = false
  try {
    const { blob, filename } = await downloadExecutiveReportCsv(report, props.seasonId)
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = filename ?? `${report}.csv`
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    error.value = true
  } finally {
    downloading.value = null
  }
}
</script>

<template>
  <section class="app-surface-flat space-y-3 p-4" aria-label="executive reports panel" dir="rtl">
    <div class="space-y-1">
      <h2 class="text-sm font-bold text-brand-text">{{ t('executiveDashboard.reports.title') }}</h2>
      <p class="text-xs text-brand-text-secondary">{{ t('executiveDashboard.reports.hint') }}</p>
    </div>

    <p v-if="error" class="text-sm text-red-700">{{ t('common.error') }}</p>

    <ul class="grid gap-2 sm:grid-cols-2">
      <li v-for="report in EXECUTIVE_REPORT_IDS" :key="report">
        <button
          type="button"
          class="flex w-full items-center justify-between gap-2 rounded-lg border border-brand-border/80 bg-[var(--surface-subtle)] px-3 py-2.5 text-start text-sm font-bold text-brand-text transition hover:border-brand-primary/30 disabled:opacity-50"
          :disabled="downloading != null"
          :data-test="`report-download-${report}`"
          @click="onDownload(report)"
        >
          <span>{{ t(`executiveDashboard.reports.items.${report}`) }}</span>
          <Download class="h-4 w-4 shrink-0 text-brand-text-muted" :class="{ 'animate-pulse': downloading === report }" />
        </button>
      </li>
    </ul>
  </section>
</template>
