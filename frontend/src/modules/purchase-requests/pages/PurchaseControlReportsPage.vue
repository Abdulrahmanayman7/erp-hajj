<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

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

watch(active, () => {
  void load()
}, { immediate: true })

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
    <header>
      <h1 class="text-2xl font-semibold">{{ t('purchaseControlReports.title') }}</h1>
      <p class="mt-1 text-sm text-muted-foreground">{{ t('purchaseControlReports.subtitle') }}</p>
    </header>

    <div class="flex flex-wrap gap-2 border-b pb-2">
      <button
        v-for="tab in tabs"
        :key="tab"
        type="button"
        class="rounded-md px-3 py-2 text-sm"
        :class="active === tab ? 'bg-primary text-primary-foreground' : 'border'"
        @click="active = tab"
      >
        {{ t(`purchaseControlReports.tabs.${tab}`) }}
      </button>
    </div>

    <div class="flex flex-wrap items-end gap-3">
      <label class="text-sm">
        {{ t('purchaseControlReports.from') }}
        <input v-model="filters.from" type="date" class="mt-1 block rounded-md border px-3 py-2" />
      </label>
      <label class="text-sm">
        {{ t('purchaseControlReports.to') }}
        <input v-model="filters.to" type="date" class="mt-1 block rounded-md border px-3 py-2" />
      </label>
      <button type="button" class="rounded-md border px-3 py-2 text-sm" @click="load">
        {{ t('purchaseControlReports.apply') }}
      </button>
      <PermissionGuard permission="purchase_requests.view_control_reports">
        <button
          type="button"
          class="rounded-md bg-primary px-3 py-2 text-sm text-primary-foreground disabled:opacity-50"
          :disabled="exportBusy"
          @click="downloadCsv"
        >
          {{ t('purchaseControlReports.exportCsv') }}
        </button>
      </PermissionGuard>
    </div>

    <div v-if="isLoading" class="text-sm text-muted-foreground">{{ t('common.loading') }}</div>
    <div v-else-if="isError" class="text-sm text-destructive">{{ errorMessage || t('common.error') }}</div>
    <p v-else-if="errorMessage" class="text-sm text-destructive">{{ errorMessage }}</p>
    <pre
      v-else-if="payload"
      class="overflow-auto rounded-md border bg-muted/30 p-4 text-xs leading-relaxed"
      dir="ltr"
    >{{ JSON.stringify(payload, null, 2) }}</pre>
    <p v-else class="text-sm text-muted-foreground">{{ t('purchaseControlReports.empty') }}</p>
  </div>
</template>
