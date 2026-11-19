<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

import AppFormDrawer from '@/shared/components/AppFormDrawer.vue'
import AppPhoneInput from '@/shared/components/AppPhoneInput.vue'
import { useCaptureOnOpen, useFormDirty } from '@/shared/composables/useFormDirty'
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
    :cancel-label="t('pilgrims.cancel')"
    :submit-label="primaryLabel"
    @update:open="onOpenUpdate"
    @close="emit('close')"
    @submit="emit('submit')"
  >
    <p
      v-if="formError"
      class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
    >
      {{ formError }}
    </p>

    <label class="app-field">
      <span class="app-field__label">{{ t('pilgrims.fields.fullName') }}</span>
      <input
        :value="form.full_name"
        type="text"
        maxlength="255"
        class="app-field__control app-field__control--input"
        :disabled="submitting"
        @input="patch({ full_name: ($event.target as HTMLInputElement).value })"
      />
      <span v-if="fieldMessage('full_name')" class="text-xs text-red-600">{{
        fieldMessage('full_name')
      }}</span>
    </label>

    <label class="app-field">
      <span class="app-field__label">{{ t('pilgrims.fields.gender') }}</span>
      <AppSelect
        :model-value="form.gender"
        :options="genderOptions"
        :disabled="submitting"
        @update:model-value="
          patch({ gender: ($event === '' ? '' : String($event)) as PilgrimFormState['gender'] })
        "
      />
      <span v-if="fieldMessage('gender')" class="text-xs text-red-600">{{
        fieldMessage('gender')
      }}</span>
    </label>

    <label class="app-field">
      <span class="app-field__label">{{ t('pilgrims.fields.nationality') }}</span>
      <input
        :value="form.nationality"
        type="text"
        maxlength="3"
        dir="ltr"
        class="app-field__control app-field__control--input uppercase"
        :disabled="submitting"
        @input="patch({ nationality: ($event.target as HTMLInputElement).value })"
      />
      <span class="text-xs text-brand-text-muted">{{ t('pilgrims.fields.nationalityHint') }}</span>
      <span v-if="fieldMessage('nationality')" class="block text-xs text-red-600">{{
        fieldMessage('nationality')
      }}</span>
    </label>

    <label class="app-field">
      <span class="app-field__label">{{ t('pilgrims.fields.phone') }}</span>
      <AppPhoneInput
        :model-value="form.phone"
        :disabled="submitting"
        :invalid="Boolean(fieldMessage('phone'))"
        @update:model-value="patch({ phone: $event })"
      />
      <span v-if="fieldMessage('phone')" class="text-xs text-red-600">{{
        fieldMessage('phone')
      }}</span>
    </label>

    <label class="app-field">
      <span class="app-field__label">{{ t('pilgrims.fields.notes') }}</span>
      <textarea
        :value="form.notes"
        rows="4"
        maxlength="5000"
        class="app-field__control app-field__control--textarea"
        :disabled="submitting"
        @input="patch({ notes: ($event.target as HTMLTextAreaElement).value })"
      />
      <span v-if="fieldMessage('notes')" class="text-xs text-red-600">{{
        fieldMessage('notes')
      }}</span>
    </label>
  </AppFormDrawer>
</template>
