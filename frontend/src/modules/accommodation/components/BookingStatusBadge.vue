<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

import type { BookingStatus } from '../types/accommodation'

const props = defineProps<{
  status: BookingStatus
}>()

const { t } = useI18n()

const label = computed(() => {
  switch (props.status) {
    case 'confirmed':
      return t('accommodation.bookingStatus.confirmed')
    case 'cancelled':
      return t('accommodation.bookingStatus.cancelled')
    case 'completed':
      return t('accommodation.bookingStatus.completed')
    default:
      return t('accommodation.bookingStatus.draft')
  }
})

const classes = computed(() => {
  switch (props.status) {
    case 'confirmed':
      return 'bg-emerald-50 text-emerald-900 ring-emerald-200/80'
    case 'cancelled':
      return 'bg-slate-100 text-slate-800 ring-slate-300/80'
    case 'completed':
      return 'bg-sky-50 text-sky-900 ring-sky-200/80'
    default:
      return 'bg-amber-50 text-amber-900 ring-amber-200/80'
  }
})
</script>

<template>
  <span
    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset"
    :class="classes"
  >
    {{ label }}
  </span>
</template>
