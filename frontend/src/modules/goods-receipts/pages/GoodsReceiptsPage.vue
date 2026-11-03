<script setup lang="ts">
import { computed, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { FilePlus2 } from 'lucide-vue-next'

import { useGoodsReceiptsQuery } from '../queries/useGoodsReceiptQuery'

const { t } = useI18n()

const filters = reactive({
  status: '',
  q: '',
  page: 1,
})

const { data, isLoading, isError, refetch } = useGoodsReceiptsQuery(filters)
const rows = computed(() => data.value?.data ?? [])
</script>

<template>
  <div class="space-y-6">
    <header>
      <h1 class="text-2xl font-semibold text-foreground">{{ t('goodsReceipts.title') }}</h1>
      <p class="mt-1 text-sm text-muted-foreground">{{ t('goodsReceipts.subtitle') }}</p>
    </header>

    <section class="flex flex-wrap gap-3">
      <input
        v-model="filters.q"
        type="search"
        class="rounded-md border border-border bg-background px-3 py-2 text-sm"
        :placeholder="t('goodsReceipts.searchPlaceholder')"
        @keyup.enter="filters.page = 1; refetch()"
      />
      <select v-model="filters.status" class="rounded-md border border-border bg-background px-3 py-2 text-sm">
        <option value="">{{ t('goodsReceipts.filters.allStatuses') }}</option>
        <option value="draft">{{ t('goodsReceipts.statuses.draft') }}</option>
        <option value="posted">{{ t('goodsReceipts.statuses.posted') }}</option>
      </select>
      <button type="button" class="rounded-md border border-border px-3 py-2 text-sm" @click="filters.page = 1; refetch()">
        {{ t('goodsReceipts.search') }}
      </button>
    </section>

    <div v-if="isLoading" class="text-sm text-muted-foreground">{{ t('goodsReceipts.loading') }}</div>
    <div v-else-if="isError" class="text-sm text-destructive">{{ t('goodsReceipts.loadError') }}</div>
    <div
      v-else-if="rows.length === 0"
      class="rounded-md border border-dashed border-border p-10 text-center text-sm text-muted-foreground"
    >
      <FilePlus2 class="mx-auto mb-3 h-8 w-8 opacity-50" />
      {{ t('goodsReceipts.empty') }}
    </div>
    <div v-else class="overflow-x-auto rounded-md border border-border">
      <table class="min-w-full text-sm">
        <thead class="bg-muted/40 text-muted-foreground">
          <tr>
            <th class="px-3 py-2 text-start font-medium">{{ t('goodsReceipts.columns.number') }}</th>
            <th class="px-3 py-2 text-start font-medium">{{ t('goodsReceipts.columns.status') }}</th>
            <th class="px-3 py-2 text-start font-medium">{{ t('goodsReceipts.columns.po') }}</th>
            <th class="px-3 py-2 text-start font-medium">{{ t('goodsReceipts.columns.warehouse') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id" class="border-t border-border hover:bg-muted/20">
            <td class="px-3 py-2">
              <RouterLink class="text-primary hover:underline" :to="`/app/goods-receipts/${row.id}`">
                {{ row.grn_number }}
              </RouterLink>
            </td>
            <td class="px-3 py-2">{{ t(`goodsReceipts.statuses.${row.status}`) }}</td>
            <td class="px-3 py-2">
              <RouterLink class="text-primary hover:underline" :to="`/app/purchase-orders/${row.purchase_order_id}`">
                {{ row.purchase_order?.po_number ?? `#${row.purchase_order_id}` }}
              </RouterLink>
            </td>
            <td class="px-3 py-2">{{ row.warehouse?.name ?? '—' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
