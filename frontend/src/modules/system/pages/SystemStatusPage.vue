<script setup lang="ts">
import { useI18n } from 'vue-i18n'

import { useHealthQuery } from '../queries/useHealthQuery'

const { t } = useI18n()
const { data, isPending, isError, refetch } = useHealthQuery()
</script>

<template>
  <section class="mx-auto max-w-2xl">
    <div class="rounded-lg border border-neutral-200 bg-white p-8 shadow-sm">
      <h2 class="text-2xl font-bold text-emerald-900">{{ t('app.name') }}</h2>
      <p class="mt-2 text-lg text-neutral-700">{{ t('status.ready') }}</p>

      <div class="mt-6 border-t border-neutral-100 pt-6">
        <!-- Loading -->
        <div v-if="isPending" class="flex items-center gap-3 text-neutral-500">
          <span
            class="inline-block size-4 animate-spin rounded-full border-2 border-neutral-300 border-t-emerald-700"
            aria-hidden="true"
          />
          <span>{{ t('status.checking') }}</span>
        </div>

        <!-- Error -->
        <div v-else-if="isError" class="rounded-md bg-red-50 p-4">
          <p class="font-medium text-red-800">{{ t('status.failed') }}</p>
          <button
            type="button"
            class="mt-3 rounded-md bg-red-100 px-4 py-2 text-sm font-medium text-red-800 hover:bg-red-200"
            @click="refetch()"
          >
            {{ t('status.retry') }}
          </button>
        </div>

        <!-- Success -->
        <div v-else-if="data" class="rounded-md bg-emerald-50 p-4">
          <p class="font-medium text-emerald-800">{{ t('status.connected') }}</p>
          <dl class="mt-4 grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
            <div>
              <dt class="text-neutral-500">{{ t('status.application') }}</dt>
              <dd class="font-medium text-neutral-800">{{ data.data.application }}</dd>
            </div>
            <div>
              <dt class="text-neutral-500">{{ t('status.version') }}</dt>
              <dd class="font-medium text-neutral-800">{{ data.data.version }}</dd>
            </div>
            <div>
              <dt class="text-neutral-500">{{ t('status.environment') }}</dt>
              <dd class="font-medium text-neutral-800">{{ data.data.environment }}</dd>
            </div>
            <div>
              <dt class="text-neutral-500">{{ t('status.timestamp') }}</dt>
              <dd class="font-medium text-neutral-800" dir="ltr">{{ data.data.timestamp }}</dd>
            </div>
          </dl>
        </div>
      </div>
    </div>
  </section>
</template>
