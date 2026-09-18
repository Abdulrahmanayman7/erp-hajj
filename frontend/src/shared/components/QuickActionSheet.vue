<script setup lang="ts">
import { onUnmounted, toRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { X } from 'lucide-vue-next'

import { useBodyScrollLock } from '@/shared/composables/useBodyScrollLock'
import { useQuickActions } from '@/shared/composables/useQuickActions'

const props = defineProps<{
  open: boolean
}>()

const emit = defineEmits<{
  close: []
}>()

const { t } = useI18n()
const router = useRouter()
const { actions } = useQuickActions()
const openRef = toRef(props, 'open')
useBodyScrollLock(openRef)

async function onSelect(to: string): Promise<void> {
  emit('close')
  await router.push(to)
}

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
      class="fixed inset-0 z-[80] md:hidden"
      role="dialog"
      aria-modal="true"
      :aria-label="t('quickActions.title')"
    >
      <button
        type="button"
        class="absolute inset-0 bg-brand-text/40"
        :aria-label="t('shell.close')"
        @click="emit('close')"
      />

      <div
        class="absolute inset-x-0 bottom-0 flex max-h-[min(85dvh,36rem)] flex-col overflow-hidden rounded-t-[20px] border border-brand-border bg-brand-surface"
        style="padding-bottom: max(12px, env(safe-area-inset-bottom)); box-shadow: var(--shadow-overlay)"
      >
        <div class="mx-auto mt-2 h-1 w-10 shrink-0 rounded-full bg-brand-border" aria-hidden="true" />

        <div class="flex shrink-0 items-center justify-between gap-3 px-4 pb-3 pt-2">
          <div class="min-w-0">
            <p class="text-base font-bold text-brand-text">{{ t('quickActions.title') }}</p>
            <p class="text-xs text-brand-text-secondary">{{ t('quickActions.subtitle') }}</p>
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

        <div class="min-h-0 flex-1 space-y-2 overflow-y-auto px-4 pb-4">
          <p
            v-if="actions.length === 0"
            class="rounded-xl bg-brand-bg px-3 py-4 text-center text-sm text-brand-text-muted"
          >
            {{ t('quickActions.empty') }}
          </p>
          <button
            v-for="action in actions"
            :key="action.key"
            type="button"
            class="flex min-h-12 w-full items-center gap-3 rounded-xl border border-brand-border bg-brand-bg/60 px-3 py-3 text-start text-sm font-semibold text-brand-text transition hover:border-brand-primary/30 hover:bg-brand-primary-soft active:scale-[0.99] motion-reduce:active:scale-100"
            @click="onSelect(action.to)"
          >
            <span
              class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-primary-soft text-brand-primary-dark"
            >
              <component :is="action.icon" class="h-5 w-5" :stroke-width="1.85" aria-hidden="true" />
            </span>
            <span class="min-w-0 truncate">{{ t(action.labelKey) }}</span>
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
