<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'

const { t } = useI18n()
</script>

<template>
  <div class="mx-auto max-w-lg rounded-xl border border-neutral-200 bg-white p-8 text-center shadow-sm">
    <h2 class="text-xl font-semibold text-neutral-900">{{ t('auth.forbiddenTitle') }}</h2>
    <p class="mt-3 text-sm text-neutral-600">{{ t('auth.forbiddenBody') }}</p>
    <RouterLink
      to="/app"
      class="mt-6 inline-flex rounded-md bg-emerald-800 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700"
    >
      {{ t('auth.backToApp') }}
    </RouterLink>
  </div>
</template>
