<script setup lang="ts">
import { onUnmounted, toRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { X } from 'lucide-vue-next'

import {
  isAppNavItemActive,
  useAppNavigation,
} from '@/shared/composables/useAppNavigation'
import { useBodyScrollLock } from '@/shared/composables/useBodyScrollLock'
import { useRoute } from 'vue-router'

const props = defineProps<{
  open: boolean
}>()

const emit = defineEmits<{
  close: []
}>()

const { t } = useI18n()
const route = useRoute()
const { navGroups } = useAppNavigation()
const openRef = toRef(props, 'open')
useBodyScrollLock(openRef)

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
      class="fixed inset-0 z-[80] hidden md:block xl:hidden"
      role="dialog"
      aria-modal="true"
      :aria-label="t('shell.navOverlay')"
    >
      <button
        type="button"
        class="absolute inset-0 bg-brand-text/40"
        :aria-label="t('shell.close')"
        @click="emit('close')"
      />

      <aside
        class="absolute inset-y-0 end-0 flex w-[min(20rem,88vw)] flex-col border-s border-brand-border bg-brand-surface"
        style="padding-top: env(safe-area-inset-top, 0px); padding-bottom: env(safe-area-inset-bottom, 0px); box-shadow: var(--shadow-overlay)"
      >
        <div class="flex shrink-0 items-center justify-between gap-3 border-b border-brand-border px-4 py-3">
          <div class="min-w-0">
            <p class="text-base font-bold text-brand-text">{{ t('nav.more') }}</p>
            <p class="text-xs text-brand-text-secondary">{{ t('shell.navOverlaySubtitle') }}</p>
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
            v-for="group in navGroups"
            :key="group.key"
            class="space-y-2"
          >
            <h3 class="text-xs font-bold tracking-wide text-brand-text-muted">
              {{ t(group.labelKey) }}
            </h3>
            <div class="space-y-1">
              <RouterLink
                v-for="item in group.items"
                :key="item.key"
                :to="item.to"
                class="flex min-h-11 items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-brand-text transition hover:bg-brand-bg"
                :class="{
                  'bg-brand-primary-soft text-brand-primary-dark': isAppNavItemActive(route.path, item),
                }"
                @click="emit('close')"
              >
                <component
                  :is="item.icon"
                  class="h-4.5 w-4.5 shrink-0 opacity-90"
                  :stroke-width="1.85"
                  aria-hidden="true"
                />
                <span class="min-w-0 truncate">{{ t(item.labelKey) }}</span>
              </RouterLink>
            </div>
          </section>
        </div>
      </aside>
    </div>
  </Teleport>
</template>
