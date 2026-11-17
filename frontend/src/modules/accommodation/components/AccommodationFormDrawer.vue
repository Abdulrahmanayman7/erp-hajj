<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

import AppFormDrawer from '@/shared/components/AppFormDrawer.vue'
import { useCaptureOnOpen, useFormDirty } from '@/shared/composables/useFormDirty'

const props = withDefaults(
  defineProps<{
    open: boolean
    submitting?: boolean
    disabled?: boolean
    /** Optional override; when omitted, dirty is derived from `form` when provided. */
    dirty?: boolean
    form?: unknown
    titleText?: string
  }>(),
  { submitting: false, disabled: false, titleText: '' },
)

const emit = defineEmits<{
  'update:open': [value: boolean]
  close: []
  submit: []
}>()

const { t } = useI18n()

const { dirty: formDirty, capture, reset: resetFormDirty } = useFormDirty(() => props.form, {
  enabled: () => props.form != null,
})
useCaptureOnOpen(() => props.open, capture, { onClose: () => resetFormDirty() })

const effectiveDirty = computed(() => props.dirty ?? formDirty.value)

function onOpenUpdate(value: boolean): void {
  emit('update:open', value)
  if (!value) emit('close')
}
</script>

<template>
  <AppFormDrawer
    :open="open"
    :title="titleText || t('accommodation.experience.eyebrow')"
    size="md"
    :submitting="submitting"
    :submit-disabled="disabled"
    :dirty="effectiveDirty"
    :cancel-label="t('accommodation.cancel')"
    data-test="accommodation-form-drawer"
    @update:open="onOpenUpdate"
    @close="emit('close')"
    @submit="emit('submit')"
  >
    <template v-if="$slots.title" #header>
      <h3 class="text-lg font-bold text-brand-text">
        <slot name="title" />
      </h3>
      <p v-if="$slots.subtitle" class="mt-1 text-sm text-brand-text-secondary">
        <slot name="subtitle" />
      </p>
    </template>
    <div class="accommodation-form-drawer space-y-4">
      <slot />
    </div>
    <template v-if="$slots['submit-label']" #footer>
      <button
        type="button"
        class="inline-flex h-11 items-center justify-center rounded-xl border border-brand-border px-4 text-sm font-semibold"
        :disabled="submitting"
        @click="emit('close')"
      >
        {{ t('accommodation.cancel') }}
      </button>
      <button
        type="button"
        class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white disabled:opacity-50"
        :disabled="submitting || disabled"
        @click="emit('submit')"
      >
        <slot name="submit-label">{{ t('accommodation.save') }}</slot>
      </button>
    </template>
  </AppFormDrawer>
</template>

<style>
.accommodation-form-drawer label > span:first-child {
  color: var(--color-brand-text);
  font-size: 0.875rem;
  font-weight: 500;
}
.accommodation-form-drawer input:not([type='checkbox']):not([type='radio']),
.accommodation-form-drawer textarea {
  width: 100%;
  margin-top: 0.25rem;
  min-height: 2.75rem;
  border: 1px solid var(--color-brand-border);
  border-radius: 0.75rem;
  padding: 0.5rem 0.75rem;
  font-size: 0.875rem;
}
.accommodation-form-drawer input[readonly] {
  background: var(--color-brand-bg);
  color: var(--color-brand-text-muted);
}
.accommodation-form-drawer input:focus-visible,
.accommodation-form-drawer textarea:focus-visible {
  outline: 2px solid var(--color-brand-primary);
  outline-offset: 2px;
}
.accommodation-form-drawer input[type='checkbox'] {
  width: 1rem;
  height: 1rem;
  accent-color: var(--color-brand-primary);
}
</style>