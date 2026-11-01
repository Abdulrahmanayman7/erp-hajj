<script setup lang="ts">
import { computed, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { FilePlus2, Plus } from 'lucide-vue-next'

import PermissionGuard from '@/shared/components/PermissionGuard.vue'

import { usePurchaseRequestsQuery } from '../queries/usePurchaseRequestsQuery'
import type { PurchaseRequestKind, PurchaseRequestStatus } from '../types/purchaseRequests'
import { statusOptions } from '../validation/purchaseRequestValidation'

const { t } = useI18n()

const filters = reactive({
  status: '' as PurchaseRequestStatus | '',
  request_kind: '' as PurchaseRequestKind | '',
  q: '',
  page: 1,
  per_page: 20,
})

const { data, isLoading, isError, refetch } = usePurchaseRequestsQuery(filters)
const rows = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta ?? {})
</script>

<template>
  <div class="space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 class="text-2xl font-semibold text-foreground">
          {{ t('purchaseRequests.title') }}
        </h1>
        <p class="mt-1 text-sm text-muted-foreground">
          {{ t('purchaseRequests.subtitle') }}
        </p>
      </div>
      <PermissionGuard permission="purchase_requests.create">
        <RouterLink
          to="/app/purchase-requests/create"
          class="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground"
        >
          <Plus class="h-4 w-4" />
          {{ t('purchaseRequests.create') }}
        </RouterLink>
      </PermissionGuard>
    </header>

    <section class="flex flex-wrap gap-3">
      <input
        v-model="filters.q"
        type="search"
        class="rounded-md border border-border bg-background px-3 py-2 text-sm"
        :placeholder="t('purchaseRequests.searchPlaceholder')"
        @keyup.enter="filters.page = 1; refetch()"
      />
      <select v-model="filters.status" class="rounded-md border border-border bg-background px-3 py-2 text-sm">
        <option value="">{{ t('purchaseRequests.filters.allStatuses') }}</option>
        <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
          {{ t(opt.labelKey) }}
        </option>
      </select>
      <select
        v-model="filters.request_kind"
        class="rounded-md border border-border bg-background px-3 py-2 text-sm"
      >
        <option value="">{{ t('purchaseRequests.filters.allKinds') }}</option>
        <option value="administrative">{{ t('purchaseRequests.kinds.administrative') }}</option>
        <option value="seasonal">{{ t('purchaseRequests.kinds.seasonal') }}</option>
      </select>
      <button
        type="button"
        class="rounded-md border border-border px-3 py-2 text-sm"
        @click="filters.page = 1; refetch()"
      >
        {{ t('purchaseRequests.search') }}
      </button>
    </section>

    <div v-if="isLoading" class="text-sm text-muted-foreground">{{ t('purchaseRequests.loading') }}</div>
    <div v-else-if="isError" class="text-sm text-destructive">{{ t('purchaseRequests.loadError') }}</div>
    <div
      v-else-if="rows.length === 0"
      class="rounded-md border border-dashed border-border p-10 text-center text-sm text-muted-foreground"
    >
      <FilePlus2 class="mx-auto mb-3 h-8 w-8 opacity-50" />
      {{ t('purchaseRequests.empty') }}
    </div>
    <div v-else class="overflow-x-auto rounded-md border border-border">
      <table class="min-w-full text-sm">
        <thead class="bg-muted/40 text-muted-foreground">
          <tr>
            <th class="px-3 py-2 text-start font-medium">{{ t('purchaseRequests.columns.number') }}</th>
            <th class="px-3 py-2 text-start font-medium">{{ t('purchaseRequests.columns.title') }}</th>
            <th class="px-3 py-2 text-start font-medium">{{ t('purchaseRequests.columns.status') }}</th>
            <th class="px-3 py-2 text-start font-medium">{{ t('purchaseRequests.columns.priority') }}</th>
            <th class="px-3 py-2 text-start font-medium">{{ t('purchaseRequests.columns.total') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id" class="border-t border-border">
            <td class="px-3 py-2">
              <RouterLink
                :to="`/app/purchase-requests/${row.id}`"
                class="font-medium text-primary hover:underline"
              >
                {{ row.pr_number }}
              </RouterLink>
            </td>
            <td class="px-3 py-2">{{ row.title }}</td>
            <td class="px-3 py-2">
              <span>{{ t(`purchaseRequests.statuses.${row.status === 'under_review' ? 'underReview' : row.status}`) }}</span>
              <span
                v-if="row.pending_approval_engine"
                class="ms-2 text-xs text-amber-700"
              >
                {{ t('purchaseRequests.pendingApprovalEngine') }}
              </span>
            </td>
            <td class="px-3 py-2">{{ t(`purchaseRequests.priorities.${row.priority}`) }}</td>
            <td class="px-3 py-2">{{ row.estimated_total_priced }} {{ row.currency }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <p v-if="meta.total" class="text-xs text-muted-foreground">
      {{ t('purchaseRequests.totalCount', { count: meta.total }) }}
    </p>
  </div>
</template>
