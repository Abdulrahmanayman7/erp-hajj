<script setup lang="ts">
import { computed, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { FilePlus2 } from 'lucide-vue-next'

import { useRfqsQuery } from '../queries/useRfqQuery'

const { t } = useI18n()

const filters = reactive({
  status: '',
  q: '',
  page: 1,
})

const { data, isLoading, isError, refetch } = useRfqsQuery(filters)
const rows = computed(() => data.value?.data ?? [])
</script>

<template>
  <div class="space-y-6">
    <header>
      <h1 class="text-2xl font-semibold text-foreground">
        {{ t('rfq.title') }}
      </h1>
      <p class="mt-1 text-sm text-muted-foreground">
        {{ t('rfq.subtitle') }}
      </p>
    </header>

    <section class="flex flex-wrap gap-3">
      <input
        v-model="filters.q"
        type="search"
        class="rounded-md border border-border bg-background px-3 py-2 text-sm"
        :placeholder="t('rfq.searchPlaceholder')"
        @keyup.enter="filters.page = 1; refetch()"
      />
      <select v-model="filters.status" class="rounded-md border border-border bg-background px-3 py-2 text-sm">
        <option value="">{{ t('rfq.filters.allStatuses') }}</option>
        <option value="draft">{{ t('rfq.statuses.draft') }}</option>
        <option value="published">{{ t('rfq.statuses.published') }}</option>
        <option value="awarded">{{ t('rfq.statuses.awarded') }}</option>
        <option value="cancelled">{{ t('rfq.statuses.cancelled') }}</option>
      </select>
      <button
        type="button"
        class="rounded-md border border-border px-3 py-2 text-sm"
        @click="filters.page = 1; refetch()"
      >
        {{ t('rfq.search') }}
      </button>
    </section>

    <div v-if="isLoading" class="text-sm text-muted-foreground">{{ t('rfq.loading') }}</div>
    <div v-else-if="isError" class="text-sm text-destructive">{{ t('rfq.loadError') }}</div>
    <div
      v-else-if="rows.length === 0"
      class="rounded-md border border-dashed border-border p-10 text-center text-sm text-muted-foreground"
    >
      <FilePlus2 class="mx-auto mb-3 h-8 w-8 opacity-50" />
      {{ t('rfq.empty') }}
    </div>
    <div v-else class="overflow-x-auto rounded-md border border-border">
      <table class="min-w-full text-sm">
        <thead class="bg-muted/40 text-muted-foreground">
          <tr>
            <th class="px-3 py-2 text-start font-medium">{{ t('rfq.columns.number') }}</th>
            <th class="px-3 py-2 text-start font-medium">{{ t('rfq.columns.title') }}</th>
            <th class="px-3 py-2 text-start font-medium">{{ t('rfq.columns.status') }}</th>
            <th class="px-3 py-2 text-start font-medium">{{ t('rfq.columns.pr') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in rows"
            :key="row.id"
            class="border-t border-border hover:bg-muted/20"
          >
            <td class="px-3 py-2">
              <RouterLink class="text-primary hover:underline" :to="`/app/rfqs/${row.id}`">
                {{ row.rfq_number }}
              </RouterLink>
            </td>
            <td class="px-3 py-2">{{ row.title }}</td>
            <td class="px-3 py-2">{{ t(`rfq.statuses.${row.status}`) }}</td>
            <td class="px-3 py-2">
              <RouterLink
                v-if="row.purchase_request_id"
                class="text-primary hover:underline"
                :to="`/app/purchase-requests/${row.purchase_request_id}`"
              >
                #{{ row.purchase_request_id }}
              </RouterLink>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
