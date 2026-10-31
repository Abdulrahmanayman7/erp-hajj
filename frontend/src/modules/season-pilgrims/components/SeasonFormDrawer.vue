<script setup lang="ts">
import { computed, nextTick, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Loader2, X } from 'lucide-vue-next'

import type { Season, SeasonFormState } from '../types/seasons'

const props = defineProps<{
  open: boolean
  editing: Season | null
  form: SeasonFormState
  formError: string
  fieldErrors: Record<string, string>
  submitting: boolean
  locked: boolean
}>()

const emit = defineEmits<{
  close: []
  submit: []
  'update:form': [SeasonFormState]
}>()

const { t } = useI18n()
const codeInputRef = ref<HTMLInputElement | null>(null)
let previouslyFocused: HTMLElement | null = null

const isEdit = computed(() => props.editing != null)
const title = computed(() => (isEdit.value ? t('seasons.editTitle') : t('seasons.createTitle')))
const subtitle = computed(() =>
  isEdit.value ? t('seasons.editSubtitle') : t('seasons.createSubtitle'),
)
const primaryLabel = computed(() =>
  isEdit.value ? t('seasons.editCta') : t('seasons.createCta'),
)

function patch(partial: Partial<SeasonFormState>): void {
  emit('update:form', { ...props.form, ...partial })
}

