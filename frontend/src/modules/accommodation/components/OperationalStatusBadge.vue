<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

import type { OperationalStatus } from '../types/accommodation'
import { effectiveOperationalStatus } from '../validation/accommodationValidation'

const props = defineProps<{
  status: OperationalStatus | null
  show?: boolean
}>()

const { t } = useI18n()

const effective = computed(() => effectiveOperationalStatus(props.status))

const label = computed(() => {
  switch (effective.value) {
    case 'arrived':
      return t('accommodation.operationalStatus.arrived')
    case 'checked_in':
      return t('accommodation.operationalStatus.checked_in')
    case 'checked_out':
      return t('accommodation.operationalStatus.checked_out')
    default:
      return t('accommodation.operationalStatus.assigned')
  }
})

const classes = computed(() => {
  switch (effective.value) {
    case 'arrived':
      return 'bg-indigo-50 text-indigo-900 ring-indigo-200/80'
    case 'checked_in':
      return 'bg-teal-50 text-teal-900 ring-teal-200/80'
    case 'checked_out':
      return 'bg-slate-100 text-slate-800 ring-slate-300/80'
    default:
      return 'bg-violet-50 text-violet-900 ring-violet-200/80'
  }
})
</script>

<template>
  <span
    v-if="show !== false"
    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset"
    :class="classes"
  >
    {{ label }}
  </span>
</template>
