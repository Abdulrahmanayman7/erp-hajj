<script setup lang="ts">
import { useI18n } from 'vue-i18n'

import type { TemplateStatus } from '../types/seasonTemplates'
import { templateStatusBadgeClass } from '../validation/seasonTemplateValidation'

defineProps<{ status: TemplateStatus }>()

const { t } = useI18n()
</script>

<template>
  <span
    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
    :class="templateStatusBadgeClass(status)"
  >
    {{ t(`seasonalTemplates.status.${status}`) }}
  </span>
</template>
