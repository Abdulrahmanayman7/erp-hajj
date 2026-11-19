<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

import AppFormDrawer from '@/shared/components/AppFormDrawer.vue'
import { useCaptureOnOpen, useFormDirty } from '@/shared/composables/useFormDirty'
import type { OperationalRisk, RiskFormState } from '../types/risks'
import OperationalLinksFields from './OperationalLinksFields.vue'

const props = defineProps<{
  open: boolean
  editing: OperationalRisk | null
  form: RiskFormState
  formError: string
  fieldErrors: Record<string, string>
  submitting: boolean
}>()

const emit = defineEmits<{
  close: []
  submit: []
  'update:form': [RiskFormState]
}>()

const { t } = useI18n()
const isEdit = computed(() => props.editing != null)
const patch = (part: Partial<RiskFormState>) => emit('update:form', { ...props.form, ...part })

const { dirty, capture, reset: resetDirty } = useFormDirty(() => props.form)
useCaptureOnOpen(() => props.open, capture, { onClose: () => resetDirty() })

function onOpenUpdate(value: boolean): void {
  if (!value) emit('close')
}
</script>

<template>
  <AppFormDrawer
    :open="open"
    :title="t(isEdit ? 'risks.editTitle' : 'risks.createTitle')"
    :description="t(isEdit ? 'risks.editSubtitle' : 'risks.createSubtitle')"
    size="md"
    :dirty="dirty"
    :submitting="submitting"
    :cancel-label="t('risks.cancel')"
    :submit-label="t(isEdit ? 'risks.save' : 'risks.createCta')"
    @update:open="onOpenUpdate"
    @close="emit('close')"
    @submit="emit('submit')"
  >
    <label class="app-field">
      <span class="app-field__label">{{ t('risks.fields.title') }}</span>
      <input
        :value="form.title"
        required
        class="app-field__control app-field__control--input"
        data-testid="risk-title-input"
        @input="patch({ title: ($event.target as HTMLInputElement).value })"
      />
      <p v-if="fieldErrors.title" class="text-xs text-red-600">
        {{ t(`risks.validation.${fieldErrors.title}`) }}
      </p>
    </label>

    <label class="app-field">
      <span class="app-field__label">{{ t('risks.fields.description') }}</span>
      <textarea
        :value="form.description"
        rows="4"
        class="app-field__control app-field__control--textarea"
        @input="patch({ description: ($event.target as HTMLTextAreaElement).value })"
      />
    </label>

    <OperationalLinksFields
      :model-value="form"
      :disabled="submitting"
      @update:model-value="emit('update:form', { ...form, ...$event })"
    />

    <p v-if="formError" class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">
      {{ formError }}
    </p>
  </AppFormDrawer>
</template>
