<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'
import {
  ChevronDown,
  Home,
  LogOut,
  Network,
  PanelRightClose,
  PanelRightOpen,
  Shield,
  Users,
  type LucideIcon,
} from 'lucide-vue-next'

import rafeeaLogo from '@/assets/brand/rafeea-logo.png'
import { useLogoutMutation } from '@/modules/auth/mutations/useLogoutMutation'
import { useCurrentUserQuery } from '@/modules/auth/queries/useCurrentUserQuery'
import AppTooltip from '@/shared/components/AppTooltip.vue'
import UserAvatar from '@/shared/components/UserAvatar.vue'
import { usePermissions } from '@/shared/composables/usePermissions'

interface NavItem {
  key: string
  labelKey: string
  to: string
  icon: LucideIcon
  permission?: string
  match?: 'exact' | 'prefix'
}

interface NavGroup {
  key: string
  labelKey: string
  items: NavItem[]
}

const COLLAPSED_KEY = 'erp-hajj.sidebar.collapsed'
const GROUPS_KEY = 'erp-hajj.sidebar.groups'

const { t } = useI18n()
const route = useRoute()
const { data: user } = useCurrentUserQuery()
const { can } = usePermissions()
const { mutate: logout, isPending: isLoggingOut } = useLogoutMutation()

const collapsed = ref(false)
const openGroups = ref<Record<string, boolean>>({ home: true, system: true, organization: true })
const accountOpen = ref(false)
const accountRoot = ref<HTMLElement | null>(null)

const displayName = computed(() => user.value?.name ?? t('auth.userFallback'))
const roleLabel = computed(() => user.value?.roles?.[0]?.name ?? t('auth.systemManager'))

const navGroups = computed<NavGroup[]>(() => {
  const groups: NavGroup[] = [
    {
      key: 'home',
      labelKey: 'nav.home',
      items: [
        {
          key: 'dashboard',
          labelKey: 'nav.dashboard',
          to: '/app',
          icon: Home,
          match: 'exact',
        },
      ],
    },
  ]

  const systemItems: NavItem[] = []
  if (can('users.view')) {
    systemItems.push({
      key: 'users',
      labelKey: 'nav.users',
      to: '/app/users',
      icon: Users,
      permission: 'users.view',
      match: 'prefix',
    })
  }
  if (can('roles.view')) {
    systemItems.push({
      key: 'roles',
      labelKey: 'nav.rolesShort',
      to: '/app/roles',
      icon: Shield,
      permission: 'roles.view',
      match: 'prefix',
    })
  }

  if (systemItems.length > 0) {
    groups.push({
      key: 'system',
      labelKey: 'nav.systemAdmin',
      items: systemItems,
    })
  }

  if (can('organization_units.view')) {
    groups.push({
      key: 'organization',
      labelKey: 'nav.organization',
      items: [
        {
          key: 'organization-structure',
          labelKey: 'nav.organization',
          to: '/app/organization',
          icon: Network,
          permission: 'organization_units.view',
          match: 'prefix',
        },
      ],
    })
  }

  return groups
})

function isItemActive(item: NavItem): boolean {
  if (item.match === 'exact') {
    return route.path === item.to || route.path === `${item.to}/`
  }
  return route.path === item.to || route.path.startsWith(`${item.to}/`)
}

function toggleCollapsed(): void {
  collapsed.value = !collapsed.value
  if (collapsed.value) {
    accountOpen.value = false
  }
}

function toggleGroup(key: string): void {
  if (collapsed.value) return
  openGroups.value = {
    ...openGroups.value,
    [key]: !openGroups.value[key],
  }
}

function isGroupOpen(key: string): boolean {
  return openGroups.value[key] !== false
}

function toggleAccount(): void {
  accountOpen.value = !accountOpen.value
}

function closeAccount(): void {
  accountOpen.value = false
}

function onLogout(): void {
  closeAccount()
  logout()
}

function onDocumentClick(event: MouseEvent): void {
  if (!accountRoot.value) return
  if (!accountRoot.value.contains(event.target as Node)) {
    closeAccount()
  }
}

watch(collapsed, (value) => {
  localStorage.setItem(COLLAPSED_KEY, value ? '1' : '0')
})

