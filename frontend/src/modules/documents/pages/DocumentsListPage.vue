<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRouter } from 'vue-router'
import {
  Archive,
  ChevronLeft,
  ChevronRight,
  Download,
  Eye,
  FolderTree,
  RotateCcw,
  Search,
  Trash2,
  Upload,
} from 'lucide-vue-next'

import { listUsers } from '@/modules/users/api/usersApi'
import { ApiError } from '@/shared/api/http'
import AppMobileFilters from '@/shared/components/AppMobileFilters.vue'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { toSelectId, userSelectOption } from '@/shared/lookups/selectOptions'
import AppTooltip from '@/shared/components/AppTooltip.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'
import { useDebouncedRef } from '@/shared/composables/useDebouncedRef'

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
  documentStatusDotClass,
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

const committedSearch = useDebouncedRef(() => filters.search)
const params = computed<ListDocumentsParams>(() => ({
  search: committedSearch.value || undefined,
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
const fetchUsers = (params: { search?: string; page: number; per_page: number }) => listUsers(params)
const emptyUploader = computed<AppSelectOption>(() => ({
  value: '',
  label: t('documents.filters.allUploaders'),
}))

const categoryOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('documents.filters.allCategories') },
  ...(categoriesData.value ?? []).map((c) => ({ value: c.id, label: c.name })),
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

const activeFilterCount = computed(() => {
  let count = 0
  if (filters.status !== 'active') count += 1
  if (filters.category_id !== '') count += 1
  if (filters.uploaded_by !== '') count += 1
  if (filters.linkable_type !== '') count += 1
  if (filters.linkable_id !== '') count += 1
  if (filters.uploaded_from) count += 1
  if (filters.uploaded_to) count += 1
  return count
})

function resetFilters(): void {
  filters.status = 'active'
  filters.category_id = ''
  filters.uploaded_by = ''
  filters.linkable_type = ''
  filters.linkable_id = ''
  filters.uploaded_from = ''
  filters.uploaded_to = ''
  filters.search = ''
}

watch(
  () => [
    committedSearch.value,
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
    <AppPageHeader
      :title="t('documents.title')"
      :subtitle="t('documents.subtitle')"
      :meta="meta ? String(meta.total) : undefined"
    >
      <template #actions>
        <PermissionGuard permission="documents.manage_categories">
          <button
            type="button"
            class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-4 text-sm font-semibold text-brand-text transition hover:bg-brand-bg sm:w-auto"
            @click="categoriesOpen = true"
          >
            <FolderTree class="h-4 w-4" :stroke-width="2" />
            <span>{{ t('documents.categoriesLink') }}</span>
          </button>
        </PermissionGuard>
        <PermissionGuard permission="documents.upload">
          <button
            type="button"
            class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white transition hover:bg-brand-primary sm:w-auto"
            @click="openUpload"
          >
            <Upload class="h-4 w-4" :stroke-width="2.25" />
            <span>{{ t('documents.upload.cta') }}</span>
          </button>
        </PermissionGuard>
      </template>
    </AppPageHeader>

    <AppMobileFilters
      v-model:search="filters.search"
      :search-placeholder="t('documents.searchPlaceholder')"
      :active-count="activeFilterCount"
      @reset="resetFilters"
    >
      <template #desktop>
        <div
          class="flex flex-wrap items-center gap-3 rounded-2xl border border-brand-border bg-brand-surface p-4 shadow-[0_1px_2px_rgba(23,32,29,0.03)]"
        >
          <div class="relative min-w-48 flex-1">
            <Search
              class="pointer-events-none absolute inset-s-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-text-muted"
              :stroke-width="1.75"
              aria-hidden="true"
            />
            <input
              v-model="filters.search"
              type="search"
              class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface pe-3 ps-10 text-sm text-brand-text outline-none transition placeholder:text-brand-text-muted focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
              :placeholder="t('documents.searchPlaceholder')"
            />
          </div>
          <AppSelect v-model="filters.status" :options="statusOptions" />
          <AppSelect v-model="filters.category_id" :options="categoryOptions" searchable />
          <AppRemoteSelect
            :model-value="filters.uploaded_by"
            query-key="users"
            :fetcher="fetchUsers"
            :map-option="userSelectOption"
            :empty-option="emptyUploader"
            @update:model-value="filters.uploaded_by = toSelectId($event)"
          />
          <AppSelect v-model="filters.linkable_type" :options="linkTypeOptions" />
          <input
            :value="filters.linkable_id"
            type="number"
            min="1"
            class="h-11 w-28 rounded-xl border border-brand-border bg-brand-surface px-3 text-sm text-brand-text outline-none transition focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
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
            class="h-11 rounded-xl border border-brand-border bg-brand-surface px-3 text-sm text-brand-text outline-none transition focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
            :aria-label="t('documents.filters.uploadedFrom')"
          />
          <input
            v-model="filters.uploaded_to"
            type="date"
            class="h-11 rounded-xl border border-brand-border bg-brand-surface px-3 text-sm text-brand-text outline-none transition focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
            :aria-label="t('documents.filters.uploadedTo')"
          />
        </div>
      </template>
      <template #filters>
        <div class="space-y-3">
          <AppSelect v-model="filters.status" :options="statusOptions" />
          <AppSelect v-model="filters.category_id" :options="categoryOptions" searchable />
          <AppRemoteSelect
            :model-value="filters.uploaded_by"
            query-key="users"
            :fetcher="fetchUsers"
            :map-option="userSelectOption"
            :empty-option="emptyUploader"
            @update:model-value="filters.uploaded_by = toSelectId($event)"
          />
          <AppSelect v-model="filters.linkable_type" :options="linkTypeOptions" />
          <input
            :value="filters.linkable_id"
            type="number"
            min="1"
            class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface px-3 text-sm text-brand-text outline-none transition focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
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
            class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface px-3 text-sm text-brand-text outline-none transition focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
            :aria-label="t('documents.filters.uploadedFrom')"
          />
          <input
            v-model="filters.uploaded_to"
            type="date"
            class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface px-3 text-sm text-brand-text outline-none transition focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
            :aria-label="t('documents.filters.uploadedTo')"
          />
        </div>
      </template>
    </AppMobileFilters>

    <div v-if="listState === 'loading'" class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted">
      {{ t('documents.loading') }}
    </div>
    <div v-else-if="listState === 'error'" class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center">
      <p class="text-sm text-red-700">{{ t('documents.errors.load') }}</p>
      <button type="button" class="mt-3 text-sm font-semibold text-brand-primary-dark underline" @click="() => refetch()">
        {{ t('documents.retry') }}
      </button>
    </div>
    <div v-else-if="listState === 'empty'" class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center">
      <p class="text-sm text-brand-text-muted">{{ t('documents.empty') }}</p>
      <PermissionGuard permission="documents.upload">
        <button type="button" class="mt-4 inline-flex h-10 items-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white" @click="openUpload">
          <Upload class="h-4 w-4" />
          {{ t('documents.upload.cta') }}
        </button>
      </PermissionGuard>
    </div>
    <template v-else>
      <div class="hidden overflow-hidden rounded-2xl border border-brand-border bg-brand-surface shadow-[0_1px_2px_rgba(23,32,29,0.03)] md:block">
        <div class="overflow-x-auto">
        <table class="min-w-full border-separate border-spacing-0 text-sm">
          <thead>
            <tr class="bg-[#F4F6F5]">
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
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text first:border-s-[3px] first:border-s-transparent"
              >
                {{ t(`documents.columns.${key}`) }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(doc, index) in documents" :key="doc.id" class="group" :class="index % 2 === 1 ? 'bg-[#FAFBFA]' : 'bg-brand-surface'">
              <td class="whitespace-nowrap border-b border-s-[3px] border-brand-border/80 border-s-transparent px-5 py-3.5 text-center transition-colors duration-150 group-hover:border-s-brand-primary group-hover:bg-[#EDF6F1]">
                <RouterLink :to="`/app/documents/${doc.id}`" class="inline-flex items-center rounded-lg border border-brand-border bg-brand-bg px-2.5 py-1 font-mono text-[12px] font-bold tracking-wide text-brand-text shadow-[0_1px_0_rgba(23,32,29,0.04)] transition group-hover:border-brand-primary/30 group-hover:bg-brand-surface hover:border-brand-primary/35 hover:bg-brand-primary-soft hover:text-brand-primary" dir="ltr">
                  {{ doc.document_number }}
                </RouterLink>
              </td>
              <td class="max-w-[14rem] whitespace-nowrap border-b border-brand-border/80 px-5 py-3.5 text-center font-semibold text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]">
                <RouterLink :to="`/app/documents/${doc.id}`" class="block truncate text-brand-text transition hover:text-brand-primary-dark hover:underline hover:underline-offset-2" :title="doc.title">{{ doc.title }}</RouterLink>
              </td>
              <td class="max-w-[16rem] whitespace-nowrap border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]">
                <p class="truncate font-medium" :title="`${doc.original_filename} · ${formatDocumentSize(doc.size_bytes)}`">
                  {{ doc.original_filename }}
                  <span class="mx-1 text-brand-text">·</span>
                  {{ formatDocumentSize(doc.size_bytes) }}
                </p>
              </td>
              <td class="whitespace-nowrap border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]">{{ doc.category?.name ?? '—' }}</td>
              <td class="max-w-[12rem] whitespace-nowrap border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]"><span class="block truncate" :title="linkLabel(doc)">{{ linkLabel(doc) }}</span></td>
              <td class="whitespace-nowrap border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]">{{ doc.uploaded_by?.name ?? '—' }}</td>
              <td class="whitespace-nowrap border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]" dir="ltr">{{ doc.created_at ? doc.created_at.slice(0, 10) : '—' }}</td>
              <td class="whitespace-nowrap border-b border-brand-border/80 px-5 py-3.5 text-center transition-colors duration-150 group-hover:bg-[#EDF6F1]">
                <span
                  class="inline-flex min-w-[6.5rem] items-center justify-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-bold tracking-wide text-brand-text shadow-sm"
                  :class="documentStatusBadgeClass(doc.status)"
                >
                  <span class="h-1.5 w-1.5 shrink-0 rounded-full" :class="documentStatusDotClass(doc.status)" aria-hidden="true" />
                  {{ t(`documents.status.${doc.status}`) }}
                </span>
              </td>
              <td class="whitespace-nowrap border-b border-brand-border/80 px-5 py-3.5 text-center transition-colors duration-150 group-hover:bg-[#EDF6F1]">
                <div class="mx-auto grid w-[4.75rem] grid-cols-2 place-items-center gap-0.5 opacity-70 transition group-hover:opacity-100">
                  <div>
                    <AppTooltip :text="t('documents.actions.view')">
                      <RouterLink :to="`/app/documents/${doc.id}`" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-brand-text transition hover:bg-brand-surface hover:text-brand-primary-dark" :aria-label="t('documents.actions.view')"><Eye class="h-4 w-4" :stroke-width="2" /></RouterLink>
                    </AppTooltip>
                  </div>
                  <div v-if="canShowDocumentAction('download', can)">
                    <AppTooltip :text="t('documents.actions.download')">
                      <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-brand-text transition hover:bg-brand-surface hover:text-brand-primary-dark" :aria-label="t('documents.actions.download')" @click="onDownload(doc)"><Download class="h-4 w-4" :stroke-width="2" /></button>
                    </AppTooltip>
                  </div>
                  <div v-if="canShowDocumentAction('archive', can, doc)">
                    <AppTooltip :text="t('documents.actions.archive')">
                      <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-brand-text transition hover:bg-brand-surface" :aria-label="t('documents.actions.archive')" @click="onArchive(doc)"><Archive class="h-4 w-4" :stroke-width="2" /></button>
                    </AppTooltip>
                  </div>
                  <div v-if="canShowDocumentAction('restore', can, doc)">
                    <AppTooltip :text="t('documents.actions.restore')">
                      <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-brand-text transition hover:bg-brand-surface" :aria-label="t('documents.actions.restore')" @click="onRestore(doc)"><RotateCcw class="h-4 w-4" :stroke-width="2" /></button>
                    </AppTooltip>
                  </div>
                  <div v-if="canShowDocumentAction('delete', can)">
                    <AppTooltip :text="t('documents.actions.delete')">
                      <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-brand-text transition hover:bg-red-50 hover:text-red-700" :aria-label="t('documents.actions.delete')" @click="onDelete(doc)"><Trash2 class="h-4 w-4" :stroke-width="2" /></button>
                    </AppTooltip>
                  </div>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
        </div>
      </div>

      <div class="space-y-3 md:hidden">
        <article
          v-for="doc in documents"
          :key="doc.id"
          class="rounded-2xl border border-brand-border bg-brand-surface p-4 shadow-[0_1px_2px_rgba(23,32,29,0.03)]"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="font-mono text-xs font-bold text-brand-primary-dark" dir="ltr">
                {{ doc.document_number }}
              </p>
              <h3 class="mt-1 truncate font-bold text-brand-text">
                <RouterLink
                  :to="`/app/documents/${doc.id}`"
                  class="transition hover:text-brand-primary-dark hover:underline hover:underline-offset-2"
                >
                  {{ doc.title }}
                </RouterLink>
              </h3>
            </div>
            <span
              class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold"
              :class="documentStatusBadgeClass(doc.status)"
            >
              <span class="h-1.5 w-1.5 rounded-full" :class="documentStatusDotClass(doc.status)" />
              {{ t(`documents.status.${doc.status}`) }}
            </span>
          </div>
          <dl class="mt-3 grid grid-cols-2 gap-2 text-xs text-brand-text-secondary">
            <div>
              <dt>{{ t('documents.columns.category') }}</dt>
              <dd class="mt-0.5 font-medium text-brand-text">{{ doc.category?.name ?? '—' }}</dd>
            </div>
            <div>
              <dt>{{ t('documents.columns.date') }}</dt>
              <dd class="mt-0.5 font-medium text-brand-text" dir="ltr">
                {{ doc.created_at ? doc.created_at.slice(0, 10) : '—' }}
              </dd>
            </div>
          </dl>
          <div class="mt-3 flex items-center justify-end gap-1 border-t border-brand-border pt-3">
            <RouterLink
              :to="`/app/documents/${doc.id}`"
              class="inline-flex h-11 items-center gap-2 rounded-xl px-3 text-sm font-semibold text-brand-primary-dark transition hover:bg-brand-primary-soft"
            >
              <Eye class="h-4 w-4" :stroke-width="2" />
              {{ t('documents.actions.view') }}
            </RouterLink>
            <button
              v-if="canShowDocumentAction('download', can)"
              type="button"
              class="inline-flex h-11 items-center gap-2 rounded-xl px-3 text-sm font-semibold text-brand-primary-dark transition hover:bg-brand-primary-soft"
              @click="onDownload(doc)"
            >
              <Download class="h-4 w-4" :stroke-width="2" />
              {{ t('documents.actions.download') }}
            </button>
          </div>
        </article>
      </div>

      <div
        v-if="meta && meta.last_page > 1"
        class="flex items-center justify-between gap-3"
      >
        <button
          type="button"
          class="inline-flex h-11 items-center gap-1 rounded-xl border border-brand-border bg-brand-surface px-3 text-sm font-semibold text-brand-text transition hover:bg-brand-bg disabled:cursor-not-allowed disabled:opacity-40"
          :disabled="filters.page <= 1"
          @click="filters.page -= 1"
        >
          <ChevronRight class="h-4 w-4" :stroke-width="2" />
          <span>{{ t('documents.prev') }}</span>
        </button>
        <span class="text-xs font-semibold text-brand-text-muted">
          {{ filters.page }} / {{ meta.last_page }}
        </span>
        <button
          type="button"
          class="inline-flex h-11 items-center gap-1 rounded-xl border border-brand-border bg-brand-surface px-3 text-sm font-semibold text-brand-text transition hover:bg-brand-bg disabled:cursor-not-allowed disabled:opacity-40"
          :disabled="filters.page >= meta.last_page"
          @click="filters.page += 1"
        >
          <span>{{ t('documents.next') }}</span>
          <ChevronLeft class="h-4 w-4" :stroke-width="2" />
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
