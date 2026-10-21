<script setup lang="ts">
import { computed } from 'vue'
import { Building2, BedDouble, CalendarCheck2, ShieldCheck } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'

import { usePermissions } from '@/shared/composables/usePermissions'

import { ACCOMMODATION_OPS_PERMISSIONS, ACCOMMODATION_READ_PERMISSIONS } from '../types/accommodation'

const { t } = useI18n()
const route = useRoute()
const { can, canAny } = usePermissions()

const icons = { overview: Building2, allocation: CalendarCheck2, drafts: BedDouble, scopes: ShieldCheck }

const items = computed(() => {
  const links: { key: keyof typeof icons; to: string; label: string; show: boolean }[] = [
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
  <nav class="grid grid-cols-2 gap-1 rounded-2xl border border-brand-border bg-brand-surface p-1.5 sm:flex" aria-label="accommodation-subnav">
    <RouterLink
      v-for="item in items"
      :key="item.key"
      :to="item.to"
      :aria-current="active(item.to) ? 'page' : undefined" class="flex min-h-12 flex-1 items-center justify-center gap-2 whitespace-nowrap rounded-xl px-4 py-2.5 text-sm font-semibold transition"
      :class="
        active(item.to)
          ? 'bg-brand-primary-soft text-brand-primary shadow-sm'
          : 'text-brand-text-secondary hover:bg-brand-bg hover:text-brand-text'
      "
    >
      <component :is="icons[item.key]" class="h-4 w-4 shrink-0" />
      {{ item.label }}
    </RouterLink>
  </nav>
</template>
