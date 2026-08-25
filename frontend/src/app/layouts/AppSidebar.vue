<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { ChevronDown, LogOut } from 'lucide-vue-next'

import rafeeaLogo from '@/assets/brand/rafeea-logo.png'
import { useLogoutMutation } from '@/modules/auth/mutations/useLogoutMutation'
import { useCurrentUserQuery } from '@/modules/auth/queries/useCurrentUserQuery'
import UserAvatar from '@/shared/components/UserAvatar.vue'
import {
  type AppNavGroup,
  type AppNavItem,
  useAppNavigation,
} from '@/shared/composables/useAppNavigation'
import { useSidebarCollapse } from '@/shared/composables/useSidebarCollapse'

const GROUPS_KEY = 'erp-hajj.sidebar.groups'

const { t } = useI18n()
const { data: user } = useCurrentUserQuery()
const { mutate: logout, isPending: isLoggingOut } = useLogoutMutation()
const { collapsed } = useSidebarCollapse()
const { navGroups, isItemActive } = useAppNavigation()

const openGroups = ref<Record<string, boolean>>({
  home: true,
  system: true,
  organization: true,
})

const displayName = computed(() => user.value?.name ?? t('auth.userFallback'))
const roleLabel = computed(() => user.value?.roles?.[0]?.name ?? t('auth.systemManager'))

