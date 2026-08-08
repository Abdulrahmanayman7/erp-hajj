<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'

import { isAuthBlockCode } from '../types/auth'

const { t } = useI18n()
const route = useRoute()

const code = computed(() => {
  const value = typeof route.query.code === 'string' ? route.query.code : 'AUTH_ACCOUNT_DISABLED'
  return isAuthBlockCode(value) ? value : 'AUTH_ACCOUNT_DISABLED'
})

const message = computed(() => t(`auth.errors.${code.value}`))
</script>

<template>
  <div class="text-center">
    <h2 class="text-xl font-semibold text-neutral-900">{{ t('auth.accessBlockedTitle') }}</h2>
    <p class="mt-3 text-sm text-neutral-700">{{ message }}</p>
    <RouterLink
      to="/login"
      class="mt-6 inline-flex rounded-lg bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-900"
    >
      {{ t('auth.backToLogin') }}
    </RouterLink>
  </div>
</template>
