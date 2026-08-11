<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRouter } from 'vue-router'
import {
  Archive,
  Download,
  Eye,
  FolderTree,
  RotateCcw,
  Trash2,
  Upload,
} from 'lucide-vue-next'

import { useUsersQuery } from '@/modules/users/queries/useUsersQuery'
import { ApiError } from '@/shared/api/http'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'

import DocumentCategoriesManagerDrawer from '../components/DocumentCategoriesManagerDrawer.vue'
import DocumentUploadDrawer from '../components/DocumentUploadDrawer.vue'
import { downloadDocument, triggerBrowserDownload } from '../api/documentsApi'
import {
  useArchiveDocumentMutation,
  useDeleteDocumentMutation,
  useRestoreDocumentMutation,
  useUploadDocumentMutation,
} from '../mutations/useDocumentMutations'
import { useDocumentCategoriesQuery } from '../queries/useCategoriesQuery'
import { useDocumentsQuery } from '../queries/useDocumentsQuery'
import type {
  Document,
  DocumentLinkableType,
  DocumentStatus,
  DocumentUploadFormState,
  ListDocumentsParams,
} from '../types/documents'
import { DOCUMENT_LINKABLE_TYPES } from '../types/documents'
import {
  canShowDocumentAction,
  documentStatusBadgeClass,
  formatDocumentSize,
  mapDocumentErrorCode,
  resolveDocumentsListState,
  validateDocumentUploadForm,
} from '../validation/documentValidation'

const { t } = useI18n()
const router = useRouter()
const { can } = usePermissions()
const toast = useToast()
const { confirm } = useConfirm()

const filters = reactive({
  search: '',
  status: 'active' as DocumentStatus | 'all',
  category_id: '' as number | '',
  uploaded_by: '' as number | '',
  linkable_type: '' as DocumentLinkableType | '',
  linkable_id: '' as number | '',
  uploaded_from: '',
  uploaded_to: '',
  page: 1,
  per_page: 15,
})

const params = computed<ListDocumentsParams>(() => ({
  search: filters.search || undefined,
  status: filters.status,
  category_id: filters.category_id,
  uploaded_by: filters.uploaded_by,
  linkable_type: filters.linkable_type,
  linkable_id: filters.linkable_id,
  uploaded_from: filters.uploaded_from || undefined,
  uploaded_to: filters.uploaded_to || undefined,
  page: filters.page,
  per_page: filters.per_page,
}))

const { data, isLoading, isError, refetch } = useDocumentsQuery(params)
const documents = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta)
const listState = computed(() =>
  resolveDocumentsListState({
    isLoading: isLoading.value,
    isError: isError.value,
    count: documents.value.length,
  }),
)

const { data: categoriesData } = useDocumentCategoriesQuery({})
const { data: usersData } = useUsersQuery(computed(() => ({ per_page: 100 })))

const categoryOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('documents.filters.allCategories') },
  ...(categoriesData.value ?? []).map((c) => ({ value: c.id, label: c.name })),
])
const uploaderOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('documents.filters.allUploaders') },
  ...(usersData.value?.data ?? []).map((u) => ({ value: u.id, label: u.name })),
])
const statusOptions = computed<AppSelectOption[]>(() => [
  { value: 'active', label: t('documents.status.active') },
  { value: 'archived', label: t('documents.status.archived') },
  { value: 'all', label: t('documents.filters.allStatuses') },
])
const linkTypeOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('documents.filters.allLinkTypes') },
  ...DOCUMENT_LINKABLE_TYPES.map((type) => ({
    value: type,
    label: t(`documents.link.types.${type}`),
  })),
])

watch(
  () => [
    filters.search,
    filters.status,
    filters.category_id,
    filters.uploaded_by,
    filters.linkable_type,
    filters.linkable_id,
    filters.uploaded_from,
    filters.uploaded_to,
  ],
  () => {
    filters.page = 1
  },
)

const upload = useUploadDocumentMutation()
const archiveMut = useArchiveDocumentMutation()
const restoreMut = useRestoreDocumentMutation()
const deleteMut = useDeleteDocumentMutation()

