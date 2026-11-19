<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

import { listUsers } from '@/modules/users/api/usersApi'
import AppFormDrawer from '@/shared/components/AppFormDrawer.vue'
import { useCaptureOnOpen, useFormDirty } from '@/shared/composables/useFormDirty'
import AppNumberInput from '@/shared/components/AppNumberInput.vue'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { toSelectNullableId, userSelectOption } from '@/shared/lookups/selectOptions'

import type { OrganizationUnit, OrganizationUnitType } from '../types/organization'

export interface OrganizationUnitFormState {
  name: string
  code: string
  type: OrganizationUnitType | ''
  parent_id: number | null
  manager_user_id: number | null
  sort_order: number
}

const props = defineProps<{
  open: boolean
  editing: OrganizationUnit | null
  form: OrganizationUnitFormState
  formError: string
  submitting: boolean
  parentOptions: AppSelectOption[]
}>()

const emit = defineEmits<{
  close: []
  submit: []
  'update:form': [OrganizationUnitFormState]
}>()

const { t } = useI18n()

const isEdit = computed(() => props.editing != null)
const fetchActiveUsers = (params: { search?: string; page: number; per_page: number }) =>
  listUsers({ ...params, status: 'active' })
const emptyManager = computed(() => ({ value: '', label: t('organization.noManager') }))
const selectedManager = computed(() =>
  props.editing?.manager ? userSelectOption(props.editing.manager) : null,
)
const title = computed(() =>
  isEdit.value ? t('organization.editTitle') : t('organization.createTitle'),
)
const subtitle = computed(() =>
  isEdit.value ? t('organization.editSubtitle') : t('organization.createSubtitle'),
)
const primaryLabel = computed(() =>
  isEdit.value ? t('organization.save') : t('organization.createCta'),
)

const typeOptions = computed<AppSelectOption[]>(() => [
  { value: 'department', label: t('organization.types.department') },
  { value: 'section', label: t('organization.types.section') },
  { value: 'unit', label: t('organization.types.unit') },
])

function patch(partial: Partial<OrganizationUnitFormState>): void {
  emit('update:form', { ...props.form, ...partial })
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
    :cancel-label="t('organization.cancel')"
    :submit-label="primaryLabel"
    @update:open="onOpenUpdate"
    @close="emit('close')"
    @submit="emit('submit')"
  >
    <p v-if="formError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">
      {{ formError }}
    </p>

    <label class="app-field">
      <span class="app-field__label">{{ t('organization.fields.name') }}</span>
      <input
        :value="form.name"
        type="text"
        class="app-field__control app-field__control--input"
        @input="patch({ name: ($event.target as HTMLInputElement).value })"
      />
    </label>

    <label class="app-field">
      <span class="app-field__label">{{ t('organization.fields.code') }}</span>
      <input
        :value="form.code"
        type="text"
        class="app-field__control app-field__control--input font-mono uppercase"
        :disabled="isEdit"
        :readonly="isEdit"
        @input="
          patch({
            code: ($event.target as HTMLInputElement).value.toUpperCase(),
          })
        "
      />
      <span v-if="isEdit" class="mt-1 block text-xs text-brand-muted">{{
        t('organization.codeImmutable')
      }}</span>
    </label>

    <div class="app-field">
      <span class="app-field__label">{{ t('organization.fields.type') }}</span>
      <AppSelect
        :model-value="form.type || null"
        :options="typeOptions"
        :placeholder="t('organization.selectType')"
        @update:model-value="patch({ type: ($event as OrganizationUnitType) || '' })"
      />
    </div>

    <div v-if="!isEdit" class="app-field">
      <span class="app-field__label">{{ t('organization.fields.parent') }}</span>
      <AppSelect
        :model-value="form.parent_id"
        :options="parentOptions"
        searchable
        :placeholder="t('organization.rootParent')"
        @update:model-value="
          patch({
            parent_id: $event === null || $event === '' ? null : Number($event),
          })
        "
      />
    </div>

    <div class="app-field">
      <span class="app-field__label">{{ t('organization.fields.manager') }}</span>
      <AppRemoteSelect
        :model-value="form.manager_user_id"
        query-key="users-active"
        :fetcher="fetchActiveUsers"
        :map-option="userSelectOption"
        :empty-option="emptyManager"
        :selected-option="selectedManager"
        :placeholder="t('organization.noManager')"
        :enabled="open"
        @update:model-value="patch({ manager_user_id: toSelectNullableId($event) })"
      />
      <span class="mt-1 block text-xs text-brand-muted">{{
        t('organization.managerHint')
      }}</span>
    </div>

    <label class="app-field">
      <span class="app-field__label">{{ t('organization.fields.sortOrder') }}</span>
      <AppNumberInput
        :model-value="form.sort_order"
        integer
        min="0"
        class="app-field__control app-field__control--input"
        @update:model-value="
          patch({
            sort_order: $event === '' ? 0 : Number($event),
          })
        "
      />
    </label>
  </AppFormDrawer>
</template>
