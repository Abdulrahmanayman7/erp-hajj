<script setup lang="ts">
import { computed, nextTick, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Loader2, Upload, X } from 'lucide-vue-next'

import { listContracts } from '@/modules/contracts/api/contractsApi'
import { listDecisions } from '@/modules/decisions/api/decisionsApi'
import { listEmployees } from '@/modules/employees/api/employeesApi'
import { listMeetings } from '@/modules/meetings/api/meetingsApi'
import { useOrganizationUnitsFlatQuery } from '@/modules/organization/queries/useOrganizationUnitsQuery'
import { listTasks } from '@/modules/tasks/api/tasksApi'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
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
const fileInputRef = ref<HTMLInputElement | null>(null)
let previouslyFocused: HTMLElement | null = null

const acceptAttr = computed(() => DOCUMENT_ALLOWED_EXTENSIONS.map((ext) => `.${ext}`).join(','))
const sizeHint = computed(() =>
  t('documents.upload.sizeHint', { size: formatDocumentSize(DOCUMENT_MAX_SIZE_BYTES) }),
)
const typeHint = computed(() =>
  t('documents.upload.typeHint', { types: DOCUMENT_ALLOWED_EXTENSIONS.join(', ') }),
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

function patch(partial: Partial<DocumentUploadFormState>): void {
  emit('update:form', { ...props.form, ...partial })
}

function onFileChange(event: Event): void {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0] ?? null
  patch({ file, title: props.form.title || (file ? file.name.replace(/\.[^.]+$/, '') : '') })
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
      return
    }
    document.removeEventListener('keydown', onKeydown)
    if (fileInputRef.value) {
      fileInputRef.value.value = ''
    }
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
    <Transition name="doc-drawer">
      <div v-if="open" class="doc-drawer-root fixed inset-0 z-50" role="presentation">
        <div
          class="doc-drawer-backdrop absolute inset-0 bg-[rgba(15,23,20,0.32)]"
          aria-hidden="true"
          @click="emit('close')"
        />

        <aside
          class="doc-drawer-panel absolute inset-y-0 start-0 flex h-dvh w-full max-w-[520px] flex-col bg-brand-surface shadow-[-12px_0_40px_-24px_rgba(23,32,29,0.35)]"
          role="dialog"
          aria-modal="true"
          :aria-label="t('documents.upload.title')"
          @click.stop
        >
          <header class="flex items-start justify-between gap-3 border-b border-brand-border px-5 py-4">
            <div>
              <h2 class="text-lg font-bold text-brand-text">{{ t('documents.upload.title') }}</h2>
              <p class="mt-1 text-sm text-brand-text-secondary">{{ t('documents.upload.subtitle') }}</p>
            </div>
            <button
              type="button"
              class="rounded-lg p-2 text-brand-text-muted hover:bg-brand-bg"
              :aria-label="t('documents.closeDrawer')"
              :disabled="submitting"
              @click="emit('close')"
            >
              <X class="h-5 w-5" />
            </button>
          </header>

          <div class="flex-1 space-y-6 overflow-y-auto px-5 py-4">
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
              <label class="block space-y-1.5">
                <span class="text-sm font-medium">{{ t('documents.fields.title') }}</span>
                <input
                  :value="form.title"
                  type="text"
                  class="h-11 w-full rounded-xl border border-brand-border px-3"
                  :placeholder="t('documents.upload.titlePlaceholder')"
                  :disabled="submitting"
                  @input="patch({ title: ($event.target as HTMLInputElement).value })"
                />
              </label>
              <label class="block space-y-1.5">
                <span class="text-sm font-medium">{{ t('documents.fields.description') }}</span>
                <textarea
                  :value="form.description"
                  rows="3"
                  class="w-full rounded-xl border border-brand-border px-3 py-2"
                  :disabled="submitting"
                  @input="patch({ description: ($event.target as HTMLTextAreaElement).value })"
                />
              </label>
              <label class="block space-y-1.5">
                <span class="text-sm font-medium">{{ t('documents.fields.category') }}</span>
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
                <label class="block space-y-1.5">
                  <span class="text-sm font-medium">{{ t('documents.fields.linkableType') }}</span>
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
                <label v-if="form.linkable_type" class="block space-y-1.5">
                  <span class="text-sm font-medium">{{ t('documents.fields.linkableId') }}</span>
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
          </div>

          <footer class="flex items-center justify-end gap-2 border-t border-brand-border px-5 py-4">
            <button
              type="button"
              class="rounded-xl px-4 py-2 text-sm font-semibold text-brand-text-secondary"
              :disabled="submitting"
              @click="emit('close')"
            >
              {{ t('documents.cancel') }}
            </button>
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-xl bg-brand-primary-dark px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
              :disabled="submitting"
              @click="emit('submit')"
            >
              <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
              <Upload v-else class="h-4 w-4" />
              {{ submitting ? t('documents.uploading') : t('documents.upload.cta') }}
            </button>
          </footer>
        </aside>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.doc-drawer-enter-active,
.doc-drawer-leave-active {
  transition: opacity 0.2s ease;
}
.doc-drawer-enter-active .doc-drawer-panel,
.doc-drawer-leave-active .doc-drawer-panel {
  transition: transform 0.25s ease;
}
.doc-drawer-enter-from,
.doc-drawer-leave-to {
  opacity: 0;
}
.doc-drawer-enter-from .doc-drawer-panel,
.doc-drawer-leave-to .doc-drawer-panel {
  transform: translateX(100%);
}
[dir='ltr'] .doc-drawer-enter-from .doc-drawer-panel,
[dir='ltr'] .doc-drawer-leave-to .doc-drawer-panel {
  transform: translateX(-100%);
}
</style>
