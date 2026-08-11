<script setup lang="ts">
import { computed, nextTick, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Loader2, X } from 'lucide-vue-next'

import type { RoleSummary } from '../types/roles'

export interface RoleFormState {
  name: string
  code: string
  description: string
}

const props = defineProps<{
  open: boolean
  editing: RoleSummary | null
  form: RoleFormState
  formError: string
  submitting: boolean
}>()

const emit = defineEmits<{
  close: []
  submit: []
}>()

const { t } = useI18n()
const nameInputRef = ref<HTMLInputElement | null>(null)
let previouslyFocused: HTMLElement | null = null

const isEdit = computed(() => props.editing != null)
const title = computed(() => (isEdit.value ? t('roles.editTitle') : t('roles.createTitle')))
const subtitle = computed(() =>
  isEdit.value ? t('roles.editSubtitle') : t('roles.createSubtitle'),
)
const primaryLabel = computed(() => (isEdit.value ? t('roles.editCta') : t('roles.createCta')))

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape' && props.open && !props.submitting) {
    event.preventDefault()
    emit('close')
  }
}

watch(
  () => props.open,
  async (isOpen) => {
    if (isOpen) {
      previouslyFocused =
        document.activeElement instanceof HTMLElement ? document.activeElement : null
      document.addEventListener('keydown', onKeydown)
      await nextTick()
      nameInputRef.value?.focus()
      return
    }

    document.removeEventListener('keydown', onKeydown)
    await nextTick()
    previouslyFocused?.focus?.()
    previouslyFocused = null
  },
)

onUnmounted(() => {
  document.removeEventListener('keydown', onKeydown)
})
</script>

<template>
  <Teleport to="body">
    <Transition name="role-drawer">
      <div v-if="open" class="role-drawer-root fixed inset-0 z-50" role="presentation">
        <div
          class="role-drawer-backdrop absolute inset-0 bg-[rgba(15,23,20,0.32)]"
          aria-hidden="true"
          @click="emit('close')"
        />

        <aside
          class="role-drawer-panel absolute inset-y-0 start-0 flex h-dvh w-full max-w-[480px] flex-col bg-brand-surface shadow-[-12px_0_40px_-24px_rgba(23,32,29,0.35)]"
          role="dialog"
          aria-modal="true"
          :aria-label="title"
          @click.stop
        >
          <header
            class="flex shrink-0 items-start justify-between gap-4 border-b border-brand-border px-6 py-5 sm:px-7"
          >
            <div class="min-w-0 text-start">
              <h3 class="text-[21px] font-bold leading-tight text-brand-text">
                {{ title }}
              </h3>
              <p class="mt-1.5 text-[13px] leading-relaxed text-brand-text-secondary">
                {{ subtitle }}
              </p>
            </div>
            <button
              type="button"
              class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] text-brand-text-secondary transition duration-150 hover:bg-brand-bg hover:text-brand-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/25"
              :aria-label="t('roles.closeDrawer')"
              :disabled="submitting"
              @click="emit('close')"
            >
              <X class="h-4 w-4" :stroke-width="2.25" />
            </button>
          </header>

          <form
            id="role-form-drawer"
            class="flex min-h-0 flex-1 flex-col"
            @submit.prevent="emit('submit')"
          >
            <div class="flex-1 space-y-5 overflow-y-auto px-6 py-6 sm:px-7">
              <label class="block">
                <span class="mb-2 block text-sm font-semibold text-brand-text">
                  {{ t('roles.fields.name') }}
                </span>
                <input
                  ref="nameInputRef"
                  v-model="form.name"
                  type="text"
                  required
                  :placeholder="t('roles.namePlaceholder')"
                  class="h-12 w-full rounded-[11px] border border-brand-border bg-brand-surface px-3.5 text-sm text-brand-text outline-none transition duration-150 placeholder:text-brand-text-muted focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:cursor-not-allowed disabled:bg-brand-bg disabled:opacity-70"
                  :disabled="submitting"
                />
              </label>

              <label v-if="!editing" class="block">
                <span class="mb-2 block text-sm font-semibold text-brand-text">
                  {{ t('roles.fields.code') }}
                </span>
                <input
                  v-model="form.code"
                  type="text"
                  dir="ltr"
                  class="h-12 w-full rounded-[11px] border border-brand-border bg-brand-surface px-3.5 font-mono text-xs text-brand-text outline-none transition duration-150 placeholder:text-brand-text-muted focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:cursor-not-allowed disabled:bg-brand-bg disabled:opacity-70"
                  :placeholder="t('roles.fields.codeHint')"
                  :disabled="submitting"
                />
              </label>
              <div v-else class="rounded-[11px] border border-brand-border bg-brand-bg px-3.5 py-3">
                <p class="text-xs font-semibold text-brand-text-muted">{{ t('roles.fields.code') }}</p>
                <p class="mt-1 font-mono text-sm text-brand-text" dir="ltr">{{ form.code }}</p>
              </div>

              <label class="block">
                <span class="mb-2 block text-sm font-semibold text-brand-text">
                  {{ t('roles.fields.description') }}
                </span>
                <textarea
                  v-model="form.description"
                  rows="4"
                  :placeholder="t('roles.descriptionPlaceholder')"
                  class="w-full rounded-[11px] border border-brand-border bg-brand-surface px-3.5 py-3 text-sm text-brand-text outline-none transition duration-150 placeholder:text-brand-text-muted focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:cursor-not-allowed disabled:bg-brand-bg disabled:opacity-70"
                  :disabled="submitting"
                />
              </label>

              <p
                v-if="formError"
                class="rounded-[11px] border border-red-200 bg-red-50 px-3.5 py-3 text-sm text-red-700"
                role="alert"
              >
                {{ formError }}
              </p>
            </div>

            <footer
              class="flex shrink-0 items-center justify-end gap-2.5 border-t border-brand-border bg-brand-surface px-6 py-4 sm:px-7"
            >
              <button
                type="button"
                class="inline-flex h-11 items-center justify-center rounded-[10px] border border-brand-border bg-brand-surface px-4 text-sm font-semibold text-brand-text transition duration-150 hover:bg-brand-bg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/20 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="submitting"
                @click="emit('close')"
              >
                {{ t('roles.cancel') }}
              </button>
              <button
                type="submit"
                class="inline-flex h-11 min-w-[8.5rem] items-center justify-center gap-2 rounded-[10px] bg-brand-primary-dark px-5 text-sm font-semibold text-white transition duration-150 hover:bg-brand-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/30 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="submitting"
              >
                <Loader2
                  v-if="submitting"
                  class="h-4 w-4 animate-spin"
                  :stroke-width="2.25"
                  aria-hidden="true"
                />
                <span>{{ submitting ? t('roles.saving') : primaryLabel }}</span>
              </button>
            </footer>
          </form>
        </aside>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.role-drawer-enter-active,
