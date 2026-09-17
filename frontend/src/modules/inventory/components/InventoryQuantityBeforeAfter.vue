<script setup lang="ts">
import { useI18n } from 'vue-i18n'

import { formatQuantity } from '../validation/inventoryValidation'

withDefaults(
  defineProps<{
    before: string | number | null | undefined
    after: string | number | null | undefined
    compact?: boolean
  }>(),
  {
    compact: false,
  },
)

const { t } = useI18n()
</script>

<template>
  <div
    class="text-start"
    :class="compact ? 'space-y-1' : 'grid grid-cols-2 gap-2'"
    dir="rtl"
  >
    <div
      :class="
        compact
          ? 'flex items-baseline justify-between gap-3'
          : 'rounded-xl border border-brand-border bg-brand-bg/70 px-2.5 py-1.5'
      "
    >
      <span class="text-[11px] font-semibold text-brand-text-muted">
        {{ t('inventory.columns.quantityBefore') }}
      </span>
      <span
        class="font-bold tabular-nums text-brand-text"
        :class="compact ? 'text-xs' : 'mt-0.5 block text-sm'"
        dir="ltr"
      >
        {{ formatQuantity(before) }}
      </span>
    </div>
    <div
      :class="
        compact
          ? 'flex items-baseline justify-between gap-3'
          : 'rounded-xl border border-brand-border bg-brand-bg/70 px-2.5 py-1.5'
      "
    >
      <span class="text-[11px] font-semibold text-brand-text-muted">
        {{ t('inventory.columns.quantityAfter') }}
      </span>
      <span
        class="font-bold tabular-nums text-brand-text"
        :class="compact ? 'text-xs' : 'mt-0.5 block text-sm'"
        dir="ltr"
      >
        {{ formatQuantity(after) }}
      </span>
    </div>
  </div>
</template>