watch(
  openGroups,
  (value) => {
    localStorage.setItem(GROUPS_KEY, JSON.stringify(value))
  },
  { deep: true },
)

onMounted(() => {
  collapsed.value = localStorage.getItem(COLLAPSED_KEY) === '1'
  const savedGroups = localStorage.getItem(GROUPS_KEY)
  if (savedGroups) {
    try {
      openGroups.value = { ...openGroups.value, ...JSON.parse(savedGroups) }
    } catch {
      // ignore invalid stored state
    }
  }
  document.addEventListener('click', onDocumentClick)
})

onUnmounted(() => {
  document.removeEventListener('click', onDocumentClick)
})
</script>

<template>
  <div
    class="relative shrink-0 transition-[width] duration-200 ease-out motion-reduce:transition-none"
    :class="collapsed ? 'w-[76px]' : 'w-[264px]'"
  >
    <aside
      class="flex h-full w-full flex-col overflow-hidden rounded-[20px] bg-brand-primary-dark text-white shadow-[0_12px_32px_-24px_rgba(6,78,59,0.5)]"
      aria-label="القائمة الجانبية"
    >
    <!-- Brand header -->
    <div
      class="relative shrink-0 border-b border-white/10 bg-gradient-to-b from-[#064E3B] to-[#075B46] transition-[padding] duration-200 ease-out motion-reduce:transition-none"
      :class="collapsed ? 'px-2 py-3' : 'px-4 py-4'"
    >
      <div
        class="sidebar-brand-pattern pointer-events-none absolute inset-0 opacity-[0.04]"
        aria-hidden="true"
      />

      <div
        class="relative flex items-start"
        :class="collapsed ? 'flex-col items-center gap-2.5' : 'gap-3'"
      >
        <img
          :src="rafeeaLogo"
          :alt="t('auth.companyNameEn')"
          class="shrink-0 bg-transparent object-contain shadow-none"
          :class="collapsed ? 'h-11 w-auto' : 'h-12 w-auto'"
          width="96"
          height="48"
          decoding="async"
        />

        <div v-if="!collapsed" class="min-w-0 flex-1 pt-0.5">
          <p class="text-[19px] font-bold leading-none tracking-tight text-white">
            {{ t('auth.companyName') }}
          </p>
          <p class="mt-1.5 text-[11.5px] font-medium leading-snug text-[#D7E5DF]/65">
            {{ t('app.tagline') }}
          </p>
          <p
            class="mt-1 text-[10.5px] font-semibold uppercase tracking-[0.08em] text-[#D4B87A]/85"
            dir="ltr"
          >
            {{ t('shell.productLabel') }}
          </p>
        </div>

        <!-- Sidebar Toggle — desktop/tablet; integrated in header -->
        <div class="hidden shrink-0 md:block" :class="collapsed ? '' : 'pt-0.5'">
          <AppTooltip
            :text="collapsed ? t('shell.expandSidebar') : t('shell.collapseSidebar')"
          >
            <button
              type="button"
              class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-white/15 bg-white/[0.06] text-[#E8F0EB] transition duration-[160ms] ease-out hover:bg-white/[0.12] hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-gold/40 active:bg-white/[0.16] motion-reduce:transition-none"
              :aria-label="collapsed ? t('shell.expandSidebar') : t('shell.collapseSidebar')"
              :aria-expanded="!collapsed"
              @click="toggleCollapsed"
            >
              <PanelRightOpen
                v-if="collapsed"
                class="h-3.5 w-3.5"
                :stroke-width="1.75"
                aria-hidden="true"
              />
              <PanelRightClose
                v-else
                class="h-3.5 w-3.5"
                :stroke-width="1.75"
                aria-hidden="true"
              />
            </button>
          </AppTooltip>
        </div>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto px-2 pb-3 pt-1">
      <div
        v-for="(group, groupIndex) in navGroups"
        :key="group.key"
        :class="groupIndex > 0 ? 'mt-6' : ''"
      >
        <button
          v-if="!collapsed"
          type="button"
          class="flex w-full items-center justify-between rounded-lg px-2.5 text-[11px] font-semibold tracking-wide text-white/45 transition hover:text-white/70"
          @click="toggleGroup(group.key)"
        >
          <span>{{ t(group.labelKey) }}</span>
          <ChevronDown
            class="h-3.5 w-3.5 transition"
            :class="isGroupOpen(group.key) ? 'rotate-180' : ''"
            :stroke-width="1.75"
          />
        </button>

        <div
          v-show="collapsed || isGroupOpen(group.key)"
          class="space-y-0.5"
          :class="collapsed ? '' : 'mt-2.5'"
        >
          <RouterLink
            v-for="item in group.items"
            :key="item.key"
            v-slot="{ href, navigate }"
            :to="item.to"
            custom
          >
            <a
              :href="href"
              class="group relative flex h-11 items-center rounded-xl text-sm font-medium transition"
              :class="[
                collapsed ? 'justify-center px-0' : 'gap-2.5 px-2.5',
                isItemActive(item)
                  ? 'bg-white/[0.06] text-white'
                  : 'text-white/75 hover:bg-white/[0.04] hover:text-white',
              ]"
              :title="collapsed ? t(item.labelKey) : undefined"
              @click="navigate"
            >
              <span
                v-if="isItemActive(item)"
                class="absolute inset-s-0 top-2.5 bottom-2.5 w-[3px] rounded-full bg-brand-gold"
                aria-hidden="true"
              />
              <component
                :is="item.icon"
                class="h-5 w-5 shrink-0 opacity-90"
                :stroke-width="1.75"
              />
              <span v-if="!collapsed" class="truncate">{{ t(item.labelKey) }}</span>
            </a>
          </RouterLink>
        </div>
      </div>
    </nav>

    <!-- Account -->
    <div ref="accountRoot" class="relative border-t border-white/10 p-2">
      <button
        type="button"
        class="flex w-full items-center rounded-xl px-2 py-2 transition hover:bg-white/[0.04]"
        :class="collapsed ? 'justify-center' : 'gap-2.5'"
        :aria-expanded="accountOpen"
        aria-haspopup="menu"
        @click.stop="toggleAccount"
      >
        <UserAvatar :user="user" size="sm" :lazy="false" />
        <div v-if="!collapsed" class="min-w-0 flex-1 text-start">
          <p class="truncate text-sm font-semibold text-white">{{ displayName }}</p>
          <p class="truncate text-[11px] text-white/55">{{ roleLabel }}</p>
        </div>
        <ChevronDown
          v-if="!collapsed"
          class="h-4 w-4 shrink-0 text-white/45 transition"
          :class="accountOpen ? 'rotate-180' : ''"
          :stroke-width="1.75"
        />
      </button>

      <div
        v-if="accountOpen"
        class="absolute inset-x-2 bottom-full z-40 mb-2 overflow-hidden rounded-xl border border-white/10 bg-[#053D2F] py-1 shadow-lg"
        role="menu"
      >
        <div v-if="collapsed" class="border-b border-white/10 px-3 py-2">
          <p class="truncate text-sm font-semibold text-white">{{ displayName }}</p>
          <p class="truncate text-[11px] text-white/55">{{ roleLabel }}</p>
        </div>
        <button
          type="button"
          class="flex w-full items-center gap-2.5 px-3 py-2.5 text-sm text-red-200 transition hover:bg-white/5 disabled:opacity-60"
          role="menuitem"
          :disabled="isLoggingOut"
          @click="onLogout"
        >
          <LogOut class="h-4 w-4" :stroke-width="1.75" />
          <span>{{ t('auth.logout') }}</span>
        </button>
      </div>
    </div>
  </aside>
  </div>
</template>

<style scoped>
.sidebar-brand-pattern {
  background-image:
    linear-gradient(30deg, rgba(255, 255, 255, 0.55) 1px, transparent 1px),
    linear-gradient(150deg, rgba(255, 255, 255, 0.45) 1px, transparent 1px),
    linear-gradient(90deg, rgba(198, 161, 91, 0.35) 1px, transparent 1px);
  background-size: 18px 18px, 18px 18px, 36px 36px;
  background-position: 0 0, 0 0, 9px 9px;
}
</style>
