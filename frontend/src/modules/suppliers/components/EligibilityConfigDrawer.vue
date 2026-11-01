<script setup lang="ts">
import { computed, nextTick, onUnmounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { CheckCheck, Copy, Eye, Loader2, Pencil, Plus, Trash2, X } from 'lucide-vue-next'

import { ApiError } from '@/shared/api/http'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'

import {
  useApproveEligibilitySetMutation,
  useCreateEligibilitySetMutation,
  useNewEligibilityDraftMutation,
  useUpdateEligibilitySetMutation,
} from '../mutations/useEligibilityMutations'
import { useSupplierCategoriesQuery } from '../queries/useCategoriesQuery'
import { useEligibilitySetQuery, useEligibilitySetsQuery } from '../queries/useEligibilityQueries'
import type {
  EligibilityConfigStatus,
  EligibilityDefFormRow,
  EligibilitySetFormState,
} from '../types/eligibility'
import type { SupplierType } from '../types/suppliers'
import {
  buildDefPayloads,
  canCreateNewDraftFrom,
  canEditEligibilitySet,
  configStatusBadgeClass,
  emptyDefRow,
  emptySetForm,
  hasEligibilitySetErrors,
  setToFormState,
  validateEligibilitySetForm,
  type EligibilitySetFieldErrors,
} from '../validation/eligibilityValidation'
import { mapSupplierErrorCode, SUPPLIER_TYPES } from '../validation/supplierValidation'

const props = defineProps<{
  open: boolean
}>()

const emit = defineEmits<{
  close: []
}>()

type Mode = 'list' | 'view' | 'form'

const { t } = useI18n()
const toast = useToast()
const { confirm } = useConfirm()
const { can } = usePermissions()

const mode = ref<Mode>('list')
const selectedId = ref<number | null>(null)
const editingId = ref<number | null>(null)
const statusFilter = ref<EligibilityConfigStatus | ''>('')
const formError = ref('')
const actionError = ref('')
const form = reactive<EligibilitySetFormState>(emptySetForm())
const errors = reactive<EligibilitySetFieldErrors>({ defRows: {} })
const approveComment = ref('')
const approveDialogOpen = ref(false)

const listParams = computed(() => ({
  status: statusFilter.value || undefined,
  per_page: 50,
}))
const listQuery = useEligibilitySetsQuery(listParams, { enabled: computed(() => props.open) })
const sets = computed(() => listQuery.data.value?.data ?? [])

const detailQuery = useEligibilitySetQuery(selectedId, {
  enabled: computed(() => props.open && mode.value !== 'list'),
})
const detail = computed(() => detailQuery.data.value ?? null)

const { data: categoriesData } = useSupplierCategoriesQuery(
  { is_active: true, per_page: 100 },
  { enabled: computed(() => props.open && mode.value === 'form') },
)

const createMutation = useCreateEligibilitySetMutation()
const updateMutation = useUpdateEligibilitySetMutation()
const approveMutation = useApproveEligibilitySetMutation()
const newDraftMutation = useNewEligibilityDraftMutation()

const isSubmitting = computed(
  () => createMutation.isPending.value || updateMutation.isPending.value,
)
const isEditing = computed(() => editingId.value != null)
const canManage = computed(() => can('suppliers.manage_eligibility_config'))

const statusOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('suppliers.filters.allStatuses') },
  { value: 'draft', label: t('suppliers.eligibility.configStatus.draft') },
  { value: 'approved', label: t('suppliers.eligibility.configStatus.approved') },
])

const typeOptions = computed<AppSelectOption[]>(() =>
  SUPPLIER_TYPES.map((type) => ({ value: type, label: t(`suppliers.type.${type}`) })),
)

const categoryOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('suppliers.eligibility.fields.anyCategory') },
  ...(categoriesData.value?.data ?? []).map((c) => ({ value: c.id, label: c.name })),
])

let previouslyFocused: HTMLElement | null = null

function apiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) return t('suppliers.errors.generic')
  const mapped = mapSupplierErrorCode(error.code)
  if (mapped !== 'generic') return t(`suppliers.errors.${mapped}`)
  return error.message || t('suppliers.errors.generic')
}

function resetErrors(): void {
  formError.value = ''
  actionError.value = ''
  errors.config_key = undefined
  errors.name = undefined
  errors.defs = undefined
  errors.defRows = {}
}

function backToList(): void {
  mode.value = 'list'
  selectedId.value = null
  editingId.value = null
  resetErrors()
}

