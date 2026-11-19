<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

import AppDateInput from '@/shared/components/AppDateInput.vue'
import AppFormDrawer from '@/shared/components/AppFormDrawer.vue'
import { useCaptureOnOpen, useFormDirty } from '@/shared/composables/useFormDirty'
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

const { dirty, capture, reset: resetDirty } = useFormDirty(() => props.form)
useCaptureOnOpen(() => props.open, capture, { onClose: () => resetDirty() })

function onOpenUpdate(value: boolean): void {
  if (!value) emit('close')
}
</script>

<template>
  <AppFormDrawer
    :open="open"
    :title="title"
    :description="subtitle"
    size="md"
    :dirty="dirty"
    :submitting="submitting"
    :submit-disabled="locked"
    :cancel-label="t('seasons.cancel')"
    :submit-label="primaryLabel"
    @update:open="onOpenUpdate"
    @close="emit('close')"
    @submit="emit('submit')"
  >
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
      <AppDateInput
        :model-value="form.operational_starts_on"
        :disabled="locked || submitting"
        :invalid="Boolean(fieldMessage('operational_starts_on'))"
        @update:model-value="patch({ operational_starts_on: $event })"
      />
      <span v-if="fieldMessage('operational_starts_on')" class="text-xs text-red-600">{{
        fieldMessage('operational_starts_on')
      }}</span>
    </label>

    <label class="block space-y-1.5">
      <span class="text-sm font-semibold text-brand-text">{{
        t('seasons.fields.endsOn')
      }}</span>
      <AppDateInput
        :model-value="form.operational_ends_on"
        :disabled="locked || submitting"
        :invalid="Boolean(fieldMessage('operational_ends_on'))"
        @update:model-value="patch({ operational_ends_on: $event })"
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
  </AppFormDrawer>
</template>
