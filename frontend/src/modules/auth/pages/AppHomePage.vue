<script setup lang="ts">
import { useI18n } from 'vue-i18n'

import { useCurrentUserQuery } from '../queries/useCurrentUserQuery'

const { t } = useI18n()
const { data: user } = useCurrentUserQuery()
</script>

<template>
  <section class="mx-auto max-w-3xl">
    <div class="rounded-2xl border border-emerald-900/10 bg-white p-8 shadow-sm">
      <h2 class="text-2xl font-bold text-emerald-950">
        {{ t('auth.welcomeTitle') }}
      </h2>
      <p class="mt-3 text-neutral-600">
        {{ t('auth.welcomeBody') }}
      </p>
      <dl v-if="user" class="mt-6 grid gap-3 text-sm sm:grid-cols-2">
        <div class="rounded-lg bg-neutral-50 p-3">
          <dt class="text-neutral-500">{{ t('auth.user') }}</dt>
          <dd class="font-medium text-neutral-900">{{ user.name }}</dd>
        </div>
        <div class="rounded-lg bg-neutral-50 p-3">
          <dt class="text-neutral-500">{{ t('auth.email') }}</dt>
          <dd class="font-medium text-neutral-900">{{ user.email }}</dd>
        </div>
        <div v-if="user.tenant" class="rounded-lg bg-neutral-50 p-3 sm:col-span-2">
          <dt class="text-neutral-500">{{ t('auth.organization') }}</dt>
          <dd class="font-medium text-neutral-900">
            {{ user.tenant.name }}
            <span class="text-neutral-500">({{ user.tenant.tenant_code }})</span>
          </dd>
        </div>
      </dl>
    </div>
  </section>
</template>