function openView(id: number): void {
  resetErrors()
  selectedId.value = id
  mode.value = 'view'
}

function openCreate(): void {
  resetErrors()
  Object.assign(form, emptySetForm())
  editingId.value = null
  selectedId.value = null
  mode.value = 'form'
}

function openEdit(): void {
  if (!detail.value || !canEditEligibilitySet(detail.value)) return
  resetErrors()
  Object.assign(form, setToFormState(detail.value))
  editingId.value = detail.value.id
  mode.value = 'form'
}

function addDef(): void {
  form.defs.push(emptyDefRow())
}

function removeDef(index: number): void {
  form.defs.splice(index, 1)
}

function patchDef(index: number, partial: Partial<EligibilityDefFormRow>): void {
  const row = form.defs[index]
  if (row) Object.assign(row, partial)
}

async function submitForm(): Promise<void> {
  resetErrors()
  const validation = validateEligibilitySetForm(form, { isCreate: !isEditing.value })
  if (hasEligibilitySetErrors(validation)) {
    Object.assign(errors, validation)
    return
  }
  try {
    if (editingId.value != null) {
      const updated = await updateMutation.mutateAsync({
        id: editingId.value,
        payload: { name: form.name.trim(), requirement_defs: buildDefPayloads(form.defs) },
      })
      toast.success(t('suppliers.eligibility.toasts.updated'))
      openView(updated.id)
    } else {
      const created = await createMutation.mutateAsync({
        config_key: form.config_key.trim(),
        name: form.name.trim(),
        requirement_defs: buildDefPayloads(form.defs),
      })
      toast.success(t('suppliers.eligibility.toasts.created'))
      openView(created.id)
    }
  } catch (error) {
    formError.value = apiMessage(error)
  }
}

function openApprove(): void {
  approveComment.value = ''
  actionError.value = ''
  approveDialogOpen.value = true
}

async function submitApprove(): Promise<void> {
  if (!detail.value) return
  actionError.value = ''
  try {
    await approveMutation.mutateAsync({ id: detail.value.id, comment: approveComment.value })
    toast.success(t('suppliers.eligibility.toasts.approved'))
    approveDialogOpen.value = false
  } catch (error) {
    // SoD and state errors are server-enforced; surface them.
    actionError.value = apiMessage(error)
    approveDialogOpen.value = false
  }
}

async function onNewDraft(): Promise<void> {
  if (!detail.value) return
  const ok = await confirm({
    title: t('suppliers.eligibility.newDraftTitle'),
    message: t('suppliers.eligibility.newDraftBody'),
    confirmLabel: t('suppliers.eligibility.createDraft'),
    cancelLabel: t('suppliers.cancel'),
    variant: 'primary',
  })
  if (!ok) return
  actionError.value = ''
  try {
    const created = await newDraftMutation.mutateAsync(detail.value.id)
    toast.success(t('suppliers.eligibility.toasts.newDraft'))
    openView(created.id)
  } catch (error) {
    actionError.value = apiMessage(error)
  }
}

function onKeydown(event: KeyboardEvent): void {
  if (
    event.key === 'Escape' &&
    props.open &&
    !isSubmitting.value &&
    !approveDialogOpen.value
  ) {
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
    backToList()
    approveDialogOpen.value = false
    document.removeEventListener('keydown', onKeydown)
    await nextTick()
    previouslyFocused?.focus?.()
    previouslyFocused = null
  },
)

onUnmounted(() => {
  document.removeEventListener('keydown', onKeydown)
})

const inputClass = 'w-full rounded-xl border border-brand-border px-3 py-2 text-sm'
</script>

