<script setup lang="ts">
import { computed, nextTick, onUnmounted, ref, useId, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Loader2, X } from 'lucide-vue-next'

import { useBodyScrollLock } from '@/shared/composables/useBodyScrollLock'
import { useConfirm } from '@/shared/composables/useConfirm'

export type AppFormDrawerSize = 'sm' | 'md' | 'lg'

const props = withDefaults(
  defineProps<{
    open: boolean
    title: string
    description?: string
    size?: AppFormDrawerSize
    loading?: boolean
    submitting?: boolean
    dirty?: boolean
    closeOnBackdrop?: boolean
    closeOnEscape?: boolean
    hideDefaultFooter?: boolean
    cancelLabel?: string
    submitLabel?: string
    submitDisabled?: boolean
    formId?: string
  }>(),
  {
    description: '',
    size: 'md',
    loading: false,
    submitting: false,
    dirty: false,
    closeOnBackdrop: true,
    closeOnEscape: true,
    hideDefaultFooter: false,
    cancelLabel: '',
    submitLabel: '',
    submitDisabled: false,
    formId: '',
  },
)

const emit = defineEmits<{
  'update:open': [value: boolean]
  close: []
  submit: []
}>()

const { t } = useI18n()
const { confirm } = useConfirm()

const titleId = useId()
const descriptionId = useId()
const panelRef = ref<HTMLElement | null>(null)
const closeBtnRef = ref<HTMLButtonElement | null>(null)
let previouslyFocused: HTMLElement | null = null
let confirmingClose = false

const openRef = computed(() => props.open)
useBodyScrollLock(openRef)

const sizeClass = computed(() => {
  if (props.size === 'sm') return 'max-w-[440px]'
  if (props.size === 'lg') return 'max-w-[720px]'
  return 'max-w-[560px]'
})

const resolvedCancelLabel = computed(
  () => props.cancelLabel || t('formDrawer.cancel'),
)
const resolvedSubmitLabel = computed(
  () => props.submitLabel || t('formDrawer.save'),
)
const resolvedFormId = computed(() => props.formId || `app-form-drawer-${titleId}`)

function focusableElements(root: HTMLElement): HTMLElement[] {
  return Array.from(
    root.querySelectorAll<HTMLElement>(
      'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])',
    ),
  ).filter((el) => !el.hasAttribute('disabled') && el.getAttribute('aria-hidden') !== 'true')
}

function onKeydown(event: KeyboardEvent): void {
  if (!props.open) return

  if (event.key === 'Escape' && props.closeOnEscape) {
    event.preventDefault()
    event.stopPropagation()
    void requestClose()
    return
  }

  if (event.key !== 'Tab' || !panelRef.value) return
  const nodes = focusableElements(panelRef.value)
  if (nodes.length === 0) {
    event.preventDefault()
    return
  }
  const first = nodes[0]!
  const last = nodes[nodes.length - 1]!
  const active = document.activeElement as HTMLElement | null
  if (event.shiftKey && active === first) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && active === last) {
    event.preventDefault()
    first.focus()
  }
}

async function requestClose(): Promise<void> {
  if (props.submitting || confirmingClose) return

  if (props.dirty) {
    confirmingClose = true
    try {
      const discard = await confirm({
        title: t('formDrawer.unsavedTitle'),
        message: t('formDrawer.unsavedMessage'),
        confirmLabel: t('formDrawer.discard'),
        cancelLabel: t('formDrawer.keepEditing'),
        variant: 'warning',
      })
      if (!discard) return
    } finally {
      confirmingClose = false
    }
  }

  emit('update:open', false)
  emit('close')
}

function onBackdropClick(): void {
  if (!props.closeOnBackdrop || props.submitting) return
  void requestClose()
}

function onSubmitClick(): void {
  if (props.submitting || props.submitDisabled || props.loading) return
  emit('submit')
}

watch(
  () => props.open,
  async (isOpen) => {
    if (isOpen) {
      previouslyFocused =
        document.activeElement instanceof HTMLElement ? document.activeElement : null
      document.addEventListener('keydown', onKeydown, true)
      await nextTick()
      const preferred =
        panelRef.value?.querySelector<HTMLElement>(
          'input:not([type="hidden"]):not([disabled]), textarea:not([disabled]), select:not([disabled])',
        ) ?? closeBtnRef.value
      preferred?.focus()
      return
    }

    document.removeEventListener('keydown', onKeydown, true)
    await nextTick()
    // Restore focus after leave transition (~220ms); skip if already moved.
    window.setTimeout(() => {
      if (document.activeElement === document.body || document.activeElement == null) {
        previouslyFocused?.focus?.()
      }
      previouslyFocused = null
    }, 230)
  },
)

onUnmounted(() => {
  document.removeEventListener('keydown', onKeydown, true)
})

defineExpose({ requestClose })
</script>

