<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { Check, ChevronDown, ChevronUp, Loader2, Plus, Repeat, Upload, X } from 'lucide-vue-next'

import DocumentUploadDrawer from '@/modules/documents/components/DocumentUploadDrawer.vue'
import { useUploadDocumentMutation } from '@/modules/documents/mutations/useDocumentMutations'
import type {
  DocumentUploadFormState,
  LockedDocumentLink,
} from '@/modules/documents/types/documents'
import {
  mapDocumentErrorCode,
  validateDocumentUploadForm,
} from '@/modules/documents/validation/documentValidation'
import { ApiError } from '@/shared/api/http'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'

import {
  useCreateDocumentRecordMutation,
  useRejectDocumentRecordMutation,
  useSupersedeDocumentRecordMutation,
  useVerifyDocumentRecordMutation,
} from '../mutations/useEligibilityMutations'
import {
  useEligibilityDefsQuery,
  useSupplierDocumentRecordsQuery,
  useSupplierEligibilityQuery,
  useSupplierLinkedDocumentsQuery,
} from '../queries/useEligibilityQueries'
import type { SupplierDocumentRecord } from '../types/eligibility'
import type { Supplier } from '../types/suppliers'
import {
  applicableDefsForSupplier,
  buildChecklist,
  canSupersedeRecord,
  canVerifyOrRejectRecord,
  configLevelReasons,
  validateDocumentRecordForm,
  verificationStatusBadgeClass,
  type ChecklistRow,
  type DocumentRecordFieldErrors,
  type DocumentRecordFormState,
} from '../validation/eligibilityValidation'
import { mapSupplierErrorCode } from '../validation/supplierValidation'
import EligibilityReasonList from './EligibilityReasonList.vue'
import SupplierEligibilityBanner from './SupplierEligibilityBanner.vue'

const props = defineProps<{
  supplier: Supplier
}>()

const { t } = useI18n()
const toast = useToast()
const { can } = usePermissions()

const supplierId = computed(() => props.supplier.id)

const eligibilityQuery = useSupplierEligibilityQuery(supplierId)
const recordsQuery = useSupplierDocumentRecordsQuery(supplierId)
const eligibility = computed(() => eligibilityQuery.data.value ?? null)
const records = computed(() => recordsQuery.data.value ?? [])

const configIds = computed(() => {
  const value = eligibility.value
  if (!value) return []
  const list = value.configs?.length ? value.configs : value.config ? [value.config] : []
  return list.map((config) => config.id)
})
const defsQuery = useEligibilityDefsQuery(configIds)

const applicableDefs = computed(() =>
  applicableDefsForSupplier(
    defsQuery.data.value ?? [],
    props.supplier,
    eligibility.value?.applicable_requirement_keys ?? [],
  ),
)

const checklist = computed(() => buildChecklist(applicableDefs.value, records.value, eligibility.value))
const labels = computed<Record<string, string>>(() =>
  Object.fromEntries(checklist.value.map((row) => [row.requirementKey, row.label])),
)
const configReasons = computed(() => configLevelReasons(eligibility.value?.reasons))

const createMutation = useCreateDocumentRecordMutation()
const verifyMutation = useVerifyDocumentRecordMutation()
const rejectMutation = useRejectDocumentRecordMutation()
const supersedeMutation = useSupersedeDocumentRecordMutation()
const uploadMutation = useUploadDocumentMutation()

const expandedHistory = reactive<Record<string, boolean>>({})

function apiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) return t('suppliers.errors.generic')
  const mapped = mapSupplierErrorCode(error.code)
  if (mapped !== 'generic') return t(`suppliers.errors.${mapped}`)
  return error.message || t('suppliers.errors.generic')
}

function documentApiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) return t('documents.errors.generic')
  const mapped = mapDocumentErrorCode(error.code)
  if (mapped !== 'generic') return t(`documents.errors.${mapped}`)
  return error.message || t('documents.errors.generic')
}

/* ----------------------------- record drawer ----------------------------- */

type RecordMode = 'create' | 'supersede'