function fieldMessage(key: string): string {
  const code = props.fieldErrors[key]
  if (!code) return ''
  return t(`seasons.errors.validation.${code}`)
}

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
      codeInputRef.value?.focus()
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
    <Transition name="season-drawer">
      <div v-if="open" class="fixed inset-0 z-50" role="presentation">
        <div
          class="absolute inset-0 bg-[rgba(15,23,20,0.32)]"
          aria-hidden="true"
          @click="emit('close')"
        />
        <aside
          class="absolute inset-y-0 start-0 flex h-dvh w-full max-w-[480px] flex-col bg-brand-surface shadow-[-12px_0_40px_-24px_rgba(23,32,29,0.35)]"
          role="dialog"
          aria-modal="true"
          v-autofocus-when
          :aria-label="title"
          @click.stop
        >
          <header
            class="flex shrink-0 items-start justify-between gap-4 border-b border-brand-border px-4 py-5 sm:px-7"
          >
            <div class="min-w-0 text-start">
              <h3 class="text-[21px] font-bold leading-tight text-brand-text">{{ title }}</h3>
              <p class="mt-1.5 text-[13px] leading-relaxed text-brand-text-secondary">
                {{ subtitle }}
              </p>
            </div>
            <button
              type="button"
              class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] text-brand-text-secondary transition hover:bg-brand-bg hover:text-brand-text"
              :aria-label="t('seasons.closeDrawer')"
              :disabled="submitting"
              @click="emit('close')"
            >
              <X class="h-5 w-5" />
            </button>
          </header>

          <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="emit('submit')">
            <div class="flex-1 space-y-4 overflow-y-auto px-4 py-5 sm:px-7">
              <p
                v-if="locked"
                class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900"
              >
                {{ t('seasons.closedLocked') }}
              </p>
              <p
                v-if="formError"
                class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
              >
                {{ formError }}
              </p>

              <label class="block space-y-1.5">
                <span class="text-sm font-semibold text-brand-text">{{ t('seasons.fields.code') }}</span>
                <input
                  ref="codeInputRef"
                  :value="form.code"
                  type="text"
                  maxlength="40"
                  dir="ltr"
                  class="h-11 w-full rounded-xl border border-brand-border px-3 text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg"
                  :disabled="locked || submitting"
                  @input="patch({ code: ($event.target as HTMLInputElement).value })"
                />
                <span v-if="fieldMessage('code')" class="text-xs text-red-600">{{
                  fieldMessage('code')
                }}</span>
              </label>

              <label class="block space-y-1.5">
                <span class="text-sm font-semibold text-brand-text">{{ t('seasons.fields.name') }}</span>
                <input
                  :value="form.name"
                  type="text"
                  maxlength="255"
                  class="h-11 w-full rounded-xl border border-brand-border px-3 text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg"
                  :disabled="locked || submitting"
                  @input="patch({ name: ($event.target as HTMLInputElement).value })"
                />
                <span v-if="fieldMessage('name')" class="text-xs text-red-600">{{
                  fieldMessage('name')
                }}</span>
              </label>

              <label class="block space-y-1.5">
                <span class="text-sm font-semibold text-brand-text">{{
                  t('seasons.fields.hajjYear')
                }}</span>
                <input
                  :value="form.hajj_year"
                  type="number"
                  min="1400"
                  max="1600"
                  dir="ltr"
                  class="h-11 w-full rounded-xl border border-brand-border px-3 text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg"
                  :disabled="locked || submitting"
                  @input="patch({ hajj_year: ($event.target as HTMLInputElement).value })"
                />
                <span v-if="fieldMessage('hajj_year')" class="text-xs text-red-600">{{
                  fieldMessage('hajj_year')
                }}</span>
              </label>

              <label class="block space-y-1.5">
                <span class="text-sm font-semibold text-brand-text">{{
                  t('seasons.fields.startsOn')
                }}</span>
                <input
                  :value="form.operational_starts_on"
                  type="date"
                  dir="ltr"
                  class="h-11 w-full rounded-xl border border-brand-border px-3 text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg"
                  :disabled="locked || submitting"
                  @input="
                    patch({ operational_starts_on: ($event.target as HTMLInputElement).value })
                  "
                />
                <span v-if="fieldMessage('operational_starts_on')" class="text-xs text-red-600">{{
                  fieldMessage('operational_starts_on')
                }}</span>
              </label>

              <label class="block space-y-1.5">
                <span class="text-sm font-semibold text-brand-text">{{
                  t('seasons.fields.endsOn')
                }}</span>
                <input
                  :value="form.operational_ends_on"
                  type="date"
                  dir="ltr"
                  class="h-11 w-full rounded-xl border border-brand-border px-3 text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg"
                  :disabled="locked || submitting"
                  @input="
                    patch({ operational_ends_on: ($event.target as HTMLInputElement).value })
                  "
                />
                <span v-if="fieldMessage('operational_ends_on')" class="text-xs text-red-600">{{
                  fieldMessage('operational_ends_on')
                }}</span>
              </label>

              <label class="block space-y-1.5">
                <span class="text-sm font-semibold text-brand-text">{{ t('seasons.fields.notes') }}</span>
                <textarea
                  :value="form.notes"
                  rows="3"
                  maxlength="5000"
                  class="w-full rounded-xl border border-brand-border px-3 py-2 text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg"
                  :disabled="locked || submitting"
                  @input="patch({ notes: ($event.target as HTMLTextAreaElement).value })"
                />
                <span v-if="fieldMessage('notes')" class="text-xs text-red-600">{{
                  fieldMessage('notes')
                }}</span>
              </label>
            </div>

            <footer
              class="flex shrink-0 items-center justify-end gap-3 border-t border-brand-border px-4 py-4 sm:px-7"
            >
              <button
                type="button"
                class="inline-flex h-11 items-center justify-center rounded-xl border border-brand-border px-4 text-sm font-semibold"
                :disabled="submitting"
                @click="emit('close')"
              >
                {{ t('seasons.cancel') }}
              </button>
              <button
                type="submit"
                class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white disabled:opacity-60"
                :disabled="submitting || locked"
              >
                <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
                <span>{{ submitting ? t('seasons.saving') : primaryLabel }}</span>
              </button>
            </footer>
          </form>
        </aside>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.season-drawer-enter-active,
.season-drawer-leave-active {
  transition: opacity 0.18s ease;
}
.season-drawer-enter-active aside,
.season-drawer-leave-active aside {
  transition: transform 0.22s ease;
}
.season-drawer-enter-from,
.season-drawer-leave-to {
  opacity: 0;
}
.season-drawer-enter-from aside,
.season-drawer-leave-to aside {
  transform: translateX(100%);
}
html[dir='ltr'] .season-drawer-enter-from aside,
html[dir='ltr'] .season-drawer-leave-to aside {
  transform: translateX(-100%);
}
</style>
