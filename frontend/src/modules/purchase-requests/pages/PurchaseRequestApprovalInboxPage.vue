<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { ArrowRight } from 'lucide-vue-next'

import { usePurchaseRequestApprovalInboxQuery } from '../queries/usePurchaseRequestsQuery'

const { t } = useI18n()
const { data, isLoading, isError, refetch } = usePurchaseRequestApprovalInboxQuery()
const rows = computed(() => data.value ?? [])
</script>

<template>
  <div class="mx-auto max-w-5xl space-y-6">
    <RouterLink
      to="/app/purchase-requests"
      class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
    >
      <ArrowRight class="h-4 w-4" />
      {{ t('purchaseRequests.backToList') }}
    </RouterLink>

    <header>
      <h1 class="text-2xl font-semibold">{{ t('purchaseRequests.inbox.title') }}</h1>
      <p class="mt-1 text-sm text-muted-foreground">{{ t('purchaseRequests.inbox.subtitle') }}</p>
    </header>

    <div v-if="isLoading" class="text-sm text-muted-foreground">{{ t('purchaseRequests.loading') }}</div>
    <div v-else-if="isError" class="space-y-2">
      <p class="text-sm text-destructive">{{ t('purchaseRequests.inbox.loadError') }}</p>
      <button type="button" class="rounded-md border border-border px-3 py-2 text-sm" @click="refetch()">
        {{ t('purchaseRequests.search') }}
      </button>
    </div>
    <div v-else-if="rows.length === 0" class="text-sm text-muted-foreground">
      {{ t('purchaseRequests.inbox.empty') }}
    </div>
    <div v-else class="overflow-x-auto rounded-md border border-border">
      <table class="min-w-full text-sm">
        <thead class="bg-muted/40 text-muted-foreground">
          <tr>
            <th class="px-3 py-2 text-start">{{ t('purchaseRequests.columns.number') }}</th>
            <th class="px-3 py-2 text-start">{{ t('purchaseRequests.columns.title') }}</th>
            <th class="px-3 py-2 text-start">{{ t('purchaseRequests.columns.priority') }}</th>
            <th class="px-3 py-2 text-start">{{ t('purchaseRequests.columns.total') }}</th>
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
            <td class="px-3 py-2">{{ t(`purchaseRequests.priorities.${row.priority}`) }}</td>
            <td class="px-3 py-2">{{ row.estimated_total_priced }} {{ row.currency }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
