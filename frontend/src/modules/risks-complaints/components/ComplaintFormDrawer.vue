<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

import AppFormDrawer from '@/shared/components/AppFormDrawer.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'

import type { ComplaintCategory, ComplaintFormState, OperationalComplaint } from '../types/complaints'
import { COMPLAINT_CATEGORIES } from '../types/complaints'
import OperationalLinksFields from './OperationalLinksFields.vue'

const props = defineProps<{
  open: boolean
  editing: OperationalComplaint | null
  form: ComplaintFormState
  formError: string
  fieldErrors: Record<string, string>
  submitting: boolean
}>()

const emit = defineEmits<{
  close: []
  submit: []
  'update:form': [ComplaintFormState]
}>()

const { t } = useI18n()
const isEdit = computed(() => props.editing != null)
const patch = (part: Partial<ComplaintFormState>) => emit('update:form', { ...props.form, ...part })

const categoryOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('complaints.fields.selectCategory') },
  ...COMPLAINT_CATEGORIES.map((c) => ({ value: c, label: t(`complaints.category.${c}`) })),
])

function onOpenUpdate(value: boolean): void {
  if (!value) emit('close')
}
</script>

<template>
  <AppFormDrawer
    :open="open"
    :title="t(isEdit ? 'complaints.editTitle' : 'complaints.createTitle')"
    :description="t(isEdit ? 'complaints.editSubtitle' : 'complaints.createSubtitle')"
    size="md"
    :submitting="submitting"
    :cancel-label="t('complaints.cancel')"
    :submit-label="t(isEdit ? 'complaints.save' : 'complaints.createCta')"
    @update:open="onOpenUpdate"
    @close="emit('close')"
    @submit="emit('submit')"
  >
    <label class="block">
      <span class="mb-1.5 block text-sm font-semibold">{{ t('complaints.fields.title') }}</span>
      <input
        :value="form.title"
        required
        class="h-11 w-full rounded-xl border border-brand-border px-3 text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
        data-testid="complaint-title-input"
        @input="patch({ title: ($event.target as HTMLInputElement).value })"
      />
      <p v-if="fieldErrors.title" class="mt-1 text-xs text-red-600">
        {{ t(`complaints.validation.${fieldErrors.title}`) }}
      </p>
    </label>

    <label class="block">
      <span class="mb-1.5 block text-sm font-semibold">{{ t('complaints.fields.category') }}</span>
      <AppSelect
        :model-value="form.category_code"
        :options="categoryOptions"
        @update:model-value="
          patch({ category_code: String($event || '') as ComplaintCategory | '' })
        "
      />
      <p v-if="fieldErrors.category_code" class="mt-1 text-xs text-red-600">
        {{ t(`complaints.validation.${fieldErrors.category_code}`) }}
      </p>
    </label>

    <label class="block">
      <span class="mb-1.5 block text-sm font-semibold">{{ t('complaints.fields.body') }}</span>
      <textarea
        :value="form.body"
        required
        rows="5"
        class="w-full rounded-xl border border-brand-border p-3 text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
        data-testid="complaint-body-input"
        @input="patch({ body: ($event.target as HTMLTextAreaElement).value })"
      />
      <p v-if="fieldErrors.body" class="mt-1 text-xs text-red-600">
        {{ t(`complaints.validation.${fieldErrors.body}`) }}
      </p>
    </label>

    <OperationalLinksFields
      :model-value="form"
      :disabled="submitting"
      @update:model-value="emit('update:form', { ...form, ...$event })"
    />

    <p
      v-if="formError"
      class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
      role="alert"
    >
      {{ formError }}
    </p>
  </AppFormDrawer>
</template>
