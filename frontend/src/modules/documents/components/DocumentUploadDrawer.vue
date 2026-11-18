<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { Loader2, Upload } from 'lucide-vue-next'

import { listContracts } from '@/modules/contracts/api/contractsApi'
import { listDecisions } from '@/modules/decisions/api/decisionsApi'
import { listEmployees } from '@/modules/employees/api/employeesApi'
import { listMeetings } from '@/modules/meetings/api/meetingsApi'
import { useOrganizationUnitsFlatQuery } from '@/modules/organization/queries/useOrganizationUnitsQuery'
import { listTasks } from '@/modules/tasks/api/tasksApi'
import AppFormDrawer from '@/shared/components/AppFormDrawer.vue'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { useCaptureOnOpen, useFormDirty } from '@/shared/composables/useFormDirty'
import {
  employeeSelectOption,
  numberedEntityOption,
  toSelectId,
} from '@/shared/lookups/selectOptions'

import { useDocumentCategoriesQuery } from '../queries/useCategoriesQuery'
import type { DocumentUploadFormState, LockedDocumentLink } from '../types/documents'
import { DOCUMENT_LINKABLE_TYPES } from '../types/documents'
import {
  DOCUMENT_ALLOWED_EXTENSIONS,
  DOCUMENT_MAX_SIZE_BYTES,
  formatDocumentSize,
} from '../validation/documentValidation'

const props = defineProps<{
  open: boolean
  form: DocumentUploadFormState
  formError: string
  fieldErrors: Record<string, string>
  submitting: boolean
  lockedLink?: LockedDocumentLink | null
}>()

const emit = defineEmits<{
  close: []
  submit: []
  'update:form': [DocumentUploadFormState]
}>()

const { t } = useI18n()
const drawerRef = ref<InstanceType<typeof AppFormDrawer> | null>(null)
const fileInputRef = ref<HTMLInputElement | null>(null)

const acceptAttr = computed(() => DOCUMENT_ALLOWED_EXTENSIONS.map((ext) => `.${ext}`).join(','))
const sizeHint = computed(() =>
  t('documents.upload.sizeHint', { size: formatDocumentSize(DOCUMENT_MAX_SIZE_BYTES) }),
)
const typeHint = computed(() =>
  t('documents.upload.typeHint', { types: DOCUMENT_ALLOWED_EXTENSIONS.join(', ') }),
)

const submitLabel = computed(() =>
  props.submitting ? t('documents.uploading') : t('documents.upload.cta'),
)

const { data: categoriesData } = useDocumentCategoriesQuery(
  { active: true },
  { enabled: computed(() => props.open) },
)

const categoryOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('documents.noCategory') },
  ...(categoriesData.value ?? []).map((c) => ({ value: c.id, label: c.name })),
])

const linkTypeOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('documents.link.standalone') },
  ...DOCUMENT_LINKABLE_TYPES.map((type) => ({
    value: type,
    label: t(`documents.link.types.${type}`),
  })),
])

const linkLocked = computed(() => props.lockedLink != null)

const { data: orgUnitsData } = useOrganizationUnitsFlatQuery({ status: 'active' })
const orgUnitOptions = computed<AppSelectOption[]>(() =>
  (orgUnitsData.value?.data ?? []).map((x) => ({
    value: x.id,
    label: x.name,
    hint: x.code,
  })),
)

const fetchContracts = (params: { search?: string; page: number; per_page: number }) =>
  listContracts(params)
const fetchMeetings = (params: { search?: string; page: number; per_page: number }) =>
  listMeetings(params)
const fetchDecisions = (params: { search?: string; page: number; per_page: number }) =>
  listDecisions(params)
const fetchTasks = (params: { search?: string; page: number; per_page: number }) =>
  listTasks(params)
const fetchActiveEmployees = (params: { search?: string; page: number; per_page: number }) =>
  listEmployees({ ...params, status: 'active' })

const { dirty, capture, reset: resetDirty } = useFormDirty(() => props.form)

useCaptureOnOpen(
  () => props.open,
  capture,
  {
    onClose: () => {
      resetDirty()
      if (fileInputRef.value) {
        fileInputRef.value.value = ''
      }
    },
  },
)

function patch(partial: Partial<DocumentUploadFormState>): void {
  emit('update:form', { ...props.form, ...partial })
}

function onFileChange(event: Event): void {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0] ?? null
  patch({ file, title: props.form.title || (file ? file.name.replace(/\.[^.]+$/, '') : '') })
}

function onOpenUpdate(value: boolean): void {
  if (!value) emit('close')
}

function requestDismiss(): void {
  void drawerRef.value?.requestClose()
}
</script>

