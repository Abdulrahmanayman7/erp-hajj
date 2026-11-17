<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

import AppFormDrawer from '@/shared/components/AppFormDrawer.vue'
import { useCaptureOnOpen, useFormDirty } from '@/shared/composables/useFormDirty'

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

const isEdit = computed(() => props.editing != null)
const title = computed(() => (isEdit.value ? t('roles.editTitle') : t('roles.createTitle')))
const subtitle = computed(() =>
  isEdit.value ? t('roles.editSubtitle') : t('roles.createSubtitle'),
)
const primaryLabel = computed(() => (isEdit.value ? t('roles.editCta') : t('roles.createCta')))

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
    :cancel-label="t('roles.cancel')"
    :submit-label="primaryLabel"
    form-id="role-form-drawer"
    @update:open="onOpenUpdate"
    @close="emit('close')"
    @submit="emit('submit')"
  >
    <label class="block">
      <span class="mb-2 block text-sm font-semibold text-brand-text">
        {{ t('roles.fields.name') }}
      </span>
      <input
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
  </AppFormDrawer>
</template>
