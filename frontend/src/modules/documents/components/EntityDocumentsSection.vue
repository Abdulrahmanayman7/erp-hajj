<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { Archive, Download, Plus, RotateCcw, Trash2 } from 'lucide-vue-next'

import { ApiError } from '@/shared/api/http'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'

import { downloadDocument, triggerBrowserDownload } from '../api/documentsApi'
import {
  useArchiveDocumentMutation,
  useDeleteDocumentMutation,
  useRestoreDocumentMutation,
  useUploadDocumentMutation,
} from '../mutations/useDocumentMutations'
import { useDocumentsQuery } from '../queries/useDocumentsQuery'
import type {
  Document,
  DocumentLinkableType,
  DocumentUploadFormState,
  LockedDocumentLink,
} from '../types/documents'
import {
  canShowDocumentAction,
  documentStatusBadgeClass,
  formatDocumentSize,
  mapDocumentErrorCode,
  resolveDocumentsListState,
  validateDocumentUploadForm,
} from '../validation/documentValidation'
import DocumentUploadDrawer from './DocumentUploadDrawer.vue'

const props = defineProps<{
  linkableType: DocumentLinkableType
  linkableId: number
  linkLabel?: string
}>()

const { t } = useI18n()
const { can } = usePermissions()
const toast = useToast()
const { confirm } = useConfirm()

const params = computed(() => ({
  linkable_type: props.linkableType,
  linkable_id: props.linkableId,
  status: 'active' as const,
  per_page: 50,
}))

const { data, isLoading, isError, refetch } = useDocumentsQuery(params)
const documents = computed(() => data.value?.data ?? [])
const listState = computed(() =>
  resolveDocumentsListState({
    isLoading: isLoading.value,
    isError: isError.value,
    count: documents.value.length,
  }),
)

const upload = useUploadDocumentMutation()
const archive = useArchiveDocumentMutation()
const restore = useRestoreDocumentMutation()
const remove = useDeleteDocumentMutation()

const drawerOpen = ref(false)
const formError = ref('')
const fieldErrors = reactive<Record<string, string>>({})
const emptyForm = (): DocumentUploadFormState => ({
  file: null,
  title: '',
  description: '',
  category_id: '',
  linkable_type: props.linkableType,
  linkable_id: props.linkableId,
})
const form = reactive<DocumentUploadFormState>(emptyForm())

const lockedLink = computed<LockedDocumentLink>(() => ({
  type: props.linkableType,
  id: props.linkableId,
  label: props.linkLabel,
}))

function openUpload(): void {
  Object.assign(form, emptyForm())
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  drawerOpen.value = true
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
  const validation = validateDocumentUploadForm(form, lockedLink.value)
  Object.entries(validation).forEach(([k, v]) => {
    if (v) fieldErrors[k] = fieldMessage(v)
  })
  if (Object.keys(fieldErrors).length || !form.file) return

  try {
    await upload.mutateAsync({
      file: form.file,
      fields: {
        title: form.title.trim() || undefined,
        description: form.description.trim() || null,
        category_id: form.category_id === '' ? null : form.category_id,
        linkable_type: props.linkableType,
        linkable_id: props.linkableId,
      },
    })
    toast.success(t('documents.toasts.uploaded'))
    drawerOpen.value = false
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
    await archive.mutateAsync({ id: doc.id })
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
    await restore.mutateAsync({ id: doc.id })
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
    await remove.mutateAsync({ id: doc.id })
    toast.success(t('documents.toasts.deleted'))
  } catch (error) {
    toast.error(apiMessage(error))
  }
}
</script>

<template>
  <PermissionGuard permission="documents.view">
    <section class="rounded-2xl border border-brand-border bg-brand-surface p-5">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h3 class="font-bold text-brand-text">{{ t('documents.entitySection.title') }}</h3>
        <PermissionGuard permission="documents.upload">
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-xl bg-brand-primary-dark px-3 py-2 text-sm font-semibold text-white"
            @click="openUpload"
          >
            <Plus class="h-4 w-4" />
            {{ t('documents.upload.cta') }}
          </button>
        </PermissionGuard>
      </div>

      <div v-if="listState === 'loading'" class="mt-4 text-sm text-brand-text-muted">
        {{ t('documents.loading') }}
      </div>
      <div v-else-if="listState === 'error'" class="mt-4 text-sm">
        <p>{{ t('documents.errors.load') }}</p>
        <button type="button" class="mt-1 underline" @click="() => refetch()">
          {{ t('documents.retry') }}
        </button>
      </div>
      <p v-else-if="listState === 'empty'" class="mt-4 text-sm text-brand-text-muted">
        {{ t('documents.entitySection.empty') }}
      </p>
      <template v-else>
        <div class="mt-4 hidden overflow-x-auto rounded-xl border border-brand-border md:block">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="bg-brand-bg">
                <th class="px-3 py-2 text-start">{{ t('documents.columns.number') }}</th>
                <th class="px-3 py-2 text-start">{{ t('documents.columns.title') }}</th>
                <th class="px-3 py-2 text-start">{{ t('documents.columns.file') }}</th>
                <th class="px-3 py-2 text-start">{{ t('documents.columns.status') }}</th>
                <th class="px-3 py-2 text-start">{{ t('documents.columns.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="doc in documents" :key="doc.id" class="border-t">
                <td class="px-3 py-2 font-mono">
                  <RouterLink :to="`/app/documents/${doc.id}`" class="underline">
                    {{ doc.document_number }}
                  </RouterLink>
                </td>
                <td class="px-3 py-2">{{ doc.title }}</td>
                <td class="px-3 py-2">
                  {{ doc.original_filename }}
                  <span class="text-brand-text-muted"> · {{ formatDocumentSize(doc.size_bytes) }}</span>
                </td>
                <td class="px-3 py-2">
                  <span
                    class="rounded-full px-2 py-1 text-xs"
                    :class="documentStatusBadgeClass(doc.status)"
                  >
                    {{ t(`documents.status.${doc.status}`) }}
                  </span>
                </td>
                <td class="px-3 py-2">
                  <div class="flex flex-wrap items-center gap-1">
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

        <div class="mt-4 space-y-3 md:hidden">
          <div
            v-for="doc in documents"
            :key="doc.id"
            class="rounded-xl border border-brand-border p-3"
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
            <p class="mt-1 font-semibold">{{ doc.title }}</p>
            <p class="text-xs text-brand-text-muted">
              {{ doc.original_filename }} · {{ formatDocumentSize(doc.size_bytes) }}
            </p>
            <div class="mt-2 flex gap-2">
              <button
                v-if="canShowDocumentAction('download', can)"
                type="button"
                class="rounded-lg border px-2 py-1 text-xs"
                @click="onDownload(doc)"
              >
                {{ t('documents.actions.download') }}
              </button>
            </div>
          </div>
        </div>
      </template>

      <DocumentUploadDrawer
        v-if="drawerOpen"
        :open="drawerOpen"
        :form="form"
        :form-error="formError"
        :field-errors="fieldErrors"
        :submitting="upload.isPending.value"
        :locked-link="lockedLink"
        @close="drawerOpen = false"
        @submit="submitUpload"
        @update:form="Object.assign(form, $event)"
      />
    </section>
  </PermissionGuard>
</template>
