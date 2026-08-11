<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import {
  Archive,
  ArrowRight,
  Download,
  Pencil,
  RotateCcw,
  Trash2,
} from 'lucide-vue-next'

import { ApiError } from '@/shared/api/http'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'

import { downloadDocument, triggerBrowserDownload } from '../api/documentsApi'
import {
  useArchiveDocumentMutation,
  useDeleteDocumentMutation,
  useRestoreDocumentMutation,
  useUpdateDocumentMutation,
} from '../mutations/useDocumentMutations'
import { useDocumentCategoriesQuery } from '../queries/useCategoriesQuery'
import { useDocumentQuery } from '../queries/useDocumentsQuery'
import type { DocumentLinkableType } from '../types/documents'
import { DOCUMENT_LINKABLE_TYPES } from '../types/documents'
import {
  canShowDocumentAction,
  documentStatusBadgeClass,
  formatDocumentSize,
  hostRouteForLink,
  mapDocumentErrorCode,
  truncateChecksum,
  validateDocumentMetadataForm,
} from '../validation/documentValidation'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { can } = usePermissions()
const toast = useToast()
const { confirm } = useConfirm()

const id = computed(() => Number(route.params.id))
const { data, isLoading, isError, refetch } = useDocumentQuery(id)
const document = computed(() => data.value ?? null)

const { data: categoriesData } = useDocumentCategoriesQuery({ active: true })
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

const update = useUpdateDocumentMutation()
const archiveMut = useArchiveDocumentMutation()
const restoreMut = useRestoreDocumentMutation()
const deleteMut = useDeleteDocumentMutation()

const editing = ref(false)
const formError = ref('')
const fieldErrors = reactive<Record<string, string>>({})
const form = reactive({
  title: '',
  description: '',
  category_id: '' as number | '',
  linkable_type: '' as DocumentLinkableType | '',
  linkable_id: '' as number | '',
})

