<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

import AppFormDrawer from '@/shared/components/AppFormDrawer.vue'
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

    <label class="block space-y-1.5">
      <span class="text-sm font-semibold text-brand-text">{{
        t('pilgrims.fields.fullName')
      }}</span>
      <input
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
  </AppFormDrawer>
</template>
