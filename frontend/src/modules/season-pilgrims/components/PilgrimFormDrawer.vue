<script setup lang="ts">
import { computed, nextTick, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Loader2, X } from 'lucide-vue-next'

import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'

import type { Pilgrim, PilgrimFormState } from '../types/pilgrims'

const props = defineProps<{
  open: boolean
  editing: Pilgrim | null
  form: PilgrimFormState
  formError: string
  fieldErrors: Record<string, string>
  submitting: boolean
}>()

const emit = defineEmits<{
  close: []
  submit: []
  'update:form': [PilgrimFormState]
}>()

const { t } = useI18n()
const nameInputRef = ref<HTMLInputElement | null>(null)
let previouslyFocused: HTMLElement | null = null

const isEdit = computed(() => props.editing != null)
const title = computed(() => (isEdit.value ? t('pilgrims.editTitle') : t('pilgrims.createTitle')))
const subtitle = computed(() =>
  isEdit.value ? t('pilgrims.editSubtitle') : t('pilgrims.createSubtitle'),
)
const primaryLabel = computed(() =>
  isEdit.value ? t('pilgrims.editCta') : t('pilgrims.createCta'),
)

const genderOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('pilgrims.fields.selectGender') },
  { value: 'male', label: t('pilgrims.gender.male') },
  { value: 'female', label: t('pilgrims.gender.female') },
])

function patch(partial: Partial<PilgrimFormState>): void {
  emit('update:form', { ...props.form, ...partial })
}

function fieldMessage(key: string): string {
  const code = props.fieldErrors[key]
  if (!code) return ''
  return t(`pilgrims.errors.validation.${code}`)
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
    <Transition name="pilgrim-drawer">
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
              class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] text-brand-text-secondary transition hover:bg-brand-bg hover:text-brand-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/25"
              :aria-label="t('pilgrims.closeDrawer')"
              :disabled="submitting"
              @click="emit('close')"
            >
              <X class="h-5 w-5" :stroke-width="2" />
            </button>
          </header>

          <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="emit('submit')">
            <div class="flex-1 space-y-4 overflow-y-auto px-4 py-5 sm:px-7">
              <p
                v-if="formError"
                class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
              >
                {{ formError }}
              </p>

              <label class="block space-y-1.5">
                <span class="text-sm font-semibold text-brand-text">{{
                  t('pilgrims.fields.fullName')
                }}</span>
                <input
                  ref="nameInputRef"
                  :value="form.full_name"
                  type="text"
                  maxlength="255"
                  class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface px-3 text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
                  @input="patch({ full_name: ($event.target as HTMLInputElement).value })"
                />
                <span v-if="fieldMessage('full_name')" class="text-xs text-red-600">{{
                  fieldMessage('full_name')
                }}</span>
              </label>

              <label class="block space-y-1.5">
                <span class="text-sm font-semibold text-brand-text">{{
                  t('pilgrims.fields.gender')
                }}</span>
                <AppSelect
                  :model-value="form.gender"
                  :options="genderOptions"
                  @update:model-value="
                    patch({ gender: ($event === '' ? '' : String($event)) as PilgrimFormState['gender'] })
                  "
                />
                <span v-if="fieldMessage('gender')" class="text-xs text-red-600">{{
                  fieldMessage('gender')
                }}</span>
              </label>

              <label class="block space-y-1.5">
                <span class="text-sm font-semibold text-brand-text">{{
                  t('pilgrims.fields.nationality')
                }}</span>
                <input
                  :value="form.nationality"
                  type="text"
                  maxlength="3"
                  dir="ltr"
                  class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface px-3 text-sm uppercase outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
                  @input="patch({ nationality: ($event.target as HTMLInputElement).value })"
                />
                <span class="text-xs text-brand-text-muted">{{
                  t('pilgrims.fields.nationalityHint')
                }}</span>
                <span v-if="fieldMessage('nationality')" class="block text-xs text-red-600">{{
                  fieldMessage('nationality')
                }}</span>
              </label>

              <label class="block space-y-1.5">
                <span class="text-sm font-semibold text-brand-text">{{
                  t('pilgrims.fields.phone')
                }}</span>
                <input
                  :value="form.phone"
                  type="text"
                  maxlength="32"
                  dir="ltr"
                  class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface px-3 text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
                  @input="patch({ phone: ($event.target as HTMLInputElement).value })"
                />
                <span v-if="fieldMessage('phone')" class="text-xs text-red-600">{{
                  fieldMessage('phone')
                }}</span>
              </label>

              <label class="block space-y-1.5">
                <span class="text-sm font-semibold text-brand-text">{{
                  t('pilgrims.fields.notes')
                }}</span>
                <textarea
                  :value="form.notes"
                  rows="4"
                  maxlength="5000"
                  class="w-full rounded-xl border border-brand-border bg-brand-surface px-3 py-2 text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
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
                class="inline-flex h-11 items-center justify-center rounded-xl border border-brand-border px-4 text-sm font-semibold text-brand-text"
                :disabled="submitting"
                @click="emit('close')"
              >
                {{ t('pilgrims.cancel') }}
              </button>
              <button
                type="submit"
                class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white disabled:opacity-60"
                :disabled="submitting"
              >
                <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
                <span>{{ submitting ? t('pilgrims.saving') : primaryLabel }}</span>
              </button>
            </footer>
          </form>
        </aside>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.pilgrim-drawer-enter-active,
.pilgrim-drawer-leave-active {
  transition: opacity 0.18s ease;
}
.pilgrim-drawer-enter-active aside,
.pilgrim-drawer-leave-active aside {
  transition: transform 0.22s ease;
}
.pilgrim-drawer-enter-from,
.pilgrim-drawer-leave-to {
  opacity: 0;
}
.pilgrim-drawer-enter-from aside,
.pilgrim-drawer-leave-to aside {
  transform: translateX(100%);
}
html[dir='ltr'] .pilgrim-drawer-enter-from aside,
html[dir='ltr'] .pilgrim-drawer-leave-to aside {
  transform: translateX(-100%);
}
</style>