<template>
  <Teleport to="body">
    <Transition name="app-form-drawer">
      <div
        v-if="open"
        class="app-form-drawer-root fixed inset-0 z-50"
        role="presentation"
        data-test="app-form-drawer"
      >
        <div
          class="app-form-drawer-backdrop absolute inset-0 bg-[rgba(15,23,20,0.35)]"
          aria-hidden="true"
          data-test="app-form-drawer-backdrop"
          @click="onBackdropClick"
        />

        <aside
          ref="panelRef"
          class="app-form-drawer-panel app-drawer-panel absolute inset-y-0 start-0 flex h-dvh w-full flex-col bg-brand-surface"
          :class="sizeClass"
          role="dialog"
          aria-modal="true"
          :aria-label="title"
          :aria-labelledby="$slots.header ? undefined : titleId"
          :aria-describedby="description && !$slots.header ? descriptionId : undefined"
          data-test="app-form-drawer-panel"
          @click.stop
        >
          <header
            class="flex shrink-0 items-start justify-between gap-3 border-b border-brand-border px-4 py-4 sm:px-6"
          >
            <div class="flex min-w-0 items-start gap-3">
              <div
                v-if="$slots.icon"
                class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[var(--surface-subtle)] text-brand-primary"
              >
                <slot name="icon" />
              </div>
              <div class="min-w-0 text-start">
                <slot name="header">
                  <h3 :id="titleId" class="text-lg font-bold leading-tight text-brand-text sm:text-[1.25rem]">
                    {{ title }}
                  </h3>
                  <p
                    v-if="description"
                    :id="descriptionId"
                    class="mt-1 text-[13px] leading-relaxed text-brand-text-secondary"
                  >
                    {{ description }}
                  </p>
                </slot>
              </div>
            </div>
            <button
              ref="closeBtnRef"
              type="button"
              class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-[10px] text-brand-text-secondary transition hover:bg-brand-bg hover:text-brand-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/30 disabled:opacity-50"
              :aria-label="t('formDrawer.close')"
              :disabled="submitting"
              data-test="app-form-drawer-close"
              @click="requestClose"
            >
              <X class="h-4 w-4" :stroke-width="2.25" />
            </button>
          </header>

          <div
            v-if="loading"
            class="flex min-h-0 flex-1 items-center justify-center px-6 py-10 text-sm text-brand-text-muted"
            data-test="app-form-drawer-loading"
          >
            <Loader2 class="me-2 h-5 w-5 animate-spin" />
            {{ t('common.loading') }}
          </div>

          <form
            v-else
            :id="resolvedFormId"
            class="flex min-h-0 flex-1 flex-col"
            @submit.prevent="onSubmitClick"
          >
            <div class="min-h-0 flex-1 space-y-4 overflow-y-auto overscroll-contain px-4 py-4 sm:space-y-5 sm:px-6 sm:py-5">
              <slot />
            </div>

            <footer
              v-if="!hideDefaultFooter || $slots.footer"
              class="flex shrink-0 flex-col-reverse gap-2 border-t border-brand-border px-4 py-3 sm:flex-row sm:justify-end sm:px-6 sm:py-4"
              style="padding-bottom: max(12px, env(safe-area-inset-bottom))"
            >
              <slot name="footer">
                <button
                  type="button"
                  class="inline-flex h-11 items-center justify-center rounded-xl border border-brand-border px-4 text-sm font-semibold disabled:opacity-50 sm:min-w-[7.5rem]"
                  :disabled="submitting"
                  data-test="app-form-drawer-cancel"
                  @click="requestClose"
                >
                  {{ resolvedCancelLabel }}
                </button>
                <button
                  type="submit"
                  class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white disabled:opacity-50 sm:min-w-[7.5rem]"
                  :disabled="submitting || submitDisabled || loading"
                  data-test="app-form-drawer-submit"
                >
                  <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
                  {{ resolvedSubmitLabel }}
                </button>
              </slot>
            </footer>
          </form>
        </aside>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.app-form-drawer-panel {
  border-start-start-radius: 0;
  border-end-start-radius: 0;
  border-start-end-radius: 1.25rem;
  border-end-end-radius: 1.25rem;
  box-shadow: -12px 0 40px rgba(12, 36, 29, 0.12);
}

.app-form-drawer-enter-active,
.app-form-drawer-leave-active {
  transition: opacity 0.28s cubic-bezier(0.22, 1, 0.36, 1);
}
.app-form-drawer-enter-active .app-form-drawer-panel,
.app-form-drawer-leave-active .app-form-drawer-panel {
  transition: transform 0.28s cubic-bezier(0.22, 1, 0.36, 1);
}
.app-form-drawer-leave-active,
.app-form-drawer-leave-active .app-form-drawer-panel {
  transition-duration: 0.22s;
}

.app-form-drawer-enter-from,
.app-form-drawer-leave-to {
  opacity: 0;
}

.app-form-drawer-enter-from .app-form-drawer-panel,
.app-form-drawer-leave-to .app-form-drawer-panel {
  transform: translate3d(100%, 0, 0);
}

:dir(ltr) .app-form-drawer-enter-from .app-form-drawer-panel,
:dir(ltr) .app-form-drawer-leave-to .app-form-drawer-panel {
  transform: translate3d(-100%, 0, 0);
}

.app-form-drawer-backdrop {
  backdrop-filter: blur(2px);
}

@media (prefers-reduced-motion: reduce) {
  .app-form-drawer-enter-active,
  .app-form-drawer-leave-active,
  .app-form-drawer-enter-active .app-form-drawer-panel,
  .app-form-drawer-leave-active .app-form-drawer-panel {
    transition: none !important;
  }

  .app-form-drawer-backdrop {
    backdrop-filter: none;
  }
}
</style>
