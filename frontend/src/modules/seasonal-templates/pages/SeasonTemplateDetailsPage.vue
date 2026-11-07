<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { ArrowRight, Copy, Loader2, Pencil } from 'lucide-vue-next'

import { useSeasonsQuery } from '@/modules/season-pilgrims/queries/useSeasonsQuery'
import { ApiError } from '@/shared/api/http'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import type { AppSelectOption } from '@/shared/components/AppSelect.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'

import TemplateFormDrawer from '../components/TemplateFormDrawer.vue'
import TemplateStatusBadge from '../components/TemplateStatusBadge.vue'
import {
  useApproveSeasonTemplateMutation,
  useArchiveSeasonTemplateMutation,
  useUpdateSeasonTemplateMutation,
} from '../mutations/useSeasonTemplateMutations'
import { useSeasonTemplateQuery } from '../queries/useSeasonTemplatesQuery'
import type { TemplateFormState } from '../types/seasonTemplates'
import { canPreviewCopy } from '../validation/seasonCopyValidation'
import {
  buildCaptureSeason,
  buildTemplateItems,
  canApproveTemplate,
  canArchiveTemplate,
  canEditTemplate,
  emptyTemplateForm,
  isApprovedEntityKind,
  mapSeasonalTemplatesErrorCode,
  templateToForm,
  validateTemplateForm,
} from '../validation/seasonTemplateValidation'

const { t, te } = useI18n()
const toast = useToast()
const route = useRoute()
const router = useRouter()
const { can } = usePermissions()
const { confirm } = useConfirm()

const templateId = computed(() => {
  const raw = Number(route.params.id)
  return Number.isFinite(raw) && raw > 0 ? raw : null
})

const { data: template, isLoading, isError, refetch } = useSeasonTemplateQuery(templateId)

const updateMutation = useUpdateSeasonTemplateMutation()
const approveMutation = useApproveSeasonTemplateMutation()
const archiveMutation = useArchiveSeasonTemplateMutation()
const actionPending = computed(() => approveMutation.isPending.value || archiveMutation.isPending.value)

const showEdit = computed(() => !!template.value && canEditTemplate(template.value.status, can))
const showApprove = computed(() => !!template.value && canApproveTemplate(template.value, can))
const showArchive = computed(() => !!template.value && canArchiveTemplate(template.value.status, can))
const showUseInCopy = computed(() => template.value?.status === 'approved' && canPreviewCopy(can))

function apiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) return t('seasonalTemplates.errors.generic')
  const mapped = mapSeasonalTemplatesErrorCode(error.code)
  return mapped !== 'generic'
    ? t(`seasonalTemplates.errors.${mapped}`)
    : error.message || t('seasonalTemplates.errors.generic')
}

function kindLabel(kind: string): string {
  const key = `seasonalTemplates.entityKinds.${kind}.label`
  return te(key) ? t(key) : kind
}

function formatWhen(iso: string | null | undefined): string {
  if (!iso) return '—'
  try {
    return new Intl.DateTimeFormat('ar-SA', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso))
  } catch {
    return iso
  }
}

/* ----------------------------------------------------------------- edit drawer */
const drawerOpen = ref(false)
const form = reactive<TemplateFormState>(emptyTemplateForm())
const formError = ref('')
const fieldErrors = reactive<Record<string, string>>({})

const seasonsQuery = useSeasonsQuery({ per_page: 100 })
const captureSeasons = computed<AppSelectOption[]>(() =>
  (seasonsQuery.data.value?.data ?? []).map((s) => ({ value: s.id, label: `${s.name} (${s.code})` })),
)

function openEdit(): void {
  if (!template.value) return
  Object.assign(form, templateToForm(template.value))
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  drawerOpen.value = true
}

async function submitEdit(): Promise<void> {
  if (templateId.value == null) return
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  const local = validateTemplateForm(form)
  Object.assign(fieldErrors, local)
  if (Object.keys(local).length) return
  try {
    await updateMutation.mutateAsync({
      id: templateId.value,
      payload: {
        name: form.name.trim(),
        description: form.description.trim() || null,
        items: buildTemplateItems(form),
        ...buildCaptureSeason(form),
      },
    })
    toast.success(t('seasonalTemplates.toasts.updated'))
    drawerOpen.value = false
  } catch (error) {
    formError.value = apiMessage(error)
  }
}