const recordDrawerOpen = ref(false)
const recordMode = ref<RecordMode>('create')
const recordRow = ref<ChecklistRow | null>(null)
const supersedeTarget = ref<SupplierDocumentRecord | null>(null)
const recordFormError = ref('')
const recordErrors = reactive<DocumentRecordFieldErrors>({})
const recordForm = reactive<DocumentRecordFormState>({
  requirement_def_id: '',
  document_id: '',
  expires_at: '',
})

const canPickLinkedDocuments = computed(() => can('documents.view'))
const linkedDocsQuery = useSupplierLinkedDocumentsQuery(supplierId, {
  enabled: computed(() => recordDrawerOpen.value && canPickLinkedDocuments.value),
})
const linkedDocumentOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('suppliers.documents.pickLinked') },
  ...(linkedDocsQuery.data.value ?? []).map((doc) => ({
    value: doc.id,
    label: `${doc.document_number} — ${doc.title}`,
  })),
])

const recordSubmitting = computed(
  () => createMutation.isPending.value || supersedeMutation.isPending.value,
)

function clearRecordErrors(): void {
  recordFormError.value = ''
  delete recordErrors.requirement_def_id
  delete recordErrors.document_id
  delete recordErrors.expires_at
}

function openCreateRecord(row: ChecklistRow): void {
  if (!row.def?.id) return
  clearRecordErrors()
  recordMode.value = 'create'
  recordRow.value = row
  supersedeTarget.value = null
  Object.assign(recordForm, { requirement_def_id: row.def.id, document_id: '', expires_at: '' })
  recordDrawerOpen.value = true
}

function openSupersede(row: ChecklistRow, record: SupplierDocumentRecord): void {
  clearRecordErrors()
  recordMode.value = 'supersede'
  recordRow.value = row
  supersedeTarget.value = record
  Object.assign(recordForm, {
    requirement_def_id: record.requirement_def_id,
    document_id: '',
    expires_at: '',
  })
  recordDrawerOpen.value = true
}

function closeRecordDrawer(): void {
  recordDrawerOpen.value = false
  uploadOpen.value = false
}

async function submitRecord(): Promise<void> {
  clearRecordErrors()
  const validation = validateDocumentRecordForm(recordForm, {
    requireDef: recordMode.value === 'create',
  })
  if (Object.keys(validation).length > 0) {
    Object.assign(recordErrors, validation)
    return
  }
  const expires = recordForm.expires_at.trim() || null
  try {
    if (recordMode.value === 'create') {
      await createMutation.mutateAsync({
        supplierId: props.supplier.id,
        payload: {
          requirement_def_id: Number(recordForm.requirement_def_id),
          document_id: Number(recordForm.document_id),
          expires_at: expires,
        },
      })
      toast.success(t('suppliers.documents.toasts.created'))
    } else if (supersedeTarget.value) {
      await supersedeMutation.mutateAsync({
        supplierId: props.supplier.id,
        recordId: supersedeTarget.value.id,
        payload: { document_id: Number(recordForm.document_id), expires_at: expires },
      })
      toast.success(t('suppliers.documents.toasts.superseded'))
    }
    recordDrawerOpen.value = false
  } catch (error) {
    recordFormError.value = apiMessage(error)
  }
}

/* ------------------------------- upload flow ------------------------------ */

const uploadOpen = ref(false)
const uploadError = ref('')
const uploadFieldErrors = reactive<Record<string, string>>({})
const emptyUploadForm = (): DocumentUploadFormState => ({
  file: null,
  title: '',
  description: '',
  category_id: '',
  linkable_type: 'supplier',
  linkable_id: props.supplier.id,
})
const uploadForm = reactive<DocumentUploadFormState>(emptyUploadForm())
const lockedLink = computed<LockedDocumentLink>(() => ({
  type: 'supplier',
  id: props.supplier.id,
  label: props.supplier.display_name,
}))

function openUpload(): void {
  Object.assign(uploadForm, emptyUploadForm())
  uploadError.value = ''
  Object.keys(uploadFieldErrors).forEach((k) => delete uploadFieldErrors[k])
  uploadOpen.value = true
}

