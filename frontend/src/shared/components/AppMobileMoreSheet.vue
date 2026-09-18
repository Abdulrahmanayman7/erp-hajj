<script setup lang="ts">
import { computed, onUnmounted, ref, toRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'
import { ChevronLeft, LogOut, Search, X } from 'lucide-vue-next'

import { useLogoutMutation } from '@/modules/auth/mutations/useLogoutMutation'
import { useCurrentUserQuery } from '@/modules/auth/queries/useCurrentUserQuery'
import PwaInstallCard from '@/shared/components/PwaInstallCard.vue'
import UserAvatar from '@/shared/components/UserAvatar.vue'
import {
  filterNavGroups,
  isAppNavItemActive,
  type AppNavItem,
  useAppNavigation,
} from '@/shared/composables/useAppNavigation'
import { useBodyScrollLock } from '@/shared/composables/useBodyScrollLock'

const props = defineProps<{
  open: boolean
  items: AppNavItem[]
}>()

const emit = defineEmits<{
  close: []
}>()

const { t } = useI18n()
const route = useRoute()
const { navGroups } = useAppNavigation()
const { data: user } = useCurrentUserQuery()
const { mutate: logout, isPending: isLoggingOut } = useLogoutMutation()
const openRef = toRef(props, 'open')
useBodyScrollLock(openRef)

const query = ref('')

const displayName = computed(() => user.value?.name ?? t('auth.userFallback'))
const roleLabel = computed(() => user.value?.roles?.[0]?.name ?? t('auth.systemManager'))

const visibleKeys = computed(() => new Set(props.items.map((item) => item.key)))

const visibleGroups = computed(() =>
  filterNavGroups(navGroups.value, {
    visibleKeys: visibleKeys.value,
    query: query.value,
    labelOf: (key) => t(key),
  }),
)

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape' && props.open) {
    event.preventDefault()
    emit('close')
  }
}

function onLogout(): void {
  emit('close')
  logout()
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      document.addEventListener('keydown', onKeydown)
      query.value = ''
      return
    }
    document.removeEventListener('keydown', onKeydown)
  },
)

onUnmounted(() => {
  document.removeEventListener('keydown', onKeydown)
})
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      id="app-mobile-more-sheet"
      class="fixed inset-0 z-[80] md:hidden"
      role="dialog"
      aria-modal="true"
      :aria-label="t('nav.more')"
    >
      <div
        class="absolute inset-0 flex flex-col bg-brand-surface"
        style="padding-top: env(safe-area-inset-top, 0px); padding-bottom: env(safe-area-inset-bottom, 0px)"
      >
        <div class="flex shrink-0 items-center gap-3 border-b border-brand-border px-4 py-3">
          <UserAvatar :user="user" size="lg" :lazy="false" />
          <div class="min-w-0 flex-1">
            <p class="truncate text-base font-bold text-brand-text">{{ displayName }}</p>
            <p class="truncate text-xs text-brand-text-secondary">{{ roleLabel }}</p>
          </div>
          <button
            type="button"
            class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-brand-text-secondary transition hover:bg-brand-bg"
            :aria-label="t('shell.close')"
            @click="emit('close')"
          >
            <X class="h-5 w-5" :stroke-width="2" />
          </button>
        </div>

        <div class="shrink-0 px-4 pt-3">
          <label class="relative block">
            <span class="sr-only">{{ t('shell.navSearchPlaceholder') }}</span>
            <Search
              class="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-text-muted"
              :stroke-width="1.85"
              aria-hidden="true"
            />
            <input
              v-model="query"
              type="search"
              class="app-input app-input--search w-full"
              :placeholder="t('shell.navSearchPlaceholder')"
              autocomplete="off"
            />
          </label>
        </div>

        <div class="min-h-0 flex-1 space-y-5 overflow-y-auto px-4 py-4">
          <PwaInstallCard />

          <section
            v-for="group in visibleGroups"
            :key="group.key"
            class="space-y-1.5"
          >
            <h3 class="px-1 text-xs font-bold tracking-wide text-brand-text-muted">
              {{ t(group.labelKey) }}
            </h3>
            <div class="divide-y divide-[var(--border-soft)] overflow-hidden rounded-2xl border border-brand-border">
              <RouterLink
                v-for="item in group.items"
                :key="item.key"
                :to="item.to"
                class="flex min-h-12 items-center gap-3 bg-brand-surface px-3 py-2.5 text-sm font-semibold text-brand-text transition hover:bg-[var(--surface-subtle)]"
                :class="{
                  'bg-brand-primary-soft text-brand-primary-dark': isAppNavItemActive(route.path, item),
                }"
                @click="emit('close')"
              >
                <component
                  :is="item.icon"
                  class="h-5 w-5 shrink-0 text-brand-primary-dark"
                  :stroke-width="1.85"
                />
                <span class="min-w-0 flex-1 leading-snug break-words">{{ t(item.labelKey) }}</span>
                <ChevronLeft
                  class="h-4 w-4 shrink-0 text-brand-text-muted"
                  :stroke-width="1.85"
                  aria-hidden="true"
                />
              </RouterLink>
            </div>
          </section>

          <p
            v-if="visibleGroups.length === 0"
            class="py-8 text-center text-sm text-brand-text-secondary"
          >
            {{ query.trim() ? t('shell.navSearchEmpty') : t('shell.moreEmpty') }}
          </p>
        </div>

        <div class="shrink-0 border-t border-brand-border px-4 py-3">
          <button
            type="button"
            class="flex min-h-11 w-full items-center justify-center gap-2 rounded-xl px-3 text-sm font-semibold text-brand-danger transition hover:bg-[var(--danger-soft)] disabled:opacity-60"
            :disabled="isLoggingOut"
            @click="onLogout"
          >
            <LogOut class="h-4 w-4" :stroke-width="1.75" />
            <span>{{ t('auth.logout') }}</span>
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