function isGroupActive(group: AppNavGroup): boolean {
  return group.items.some((item) => isItemActive(item))
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

function navLinkClass(item: AppNavItem): string[] {
  return [
    'sidebar-nav-link',
    collapsed.value ? 'sidebar-nav-link--collapsed' : '',
    isItemActive(item) ? 'sidebar-nav-link--active' : '',
  ]
}

watch(
  openGroups,
  (value) => {
    localStorage.setItem(GROUPS_KEY, JSON.stringify(value))
  },
  { deep: true },
)

onMounted(() => {
  const savedGroups = localStorage.getItem(GROUPS_KEY)
  if (savedGroups) {
    try {
      openGroups.value = { ...openGroups.value, ...JSON.parse(savedGroups) }
    } catch {
      // ignore invalid stored state
    }
  }
})
</script>

<template>
  <div
    class="sidebar-shell relative h-full shrink-0"
    :class="collapsed ? 'w-[4.75rem]' : 'w-[17.5rem]'"
  >
    <aside
      id="app-sidebar"
      class="dashboard-sidebar sidebar-glass flex h-full w-full min-h-0 flex-col text-white"
      aria-label="القائمة الجانبية"
    >
      <div class="sidebar-brand shrink-0 px-3.5 pb-3 pt-3.5">
        <div class="flex flex-col items-center text-center" :class="collapsed ? 'gap-0' : 'gap-3'">
          <div
            class="flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-gradient-to-br from-brand-gold to-[#8a6a2e] p-0.5 shadow-lg shadow-brand-gold/25 ring-2 ring-white/10"
            :class="collapsed ? 'h-12 w-12' : 'h-20 w-20'"
          >
            <div class="sidebar-logo-frame flex h-full w-full items-center justify-center overflow-hidden rounded-full bg-[#05291f]">
              <img
                :src="rafeeaLogo"
                :alt="t('auth.companyNameEn')"
                class="sidebar-logo"
                width="80"
                height="80"
                decoding="async"
              />
            </div>
          </div>

          <template v-if="!collapsed">
            <div class="min-w-0 space-y-1">
              <div class="text-[15px] font-bold leading-tight tracking-tight">
                {{ t('auth.companyName') }}
              </div>
              <div class="text-[11px] font-medium text-white/45">
                {{ t('app.tagline') }}
              </div>
            </div>
            <div
              class="h-px w-full bg-gradient-to-l from-transparent via-white/14 to-transparent"
              aria-hidden="true"
            />
          </template>
        </div>
      </div>

      <div class="sidebar-body relative z-[1] flex min-h-0 flex-1 flex-col overflow-hidden">
        <nav
          class="sidebar-nav flex min-h-0 flex-1 flex-col gap-2 overflow-x-hidden overflow-y-auto overscroll-contain px-3 py-2"
        >
          <div
            v-for="group in navGroups"
            :key="group.key"
            class="sidebar-nav-group"
          >
            <button
              v-if="!collapsed && group.items.length > 1"
              type="button"
              class="sidebar-dropdown-trigger flex w-full items-center justify-between gap-2 px-2.5 py-2 text-[12px] font-extrabold"
              :class="isGroupActive(group) ? 'sidebar-dropdown-trigger--active' : ''"
              :aria-expanded="isGroupOpen(group.key)"
              @click="toggleGroup(group.key)"
            >
              <span class="truncate">{{ t(group.labelKey) }}</span>
              <ChevronDown
                class="sidebar-dropdown-chevron h-4 w-4 shrink-0"
                :class="{ 'sidebar-dropdown-chevron--open': isGroupOpen(group.key) }"
                :stroke-width="2.25"
              />
            </button>

            <div
              v-else-if="collapsed && group.items.length > 1"
              class="mx-2 my-1 h-px bg-white/[0.08]"
              :title="t(group.labelKey)"
              aria-hidden="true"
            />

            <div
              v-show="collapsed || isGroupOpen(group.key) || group.items.length === 1"
              class="sidebar-dropdown-panel flex flex-col gap-1"
              :class="!collapsed && group.items.length > 1 ? 'mt-0.5 border-s border-white/[0.08] ms-2.5 ps-2' : ''"
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
                  :title="collapsed ? t(item.labelKey) : undefined"
                  class="group flex shrink-0 cursor-pointer items-center py-2.5 text-[13px] font-semibold"
                  :class="navLinkClass(item)"
                  @click="navigate"
                >
                  <span class="sidebar-nav-link__icon">
                    <component
                      :is="item.icon"
                      class="h-[18px] w-[18px]"
                      :stroke-width="1.75"
                    />
                  </span>
                  <span
                    v-if="!collapsed"
                    class="sidebar-nav-link__label min-w-0 flex-1 truncate leading-snug"
                  >
                    {{ t(item.labelKey) }}
                  </span>
                </a>
              </RouterLink>
            </div>
          </div>
        </nav>

        <div
          class="sidebar-scroll-hint pointer-events-none absolute inset-x-0 bottom-[5.25rem] h-10 bg-gradient-to-t from-[#05291f] to-transparent"
          aria-hidden="true"
        />

        <div class="sidebar-footer shrink-0 border-t border-white/[0.08] p-3.5 pt-3">
          <div v-if="collapsed" class="flex justify-center">
            <button
              type="button"
              class="sidebar-footer-btn sidebar-footer-btn--danger flex h-10 w-10 items-center justify-center rounded-xl text-white/60"
              :title="t('auth.logout')"
              :aria-label="t('auth.logout')"
              :disabled="isLoggingOut"
              @click="logout()"
            >
              <LogOut class="h-5 w-5" :stroke-width="2" />
            </button>
          </div>

          <div v-else class="sidebar-footer-card flex items-center gap-3 px-3 py-2.5">
            <UserAvatar :user="user" size="sm" :lazy="false" />
            <div class="min-w-0 flex-1 space-y-0.5">
              <div class="truncate text-xs font-bold leading-tight">{{ displayName }}</div>
              <div class="truncate text-[10px] leading-tight text-white/45">{{ roleLabel }}</div>
            </div>
            <button
              type="button"
              class="sidebar-footer-btn sidebar-footer-btn--danger flex h-8 w-8 shrink-0 items-center justify-center"
              :title="t('auth.logout')"
              :aria-label="t('auth.logout')"
              :disabled="isLoggingOut"
              @click="logout()"
            >
              <LogOut class="h-4 w-4" :stroke-width="2" />
            </button>
          </div>
        </div>
      </div>
    </aside>
  </div>
</template>

<style scoped>
.sidebar-shell {
  position: relative;
  align-self: stretch;
  min-height: 0;
  height: 100%;
  transition: width 0.32s cubic-bezier(0.4, 0, 0.2, 1);
}

.dashboard-sidebar {
  height: 100%;
  max-height: 100%;
}

.sidebar-logo-frame {
  position: relative;
}

.sidebar-logo {
  position: absolute;
  top: 50%;
  left: 50%;
  width: 178%;
  height: 178%;
  max-width: none;
  object-fit: cover;
  transform: translate(-50%, -50%);
}

.sidebar-glass {
  position: relative;
  isolation: isolate;
  overflow: hidden;
  border-radius: 2rem;
  border: 1px solid rgb(255 255 255 / 0.08);
  background:
    radial-gradient(130% 90% at 100% 0%, rgb(198 161 91 / 0.16), transparent 52%),
    radial-gradient(90% 70% at 0% 100%, rgb(7 107 82 / 0.22), transparent 48%),
    linear-gradient(180deg, rgb(6 78 59 / 0.96), rgb(4 40 31 / 0.98));
  box-shadow:
    inset 0 1px 0 rgb(255 255 255 / 0.07),
    0 4px 8px rgb(0 0 0 / 0.12),
    0 24px 56px rgb(6 78 59 / 0.28);
}

.sidebar-glass::after {
  content: '';
  position: absolute;
  inset: 0;
  border-radius: inherit;
  pointer-events: none;
  z-index: 0;
  background: linear-gradient(180deg, rgb(255 255 255 / 0.04) 0%, transparent 38%);
}

.sidebar-brand,
.sidebar-body {
  position: relative;
  z-index: 1;
}

.sidebar-body {
  flex: 1 1 0%;
  min-height: 0;
}

.sidebar-nav {
  -webkit-overflow-scrolling: touch;
  scrollbar-gutter: stable;
}

.sidebar-nav-group + .sidebar-nav-group {
  padding-top: 0.125rem;
}

.sidebar-dropdown-trigger {
  position: relative;
  border-radius: 0.75rem;
  color: rgb(255 255 255 / 0.55);
  transition:
    color 220ms cubic-bezier(0.4, 0, 0.2, 1),
    background 220ms cubic-bezier(0.4, 0, 0.2, 1),
    box-shadow 220ms cubic-bezier(0.4, 0, 0.2, 1);
}

.sidebar-dropdown-trigger::before {
  content: '';
  position: absolute;
  inset-inline-end: 0.5rem;
  top: 50%;
  width: 3px;
  height: 0;
  border-radius: 999px;
  background: linear-gradient(180deg, rgb(255 255 255 / 0.95), rgb(198 161 91 / 0.85));
  transform: translateY(-50%);
  opacity: 0;
  transition:
    height 220ms cubic-bezier(0.34, 1.2, 0.64, 1),
    opacity 180ms ease;
}

.sidebar-dropdown-trigger:hover {
  color: rgb(255 255 255 / 0.92);
  background: linear-gradient(90deg, rgb(255 255 255 / 0.03), rgb(255 255 255 / 0.08));
  box-shadow: inset 0 1px 0 rgb(255 255 255 / 0.05);
}

.sidebar-dropdown-trigger:hover::before {
  height: 1rem;
  opacity: 0.85;
}

.sidebar-dropdown-trigger--active {
  color: #fff;
}

.sidebar-nav-link {
  position: relative;
  isolation: isolate;
  gap: 0.75rem;
  padding-inline: 0.75rem;
  border-radius: 0.875rem;
  color: rgb(255 255 255 / 0.78);
  overflow: hidden;
  transition:
    color 220ms cubic-bezier(0.4, 0, 0.2, 1),
    background 220ms cubic-bezier(0.4, 0, 0.2, 1),
    box-shadow 220ms cubic-bezier(0.4, 0, 0.2, 1),
    transform 220ms cubic-bezier(0.34, 1.2, 0.64, 1);
}

.sidebar-nav-link--collapsed {
  justify-content: center;
  padding-inline: 0.5rem;
}

.sidebar-nav-link::before {
  content: '';
  position: absolute;
  inset: 0;
  border-radius: inherit;
  background: linear-gradient(90deg, rgb(255 255 255 / 0.02), rgb(255 255 255 / 0.07));
  opacity: 0;
  transition: opacity 220ms ease;
  pointer-events: none;
}

.sidebar-nav-link::after {
  content: '';
  position: absolute;
  inset-inline-end: 0;
  top: 50%;
  width: 3px;
  height: 0;
  border-start-start-radius: 999px;
  border-end-start-radius: 999px;
  background: linear-gradient(180deg, rgb(255 255 255 / 0.95), rgb(198 161 91 / 0.9));
  box-shadow: 0 0 12px rgb(198 161 91 / 0.45);
  transform: translateY(-50%);
  opacity: 0;
  transition:
    height 240ms cubic-bezier(0.34, 1.25, 0.64, 1),
    opacity 200ms ease;
}

.sidebar-nav-link:hover {
  color: #fff;
  transform: translateX(-2px);
  box-shadow:
    inset 0 1px 0 rgb(255 255 255 / 0.06),
    0 6px 18px rgb(0 0 0 / 0.14);
}

.sidebar-nav-link:hover::before {
  opacity: 1;
}

.sidebar-nav-link:hover::after {
  height: 1.35rem;
  opacity: 1;
}

.sidebar-nav-link:hover .sidebar-nav-link__icon {
  background: rgb(255 255 255 / 0.14);
  color: #fff;
  transform: scale(1.06);
  box-shadow: 0 4px 14px rgb(198 161 91 / 0.22);
}

.sidebar-nav-link:hover .sidebar-nav-link__label {
  transform: translateX(-2px);
}

.sidebar-nav-link--active {
  color: #fff;
  background: linear-gradient(90deg, rgb(7 107 82 / 0.92), rgb(6 78 59 / 0.88));
  box-shadow:
    inset 0 1px 0 rgb(255 255 255 / 0.12),
    0 8px 22px rgb(7 107 82 / 0.32);
}

.sidebar-nav-link--active::before {
  opacity: 0;
}

.sidebar-nav-link--active::after {
  height: 1.75rem;
  opacity: 1;
  background: rgb(198 161 91 / 0.95);
  box-shadow: 0 0 10px rgb(198 161 91 / 0.35);
}

.sidebar-nav-link--active:hover {
  transform: translateX(-1px);
  box-shadow:
    inset 0 1px 0 rgb(255 255 255 / 0.14),
    0 10px 26px rgb(7 107 82 / 0.38);
}

.sidebar-nav-link--active .sidebar-nav-link__icon {
  background: rgb(255 255 255 / 0.2);
  color: #fff;
  box-shadow: inset 0 1px 0 rgb(255 255 255 / 0.15);
}

.sidebar-nav-link__icon {
  display: flex;
  height: 2rem;
  width: 2rem;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  border-radius: 0.75rem;
  background: rgb(255 255 255 / 0.06);
  color: rgb(255 255 255 / 0.88);
  transition:
    background 220ms cubic-bezier(0.4, 0, 0.2, 1),
    color 220ms ease,
    transform 220ms cubic-bezier(0.34, 1.2, 0.64, 1),
    box-shadow 220ms ease;
}

.sidebar-nav-link__label {
  transition: transform 220ms cubic-bezier(0.34, 1.2, 0.64, 1);
}

.sidebar-footer-card {
  border-radius: 1rem;
  border: 1px solid rgb(255 255 255 / 0.07);
  background: rgb(0 0 0 / 0.24);
  box-shadow: inset 0 1px 0 rgb(255 255 255 / 0.05);
  transition:
    background 220ms ease,
    border-color 220ms ease,
    box-shadow 220ms ease;
}

.sidebar-footer-card:hover {
  border-color: rgb(255 255 255 / 0.12);
  background: rgb(0 0 0 / 0.32);
  box-shadow:
    inset 0 1px 0 rgb(255 255 255 / 0.07),
    0 8px 20px rgb(0 0 0 / 0.18);
}

.sidebar-footer-btn {
  border-radius: 0.625rem;
  color: rgb(255 255 255 / 0.5);
  transition:
    color 200ms ease,
    background 200ms ease,
    transform 180ms cubic-bezier(0.34, 1.2, 0.64, 1),
    box-shadow 200ms ease;
}

.sidebar-footer-btn:hover {
  color: #fff;
  background: rgb(255 255 255 / 0.1);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgb(0 0 0 / 0.15);
}

.sidebar-footer-btn:active {
  transform: translateY(0) scale(0.96);
}

.sidebar-footer-btn:disabled {
  cursor: not-allowed;
  opacity: 0.6;
  transform: none;
}

.sidebar-footer-btn--danger:hover {
  color: rgb(254 202 202);
  background: rgb(197 61 61 / 0.16);
  box-shadow: 0 4px 14px rgb(197 61 61 / 0.18);
}

.sidebar-dropdown-chevron {
  opacity: 0.7;
  transition: transform 200ms ease, opacity 200ms ease;
}

.sidebar-dropdown-chevron--open {
  transform: rotate(180deg);
  opacity: 1;
}

.sidebar-dropdown-panel {
  animation: sidebar-dropdown-in 0.18s ease-out;
}

@keyframes sidebar-dropdown-in {
  from {
    opacity: 0;
    transform: translateY(-4px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (prefers-reduced-motion: reduce) {
  .sidebar-shell,
  .sidebar-nav-link,
  .sidebar-nav-link__icon,
  .sidebar-nav-link__label,
  .sidebar-dropdown-trigger,
  .sidebar-dropdown-chevron,
  .sidebar-footer-btn,
  .sidebar-footer-card,
  .sidebar-dropdown-panel {
    transition: none;
    animation: none;
    transform: none;
  }
}
</style>
