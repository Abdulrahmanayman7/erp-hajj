<script setup lang="ts">
import { useI18n } from 'vue-i18n'

import { tenantStatusBadgeClass } from '../validation/platformValidation'
import type { PlatformTenantStatus } from '../types/platform'

defineProps<{
  status: PlatformTenantStatus | string
}>()

const { t } = useI18n()
</script>

<template>
  <span
    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
    :class="tenantStatusBadgeClass(status)"
  >
    {{ t(`platform.status.${status}`, status) }}
  </span>
</template>