<template>
  <Teleport to="body">
    <Transition name="elig-drawer">
      <div v-if="open" class="fixed inset-0 z-[70] flex justify-start">
        <button
          type="button"
          class="absolute inset-0 bg-black/40"
          :aria-label="t('suppliers.closeDrawer')"
          @click="emit('close')"
        />
        <aside
          class="relative z-10 flex h-full w-full max-w-3xl flex-col bg-brand-surface shadow-2xl"
          role="dialog"
          aria-modal="true"
          :aria-label="t('suppliers.eligibility.configTitle')"
        >
          <header class="flex items-start justify-between border-b border-brand-border px-5 py-4">
            <div>
              <h2 class="text-lg font-bold text-brand-text">
                {{
                  mode === 'form'
                    ? isEditing
                      ? t('suppliers.eligibility.editTitle')
                      : t('suppliers.eligibility.createTitle')
                    : mode === 'view'
                      ? t('suppliers.eligibility.viewTitle')
                      : t('suppliers.eligibility.configTitle')
                }}
              </h2>
              <p class="mt-1 text-sm text-brand-text-secondary">
                {{ t('suppliers.eligibility.configSubtitle') }}
              </p>
            </div>
            <button
              type="button"
              class="rounded-lg p-1 hover:bg-brand-bg"
              :aria-label="t('suppliers.closeDrawer')"
              @click="emit('close')"
            >
              <X class="h-5 w-5" />
            </button>
          </header>

          <!-- LIST -->
          <div v-if="mode === 'list'" class="flex-1 overflow-y-auto px-5 py-4">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
              <div class="w-48">
                <AppSelect v-model="statusFilter" :options="statusOptions" :label="t('suppliers.fields.status')" />
              </div>
              <PermissionGuard permission="suppliers.manage_eligibility_config">
                <button
                  type="button"
                  class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-3 py-2 text-sm font-semibold text-white"
                  @click="openCreate"
                >
                  <Plus class="h-4 w-4" />
                  {{ t('suppliers.eligibility.createDraft') }}
                </button>
              </PermissionGuard>
            </div>
            <p v-if="!canManage" class="mb-3 text-xs text-brand-text-muted">
              {{ t('suppliers.eligibility.readOnlyHint') }}
            </p>

            <div v-if="listQuery.isLoading.value" class="py-8 text-center text-sm text-brand-text-muted">
              {{ t('suppliers.eligibility.listLoading') }}
            </div>
            <div v-else-if="listQuery.isError.value" class="py-8 text-center">
              <p class="text-sm text-red-600">{{ t('suppliers.errors.generic') }}</p>
              <button type="button" class="mt-2 text-sm font-semibold text-brand-primary" @click="() => listQuery.refetch()">
                {{ t('suppliers.retry') }}
              </button>
            </div>
            <div v-else-if="sets.length === 0" class="py-8 text-center text-sm text-brand-text-muted">
              {{ t('suppliers.eligibility.listEmpty') }}
            </div>
            <div v-else class="overflow-x-auto rounded-xl border border-brand-border">
              <table class="min-w-full text-sm">
                <thead class="bg-brand-bg/50 text-start text-brand-text-secondary">
                  <tr>
                    <th class="px-3 py-2 text-start font-semibold">{{ t('suppliers.eligibility.columns.configKey') }}</th>
                    <th class="px-3 py-2 text-start font-semibold">{{ t('suppliers.eligibility.columns.name') }}</th>
                    <th class="px-3 py-2 text-start font-semibold">{{ t('suppliers.eligibility.columns.version') }}</th>
                    <th class="px-3 py-2 text-start font-semibold">{{ t('suppliers.eligibility.columns.status') }}</th>
                    <th class="px-3 py-2 text-start font-semibold">{{ t('suppliers.eligibility.columns.actions') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="set in sets" :key="set.id" class="border-t border-brand-border/60">
                    <td class="px-3 py-2 font-mono text-xs">{{ set.config_key }}</td>
                    <td class="px-3 py-2 font-semibold">{{ set.name }}</td>
                    <td class="px-3 py-2">{{ set.version_no }}</td>
                    <td class="px-3 py-2">
                      <span
                        class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                        :class="configStatusBadgeClass(set.status)"
                      >
                        {{ t(`suppliers.eligibility.configStatus.${set.status}`) }}
                      </span>
                    </td>
                    <td class="px-3 py-2">
                      <button
                        type="button"
                        class="rounded-lg p-2 hover:bg-brand-bg"
                        :aria-label="t('suppliers.viewDetails')"
                        @click="openView(set.id)"
                      >
                        <Eye class="h-4 w-4" />
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- VIEW -->
          <div v-else-if="mode === 'view'" class="flex-1 overflow-y-auto px-5 py-4">
            <button type="button" class="mb-4 text-sm font-semibold text-brand-primary" @click="backToList">
              {{ t('suppliers.eligibility.back') }}
            </button>

            <div v-if="detailQuery.isLoading.value" class="py-8 text-center text-sm text-brand-text-muted">
              {{ t('suppliers.eligibility.listLoading') }}
            </div>
            <div v-else-if="detailQuery.isError.value || !detail" class="py-8 text-center text-sm text-red-600">
              {{ t('suppliers.errors.generic') }}
            </div>
            <template v-else>
              <p v-if="actionError" class="mb-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-950" role="alert">
                {{ actionError }}
              </p>

              <div class="rounded-xl border border-brand-border p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <p class="font-mono text-xs text-brand-text-muted">{{ detail.config_key }}</p>
                    <h3 class="text-base font-bold text-brand-text">{{ detail.name }}</h3>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                      <span
                        class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                        :class="configStatusBadgeClass(detail.status)"
                      >
                        {{ t(`suppliers.eligibility.configStatus.${detail.status}`) }}
                      </span>
                      <span class="text-brand-text-secondary">
                        {{ t('suppliers.eligibility.versionLabel', { version: detail.version_no }) }}
                      </span>
                    </div>
                  </div>
                  <div class="flex flex-wrap gap-2">
                    <PermissionGuard permission="suppliers.manage_eligibility_config">
                      <button
                        v-if="canEditEligibilitySet(detail)"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl border border-brand-border px-3 py-2 text-sm font-semibold"
                        @click="openEdit"
                      >
                        <Pencil class="h-4 w-4" />
                        {{ t('suppliers.eligibility.editDraft') }}
                      </button>
                      <button
                        v-if="canCreateNewDraftFrom(detail)"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl border border-brand-border px-3 py-2 text-sm font-semibold disabled:opacity-60"
                        :disabled="newDraftMutation.isPending.value"
                        @click="onNewDraft"
                      >
                        <Copy class="h-4 w-4" />
                        {{ t('suppliers.eligibility.newDraft') }}
                      </button>
                    </PermissionGuard>
                    <PermissionGuard permission="suppliers.approve_eligibility_config">
                      <button
                        v-if="canEditEligibilitySet(detail)"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-3 py-2 text-sm font-semibold text-white disabled:opacity-60"
                        :disabled="approveMutation.isPending.value"
                        @click="openApprove"
                      >
                        <CheckCheck class="h-4 w-4" />
                        {{ t('suppliers.eligibility.approve') }}
                      </button>
                    </PermissionGuard>
                  </div>
                </div>
                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                  <div>
                    <dt class="text-xs text-brand-text-muted">{{ t('suppliers.eligibility.authoredBy') }}</dt>
                    <dd class="font-semibold">{{ detail.authored_by?.name || '—' }}</dd>
                  </div>
                  <div>
                    <dt class="text-xs text-brand-text-muted">{{ t('suppliers.eligibility.approvedBy') }}</dt>
                    <dd class="font-semibold">{{ detail.approved_by?.name || '—' }}</dd>
                  </div>
                  <div>
                    <dt class="text-xs text-brand-text-muted">{{ t('suppliers.eligibility.approvedAt') }}</dt>
                    <dd class="font-semibold" dir="ltr">{{ detail.approved_at ? detail.approved_at.slice(0, 10) : '—' }}</dd>
                  </div>
                </dl>
                <p v-if="detail.status === 'approved'" class="mt-3 text-xs text-brand-text-muted">
                  {{ t('suppliers.eligibility.immutableHint') }}
                </p>
              </div>

              <h4 class="mb-2 mt-5 text-sm font-bold text-brand-text">{{ t('suppliers.eligibility.defsTitle') }}</h4>
              <p v-if="(detail.requirement_defs?.length ?? 0) === 0" class="text-sm text-brand-text-muted">
                {{ t('suppliers.eligibility.noDefs') }}
              </p>
              <ul v-else class="space-y-2">
                <li
                  v-for="def in detail.requirement_defs"
                  :key="def.id ?? def.requirement_key"
                  class="rounded-xl border border-brand-border px-3 py-2 text-sm"
                >
                  <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="font-semibold">{{ def.label_ar }}</span>
                    <span class="font-mono text-xs text-brand-text-muted">{{ def.requirement_key }}</span>
                  </div>
                  <p class="mt-1 text-xs text-brand-text-secondary">
                    {{ t(`suppliers.type.${def.supplier_type}`) }}
                    ·
                    {{ def.is_mandatory ? t('suppliers.documents.mandatory') : t('suppliers.documents.optional') }}
                    <template v-if="def.country_code"> · {{ def.country_code }}</template>
                    <template v-if="def.default_validity_days">
                      · {{ t('suppliers.eligibility.fields.validityDays') }}: {{ def.default_validity_days }}
                    </template>
                  </p>
                </li>
              </ul>
            </template>
          </div>

          <!-- FORM -->
          <form
            v-else
            class="flex flex-1 flex-col overflow-hidden"
            @submit.prevent="submitForm"
          >
            <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
              <p v-if="formError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">
                {{ formError }}
              </p>

              <div class="grid gap-3 sm:grid-cols-2">
                <div>
                  <label class="mb-1 block text-sm font-semibold text-brand-text" for="elig-config-key">
                    {{ t('suppliers.eligibility.fields.configKey') }}
                  </label>
                  <input
                    id="elig-config-key"
                    v-model="form.config_key"
                    type="text"
                    dir="ltr"
                    :disabled="isEditing"
                    :class="[inputClass, 'text-start disabled:bg-brand-bg']"
                  />
                  <p class="mt-1 text-xs text-brand-text-muted">{{ t('suppliers.eligibility.fields.configKeyHint') }}</p>
                  <p v-if="errors.config_key" class="mt-1 text-xs text-red-600">
                    {{ t(errors.config_key === 'required' ? 'suppliers.validation.required' : `suppliers.eligibility.validation.${errors.config_key}`) }}
                  </p>
                </div>
                <div>
                  <label class="mb-1 block text-sm font-semibold text-brand-text" for="elig-name">
                    {{ t('suppliers.eligibility.fields.name') }}
                  </label>
                  <input id="elig-name" v-model="form.name" type="text" :class="inputClass" />
                  <p v-if="errors.name" class="mt-1 text-xs text-red-600">{{ t('suppliers.validation.required') }}</p>
                </div>
              </div>

              <div class="flex items-center justify-between">
                <h4 class="text-sm font-bold text-brand-text">{{ t('suppliers.eligibility.defsTitle') }}</h4>
                <button
                  type="button"
                  class="inline-flex items-center gap-1 rounded-xl border border-brand-border px-3 py-1.5 text-sm font-semibold"
                  @click="addDef"
                >
                  <Plus class="h-4 w-4" />
                  {{ t('suppliers.eligibility.addDef') }}
                </button>
              </div>
              <p v-if="errors.defs" class="text-xs text-red-600">
                {{ t(`suppliers.eligibility.validation.${errors.defs}`) }}
              </p>

              <div
                v-for="(row, index) in form.defs"
                :key="index"
                class="space-y-3 rounded-xl border border-brand-border p-3"
              >
                <div class="grid gap-3 sm:grid-cols-2">
                  <div>
                    <label class="mb-1 block text-xs font-semibold">{{ t('suppliers.eligibility.fields.requirementKey') }}</label>
                    <input
                      :value="row.requirement_key"
                      type="text"
                      dir="ltr"
                      :class="[inputClass, 'text-start']"
                      @input="patchDef(index, { requirement_key: ($event.target as HTMLInputElement).value })"
                    />
                    <p v-if="errors.defRows[index]?.requirement_key" class="mt-1 text-xs text-red-600">
                      {{ t(errors.defRows[index]?.requirement_key === 'required' ? 'suppliers.validation.required' : `suppliers.eligibility.validation.${errors.defRows[index]?.requirement_key}`) }}
                    </p>
                  </div>
                  <div>
                    <label class="mb-1 block text-xs font-semibold">{{ t('suppliers.eligibility.fields.labelAr') }}</label>
                    <input
                      :value="row.label_ar"
                      type="text"
                      :class="inputClass"
                      @input="patchDef(index, { label_ar: ($event.target as HTMLInputElement).value })"
                    />
                    <p v-if="errors.defRows[index]?.label_ar" class="mt-1 text-xs text-red-600">
                      {{ t('suppliers.validation.required') }}
                    </p>
                  </div>
                  <div>
                    <label class="mb-1 block text-xs font-semibold">{{ t('suppliers.eligibility.fields.supplierType') }}</label>
                    <AppSelect
                      :model-value="row.supplier_type"
                      :options="typeOptions"
                      @update:model-value="patchDef(index, { supplier_type: $event as SupplierType })"
                    />
                  </div>
                  <div>
                    <label class="mb-1 block text-xs font-semibold">{{ t('suppliers.eligibility.fields.category') }}</label>
                    <AppSelect
                      :model-value="row.supplier_category_id"
                      :options="categoryOptions"
                      searchable
                      @update:model-value="patchDef(index, { supplier_category_id: $event === '' || $event == null ? '' : Number($event) })"
                    />
                  </div>
                  <div>
                    <label class="mb-1 block text-xs font-semibold">{{ t('suppliers.eligibility.fields.countryCode') }}</label>
                    <input
                      :value="row.country_code"
                      type="text"
                      maxlength="2"
                      dir="ltr"
                      :class="[inputClass, 'text-start uppercase']"
                      @input="patchDef(index, { country_code: ($event.target as HTMLInputElement).value.toUpperCase() })"
                    />
                    <p v-if="errors.defRows[index]?.country_code" class="mt-1 text-xs text-red-600">
                      {{ t('suppliers.eligibility.validation.countryCode') }}
                    </p>
                  </div>
                  <div>
                    <label class="mb-1 block text-xs font-semibold">{{ t('suppliers.eligibility.fields.validityDays') }}</label>
                    <input
                      :value="row.default_validity_days"
                      type="number"
                      min="1"
                      max="3650"
                      dir="ltr"
                      :class="[inputClass, 'text-start']"
                      @input="patchDef(index, { default_validity_days: ($event.target as HTMLInputElement).value === '' ? '' : Number(($event.target as HTMLInputElement).value) })"
                    />
                    <p v-if="errors.defRows[index]?.default_validity_days" class="mt-1 text-xs text-red-600">
                      {{ t('suppliers.eligibility.validation.validityDays') }}
                    </p>
                  </div>
                </div>
                <div class="flex items-center justify-between">
                  <label class="flex items-center gap-2 text-sm">
                    <input
                      type="checkbox"
                      :checked="row.is_mandatory"
                      @change="patchDef(index, { is_mandatory: ($event.target as HTMLInputElement).checked })"
                    />
                    {{ t('suppliers.eligibility.fields.isMandatory') }}
                  </label>
                  <button
                    type="button"
                    class="inline-flex items-center gap-1 text-sm text-red-600"
                    :aria-label="t('suppliers.eligibility.removeDef')"
                    @click="removeDef(index)"
                  >
                    <Trash2 class="h-4 w-4" />
                    {{ t('suppliers.eligibility.removeDef') }}
                  </button>
                </div>
              </div>
            </div>

            <footer class="flex justify-end gap-2 border-t border-brand-border px-5 py-4">
              <button
                type="button"
                class="rounded-xl border border-brand-border px-4 py-2 text-sm font-semibold"
                :disabled="isSubmitting"
                @click="isEditing && selectedId != null ? openView(selectedId) : backToList()"
              >
                {{ t('suppliers.cancel') }}
              </button>
              <button
                type="submit"
                class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
                :disabled="isSubmitting"
              >
                <Loader2 v-if="isSubmitting" class="h-4 w-4 animate-spin" />
                {{ t('suppliers.save') }}
              </button>
            </footer>
          </form>

          <!-- APPROVE DIALOG -->
          <div
            v-if="approveDialogOpen"
            class="absolute inset-0 z-20 flex items-center justify-center bg-black/30 p-4"
            role="dialog"
            aria-modal="true"
          >
            <div class="w-full max-w-md rounded-2xl bg-brand-surface p-5 shadow-xl">
              <h3 class="text-base font-bold text-brand-text">{{ t('suppliers.eligibility.approveTitle') }}</h3>
              <p class="mt-1 text-sm text-brand-text-secondary">{{ t('suppliers.eligibility.approveBody') }}</p>
              <textarea
                v-model="approveComment"
                rows="3"
                :class="[inputClass, 'mt-3']"
                :placeholder="t('suppliers.eligibility.approveCommentPlaceholder')"
              />
              <div class="mt-4 flex justify-end gap-2">
                <button
                  type="button"
                  class="rounded-xl border border-brand-border px-4 py-2 text-sm font-semibold"
                  @click="approveDialogOpen = false"
                >
                  {{ t('suppliers.cancel') }}
                </button>
                <button
                  type="button"
                  class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
                  :disabled="approveMutation.isPending.value"
                  @click="submitApprove"
                >
                  <Loader2 v-if="approveMutation.isPending.value" class="h-4 w-4 animate-spin" />
                  {{ t('suppliers.eligibility.approve') }}
                </button>
              </div>
            </div>
          </div>
        </aside>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.elig-drawer-enter-active,
.elig-drawer-leave-active {
  transition: opacity 0.2s ease;
}
.elig-drawer-enter-from,
.elig-drawer-leave-to {
  opacity: 0;
}
</style>
