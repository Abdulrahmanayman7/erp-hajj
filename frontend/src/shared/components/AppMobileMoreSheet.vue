<script setup lang="ts">
import { computed, onUnmounted, toRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'
import { X } from 'lucide-vue-next'

import {
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
const openRef = toRef(props, 'open')
useBodyScrollLock(openRef)

const visibleKeys = computed(() => new Set(props.items.map((item) => item.key)))

const visibleGroups = computed(() =>
  navGroups.value
    .map((group) => ({
      ...group,
      items: group.items.filter((item) => visibleKeys.value.has(item.key)),
    }))
    .filter((group) => group.items.length > 0),
)

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape' && props.open) {
    event.preventDefault()
    emit('close')
  }
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      document.addEventListener('keydown', onKeydown)
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
      <button
        type="button"
        class="absolute inset-0 bg-brand-text/40"
        :aria-label="t('shell.close')"
        @click="emit('close')"
      />

      <div
        class="absolute inset-x-0 bottom-0 flex max-h-[85dvh] flex-col overflow-hidden rounded-t-[1.5rem] border border-brand-border bg-brand-surface shadow-[0_-18px_40px_-24px_rgba(23,32,29,0.45)]"
        style="padding-bottom: max(12px, env(safe-area-inset-bottom))"
      >
        <div class="flex shrink-0 items-center justify-between gap-3 border-b border-brand-border px-4 py-3">
          <div class="min-w-0">
            <p class="text-base font-bold text-brand-text">{{ t('nav.more') }}</p>
            <p class="text-xs text-brand-text-secondary">{{ t('shell.moreSubtitle') }}</p>
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

        <div class="min-h-0 flex-1 space-y-5 overflow-y-auto px-4 py-4">
          <section
            v-for="group in visibleGroups"
            :key="group.key"
            class="space-y-2"
          >
            <h3 class="text-xs font-bold tracking-wide text-brand-text-muted">
              {{ t(group.labelKey) }}
            </h3>
            <div class="grid grid-cols-2 gap-2">
              <RouterLink
                v-for="item in group.items"
                :key="item.key"
                :to="item.to"
                class="flex min-h-14 items-center gap-2.5 rounded-2xl border border-brand-border bg-brand-bg/60 px-3 py-3 text-sm font-semibold text-brand-text transition hover:border-brand-primary/30 hover:bg-brand-primary-soft"
                :class="{
                  'border-brand-primary/40 bg-brand-primary-soft text-brand-primary-dark': isAppNavItemActive(route.path, item),
                }"
                @click="emit('close')"
              >
                <component
                  :is="item.icon"
                  class="h-5 w-5 shrink-0 text-brand-primary-dark"
                  :stroke-width="1.85"
                />
                <span class="min-w-0 leading-snug break-words">{{ t(item.labelKey) }}</span>
              </RouterLink>
            </div>
          </section>

          <p
            v-if="visibleGroups.length === 0"
            class="py-8 text-center text-sm text-brand-text-secondary"
          >
            {{ t('shell.moreEmpty') }}
          </p>
        </div>
      </div>
    </div>
  </Teleport>
</template>
