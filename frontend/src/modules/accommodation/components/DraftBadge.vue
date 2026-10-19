<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>

<template>
  <span
    class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-900 ring-1 ring-inset ring-amber-200/80"
  >
    {{ t('accommodation.drafts.badge') }}
  </span>
</template>