<template>
  <AppFormDrawer
    ref="drawerRef"
    :open="open"
    :title="t('documents.upload.title')"
    :description="t('documents.upload.subtitle')"
    size="md"
    :dirty="dirty"
    :submitting="submitting"
    hide-default-footer
    @update:open="onOpenUpdate"
    @close="emit('close')"
    @submit="emit('submit')"
  >
    <section class="space-y-3">
      <h3 class="text-sm font-bold text-brand-text">{{ t('documents.upload.fileSection') }}</h3>
      <input
        ref="fileInputRef"
        type="file"
        class="block w-full text-sm text-brand-text-secondary file:me-3 file:rounded-lg file:border-0 file:bg-brand-primary-soft file:px-3 file:py-2 file:text-sm file:font-semibold file:text-brand-primary-dark"
        :accept="acceptAttr"
        :disabled="submitting"
        @change="onFileChange"
      />
      <p v-if="form.file" class="text-sm text-brand-text">
        {{ form.file.name }} · {{ formatDocumentSize(form.file.size) }}
      </p>
      <p class="text-xs text-brand-text-muted">{{ sizeHint }}</p>
      <p class="text-xs text-brand-text-muted">{{ typeHint }}</p>
      <p v-if="fieldErrors.file" class="text-sm text-red-700">{{ fieldErrors.file }}</p>
    </section>

    <section class="space-y-3">
      <h3 class="text-sm font-bold text-brand-text">{{ t('documents.upload.metaSection') }}</h3>
      <label class="app-field">
        <span class="app-field__label">{{ t('documents.fields.title') }}</span>
        <input
          :value="form.title"
          type="text"
          class="app-field__control app-field__control--input"
          :placeholder="t('documents.upload.titlePlaceholder')"
          :disabled="submitting"
          @input="patch({ title: ($event.target as HTMLInputElement).value })"
        />
      </label>
      <label class="app-field">
        <span class="app-field__label">{{ t('documents.fields.description') }}</span>
        <textarea
          :value="form.description"
          rows="3"
          class="app-field__control app-field__control--textarea"
          :disabled="submitting"
          @input="patch({ description: ($event.target as HTMLTextAreaElement).value })"
        />
      </label>
      <label class="app-field">
        <span class="app-field__label">{{ t('documents.fields.category') }}</span>
        <AppSelect
          :model-value="form.category_id"
          :options="categoryOptions"
          searchable
          :disabled="submitting"
          @update:model-value="patch({ category_id: $event === '' || $event == null ? '' : Number($event) })"
        />
      </label>
    </section>

    <section class="space-y-3">
      <h3 class="text-sm font-bold text-brand-text">{{ t('documents.upload.linkSection') }}</h3>
      <template v-if="linkLocked && lockedLink">
        <p class="rounded-xl border border-brand-border bg-brand-bg px-3 py-2 text-sm">
          {{ t(`documents.link.types.${lockedLink.type}`) }}
          <span class="text-brand-text-muted"> · </span>
          {{ lockedLink.label ?? `#${lockedLink.id}` }}
        </p>
      </template>
      <template v-else>
        <label class="app-field">
          <span class="app-field__label">{{ t('documents.fields.linkableType') }}</span>
          <AppSelect
            :model-value="form.linkable_type"
            :options="linkTypeOptions"
            :disabled="submitting"
            @update:model-value="
              patch({
                linkable_type: ($event || '') as DocumentUploadFormState['linkable_type'],
                linkable_id: '',
              })
            "
          />
        </label>
        <label v-if="form.linkable_type" class="app-field">
          <span class="app-field__label">{{ t('documents.fields.linkableId') }}</span>
          <AppRemoteSelect
            v-if="form.linkable_type === 'contract'"
            :model-value="form.linkable_id"
            query-key="document-link-contracts"
            :fetcher="fetchContracts"
            :map-option="numberedEntityOption"
            :enabled="open"
            :disabled="submitting"
            @update:model-value="patch({ linkable_id: toSelectId($event) })"
          />
          <AppRemoteSelect
            v-else-if="form.linkable_type === 'meeting'"
            :model-value="form.linkable_id"
            query-key="document-link-meetings"
            :fetcher="fetchMeetings"
            :map-option="numberedEntityOption"
            :enabled="open"
            :disabled="submitting"
            @update:model-value="patch({ linkable_id: toSelectId($event) })"
          />
          <AppRemoteSelect
            v-else-if="form.linkable_type === 'decision'"
            :model-value="form.linkable_id"
            query-key="document-link-decisions"
            :fetcher="fetchDecisions"
            :map-option="numberedEntityOption"
            :enabled="open"
            :disabled="submitting"
            @update:model-value="patch({ linkable_id: toSelectId($event) })"
          />
          <AppRemoteSelect
            v-else-if="form.linkable_type === 'task'"
            :model-value="form.linkable_id"
            query-key="document-link-tasks"
            :fetcher="fetchTasks"
            :map-option="numberedEntityOption"
            :enabled="open"
            :disabled="submitting"
            @update:model-value="patch({ linkable_id: toSelectId($event) })"
          />
          <AppRemoteSelect
            v-else-if="form.linkable_type === 'employee'"
            :model-value="form.linkable_id"
            query-key="employees-active"
            :fetcher="fetchActiveEmployees"
            :map-option="employeeSelectOption"
            :enabled="open"
            :disabled="submitting"
            @update:model-value="patch({ linkable_id: toSelectId($event) })"
          />
          <AppSelect
            v-else-if="form.linkable_type === 'organization_unit'"
            :model-value="form.linkable_id"
            :options="orgUnitOptions"
            searchable
            :disabled="submitting"
            @update:model-value="patch({ linkable_id: toSelectId($event) })"
          />
          <p v-if="fieldErrors.linkable_id" class="text-sm text-red-700">
            {{ fieldErrors.linkable_id }}
          </p>
        </label>
      </template>
    </section>

    <p v-if="formError" class="rounded-xl bg-red-50 px-3 py-2 text-sm text-red-800">
      {{ formError }}
    </p>

    <template #footer>
      <button
        type="button"
        class="inline-flex h-11 items-center justify-center rounded-xl px-4 text-sm font-semibold text-brand-text-secondary disabled:opacity-50 sm:min-w-[7.5rem]"
        :disabled="submitting"
        @click="requestDismiss"
      >
        {{ t('documents.cancel') }}
      </button>
      <button
        type="submit"
        class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white disabled:opacity-60 sm:min-w-[7.5rem]"
        :disabled="submitting"
      >
        <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
        <Upload v-else class="h-4 w-4" />
        {{ submitLabel }}
      </button>
    </template>
  </AppFormDrawer>
</template>