function startEdit(): void {
  if (!document.value || !can('documents.update')) return
  form.title = document.value.title
  form.description = document.value.description ?? ''
  form.category_id = document.value.category?.id ?? ''
  form.linkable_type = document.value.link?.type ?? ''
  form.linkable_id = document.value.link?.id ?? ''
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  editing.value = true
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

async function saveEdit(): Promise<void> {
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  const validation = validateDocumentMetadataForm(form)
  Object.entries(validation).forEach(([k, v]) => {
    if (v) fieldErrors[k] = fieldMessage(v)
  })
  if (Object.keys(fieldErrors).length || !document.value) return

  try {
    await update.mutateAsync({
      id: document.value.id,
      payload: {
        title: form.title.trim(),
        description: form.description.trim() || null,
        category_id: form.category_id === '' ? null : form.category_id,
        linkable_type: form.linkable_type || null,
        linkable_id: form.linkable_id === '' ? null : form.linkable_id,
      },
    })
    toast.success(t('documents.toasts.updated'))
    editing.value = false
  } catch (error) {
    formError.value = apiMessage(error)
  }
}

async function onDownload(): Promise<void> {
  if (!document.value || !canShowDocumentAction('download', can)) return
  try {
    const result = await downloadDocument(document.value.id)
    triggerBrowserDownload(
      result.blob,
      result.filename || document.value.original_filename,
    )
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

async function onArchive(): Promise<void> {
  if (!document.value || !canShowDocumentAction('archive', can, document.value)) return
  const ok = await confirm({
    title: t('documents.confirm.archive.title'),
    message: t('documents.confirm.archive.body'),
    confirmLabel: t('documents.actions.archive'),
    variant: 'warning',
  })
  if (!ok) return
  try {
    await archiveMut.mutateAsync({ id: document.value.id })
    toast.success(t('documents.toasts.archived'))
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

async function onRestore(): Promise<void> {
  if (!document.value || !canShowDocumentAction('restore', can, document.value)) return
  const ok = await confirm({
    title: t('documents.confirm.restore.title'),
    message: t('documents.confirm.restore.body'),
    confirmLabel: t('documents.actions.restore'),
    variant: 'primary',
  })
  if (!ok) return
  try {
    await restoreMut.mutateAsync({ id: document.value.id })
    toast.success(t('documents.toasts.restored'))
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

async function onDelete(): Promise<void> {
  if (!document.value || !canShowDocumentAction('delete', can)) return
  const ok = await confirm({
    title: t('documents.confirm.delete.title'),
    message: t('documents.confirm.delete.body'),
    confirmLabel: t('documents.actions.delete'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await deleteMut.mutateAsync({ id: document.value.id })
    toast.success(t('documents.toasts.deleted'))
    await router.push('/app/documents')
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

const hostLink = computed(() => {
  if (!document.value?.link) return null
  return hostRouteForLink(document.value.link.type, document.value.link.id)
})
</script>

<template>
  <div class="space-y-6">
    <button
      type="button"
      class="rounded-lg border px-3 py-2"
      @click="router.push('/app/documents')"
    >
      <ArrowRight class="inline h-4 w-4" />
      {{ t('documents.backToList') }}
    </button>

    <div v-if="isLoading" class="rounded-2xl border p-10 text-center">
      {{ t('documents.loadingDetails') }}
    </div>
    <div v-else-if="isError || !document" class="rounded-2xl border p-10 text-center">
      {{ t('documents.errors.loadDetails') }}
      <button type="button" class="ms-2 underline" @click="() => refetch()">
        {{ t('documents.retry') }}
      </button>
    </div>
    <template v-else>
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
          <div class="flex flex-wrap items-center gap-2">
            <span class="font-mono">{{ document.document_number }}</span>
            <span
              class="rounded-full px-2 py-1 text-xs"
              :class="documentStatusBadgeClass(document.status)"
            >
              {{ t(`documents.status.${document.status}`) }}
            </span>
          </div>
          <h2 class="mt-2 text-2xl font-bold">{{ document.title }}</h2>
        </div>
        <div class="flex flex-wrap gap-2">
          <PermissionGuard permission="documents.update">
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-xl border px-4 py-2 text-sm font-semibold"
              @click="startEdit"
            >
              <Pencil class="h-4 w-4" />
              {{ t('documents.actions.edit') }}
            </button>
          </PermissionGuard>
          <PermissionGuard permission="documents.download">
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-xl bg-brand-primary-dark px-4 py-2 text-sm font-semibold text-white"
              @click="onDownload"
            >
              <Download class="h-4 w-4" />
              {{ t('documents.actions.download') }}
            </button>
          </PermissionGuard>
        </div>
      </div>

      <section>
        <h3 class="mb-3 font-bold">{{ t('documents.sections.overview') }}</h3>
        <div class="grid gap-4 md:grid-cols-3">
          <div class="rounded-2xl border border-brand-border bg-brand-surface p-4">
            <p class="text-xs text-brand-text-muted">{{ t('documents.fields.category') }}</p>
            <p class="mt-1 font-semibold">{{ document.category?.name ?? '—' }}</p>
          </div>
          <div class="rounded-2xl border border-brand-border bg-brand-surface p-4">
            <p class="text-xs text-brand-text-muted">{{ t('documents.fields.mime') }}</p>
            <p class="mt-1 font-semibold">{{ document.mime_type }}</p>
          </div>
          <div class="rounded-2xl border border-brand-border bg-brand-surface p-4">
            <p class="text-xs text-brand-text-muted">{{ t('documents.fields.size') }}</p>
            <p class="mt-1 font-semibold">{{ formatDocumentSize(document.size_bytes) }}</p>
          </div>
          <div class="rounded-2xl border border-brand-border bg-brand-surface p-4">
            <p class="text-xs text-brand-text-muted">{{ t('documents.fields.checksum') }}</p>
            <p class="mt-1 font-mono text-sm" :title="document.checksum_sha256">
              {{ truncateChecksum(document.checksum_sha256) }}
            </p>
          </div>
          <div class="rounded-2xl border border-brand-border bg-brand-surface p-4">
            <p class="text-xs text-brand-text-muted">{{ t('documents.fields.uploader') }}</p>
            <p class="mt-1 font-semibold">{{ document.uploaded_by?.name ?? '—' }}</p>
          </div>
          <div class="rounded-2xl border border-brand-border bg-brand-surface p-4">
            <p class="text-xs text-brand-text-muted">{{ t('documents.fields.createdAt') }}</p>
            <p class="mt-1 font-semibold">
              {{ document.created_at ? document.created_at.slice(0, 10) : '—' }}
            </p>
          </div>
        </div>
        <div class="mt-4 rounded-2xl border p-4">
          <p class="text-xs text-brand-text-muted">{{ t('documents.fields.description') }}</p>
          <p class="mt-2 whitespace-pre-wrap">{{ document.description || '—' }}</p>
        </div>
      </section>

      <section class="rounded-2xl border p-5">
        <h3 class="font-bold">{{ t('documents.sections.file') }}</h3>
        <p class="mt-2 text-sm">{{ document.original_filename }}</p>
        <PermissionGuard permission="documents.download">
          <button
            type="button"
            class="mt-3 inline-flex items-center gap-2 rounded-xl border px-3 py-2 text-sm font-semibold"
            @click="onDownload"
          >
            <Download class="h-4 w-4" />
            {{ t('documents.actions.download') }}
          </button>
        </PermissionGuard>
      </section>

      <section class="rounded-2xl border p-5">
        <h3 class="font-bold">{{ t('documents.sections.link') }}</h3>
        <p v-if="!document.link" class="mt-2 text-sm text-brand-text-muted">
          {{ t('documents.link.standalone') }}
        </p>
        <template v-else>
          <p class="mt-2">
            {{ t(`documents.link.types.${document.link.type}`) }}
            <span class="text-brand-text-muted"> · </span>
            {{ document.link.label ?? `#${document.link.id}` }}
          </p>
          <RouterLink
            v-if="hostLink"
            :to="hostLink"
            class="mt-2 inline-block text-sm text-brand-primary-dark underline"
          >
            {{ t('documents.link.openHost') }}
          </RouterLink>
        </template>
      </section>

      <section class="rounded-2xl border p-5">
        <h3 class="mb-3 font-bold">{{ t('documents.sections.archive') }}</h3>
        <div class="flex flex-wrap gap-2">
          <button
            v-if="canShowDocumentAction('archive', can, document)"
            type="button"
            class="inline-flex items-center gap-2 rounded-xl border px-3 py-2 text-sm font-semibold"
            @click="onArchive"
          >
            <Archive class="h-4 w-4" />
            {{ t('documents.actions.archive') }}
          </button>
          <button
            v-if="canShowDocumentAction('restore', can, document)"
            type="button"
            class="inline-flex items-center gap-2 rounded-xl border px-3 py-2 text-sm font-semibold"
            @click="onRestore"
          >
            <RotateCcw class="h-4 w-4" />
            {{ t('documents.actions.restore') }}
          </button>
          <button
            v-if="canShowDocumentAction('delete', can)"
            type="button"
            class="inline-flex items-center gap-2 rounded-xl border border-red-200 px-3 py-2 text-sm font-semibold text-red-700"
            @click="onDelete"
          >
            <Trash2 class="h-4 w-4" />
            {{ t('documents.actions.delete') }}
          </button>
        </div>
      </section>

      <div
        v-if="editing"
        class="fixed inset-0 z-50 flex items-end bg-[rgba(15,23,20,0.32)] p-0 sm:items-center sm:justify-center sm:p-4"
      >
        <div class="w-full rounded-t-2xl bg-brand-surface p-5 shadow-xl sm:max-w-lg sm:rounded-2xl">
          <h3 class="text-lg font-bold">{{ t('documents.editTitle') }}</h3>
          <div class="mt-4 space-y-3">
            <label class="block space-y-1.5">
              <span class="text-sm font-medium">{{ t('documents.fields.title') }}</span>
              <input
                v-model="form.title"
                type="text"
                class="h-11 w-full rounded-xl border border-brand-border px-3"
              />
              <p v-if="fieldErrors.title" class="text-sm text-red-700">{{ fieldErrors.title }}</p>
            </label>
            <label class="block space-y-1.5">
              <span class="text-sm font-medium">{{ t('documents.fields.description') }}</span>
              <textarea
                v-model="form.description"
                rows="3"
                class="w-full rounded-xl border border-brand-border px-3 py-2"
              />
            </label>
            <label class="block space-y-1.5">
              <span class="text-sm font-medium">{{ t('documents.fields.category') }}</span>
              <AppSelect
                v-model="form.category_id"
                :options="categoryOptions"
                searchable
              />
            </label>
            <label class="block space-y-1.5">
              <span class="text-sm font-medium">{{ t('documents.fields.linkableType') }}</span>
              <AppSelect
                :model-value="form.linkable_type"
                :options="linkTypeOptions"
                @update:model-value="
                  form.linkable_type = ($event || '') as DocumentLinkableType | '';
                  if (!form.linkable_type) form.linkable_id = ''
                "
              />
            </label>
            <label v-if="form.linkable_type" class="block space-y-1.5">
              <span class="text-sm font-medium">{{ t('documents.fields.linkableId') }}</span>
              <input
                :value="form.linkable_id"
                type="number"
                min="1"
                class="h-11 w-full rounded-xl border border-brand-border px-3"
                @input="
                  form.linkable_id =
                    ($event.target as HTMLInputElement).value === ''
                      ? ''
                      : Number(($event.target as HTMLInputElement).value)
                "
              />
              <p v-if="fieldErrors.linkable_id" class="text-sm text-red-700">
                {{ fieldErrors.linkable_id }}
              </p>
            </label>
            <p v-if="formError" class="text-sm text-red-700">{{ formError }}</p>
          </div>
          <div class="mt-5 flex justify-end gap-2">
            <button type="button" class="rounded-xl px-4 py-2 text-sm" @click="editing = false">
              {{ t('documents.cancel') }}
            </button>
            <button
              type="button"
              class="rounded-xl bg-brand-primary-dark px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
              :disabled="update.isPending.value"
              @click="saveEdit"
            >
              {{ t('documents.save') }}
            </button>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