const uploadOpen = ref(false)
const categoriesOpen = ref(false)
const formError = ref('')
const fieldErrors = reactive<Record<string, string>>({})
const emptyForm = (): DocumentUploadFormState => ({
  file: null,
  title: '',
  description: '',
  category_id: '',
  linkable_type: '',
  linkable_id: '',
})
const form = reactive<DocumentUploadFormState>(emptyForm())

function openUpload(): void {
  Object.assign(form, emptyForm())
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  uploadOpen.value = true
}

function fieldMessage(key: string | undefined): string {
  if (!key) return ''
  return t(`documents.validation.${key}`)
}

function apiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) return t('documents.errors.generic')
  const mapped = mapDocumentErrorCode(error.code)
  if (mapped !== 'generic') return t(`documents.errors.${mapped}`)
  return error.message || t('documents.errors.generic')
}

async function submitUpload(): Promise<void> {
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  const validation = validateDocumentUploadForm(form)
  Object.entries(validation).forEach(([k, v]) => {
    if (v) fieldErrors[k] = fieldMessage(v)
  })
  if (Object.keys(fieldErrors).length || !form.file) return

  try {
    const created = await upload.mutateAsync({
      file: form.file,
      fields: {
        title: form.title.trim() || undefined,
        description: form.description.trim() || null,
        category_id: form.category_id === '' ? null : form.category_id,
        linkable_type: form.linkable_type || null,
        linkable_id: form.linkable_id === '' ? null : form.linkable_id,
      },
    })
    toast.success(t('documents.toasts.uploaded'))
    uploadOpen.value = false
    await router.push(`/app/documents/${created.id}`)
  } catch (error) {
    formError.value = apiMessage(error)
  }
}

