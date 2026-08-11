<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { ArrowLeft, Shield, Users } from 'lucide-vue-next'

import { usePermissions } from '@/shared/composables/usePermissions'

const { t } = useI18n()
const { can } = usePermissions()

const showUsers = computed(() => can('users.view'))
const showRoles = computed(() => can('roles.view'))
const hasQuickActions = computed(() => showUsers.value || showRoles.value)
</script>

<template>
  <section>
    <div v-if="hasQuickActions">
      <h2 class="mb-4 text-xl font-semibold text-brand-text">
        {{ t('home.quickActions') }}
      </h2>

      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <RouterLink
          v-if="showUsers"
          to="/app/users"
          class="group rounded-2xl border border-brand-border bg-brand-surface p-5 transition hover:border-brand-primary/25 hover:shadow-[0_10px_28px_-20px_rgba(6,78,59,0.45)]"
        >
          <div
            class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-primary-soft text-brand-primary-dark"
          >
            <Users class="h-5 w-5" :stroke-width="1.75" />
          </div>
          <h3 class="mt-4 text-lg font-semibold text-brand-text">
            {{ t('home.usersTitle') }}
          </h3>
          <p class="mt-1.5 text-sm leading-6 text-brand-text-secondary">
            {{ t('home.usersBody') }}
          </p>
          <p
            class="mt-5 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-primary transition group-hover:gap-2"
          >
            <span>{{ t('home.usersCta') }}</span>
            <ArrowLeft class="h-4 w-4" :stroke-width="1.75" />
          </p>
        </RouterLink>

        <RouterLink
          v-if="showRoles"
          to="/app/roles"
          class="group rounded-2xl border border-brand-border bg-brand-surface p-5 transition hover:border-brand-primary/25 hover:shadow-[0_10px_28px_-20px_rgba(6,78,59,0.45)]"
        >
          <div
            class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-primary-soft text-brand-primary-dark"
          >
            <Shield class="h-5 w-5" :stroke-width="1.75" />
          </div>
          <h3 class="mt-4 text-lg font-semibold text-brand-text">
            {{ t('home.rolesTitle') }}
          </h3>
          <p class="mt-1.5 text-sm leading-6 text-brand-text-secondary">
            {{ t('home.rolesBody') }}
          </p>
          <p
            class="mt-5 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-primary transition group-hover:gap-2"
          >
            <span>{{ t('home.rolesCta') }}</span>
            <ArrowLeft class="h-4 w-4" :stroke-width="1.75" />
          </p>
        </RouterLink>
      </div>
    </div>

    <div
      v-else
      class="rounded-2xl border border-brand-border bg-brand-surface p-6 text-sm text-brand-text-secondary"
    >
      {{ t('auth.welcomeBody') }}
    </div>
  </section>
</template>
