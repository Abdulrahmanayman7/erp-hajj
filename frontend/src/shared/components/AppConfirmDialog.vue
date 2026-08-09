<script setup lang="ts">
import { computed, onUnmounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { AlertTriangle, UserCheck, UserX, X } from 'lucide-vue-next'

import { useConfirm, type ConfirmVariant } from '@/shared/composables/useConfirm'

const { t } = useI18n()
const { state, accept, cancel } = useConfirm()

const icon = computed(() => {
  if (state.value.variant === 'primary') return UserCheck
  if (state.value.variant === 'danger') return UserX
  return AlertTriangle
})

function iconWrapClass(variant: ConfirmVariant | undefined): string {
  if (variant === 'primary') return 'bg-brand-primary-soft text-brand-primary-dark'
  if (variant === 'danger') return 'bg-red-50 text-red-700'
  return 'bg-brand-gold-soft text-brand-warning'
}

function confirmBtnClass(variant: ConfirmVariant | undefined): string {
  if (variant === 'primary') {
    return 'bg-brand-primary-dark text-white hover:bg-brand-primary'
  }
  if (variant === 'danger') {
    return 'bg-red-700 text-white hover:bg-red-800'
  }
  return 'bg-brand-primary-dark text-white hover:bg-brand-primary'
}

function onKeydown(event: KeyboardEvent): void {
  if (!state.value.open) return
  if (event.key === 'Escape') {
    event.preventDefault()
    cancel()
  }
}

watch(
  () => state.value.open,
  (open) => {
    if (open) {
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
    <Transition name="app-confirm">
      <div
        v-if="state.open"
        class="fixed inset-0 z-[450] flex items-center justify-center p-4"
        role="presentation"
      >
        <div
          class="app-confirm-backdrop absolute inset-0 bg-[rgba(15,23,20,0.38)]"
          aria-hidden="true"
          @click="cancel"
        />

        <div
          class="app-confirm-panel relative w-full max-w-[420px] overflow-hidden rounded-2xl border border-brand-border bg-brand-surface shadow-[0_24px_60px_-28px_rgba(23,32,29,0.55)]"
          role="alertdialog"
          aria-modal="true"
          :aria-labelledby="'app-confirm-title'"
          :aria-describedby="'app-confirm-message'"
          @click.stop
        >
          <button
            type="button"
            class="absolute end-3 top-3 inline-flex h-8 w-8 items-center justify-center rounded-lg text-brand-text-muted transition hover:bg-brand-bg hover:text-brand-text"
            :aria-label="t('users.closeDrawer')"
            @click="cancel"
          >
            <X class="h-4 w-4" :stroke-width="2.25" />
          </button>

          <div class="px-6 pb-5 pt-6">
            <div
              class="mb-4 inline-flex h-11 w-11 items-center justify-center rounded-xl"
              :class="iconWrapClass(state.variant)"
            >
              <component :is="icon" class="h-5 w-5" :stroke-width="2" aria-hidden="true" />
            </div>

            <h3 id="app-confirm-title" class="text-[18px] font-bold leading-snug text-brand-text">
              {{ state.title }}
            </h3>
            <p
              id="app-confirm-message"
              class="mt-2 text-sm leading-relaxed text-brand-text-secondary"
            >
              {{ state.message }}
            </p>
          </div>

          <div
            class="flex items-center justify-end gap-2.5 border-t border-brand-border bg-[#F7F8F6] px-6 py-4"
          >
            <button
              type="button"
              class="inline-flex h-10 items-center justify-center rounded-[10px] border border-brand-border bg-brand-surface px-4 text-sm font-semibold text-brand-text transition hover:bg-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/20"
              @click="cancel"
            >
              {{ state.cancelLabel || t('users.cancel') }}
            </button>
            <button
              type="button"
              class="inline-flex h-10 min-w-[7.5rem] items-center justify-center rounded-[10px] px-4 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/25"
              :class="confirmBtnClass(state.variant)"
              @click="accept"
            >
              {{ state.confirmLabel || t('users.confirm') }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.app-confirm-enter-active,
.app-confirm-leave-active {
  pointer-events: none;
}

.app-confirm-enter-active .app-confirm-backdrop {
  transition: opacity 180ms ease;
}

.app-confirm-leave-active .app-confirm-backdrop {
  transition: opacity 150ms ease;
}

.app-confirm-enter-from .app-confirm-backdrop,
.app-confirm-leave-to .app-confirm-backdrop {
  opacity: 0;
}

.app-confirm-enter-active .app-confirm-panel {
  transition:
    opacity 200ms cubic-bezier(0.22, 1, 0.36, 1),
    transform 200ms cubic-bezier(0.22, 1, 0.36, 1);
}

.app-confirm-leave-active .app-confirm-panel {
  transition:
    opacity 150ms ease,
    transform 150ms ease;
}

.app-confirm-enter-from .app-confirm-panel,
.app-confirm-leave-to .app-confirm-panel {
  opacity: 0;
  transform: translateY(6px);
}

@media (prefers-reduced-motion: reduce) {
  .app-confirm-enter-active .app-confirm-backdrop,
  .app-confirm-leave-active .app-confirm-backdrop,
  .app-confirm-enter-active .app-confirm-panel,
  .app-confirm-leave-active .app-confirm-panel {
    transition: none;
  }

  .app-confirm-enter-from .app-confirm-panel,
  .app-confirm-leave-to .app-confirm-panel {
    opacity: 1;
    transform: none;
  }
}
</style>