async function submitUpload(): Promise<void> {
  uploadError.value = ''
  Object.keys(uploadFieldErrors).forEach((k) => delete uploadFieldErrors[k])
  const validation = validateDocumentUploadForm(uploadForm, lockedLink.value)
  Object.entries(validation).forEach(([key, value]) => {
    if (value) uploadFieldErrors[key] = t(`documents.validation.${value}`)
  })
  if (Object.keys(uploadFieldErrors).length > 0 || !uploadForm.file) return

  try {
    const document = await uploadMutation.mutateAsync({
      file: uploadForm.file,
      fields: {
        title: uploadForm.title.trim() || undefined,
        description: uploadForm.description.trim() || null,
        category_id: uploadForm.category_id === '' ? null : uploadForm.category_id,
        linkable_type: 'supplier',
        linkable_id: props.supplier.id,
      },
    })
    recordForm.document_id = document.id
    uploadOpen.value = false
    toast.success(t('suppliers.documents.uploadedLinked'))
  } catch (error) {
    uploadError.value = documentApiMessage(error)
  }
}

/* ------------------------------ verify / reject ---------------------------- */

type ActionKind = 'verify' | 'reject'

const actionOpen = ref(false)
const actionKind = ref<ActionKind>('verify')
const actionRecord = ref<SupplierDocumentRecord | null>(null)
const actionText = ref('')
const actionError = ref('')
const actionSubmitting = computed(
  () => verifyMutation.isPending.value || rejectMutation.isPending.value,
)

function openAction(kind: ActionKind, record: SupplierDocumentRecord): void {
  actionKind.value = kind
  actionRecord.value = record
  actionText.value = ''
  actionError.value = ''
  actionOpen.value = true
}

async function submitAction(): Promise<void> {
  if (!actionRecord.value) return
  actionError.value = ''
  try {
    if (actionKind.value === 'reject') {
      if (!actionText.value.trim()) {
        actionError.value = t('suppliers.documents.validation.reasonRequired')
        return
      }
      await rejectMutation.mutateAsync({
        supplierId: props.supplier.id,
        recordId: actionRecord.value.id,
        rejectionReason: actionText.value.trim(),
      })
      toast.success(t('suppliers.documents.toasts.rejected'))
    } else {
      await verifyMutation.mutateAsync({
        supplierId: props.supplier.id,
        recordId: actionRecord.value.id,
        comment: actionText.value,
      })
      toast.success(t('suppliers.documents.toasts.verified'))
    }
    actionOpen.value = false
  } catch (error) {
    // SoD (uploader ≠ verifier) and state errors are server-enforced.
    actionError.value = apiMessage(error)
  }
}

const inputClass = 'w-full rounded-xl border border-brand-border px-3 py-2 text-sm'
</script>

