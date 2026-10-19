<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'

import { usePermissions } from '@/shared/composables/usePermissions'

import { ACCOMMODATION_OPS_PERMISSIONS, ACCOMMODATION_READ_PERMISSIONS } from '../types/accommodation'

const { t } = useI18n()
const route = useRoute()
const { can, canAny } = usePermissions()

const items = computed(() => {
  const links: { key: string; to: string; label: string; show: boolean }[] = [
    {
      key: 'overview',
      to: '/app/accommodation',
      label: t('accommodation.nav.overview'),
      show: canAny([...ACCOMMODATION_READ_PERMISSIONS]) || can('accommodation.manage_facilities'),
    },
    {
      key: 'allocation',
      to: '/app/accommodation/allocation',
      label: t('accommodation.nav.allocation'),
      show: canAny([...ACCOMMODATION_READ_PERMISSIONS]) || can('accommodation.manage_season_allocation'),
    },
    {
      key: 'drafts',
      to: '/app/accommodation/drafts',
      label: t('accommodation.nav.drafts'),
      show: canAny([
        ...ACCOMMODATION_READ_PERMISSIONS,
        'accommodation.draft',
        'accommodation.confirm',
        ...ACCOMMODATION_OPS_PERMISSIONS,
      ]),
    },
    {
      key: 'scopes',
      to: '/app/accommodation/scopes',
      label: t('accommodation.nav.scopes'),
      show: can('accommodation.manage_scopes'),
    },
  ]
  return links.filter((item) => item.show)
})

function active(to: string): boolean {
  if (to === '/app/accommodation') {
    return route.path === '/app/accommodation' || route.path.startsWith('/app/accommodation/facilities/')
  }
  return route.path === to || route.path.startsWith(`${to}/`)
}
</script>

<template>
  <nav class="flex flex-wrap gap-2" aria-label="accommodation-subnav">
    <RouterLink
      v-for="item in items"
      :key="item.key"
      :to="item.to"
      class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
      :class="
        active(item.to)
          ? 'bg-brand-primary text-white'
          : 'bg-brand-bg text-brand-text-secondary hover:text-brand-text'
      "
    >
      {{ item.label }}
    </RouterLink>
  </nav>
</template>
