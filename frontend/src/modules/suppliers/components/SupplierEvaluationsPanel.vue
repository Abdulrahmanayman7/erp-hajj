<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { ClipboardList } from 'lucide-vue-next'

import { useEvaluationsQuery } from '../queries/useEvaluationsQuery'
import type { Supplier } from '../types/suppliers'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'

const props = defineProps<{ supplier: Supplier }>()
const { t } = useI18n()

const filters = computed(() => ({
  supplier_id: props.supplier.id,
  per_page: 20,
}))

const { data, isLoading, isError, refetch } = useEvaluationsQuery(filters)
const rows = computed(() => data.value?.data ?? [])
</script>

<template>
  <section class="space-y-4 rounded-2xl border border-brand-border bg-brand-surface p-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-2">
        <ClipboardList class="h-5 w-5 text-brand-primary" />
        <h2 class="text-base font-bold text-brand-text">{{ t('evaluations.tabTitle') }}</h2>
      </div>
      <PermissionGuard permission="supplier_evaluations.view">
        <RouterLink
          :to="{ path: '/app/supplier-evaluations', query: { supplier_id: String(supplier.id) } }"
          class="text-sm font-semibold text-brand-primary"
        >
          {{ t('evaluations.openList') }}
        </RouterLink>
      </PermissionGuard>
    </div>

    <p v-if="isLoading" class="text-sm text-brand-text-muted">{{ t('common.loading') }}</p>
    <p v-else-if="isError" class="text-sm text-red-700">
      {{ t('evaluations.loadError') }}
      <button type="button" class="ms-2 underline" @click="refetch()">{{ t('common.retry') }}</button>
    </p>
    <p v-else-if="rows.length === 0" class="text-sm text-brand-text-muted">
      {{ t('evaluations.empty') }}
    </p>
    <ul v-else class="divide-y divide-brand-border rounded-xl border border-brand-border">
      <li
        v-for="row in rows"
        :key="row.id"
        class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm"
      >
        <div>
          <p class="font-semibold text-brand-text">{{ row.evaluation_number }}</p>
          <p class="text-brand-text-secondary">
            {{ t(`evaluations.kinds.${row.evaluation_kind}`) }} ·
            {{ t(`evaluations.statuses.${row.status}`) }} · v{{ row.version_no }}
          </p>
        </div>
        <div class="text-start">
          <p class="font-bold text-brand-primary">{{ row.total_score ?? '—' }}</p>
          <RouterLink
            :to="`/app/supplier-evaluations/${row.id}`"
            class="text-xs font-semibold text-brand-primary"
          >
            {{ t('evaluations.view') }}
          </RouterLink>
        </div>
      </li>
    </ul>
  </section>
</template>
