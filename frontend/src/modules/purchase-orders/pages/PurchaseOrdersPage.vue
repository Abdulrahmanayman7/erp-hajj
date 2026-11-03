<script setup lang="ts">
import { computed, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { FilePlus2 } from 'lucide-vue-next'

import { usePurchaseOrdersQuery } from '../queries/usePurchaseOrderQuery'

const { t } = useI18n()

const filters = reactive({
  status: '',
  q: '',
  page: 1,
})

const { data, isLoading, isError, refetch } = usePurchaseOrdersQuery(filters)
const rows = computed(() => data.value?.data ?? [])
</script>

<template>
  <div class="space-y-6">
    <header>
      <h1 class="text-2xl font-semibold text-foreground">
        {{ t('purchaseOrders.title') }}
      </h1>
      <p class="mt-1 text-sm text-muted-foreground">
        {{ t('purchaseOrders.subtitle') }}
      </p>
    </header>

    <section class="flex flex-wrap gap-3">
      <input
        v-model="filters.q"
        type="search"
        class="rounded-md border border-border bg-background px-3 py-2 text-sm"
        :placeholder="t('purchaseOrders.searchPlaceholder')"
        @keyup.enter="filters.page = 1; refetch()"
      />
      <select v-model="filters.status" class="rounded-md border border-border bg-background px-3 py-2 text-sm">
        <option value="">{{ t('purchaseOrders.filters.allStatuses') }}</option>
        <option value="draft">{{ t('purchaseOrders.statuses.draft') }}</option>
        <option value="issued">{{ t('purchaseOrders.statuses.issued') }}</option>
        <option value="partially_received">{{ t('purchaseOrders.statuses.partially_received') }}</option>
        <option value="received">{{ t('purchaseOrders.statuses.received') }}</option>
        <option value="closed">{{ t('purchaseOrders.statuses.closed') }}</option>
        <option value="cancelled">{{ t('purchaseOrders.statuses.cancelled') }}</option>
      </select>
      <button
        type="button"
        class="rounded-md border border-border px-3 py-2 text-sm"
        @click="filters.page = 1; refetch()"
      >
        {{ t('purchaseOrders.search') }}
      </button>
    </section>

    <div v-if="isLoading" class="text-sm text-muted-foreground">{{ t('purchaseOrders.loading') }}</div>
    <div v-else-if="isError" class="text-sm text-destructive">{{ t('purchaseOrders.loadError') }}</div>
    <div
      v-else-if="rows.length === 0"
      class="rounded-md border border-dashed border-border p-10 text-center text-sm text-muted-foreground"
    >
      <FilePlus2 class="mx-auto mb-3 h-8 w-8 opacity-50" />
      {{ t('purchaseOrders.empty') }}
    </div>
    <div v-else class="overflow-x-auto rounded-md border border-border">
      <table class="min-w-full text-sm">
        <thead class="bg-muted/40 text-muted-foreground">
          <tr>
            <th class="px-3 py-2 text-start font-medium">{{ t('purchaseOrders.columns.number') }}</th>
            <th class="px-3 py-2 text-start font-medium">{{ t('purchaseOrders.columns.status') }}</th>
            <th class="px-3 py-2 text-start font-medium">{{ t('purchaseOrders.columns.supplier') }}</th>
            <th class="px-3 py-2 text-start font-medium">{{ t('purchaseOrders.columns.rfq') }}</th>
            <th class="px-3 py-2 text-start font-medium">{{ t('purchaseOrders.columns.pr') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in rows"
            :key="row.id"
            class="border-t border-border hover:bg-muted/20"
          >
            <td class="px-3 py-2">
              <RouterLink class="text-primary hover:underline" :to="`/app/purchase-orders/${row.id}`">
                {{ row.po_number }}
              </RouterLink>
            </td>
            <td class="px-3 py-2">{{ t(`purchaseOrders.statuses.${row.status}`) }}</td>
            <td class="px-3 py-2">{{ row.supplier?.display_name ?? `#${row.supplier_id}` }}</td>
            <td class="px-3 py-2">
              <RouterLink
                class="text-primary hover:underline"
                :to="`/app/rfqs/${row.procurement_rfq_id}`"
              >
                {{ row.rfq?.rfq_number ?? `#${row.procurement_rfq_id}` }}
              </RouterLink>
            </td>
            <td class="px-3 py-2">
              <RouterLink
                class="text-primary hover:underline"
                :to="`/app/purchase-requests/${row.purchase_request_id}`"
              >
                {{ row.purchase_request?.pr_number ?? `#${row.purchase_request_id}` }}
              </RouterLink>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