/* ----------------------------------------------------------------- lifecycle */
async function runApprove(): Promise<void> {
  if (templateId.value == null) return
  const ok = await confirm({
    title: t('seasonalTemplates.details.confirm.approve.title'),
    message: t('seasonalTemplates.details.confirm.approve.body'),
    confirmLabel: t('seasonalTemplates.details.actions.approve'),
    cancelLabel: t('seasonalTemplates.common.cancel'),
  })
  if (!ok) return
  try {
    await approveMutation.mutateAsync(templateId.value)
    toast.success(t('seasonalTemplates.toasts.approved'))
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

async function runArchive(): Promise<void> {
  if (templateId.value == null) return
  const ok = await confirm({
    title: t('seasonalTemplates.details.confirm.archive.title'),
    message: t('seasonalTemplates.details.confirm.archive.body'),
    confirmLabel: t('seasonalTemplates.details.actions.archive'),
    cancelLabel: t('seasonalTemplates.common.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await archiveMutation.mutateAsync(templateId.value)
    toast.success(t('seasonalTemplates.toasts.archived'))
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

function useInCopy(): void {
  if (templateId.value == null) return
  void router.push({ path: '/app/season-safe-copy', query: { template: String(templateId.value) } })
}
</script>

<template>
  <div class="space-y-6" data-testid="season-template-details-page" dir="rtl">
    <AppPageHeader
      :title="template?.name ?? t('seasonalTemplates.details.title')"
      :subtitle="template?.template_number ?? t('seasonalTemplates.templates.subtitle')"
    >
      <template #actions>
        <button
          type="button"
          class="inline-flex h-11 items-center gap-2 rounded-xl border border-brand-border px-4 text-sm font-semibold"
          @click="router.push('/app/season-templates')"
        >
          <ArrowRight class="h-4 w-4" />
          {{ t('seasonalTemplates.details.backToList') }}
        </button>
        <button
          v-if="showEdit"
          type="button"
          class="inline-flex h-11 items-center gap-2 rounded-xl border border-brand-border px-4 text-sm font-semibold"
          data-testid="template-edit-btn"
          @click="openEdit"
        >
          <Pencil class="h-4 w-4" />
          {{ t('seasonalTemplates.details.actions.edit') }}
        </button>
        <button
          v-if="showUseInCopy"
          type="button"
          class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white"
          data-testid="template-use-btn"
          @click="useInCopy"
        >
          <Copy class="h-4 w-4" />
          {{ t('seasonalTemplates.details.actions.useInCopy') }}
        </button>
      </template>
    </AppPageHeader>

    <div v-if="isLoading" class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted">
      {{ t('seasonalTemplates.common.loading') }}
    </div>
    <div v-else-if="isError || !template" class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center" data-testid="template-error">
      <p class="text-sm text-red-700">{{ t('seasonalTemplates.errors.load') }}</p>
      <button type="button" class="mt-3 text-sm font-semibold underline" @click="() => refetch()">
        {{ t('seasonalTemplates.common.retry') }}
      </button>
    </div>

    <div v-else class="grid gap-6 lg:grid-cols-3">
      <section class="space-y-6 lg:col-span-2">
        <div class="rounded-2xl border border-brand-border bg-brand-surface p-5 text-start">
          <h2 class="text-base font-bold text-brand-text">{{ t('seasonalTemplates.details.items') }}</h2>
          <p v-if="template.description" class="mt-2 text-sm text-brand-text-secondary">{{ template.description }}</p>

          <ul class="mt-4 space-y-3" data-testid="template-items">
            <li
              v-for="item in template.items"
              :key="item.entity_kind"
              class="rounded-xl border border-brand-border bg-brand-bg p-4"
            >
              <div class="flex flex-wrap items-center gap-2">
                <span class="text-sm font-semibold text-brand-text">{{ kindLabel(item.entity_kind) }}</span>
                <span
                  v-if="!isApprovedEntityKind(item.entity_kind)"
                  class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-900"
                >
                  {{ t('seasonalTemplates.details.unapprovedKind') }}
                </span>
              </div>
              <p
                v-if="item.has_snapshot"
                class="mt-2 text-xs text-brand-text-secondary"
                data-testid="template-item-snapshot"
              >
                {{ t('seasonalTemplates.details.snapshot', { count: item.snapshot_row_count ?? 0, anchor: item.snapshot_anchor ?? '—' }) }}
              </p>
              <p v-else class="mt-2 text-xs text-brand-text-muted">{{ t('seasonalTemplates.details.liveSource') }}</p>
              <p v-if="item.selection?.codes?.length" class="mt-2 break-words font-mono text-xs text-brand-text-secondary" dir="ltr">
                {{ item.selection.codes.join(', ') }}
              </p>
              <p v-else class="mt-2 text-xs text-brand-text-muted">{{ t('seasonalTemplates.details.allCodes') }}</p>
            </li>
          </ul>
        </div>
      </section>

      <aside class="space-y-4">
        <div class="space-y-3 rounded-2xl border border-brand-border bg-brand-surface p-5 text-start text-sm">
          <div class="flex items-center justify-between gap-2">
            <span class="text-xs font-semibold text-brand-text-muted">{{ t('seasonalTemplates.templates.columns.status') }}</span>
            <TemplateStatusBadge :status="template.status" />
          </div>
          <div class="flex items-center justify-between gap-2">
            <span class="text-xs font-semibold text-brand-text-muted">{{ t('seasonalTemplates.details.matrixVersion') }}</span>
            <span class="font-mono text-xs" dir="ltr">{{ template.matrix_version }}</span>
          </div>
          <div class="flex items-center justify-between gap-2">
            <span class="text-xs font-semibold text-brand-text-muted">{{ t('seasonalTemplates.details.createdBy') }}</span>
            <span>{{ template.created_by?.name ?? '—' }}</span>
          </div>
          <div class="flex items-center justify-between gap-2">
            <span class="text-xs font-semibold text-brand-text-muted">{{ t('seasonalTemplates.details.createdAt') }}</span>
            <span class="text-xs">{{ formatWhen(template.created_at) }}</span>
          </div>
          <div v-if="template.approved_at" class="flex items-center justify-between gap-2">
            <span class="text-xs font-semibold text-brand-text-muted">{{ t('seasonalTemplates.details.approvedBy') }}</span>
            <span>{{ template.approved_by?.name ?? '—' }}</span>
          </div>
          <div v-if="template.approved_at" class="flex items-center justify-between gap-2">
            <span class="text-xs font-semibold text-brand-text-muted">{{ t('seasonalTemplates.details.approvedAt') }}</span>
            <span class="text-xs">{{ formatWhen(template.approved_at) }}</span>
          </div>
          <div v-if="template.archived_at" class="flex items-center justify-between gap-2">
            <span class="text-xs font-semibold text-brand-text-muted">{{ t('seasonalTemplates.details.archivedAt') }}</span>
            <span class="text-xs">{{ formatWhen(template.archived_at) }}</span>
          </div>
        </div>

        <div v-if="showApprove || showArchive" class="space-y-3 rounded-2xl border border-brand-border bg-brand-surface p-5">
          <button
            v-if="showApprove"
            type="button"
            class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 text-sm font-semibold text-emerald-950 hover:bg-emerald-100 disabled:opacity-50"
            :disabled="actionPending"
            data-testid="template-approve-btn"
            @click="runApprove"
          >
            <Loader2 v-if="approveMutation.isPending.value" class="h-3.5 w-3.5 animate-spin" />
            {{ t('seasonalTemplates.details.actions.approve') }}
          </button>
          <button
            v-if="showArchive"
            type="button"
            class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl border border-red-300 bg-red-50 px-4 text-sm font-semibold text-red-800 hover:bg-red-100 disabled:opacity-50"
            :disabled="actionPending"
            data-testid="template-archive-btn"
            @click="runArchive"
          >
            <Loader2 v-if="archiveMutation.isPending.value" class="h-3.5 w-3.5 animate-spin" />
            {{ t('seasonalTemplates.details.actions.archive') }}
          </button>
        </div>
      </aside>
    </div>

    <TemplateFormDrawer
      :open="drawerOpen"
      :editing="true"
      :form="form"
      :form-error="formError"
      :field-errors="fieldErrors"
      :submitting="updateMutation.isPending.value"
      :capture-seasons="captureSeasons"
      @close="drawerOpen = false"
      @submit="submitEdit"
      @update:form="Object.assign(form, $event)"
    />
  </div>
</template>