async function onDownload(doc: Document): Promise<void> {
  if (!canShowDocumentAction('download', can)) return
  try {
    const result = await downloadDocument(doc.id)
    triggerBrowserDownload(result.blob, result.filename || doc.original_filename)
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

async function onArchive(doc: Document): Promise<void> {
  if (!canShowDocumentAction('archive', can, doc)) return
  const ok = await confirm({
    title: t('documents.confirm.archive.title'),
    message: t('documents.confirm.archive.body'),
    confirmLabel: t('documents.actions.archive'),
    variant: 'warning',
  })
  if (!ok) return
  try {
    await archiveMut.mutateAsync({ id: doc.id })
    toast.success(t('documents.toasts.archived'))
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

async function onRestore(doc: Document): Promise<void> {
  if (!canShowDocumentAction('restore', can, doc)) return
  const ok = await confirm({
    title: t('documents.confirm.restore.title'),
    message: t('documents.confirm.restore.body'),
    confirmLabel: t('documents.actions.restore'),
    variant: 'primary',
  })
  if (!ok) return
  try {
    await restoreMut.mutateAsync({ id: doc.id })
    toast.success(t('documents.toasts.restored'))
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

async function onDelete(doc: Document): Promise<void> {
  if (!canShowDocumentAction('delete', can)) return
  const ok = await confirm({
    title: t('documents.confirm.delete.title'),
    message: t('documents.confirm.delete.body'),
    confirmLabel: t('documents.actions.delete'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deleteMut.mutateAsync({ id: doc.id })
    toast.success(t('documents.toasts.deleted'))
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

function linkLabel(doc: Document): string {
  if (!doc.link) return t('documents.link.standalone')
  return doc.link.label || `${t(`documents.link.types.${doc.link.type}`)} #${doc.link.id}`
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <h2 class="text-2xl font-bold">{{ t('documents.title') }}</h2>
        <p class="text-sm text-brand-text-secondary">{{ t('documents.subtitle') }}</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <PermissionGuard permission="documents.manage_categories">
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-xl border border-brand-border px-4 py-2 text-sm font-semibold"
            @click="categoriesOpen = true"
          >
            <FolderTree class="h-4 w-4" />
            {{ t('documents.categoriesLink') }}
          </button>
        </PermissionGuard>
        <PermissionGuard permission="documents.upload">
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-xl bg-brand-primary-dark px-4 py-2 text-sm font-semibold text-white"
            @click="openUpload"
          >
            <Upload class="h-4 w-4" />
            {{ t('documents.upload.cta') }}
          </button>
        </PermissionGuard>
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-brand-border bg-brand-surface p-4">
      <input
        v-model="filters.search"
        class="h-11 min-w-48 flex-1 rounded-xl border border-brand-border px-3"
        :placeholder="t('documents.searchPlaceholder')"
      />
      <AppSelect v-model="filters.status" :options="statusOptions" />
      <AppSelect v-model="filters.category_id" :options="categoryOptions" searchable />
      <AppSelect v-model="filters.uploaded_by" :options="uploaderOptions" searchable />
      <AppSelect v-model="filters.linkable_type" :options="linkTypeOptions" />
      <input
        :value="filters.linkable_id"
        type="number"
        min="1"
        class="h-11 w-28 rounded-xl border border-brand-border px-3"
        :placeholder="t('documents.filters.linkableId')"
        @input="
          filters.linkable_id =
            ($event.target as HTMLInputElement).value === ''
              ? ''
              : Number(($event.target as HTMLInputElement).value)
        "
      />
      <input
        v-model="filters.uploaded_from"
        type="date"
        class="h-11 rounded-xl border border-brand-border px-3"
        :aria-label="t('documents.filters.uploadedFrom')"
      />
      <input
        v-model="filters.uploaded_to"
        type="date"
        class="h-11 rounded-xl border border-brand-border px-3"
        :aria-label="t('documents.filters.uploadedTo')"
      />
    </div>

    <div v-if="listState === 'loading'" class="rounded-2xl border p-10 text-center">
      {{ t('documents.loading') }}
    </div>
    <div v-else-if="listState === 'error'" class="rounded-2xl border p-10 text-center">
      <p>{{ t('documents.errors.load') }}</p>
      <button type="button" class="mt-2 underline" @click="() => refetch()">
        {{ t('documents.retry') }}
      </button>
    </div>
    <div v-else-if="listState === 'empty'" class="rounded-2xl border p-10 text-center">
      {{ t('documents.empty') }}
    </div>
    <template v-else>
      <div class="hidden overflow-x-auto rounded-2xl border border-brand-border bg-brand-surface md:block">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="bg-brand-bg">
              <th
                v-for="key in [
                  'number',
                  'title',
                  'file',
                  'category',
                  'link',
                  'uploader',
                  'date',
                  'status',
                  'actions',
                ]"
                :key="key"
                class="px-4 py-3 text-start"
              >
                {{ t(`documents.columns.${key}`) }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="doc in documents" :key="doc.id" class="border-t">
              <td class="px-4 py-3 font-mono">
                <RouterLink :to="`/app/documents/${doc.id}`">{{ doc.document_number }}</RouterLink>
              </td>
              <td class="px-4 py-3">{{ doc.title }}</td>
              <td class="px-4 py-3">
                {{ doc.original_filename }}
                <span class="text-brand-text-muted"> · {{ formatDocumentSize(doc.size_bytes) }}</span>
              </td>
              <td class="px-4 py-3">{{ doc.category?.name ?? '—' }}</td>
              <td class="px-4 py-3">{{ linkLabel(doc) }}</td>
              <td class="px-4 py-3">{{ doc.uploaded_by?.name ?? '—' }}</td>
              <td class="px-4 py-3">{{ doc.created_at ? doc.created_at.slice(0, 10) : '—' }}</td>
              <td class="px-4 py-3">
                <span
                  class="rounded-full px-2 py-1 text-xs"
                  :class="documentStatusBadgeClass(doc.status)"
                >
                  {{ t(`documents.status.${doc.status}`) }}
                </span>
              </td>
              <td class="px-4 py-3">
                <div class="flex flex-wrap items-center gap-1">
                  <RouterLink
                    :to="`/app/documents/${doc.id}`"
                    class="rounded-lg p-1.5 hover:bg-brand-bg"
                    :aria-label="t('documents.actions.view')"
                  >
                    <Eye class="h-4 w-4" />
                  </RouterLink>
                  <button
                    v-if="canShowDocumentAction('download', can)"
                    type="button"
                    class="rounded-lg p-1.5 hover:bg-brand-bg"
                    :aria-label="t('documents.actions.download')"
                    @click="onDownload(doc)"
                  >
                    <Download class="h-4 w-4" />
                  </button>
                  <button
                    v-if="canShowDocumentAction('archive', can, doc)"
                    type="button"
                    class="rounded-lg p-1.5 hover:bg-brand-bg"
                    :aria-label="t('documents.actions.archive')"
                    @click="onArchive(doc)"
                  >
                    <Archive class="h-4 w-4" />
                  </button>
                  <button
                    v-if="canShowDocumentAction('restore', can, doc)"
                    type="button"
                    class="rounded-lg p-1.5 hover:bg-brand-bg"
                    :aria-label="t('documents.actions.restore')"
                    @click="onRestore(doc)"
                  >
                    <RotateCcw class="h-4 w-4" />
                  </button>
                  <button
                    v-if="canShowDocumentAction('delete', can)"
                    type="button"
                    class="rounded-lg p-1.5 text-red-700 hover:bg-red-50"
                    :aria-label="t('documents.actions.delete')"
                    @click="onDelete(doc)"
                  >
                    <Trash2 class="h-4 w-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="space-y-3 md:hidden">
        <div
          v-for="doc in documents"
          :key="doc.id"
          class="rounded-2xl border border-brand-border bg-brand-surface p-4"
        >
          <div class="flex items-center justify-between gap-2">
            <RouterLink :to="`/app/documents/${doc.id}`" class="font-mono text-sm underline">
              {{ doc.document_number }}
            </RouterLink>
            <span
              class="rounded-full px-2 py-1 text-xs"
              :class="documentStatusBadgeClass(doc.status)"
            >
              {{ t(`documents.status.${doc.status}`) }}
            </span>
          </div>
          <p class="mt-2 font-semibold">{{ doc.title }}</p>
          <p class="mt-1 text-sm text-brand-text-secondary">
            {{ doc.original_filename }} · {{ formatDocumentSize(doc.size_bytes) }}
          </p>
          <button
            v-if="canShowDocumentAction('download', can)"
            type="button"
            class="mt-3 inline-flex items-center gap-1 rounded-lg border px-3 py-1.5 text-sm"
            @click="onDownload(doc)"
          >
            <Download class="h-4 w-4" />
            {{ t('documents.actions.download') }}
          </button>
        </div>
      </div>

      <div
        v-if="meta && meta.last_page > 1"
        class="flex items-center justify-between gap-3 text-sm"
      >
        <button
          type="button"
          class="rounded-xl border px-3 py-2 disabled:opacity-40"
          :disabled="filters.page <= 1"
          @click="filters.page -= 1"
        >
          {{ t('documents.prev') }}
        </button>
        <span>{{ filters.page }} / {{ meta.last_page }}</span>
        <button
          type="button"
          class="rounded-xl border px-3 py-2 disabled:opacity-40"
          :disabled="filters.page >= meta.last_page"
          @click="filters.page += 1"
        >
          {{ t('documents.next') }}
        </button>
      </div>
    </template>

    <DocumentUploadDrawer
      v-if="uploadOpen"
      :open="uploadOpen"
      :form="form"
      :form-error="formError"
      :field-errors="fieldErrors"
      :submitting="upload.isPending.value"
      @close="uploadOpen = false"
      @submit="submitUpload"
      @update:form="Object.assign(form, $event)"
    />
    <DocumentCategoriesManagerDrawer
      v-if="categoriesOpen"
      :open="categoriesOpen"
      @close="categoriesOpen = false"
    />
  </div>
</template>