.role-drawer-leave-active {
  pointer-events: none;
}

.role-drawer-enter-active .role-drawer-backdrop {
  transition: opacity 200ms ease;
}

.role-drawer-leave-active .role-drawer-backdrop {
  transition: opacity 180ms ease;
}

.role-drawer-enter-from .role-drawer-backdrop,
.role-drawer-leave-to .role-drawer-backdrop {
  opacity: 0;
}

.role-drawer-enter-active .role-drawer-panel {
  transition:
    transform 280ms cubic-bezier(0.22, 1, 0.36, 1),
    opacity 280ms cubic-bezier(0.22, 1, 0.36, 1);
}

.role-drawer-leave-active .role-drawer-panel {
  transition:
    transform 220ms cubic-bezier(0.22, 1, 0.36, 1),
    opacity 220ms cubic-bezier(0.22, 1, 0.36, 1);
}

.role-drawer-enter-from .role-drawer-panel,
.role-drawer-leave-to .role-drawer-panel {
  transform: translateX(100%);
  opacity: 0.92;
}

[dir='ltr'] .role-drawer-enter-from .role-drawer-panel,
[dir='ltr'] .role-drawer-leave-to .role-drawer-panel {
  transform: translateX(-100%);
}

@media (prefers-reduced-motion: reduce) {
  .role-drawer-enter-active .role-drawer-backdrop,
  .role-drawer-leave-active .role-drawer-backdrop,
  .role-drawer-enter-active .role-drawer-panel,
  .role-drawer-leave-active .role-drawer-panel {
    transition: none;
  }

  .role-drawer-enter-from .role-drawer-panel,
  .role-drawer-leave-to .role-drawer-panel,
  [dir='ltr'] .role-drawer-enter-from .role-drawer-panel,
  [dir='ltr'] .role-drawer-leave-to .role-drawer-panel {
    transform: none;
    opacity: 1;
  }
}
</style>