<template>
  <div class="space-y-4">
    <SupplierEligibilityBanner
      :eligibility="eligibility"
      :loading="eligibilityQuery.isLoading.value"
      :error="eligibilityQuery.isError.value"
      :labels="labels"
    />

    <section class="rounded-2xl border border-brand-border bg-brand-surface p-5">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h3 class="font-bold text-brand-text">{{ t('suppliers.documents.checklistTitle') }}</h3>
          <p class="mt-1 text-sm text-brand-text-secondary">{{ t('suppliers.documents.subtitle') }}</p>
        </div>
      </div>

      <div v-if="eligibilityQuery.isLoading.value || recordsQuery.isLoading.value" class="mt-4 text-sm text-brand-text-muted">
        {{ t('suppliers.documents.loading') }}
      </div>
      <div v-else-if="recordsQuery.isError.value" class="mt-4 text-sm text-red-600" role="alert">
        {{ t('suppliers.documents.error') }}
        <button type="button" class="ms-2 font-semibold text-brand-primary" @click="() => recordsQuery.refetch()">
          {{ t('suppliers.retry') }}
        </button>
      </div>
      <template v-else>
        <div v-if="configReasons.length > 0" class="mt-4 rounded-xl bg-amber-50 p-3 text-amber-950">
          <EligibilityReasonList :reasons="configReasons" />
        </div>

        <p v-if="checklist.length === 0" class="mt-4 text-sm text-brand-text-muted">
          {{ t('suppliers.documents.checklistEmpty') }}
        </p>

        <ul v-else class="mt-4 space-y-3">
          <li
            v-for="row in checklist"
            :key="row.requirementKey"
            class="rounded-xl border border-brand-border p-4"
            data-testid="checklist-row"
          >
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <p class="font-semibold text-brand-text">{{ row.label }}</p>
                <p class="mt-0.5 text-xs text-brand-text-muted">
                  <span class="font-mono">{{ row.requirementKey }}</span>
                  ·
                  {{ row.isMandatory ? t('suppliers.documents.mandatory') : t('suppliers.documents.optional') }}
                </p>
              </div>
              <span
                v-if="row.current"
                class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                :class="verificationStatusBadgeClass(row.current.verification_status)"
              >
                {{ t(`suppliers.documents.status.${row.current.verification_status}`) }}
              </span>
              <span v-else class="text-xs text-brand-text-muted">{{ t('suppliers.documents.noRecord') }}</span>
            </div>

            <div v-if="row.reasons.length > 0" class="mt-2 text-amber-900">
              <EligibilityReasonList :reasons="row.reasons" :labels="labels" />
            </div>

            <dl v-if="row.current" class="mt-3 grid gap-2 text-sm sm:grid-cols-3">
              <div>
                <dt class="text-xs text-brand-text-muted">{{ t('suppliers.documents.documentNumber') }}</dt>
                <dd class="font-mono text-xs">{{ row.current.document_number || `#${row.current.document_id}` }}</dd>
              </div>
              <div>
                <dt class="text-xs text-brand-text-muted">{{ t('suppliers.documents.expiresAt') }}</dt>
                <dd dir="ltr" class="text-start">{{ row.current.expires_at || t('suppliers.documents.noExpiry') }}</dd>
              </div>
              <div v-if="row.current.verified_by">
                <dt class="text-xs text-brand-text-muted">{{ t('suppliers.documents.verifiedBy') }}</dt>
                <dd>{{ row.current.verified_by.name || '—' }}</dd>
              </div>
              <div v-if="row.current.uploaded_by">
                <dt class="text-xs text-brand-text-muted">{{ t('suppliers.documents.uploadedBy') }}</dt>
                <dd>{{ row.current.uploaded_by.name || '—' }}</dd>
              </div>
              <div v-if="row.current.rejection_reason" class="sm:col-span-3">
                <dt class="text-xs text-brand-text-muted">{{ t('suppliers.documents.rejectionReason') }}</dt>
                <dd>{{ row.current.rejection_reason }}</dd>
              </div>
            </dl>

            <PermissionGuard permission="suppliers.manage_documents">
              <div class="mt-3 flex flex-wrap gap-2">
                <template v-if="!row.current">
                  <button
                    v-if="row.def?.id"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-3 py-2 text-sm font-semibold text-white"
                    @click="openCreateRecord(row)"
                  >
                    <Plus class="h-4 w-4" />
                    {{ t('suppliers.documents.addRecord') }}
                  </button>
                  <p v-else class="text-xs text-brand-text-muted">{{ t('suppliers.documents.requirementMissing') }}</p>
                </template>
                <template v-else>
                  <button
                    v-if="canVerifyOrRejectRecord(row.current)"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-950"
                    @click="openAction('verify', row.current)"
                  >
                    <Check class="h-4 w-4" />
                    {{ t('suppliers.documents.verify') }}
                  </button>
                  <button
                    v-if="canVerifyOrRejectRecord(row.current)"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-900"
                    @click="openAction('reject', row.current)"
                  >
                    <X class="h-4 w-4" />
                    {{ t('suppliers.documents.reject') }}
                  </button>
                  <button
                    v-if="canSupersedeRecord(row.current)"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl border border-brand-border px-3 py-2 text-sm font-semibold"
                    @click="openSupersede(row, row.current)"
                  >
                    <Repeat class="h-4 w-4" />
                    {{ t('suppliers.documents.supersede') }}
                  </button>
                </template>
              </div>
            </PermissionGuard>

            <div v-if="row.history.length > 0" class="mt-3 border-t border-brand-border/60 pt-2">
              <button
                type="button"
                class="inline-flex items-center gap-1 text-xs font-semibold text-brand-text-secondary"
                :aria-expanded="Boolean(expandedHistory[row.requirementKey])"
                @click="expandedHistory[row.requirementKey] = !expandedHistory[row.requirementKey]"
              >
                <ChevronUp v-if="expandedHistory[row.requirementKey]" class="h-3 w-3" />
                <ChevronDown v-else class="h-3 w-3" />
                {{
                  expandedHistory[row.requirementKey]
                    ? t('suppliers.documents.historyHide')
                    : t('suppliers.documents.historyToggle', { count: row.history.length })
                }}
              </button>
              <ul v-if="expandedHistory[row.requirementKey]" class="mt-2 space-y-1">
                <li
                  v-for="record in row.history"
                  :key="record.id"
                  class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-brand-bg/60 px-3 py-2 text-xs"
                >
                  <span class="font-mono">{{ record.document_number || `#${record.document_id}` }}</span>
                  <span dir="ltr">{{ record.expires_at || t('suppliers.documents.noExpiry') }}</span>
                  <span
                    class="inline-flex rounded-full px-2 py-0.5 font-semibold"
                    :class="verificationStatusBadgeClass(record.verification_status)"
                  >
                    {{ t(`suppliers.documents.status.${record.verification_status}`) }}
                  </span>
                </li>
              </ul>
            </div>
          </li>
        </ul>
      </template>
    </section>

    <!-- Create / supersede record drawer -->
    <Teleport to="body">
      <div
        v-if="recordDrawerOpen && !uploadOpen"
        class="fixed inset-0 z-[70] flex justify-start"
      >
        <button
          type="button"
          class="absolute inset-0 bg-black/40"
          :aria-label="t('suppliers.closeDrawer')"
          @click="closeRecordDrawer"
        />
        <aside
          class="relative z-10 flex h-full w-full max-w-lg flex-col bg-brand-surface shadow-2xl"
          role="dialog"
          aria-modal="true"
        >
          <header class="flex items-start justify-between border-b border-brand-border px-5 py-4">
            <div>
              <h2 class="text-lg font-bold text-brand-text">
                {{ recordMode === 'create' ? t('suppliers.documents.addRecordTitle') : t('suppliers.documents.supersedeTitle') }}
              </h2>
              <p v-if="recordRow" class="mt-1 text-sm text-brand-text-secondary">{{ recordRow.label }}</p>
            </div>
            <button type="button" class="rounded-lg p-1 hover:bg-brand-bg" :aria-label="t('suppliers.closeDrawer')" @click="closeRecordDrawer">
              <X class="h-5 w-5" />
            </button>
          </header>

          <form class="flex flex-1 flex-col overflow-hidden" @submit.prevent="submitRecord">
            <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
              <p v-if="recordFormError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">
                {{ recordFormError }}
              </p>
              <p class="rounded-lg bg-brand-bg px-3 py-2 text-xs text-brand-text-secondary">
                {{ t('suppliers.documents.uploadLinkNote') }}
              </p>

              <PermissionGuard permission="documents.upload">
                <button
                  type="button"
                  class="inline-flex items-center gap-2 rounded-xl border border-brand-border px-3 py-2 text-sm font-semibold"
                  @click="openUpload"
                >
                  <Upload class="h-4 w-4" />
                  {{ t('suppliers.documents.uploadAndLink') }}
                </button>
              </PermissionGuard>

              <div v-if="canPickLinkedDocuments && (linkedDocsQuery.data.value?.length ?? 0) > 0">
                <label class="mb-1 block text-sm font-semibold text-brand-text">{{ t('suppliers.documents.pickLinked') }}</label>
                <AppSelect
                  :model-value="recordForm.document_id"
                  :options="linkedDocumentOptions"
                  searchable
                  @update:model-value="recordForm.document_id = $event === '' || $event == null ? '' : Number($event)"
                />
              </div>

              <div>
                <label class="mb-1 block text-sm font-semibold text-brand-text" for="rec-document-id">
                  {{ t('suppliers.documents.documentId') }}
                </label>
                <input
                  id="rec-document-id"
                  :value="recordForm.document_id"
                  type="number"
                  min="1"
                  dir="ltr"
                  :class="[inputClass, 'text-start']"
                  @input="recordForm.document_id = ($event.target as HTMLInputElement).value === '' ? '' : Number(($event.target as HTMLInputElement).value)"
                />
                <p class="mt-1 text-xs text-brand-text-muted">{{ t('suppliers.documents.documentIdHint') }}</p>
                <p v-if="recordErrors.document_id" class="mt-1 text-xs text-red-600">
                  {{ t('suppliers.documents.validation.documentId') }}
                </p>
              </div>

              <div>
                <label class="mb-1 block text-sm font-semibold text-brand-text" for="rec-expires-at">
                  {{ t('suppliers.documents.expiresAtField') }}
                </label>
                <input
                  id="rec-expires-at"
                  v-model="recordForm.expires_at"
                  type="date"
                  dir="ltr"
                  :class="[inputClass, 'text-start']"
                />
                <p v-if="recordErrors.expires_at" class="mt-1 text-xs text-red-600">
                  {{ t('suppliers.documents.validation.date') }}
                </p>
              </div>
            </div>

            <footer class="flex justify-end gap-2 border-t border-brand-border px-5 py-4">
              <button
                type="button"
                class="rounded-xl border border-brand-border px-4 py-2 text-sm font-semibold"
                :disabled="recordSubmitting"
                @click="closeRecordDrawer"
              >
                {{ t('suppliers.cancel') }}
              </button>
              <button
                type="submit"
                class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
                :disabled="recordSubmitting"
              >
                <Loader2 v-if="recordSubmitting" class="h-4 w-4 animate-spin" />
                {{ t('suppliers.save') }}
              </button>
            </footer>
          </form>
        </aside>
      </div>
    </Teleport>

    <DocumentUploadDrawer
      v-if="uploadOpen"
      :open="uploadOpen"
      :form="uploadForm"
      :form-error="uploadError"
      :field-errors="uploadFieldErrors"
      :submitting="uploadMutation.isPending.value"
      :locked-link="lockedLink"
      @close="uploadOpen = false"
      @submit="submitUpload"
      @update:form="Object.assign(uploadForm, $event)"
    />

    <!-- Verify / reject dialog -->
    <Teleport to="body">
      <div
        v-if="actionOpen"
        class="fixed inset-0 z-[80] flex items-center justify-center bg-black/40 p-4"
        role="dialog"
        aria-modal="true"
      >
        <div class="w-full max-w-md rounded-2xl bg-brand-surface p-5 shadow-xl">
          <h4 class="text-base font-bold text-brand-text">
            {{ actionKind === 'verify' ? t('suppliers.documents.verifyTitle') : t('suppliers.documents.rejectTitle') }}
          </h4>
          <p class="mt-1 text-sm text-brand-text-secondary">
            {{ actionKind === 'verify' ? t('suppliers.documents.verifyBody') : t('suppliers.documents.rejectBody') }}
          </p>
          <textarea
            v-model="actionText"
            rows="3"
            :class="[inputClass, 'mt-3']"
            :placeholder="actionKind === 'verify' ? t('suppliers.documents.verifyCommentPlaceholder') : t('suppliers.reasonPlaceholder')"
          />
          <p v-if="actionError" class="mt-2 text-sm text-red-600" role="alert">{{ actionError }}</p>
          <div class="mt-4 flex justify-end gap-2">
            <button
              type="button"
              class="rounded-xl border border-brand-border px-4 py-2 text-sm font-semibold"
              @click="actionOpen = false"
            >
              {{ t('suppliers.cancel') }}
            </button>
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
              :class="actionKind === 'verify' ? 'bg-brand-primary' : 'bg-red-700'"
              :disabled="actionSubmitting"
              @click="submitAction"
            >
              <Loader2 v-if="actionSubmitting" class="h-4 w-4 animate-spin" />
              {{ actionKind === 'verify' ? t('suppliers.documents.verify') : t('suppliers.documents.reject') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
