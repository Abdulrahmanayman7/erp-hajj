<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import {
  Archive,
  ArrowRight,
  CalendarRange,
  Download,
  FileText,
  Hash,
  Link,
  Pencil,
  RotateCcw,
  Tag,
  Trash2,
  UserRound,
} from 'lucide-vue-next'

import { ApiError } from '@/shared/api/http'
import AppNumberInput from '@/shared/components/AppNumberInput.vue'
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
  documentStatusDotClass,
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
  <div class="mx-auto min-w-0 max-w-[1200px] space-y-5">
    <div class="flex flex-wrap items-center gap-3">
      <button type="button" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-brand-border bg-brand-surface px-3 text-sm font-semibold text-brand-text transition hover:bg-brand-bg" @click="router.push('/app/documents')">
        <ArrowRight class="h-4 w-4" />
        {{ t('documents.backToList') }}
      </button>
    </div>

    <div v-if="isLoading" class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted">
      {{ t('documents.loadingDetails') }}
    </div>
    <div v-else-if="isError || !document" class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center">
      <p class="text-sm text-red-700">{{ t('documents.errors.loadDetails') }}</p>
      <button type="button" class="mt-3 text-sm font-semibold text-brand-primary-dark underline" @click="() => refetch()">
        {{ t('documents.retry') }}
      </button>
    </div>
    <template v-else>
      <section class="rounded-2xl border border-brand-border bg-brand-surface px-5 py-5 sm:px-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <p class="font-mono text-xs font-semibold tracking-wide text-brand-text-muted" dir="ltr">{{ document.document_number }}</p>
              <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold tracking-wide" :class="documentStatusBadgeClass(document.status)">
                <span class="h-1.5 w-1.5 shrink-0 rounded-full" :class="documentStatusDotClass(document.status)" aria-hidden="true" />
                {{ t(`documents.status.${document.status}`) }}
              </span>
            </div>
            <h2 class="mt-2 break-words text-[1.35rem] font-bold leading-snug text-brand-text sm:text-[1.85rem]">{{ document.title }}</h2>
            <p class="mt-2 break-words text-sm text-brand-text-secondary">{{ document.original_filename }}</p>
          </div>
          <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap">
            <PermissionGuard permission="documents.update">
              <button type="button" class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-4 text-sm font-semibold text-brand-primary-dark transition hover:bg-brand-primary-soft sm:h-10 sm:w-auto" @click="startEdit">
                <Pencil class="h-4 w-4" />
                {{ t('documents.actions.edit') }}
              </button>
            </PermissionGuard>
            <PermissionGuard permission="documents.download">
              <button type="button" class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white transition hover:bg-brand-primary sm:h-10 sm:w-auto" @click="onDownload">
                <Download class="h-4 w-4" />
                {{ t('documents.actions.download') }}
              </button>
            </PermissionGuard>
          </div>
        </div>
      </section>

      <div class="grid min-w-0 gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
        <div class="order-2 min-w-0 space-y-5 xl:order-1">
          <section class="rounded-2xl border border-brand-border bg-brand-surface">
            <header class="border-b border-brand-border px-5 py-4">
              <h3 class="text-sm font-bold text-brand-text">{{ t('documents.sections.overview') }}</h3>
            </header>
            <dl class="grid gap-0 md:grid-cols-2">
              <div class="flex gap-3 border-b border-brand-border/80 px-5 py-4 sm:border-e">
                <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary"><Tag class="h-4 w-4" :stroke-width="1.75" /></span>
                <div class="min-w-0"><dt class="text-xs font-semibold text-brand-text-muted">{{ t('documents.fields.category') }}</dt><dd class="mt-1 text-sm font-semibold text-brand-text">{{ document.category?.name ?? '—' }}</dd></div>
              </div>
              <div class="flex gap-3 border-b border-brand-border/80 px-5 py-4">
                <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary"><FileText class="h-4 w-4" :stroke-width="1.75" /></span>
                <div class="min-w-0"><dt class="text-xs font-semibold text-brand-text-muted">{{ t('documents.fields.mime') }} / {{ t('documents.fields.size') }}</dt><dd class="mt-1 text-sm font-semibold text-brand-text">{{ document.mime_type }} <span class="text-brand-text-muted">·</span> {{ formatDocumentSize(document.size_bytes) }}</dd></div>
              </div>
              <div class="flex gap-3 border-b border-brand-border/80 px-5 py-4 sm:border-e sm:border-b-0">
                <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary"><UserRound class="h-4 w-4" :stroke-width="1.75" /></span>
                <div class="min-w-0"><dt class="text-xs font-semibold text-brand-text-muted">{{ t('documents.fields.uploader') }}</dt><dd class="mt-1 text-sm font-semibold text-brand-text">{{ document.uploaded_by?.name ?? '—' }}</dd></div>
              </div>
              <div class="flex gap-3 px-5 py-4">
                <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary"><CalendarRange class="h-4 w-4" :stroke-width="1.75" /></span>
                <div class="min-w-0"><dt class="text-xs font-semibold text-brand-text-muted">{{ t('documents.fields.createdAt') }}</dt><dd class="mt-1 text-sm font-semibold text-brand-text" dir="ltr">{{ document.created_at ? document.created_at.slice(0, 10) : '—' }}</dd></div>
              </div>
            </dl>
          </section>

          <section class="rounded-2xl border border-brand-border bg-brand-surface">
            <header class="flex items-center gap-2 border-b border-brand-border px-5 py-4">
              <FileText class="h-4 w-4 text-brand-primary" :stroke-width="1.75" />
              <h3 class="text-sm font-bold text-brand-text">{{ t('documents.fields.description') }}</h3>
            </header>
            <p class="whitespace-pre-wrap px-5 py-4 text-sm leading-relaxed text-brand-text-secondary">{{ document.description || '—' }}</p>
          </section>

          <section class="rounded-2xl border border-brand-border bg-brand-surface">
            <header class="flex items-center gap-2 border-b border-brand-border px-5 py-4">
              <FileText class="h-4 w-4 text-brand-primary" :stroke-width="1.75" />
              <h3 class="text-sm font-bold text-brand-text">{{ t('documents.sections.file') }}</h3>
            </header>
            <div class="flex flex-wrap items-center justify-between gap-4 px-5 py-4">
              <div class="min-w-0"><p class="truncate text-sm font-semibold text-brand-text" :title="document.original_filename">{{ document.original_filename }}</p><p class="mt-1 text-xs text-brand-text-muted">{{ formatDocumentSize(document.size_bytes) }}</p></div>
              <PermissionGuard permission="documents.download"><button type="button" class="inline-flex h-10 items-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-4 text-sm font-semibold text-brand-primary-dark transition hover:bg-brand-primary-soft" @click="onDownload"><Download class="h-4 w-4" />{{ t('documents.actions.download') }}</button></PermissionGuard>
            </div>
          </section>

          <section class="rounded-2xl border border-brand-border bg-brand-surface">
            <header class="flex items-center gap-2 border-b border-brand-border px-5 py-4"><Link class="h-4 w-4 text-brand-primary" :stroke-width="1.75" /><h3 class="text-sm font-bold text-brand-text">{{ t('documents.sections.link') }}</h3></header>
            <div class="px-5 py-4">
              <p v-if="!document.link" class="text-sm text-brand-text-muted">{{ t('documents.link.standalone') }}</p>
              <template v-else>
                <p class="text-sm text-brand-text"><span class="font-semibold">{{ t(`documents.link.types.${document.link.type}`) }}</span><span class="mx-1.5 text-brand-text-muted">·</span>{{ document.link.label ?? `#${document.link.id}` }}</p>
                <RouterLink v-if="hostLink" :to="hostLink" class="mt-2 inline-block text-sm font-semibold text-brand-primary-dark hover:underline">{{ t('documents.link.openHost') }}</RouterLink>
              </template>
            </div>
          </section>
        </div>

        <aside class="order-1 space-y-5 xl:order-2">
          <section class="rounded-2xl border border-brand-border bg-brand-surface">
            <header class="flex items-center gap-2 border-b border-brand-border px-5 py-4"><Hash class="h-4 w-4 text-brand-primary" :stroke-width="1.75" /><h3 class="text-sm font-bold text-brand-text">{{ t('documents.fields.checksum') }}</h3></header>
            <p class="px-5 py-4 font-mono text-sm text-brand-text" :title="document.checksum_sha256">{{ truncateChecksum(document.checksum_sha256) }}</p>
          </section>
          <section class="rounded-2xl border border-brand-border bg-brand-surface">
            <header class="border-b border-brand-border px-5 py-4"><h3 class="text-sm font-bold text-brand-text">{{ t('documents.sections.archive') }}</h3></header>
            <div class="flex flex-wrap gap-2 px-4 py-4">
              <button v-if="canShowDocumentAction('archive', can, document)" type="button" class="inline-flex h-10 items-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-3 text-sm font-semibold text-brand-text transition hover:bg-brand-bg" @click="onArchive"><Archive class="h-4 w-4" />{{ t('documents.actions.archive') }}</button>
              <button v-if="canShowDocumentAction('restore', can, document)" type="button" class="inline-flex h-10 items-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-3 text-sm font-semibold text-brand-text transition hover:bg-brand-bg" @click="onRestore"><RotateCcw class="h-4 w-4" />{{ t('documents.actions.restore') }}</button>
              <button v-if="canShowDocumentAction('delete', can)" type="button" class="inline-flex h-10 items-center gap-2 rounded-xl border border-red-200 bg-red-50/40 px-3 text-sm font-semibold text-red-700 transition hover:bg-red-50" @click="onDelete"><Trash2 class="h-4 w-4" />{{ t('documents.actions.delete') }}</button>
            </div>
          </section>
        </aside>
      </div>

      <div
        v-if="editing"
        class="fixed inset-0 z-50 flex items-end bg-[rgba(15,23,20,0.32)] p-0 sm:items-center sm:justify-center sm:p-4"
      >
        <form
          class="w-full rounded-t-2xl bg-brand-surface p-5 shadow-xl sm:max-w-lg sm:rounded-2xl"
          v-autofocus-when
          @submit.prevent="saveEdit"
        >
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
              <AppNumberInput
                :model-value="form.linkable_id"
                integer
                min="1"
                class="h-11 w-full rounded-xl border border-brand-border px-3"
                @update:model-value="
                  form.linkable_id = $event === '' ? '' : Number($event)
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
              type="submit"
              class="rounded-xl bg-brand-primary-dark px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
              :disabled="update.isPending.value"
            >
              {{ t('documents.save') }}
            </button>
          </div>
        </form>
      </div>
    </template>
  </div>
</template>
