<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { Loader2 } from 'lucide-vue-next'

import { useAccommodationSeasonStore } from '@/modules/accommodation/stores/seasonContextStore'
import { useCurrentUserQuery } from '@/modules/auth/queries/useCurrentUserQuery'
import { listEmployees } from '@/modules/employees/api/employeesApi'
import { ApiError } from '@/shared/api/http'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import { employeeSelectOption, toSelectId } from '@/shared/lookups/selectOptions'
import { useConfirm } from '@/shared/composables/useConfirm'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'

import {
  useAssignComplaintMutation,
  useCloseComplaintMutation,
  useDeleteComplaintMutation,
  useEscalateComplaintMutation,
  useReopenComplaintMutation,
  useResolveComplaintMutation,
  useUpdateComplaintMutation,
} from '../mutations/useComplaintMutations'
import type { OperationalComplaint } from '../types/complaints'
import {
  canAssignComplaint,
  canCloseComplaint,
  canEscalateComplaint,
  canReopenComplaint,
  canResolveComplaint,
  canSoftDeleteComplaint,
  canStartComplaintReview,
  mapComplaintErrorCode,
} from '../validation/complaintValidation'

type ComplaintPanelAction = 'assign' | 'escalate' | 'resolve' | 'close' | 'reopen'

const props = defineProps<{
  complaint: OperationalComplaint
}>()

const emit = defineEmits<{ refreshed: [] }>()

const { t } = useI18n()
const { can } = usePermissions()
const { confirm } = useConfirm()
const toast = useToast()
const router = useRouter()
const seasonStore = useAccommodationSeasonStore()
const { selectedSeasonId } = storeToRefs(seasonStore)
const { data: me } = useCurrentUserQuery()

const update = useUpdateComplaintMutation()
const assign = useAssignComplaintMutation()
const escalate = useEscalateComplaintMutation()
const resolve = useResolveComplaintMutation()
const close = useCloseComplaintMutation()
const reopen = useReopenComplaintMutation()
const remove = useDeleteComplaintMutation()

const activeAction = ref<ComplaintPanelAction | null>(null)
const assigneeId = ref<number | ''>(props.complaint.assignee_employee_id ?? '')
const escalateToId = ref<number | ''>('')
const escalateReason = ref('')
const resolutionText = ref(props.complaint.resolution_text ?? '')
const closeReason = ref('')
const reopenReason = ref('')
const actionError = ref('')

const showStartReview = computed(
  () => can('complaints.update') && canStartComplaintReview(props.complaint.status),
)
const showAssign = computed(
  () => can('complaints.assign') && canAssignComplaint(props.complaint.status),
)
const isAssigneeUser = computed(() => {
  const employeeId = me.value?.employee_id
  return (
    employeeId != null &&
    props.complaint.assignee_employee_id != null &&
    Number(employeeId) === Number(props.complaint.assignee_employee_id)
  )
})

/** Matches backend D03: complaints.escalate OR assignee's linked User. */
const showEscalate = computed(
  () =>
    (can('complaints.escalate') || isAssigneeUser.value) &&
    canEscalateComplaint(props.complaint.status),
)
const showResolve = computed(
  () => can('complaints.resolve') && canResolveComplaint(props.complaint.status),
)
/** Sensitive — never inferred from tenant_owner. */
const showClose = computed(
  () => can('complaints.close') && canCloseComplaint(props.complaint.status),
)
const showReopen = computed(
  () => can('complaints.reopen') && canReopenComplaint(props.complaint.status),
)
const showDelete = computed(
  () => can('complaints.delete') && canSoftDeleteComplaint(props.complaint.status),
)

const isPending = computed(
  () =>
    update.isPending.value ||
    assign.isPending.value ||
    escalate.isPending.value ||
    resolve.isPending.value ||
    close.isPending.value ||
    reopen.isPending.value ||
    remove.isPending.value,
)

const emptyEmployee = computed(() => ({ value: '' as const, label: t('complaints.fields.noEmployee') }))
const selectedAssignee = computed(() =>
  props.complaint.assignee_employee
    ? employeeSelectOption(props.complaint.assignee_employee)
    : null,
)

const fetchActiveEmployees = (params: { search?: string; page: number; per_page: number }) =>
  listEmployees({ ...params, status: 'active' })

function apiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) return t('complaints.errors.generic')
  const mapped = mapComplaintErrorCode(error.code)
  return mapped !== 'generic'
    ? t(`complaints.errors.${mapped}`)
    : error.message || t('complaints.errors.generic')
}

function openPanel(action: ComplaintPanelAction): void {
  actionError.value = ''
  activeAction.value = action
  if (action === 'assign') {
    assigneeId.value = props.complaint.assignee_employee_id ?? ''
  }
  if (action === 'escalate') {
    escalateToId.value = ''
    escalateReason.value = ''
  }
  if (action === 'resolve') {
    resolutionText.value = props.complaint.resolution_text ?? ''
  }
  if (action === 'close') {
    closeReason.value = ''
  }
  if (action === 'reopen') {
    reopenReason.value = ''
  }
}

function closePanel(): void {
  activeAction.value = null
  actionError.value = ''
}

async function runStartReview(): Promise<void> {
  actionError.value = ''
  const ok = await confirm({
    title: t('complaints.confirm.startReview.title'),
    message: t('complaints.confirm.startReview.body'),
    confirmLabel: t('complaints.actions.startReview'),
    cancelLabel: t('complaints.cancel'),
  })
  if (!ok) return
  try {
    await update.mutateAsync({
      id: props.complaint.id,
      payload: { start_review: true },
      seasonId: selectedSeasonId.value,
    })
    toast.success(t('complaints.toasts.reviewStarted'))
    emit('refreshed')
  } catch (error) {
    actionError.value = apiMessage(error)
    toast.error(actionError.value)
  }
}

async function runAssign(): Promise<void> {
  actionError.value = ''
  if (assigneeId.value === '') {
    actionError.value = t('complaints.validation.required')
    return
  }
  try {
    await assign.mutateAsync({
      id: props.complaint.id,
      payload: { assignee_employee_id: Number(assigneeId.value) },
      seasonId: selectedSeasonId.value,
    })
    toast.success(t('complaints.toasts.assigned'))
    closePanel()
    emit('refreshed')
  } catch (error) {
    actionError.value = apiMessage(error)
    toast.error(actionError.value)
  }
}

async function runEscalate(): Promise<void> {
  actionError.value = ''
  if (escalateToId.value === '' || !escalateReason.value.trim()) {
    actionError.value = t('complaints.validation.required')
    return
  }
  try {
    await escalate.mutateAsync({
      id: props.complaint.id,
      payload: {
        escalated_to_employee_id: Number(escalateToId.value),
        escalate_reason: escalateReason.value.trim(),
      },
      seasonId: selectedSeasonId.value,
    })
    toast.success(t('complaints.toasts.escalated'))
    closePanel()
    emit('refreshed')
  } catch (error) {
    actionError.value = apiMessage(error)
    toast.error(actionError.value)
  }
}

async function runResolve(): Promise<void> {
  actionError.value = ''
  if (!resolutionText.value.trim()) {
    actionError.value = t('complaints.validation.required')
    return
  }
  try {
    await resolve.mutateAsync({
      id: props.complaint.id,
      payload: { resolution_text: resolutionText.value.trim() },
      seasonId: selectedSeasonId.value,
    })
    toast.success(t('complaints.toasts.resolved'))
    closePanel()
    emit('refreshed')
  } catch (error) {
    actionError.value = apiMessage(error)
    toast.error(actionError.value)
  }
}

async function runClose(): Promise<void> {
  actionError.value = ''
  if (!closeReason.value.trim()) {
    actionError.value = t('complaints.validation.required')
    return
  }
  const ok = await confirm({
    title: t('complaints.confirm.close.title'),
    message: t('complaints.confirm.close.body'),
    confirmLabel: t('complaints.actions.close'),
    cancelLabel: t('complaints.cancel'),
  })
  if (!ok) return
  try {
    await close.mutateAsync({
      id: props.complaint.id,
      payload: { close_reason: closeReason.value.trim() },
      seasonId: selectedSeasonId.value,
    })
    toast.success(t('complaints.toasts.closed'))
    closePanel()
    emit('refreshed')
  } catch (error) {
    actionError.value = apiMessage(error)
    toast.error(actionError.value)
  }
}

async function runReopen(): Promise<void> {
  actionError.value = ''
  if (!reopenReason.value.trim()) {
    actionError.value = t('complaints.validation.required')
    return
  }
  const ok = await confirm({
    title: t('complaints.confirm.reopen.title'),
    message: t('complaints.confirm.reopen.body'),
    confirmLabel: t('complaints.actions.reopen'),
    cancelLabel: t('complaints.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await reopen.mutateAsync({
      id: props.complaint.id,
      payload: { reopen_reason: reopenReason.value.trim() },
      seasonId: selectedSeasonId.value,
    })
    toast.success(t('complaints.toasts.reopened'))
    closePanel()
    emit('refreshed')
  } catch (error) {
    actionError.value = apiMessage(error)
    toast.error(actionError.value)
  }
}

async function runDelete(): Promise<void> {
  actionError.value = ''
  const ok = await confirm({
    title: t('complaints.confirm.delete.title'),
    message: t('complaints.confirm.delete.body'),
    confirmLabel: t('complaints.actions.delete'),
    cancelLabel: t('complaints.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await remove.mutateAsync({ id: props.complaint.id, seasonId: selectedSeasonId.value })
    toast.success(t('complaints.toasts.deleted'))
    await router.push('/app/complaints')
  } catch (error) {
    actionError.value = apiMessage(error)
    toast.error(actionError.value)
  }
}
</script>

<template>
  <div class="space-y-3" data-testid="complaint-lifecycle-actions">
    <div class="flex flex-col gap-2">
      <button
        v-if="showStartReview"
        type="button"
        class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl border border-sky-200 bg-sky-50 px-4 text-sm font-semibold text-sky-950 transition hover:bg-sky-100 disabled:opacity-50 xl:justify-start"
        :disabled="isPending"
        data-testid="complaint-start-review-btn"
        @click="runStartReview"
      >
        <Loader2 v-if="isPending" class="h-3.5 w-3.5 animate-spin" />
        {{ t('complaints.actions.startReview') }}
      </button>

      <button
        v-if="showAssign"
        type="button"
        class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl border border-sky-200 bg-sky-50 px-4 text-sm font-semibold text-sky-950 transition hover:bg-sky-100 disabled:opacity-50 xl:justify-start"
        :disabled="isPending"
        data-testid="complaint-assign-btn"
        @click="openPanel('assign')"
      >
        {{ t('complaints.actions.assign') }}
      </button>

      <button
        v-if="showEscalate"
        type="button"
        class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 text-sm font-semibold text-amber-950 transition hover:bg-amber-100 disabled:opacity-50 xl:justify-start"
        :disabled="isPending"
        data-testid="complaint-escalate-btn"
        @click="openPanel('escalate')"
      >
        {{ t('complaints.actions.escalate') }}
      </button>

      <button
        v-if="showResolve"
        type="button"
        class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 text-sm font-semibold text-emerald-950 transition hover:bg-emerald-100 disabled:opacity-50 xl:justify-start"
        :disabled="isPending"
        data-testid="complaint-resolve-btn"
        @click="openPanel('resolve')"
      >
        {{ t('complaints.actions.resolve') }}
      </button>

      <button
        v-if="showClose"
        type="button"
        class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl border border-teal-200 bg-teal-50 px-4 text-sm font-semibold text-teal-950 transition hover:bg-teal-100 disabled:opacity-50 xl:justify-start"
        :disabled="isPending"
        data-testid="complaint-close-btn"
        @click="openPanel('close')"
      >
        {{ t('complaints.actions.close') }}
      </button>

      <button
        v-if="showReopen"
        type="button"
        class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl border border-orange-200 bg-orange-50 px-4 text-sm font-semibold text-orange-950 transition hover:bg-orange-100 disabled:opacity-50 xl:justify-start"
        :disabled="isPending"
        data-testid="complaint-reopen-btn"
        @click="openPanel('reopen')"
      >
        {{ t('complaints.actions.reopen') }}
      </button>

      <button
        v-if="showDelete"
        type="button"
        class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl border border-red-300 bg-red-50 px-4 text-sm font-semibold text-red-800 transition hover:bg-red-100 disabled:opacity-50 xl:justify-start"
        :disabled="isPending"
        data-testid="complaint-delete-btn"
        @click="runDelete"
      >
        {{ t('complaints.actions.delete') }}
      </button>
    </div>

    <p
      v-if="actionError"
      class="rounded-xl border border-red-200 bg-red-50 px-3.5 py-2.5 text-sm text-red-700"
      role="alert"
    >
      {{ actionError }}
    </p>

    <div
      v-if="activeAction === 'assign'"
      class="rounded-xl border border-brand-border bg-brand-bg p-3"
    >
      <label class="app-field">
        <span class="app-field__label">{{ t('complaints.fields.assignee') }}</span>
        <AppRemoteSelect
          :model-value="assigneeId"
          query-key="employees-active"
          :fetcher="fetchActiveEmployees"
          :map-option="employeeSelectOption"
          :empty-option="emptyEmployee"
          :selected-option="selectedAssignee"
          :placeholder="t('complaints.fields.assignee')"
          @update:model-value="assigneeId = toSelectId($event)"
        />
      </label>
      <div class="mt-2 flex flex-wrap gap-2">
        <button
          type="button"
          class="inline-flex h-10 items-center rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white disabled:opacity-50"
          :disabled="isPending"
          @click="runAssign"
        >
          <Loader2 v-if="isPending" class="me-2 h-3.5 w-3.5 animate-spin" />
          {{ t('complaints.actions.assign') }}
        </button>
        <button
          type="button"
          class="inline-flex h-10 items-center rounded-xl border border-brand-border px-4 text-sm font-semibold text-brand-text"
          :disabled="isPending"
          @click="closePanel"
        >
          {{ t('complaints.cancel') }}
        </button>
      </div>
    </div>

    <div
      v-if="activeAction === 'escalate'"
      class="rounded-xl border border-brand-border bg-brand-bg p-3"
    >
      <label class="app-field">
        <span class="app-field__label">{{ t('complaints.fields.escalateTo') }}</span>
        <AppRemoteSelect
          :model-value="escalateToId"
          query-key="employees-active"
          :fetcher="fetchActiveEmployees"
          :map-option="employeeSelectOption"
          :empty-option="emptyEmployee"
          :placeholder="t('complaints.fields.escalateTo')"
          @update:model-value="escalateToId = toSelectId($event)"
        />
      </label>
      <label class="app-field mt-3">
        <span class="app-field__label">{{ t('complaints.fields.escalateReason') }}</span>
        <textarea
          v-model="escalateReason"
          rows="2"
          class="app-field__control app-field__control--textarea"
          data-testid="complaint-escalate-reason"
        />
      </label>
      <div class="mt-2 flex flex-wrap gap-2">
        <button
          type="button"
          class="inline-flex h-10 items-center rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white disabled:opacity-50"
          :disabled="isPending"
          @click="runEscalate"
        >
          {{ t('complaints.actions.escalate') }}
        </button>
        <button
          type="button"
          class="inline-flex h-10 items-center rounded-xl border border-brand-border px-4 text-sm font-semibold text-brand-text"
          :disabled="isPending"
          @click="closePanel"
        >
          {{ t('complaints.cancel') }}
        </button>
      </div>
    </div>

    <div
      v-if="activeAction === 'resolve'"
      class="rounded-xl border border-brand-border bg-brand-bg p-3"
    >
      <label class="app-field">
        <span class="app-field__label">{{ t('complaints.fields.resolutionText') }}</span>
        <textarea
          v-model="resolutionText"
          rows="3"
          class="app-field__control app-field__control--textarea"
          data-testid="complaint-resolution-input"
        />
      </label>
      <div class="mt-2 flex flex-wrap gap-2">
        <button
          type="button"
          class="inline-flex h-10 items-center rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white disabled:opacity-50"
          :disabled="isPending"
          @click="runResolve"
        >
          {{ t('complaints.actions.resolve') }}
        </button>
        <button
          type="button"
          class="inline-flex h-10 items-center rounded-xl border border-brand-border px-4 text-sm font-semibold text-brand-text"
          :disabled="isPending"
          @click="closePanel"
        >
          {{ t('complaints.cancel') }}
        </button>
      </div>
    </div>

    <div
      v-if="activeAction === 'close'"
      class="rounded-xl border border-brand-border bg-brand-bg p-3"
    >
      <label class="app-field">
        <span class="app-field__label">{{ t('complaints.fields.closeReason') }}</span>
        <textarea
          v-model="closeReason"
          rows="2"
          class="app-field__control app-field__control--textarea"
          data-testid="complaint-close-reason"
        />
      </label>
      <div class="mt-2 flex flex-wrap gap-2">
        <button
          type="button"
          class="inline-flex h-10 items-center rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white disabled:opacity-50"
          :disabled="isPending"
          @click="runClose"
        >
          {{ t('complaints.actions.close') }}
        </button>
        <button
          type="button"
          class="inline-flex h-10 items-center rounded-xl border border-brand-border px-4 text-sm font-semibold text-brand-text"
          :disabled="isPending"
          @click="closePanel"
        >
          {{ t('complaints.cancel') }}
        </button>
      </div>
    </div>

    <div
      v-if="activeAction === 'reopen'"
      class="rounded-xl border border-brand-border bg-brand-bg p-3"
    >
      <label class="app-field">
        <span class="app-field__label">{{ t('complaints.fields.reopenReason') }}</span>
        <textarea
          v-model="reopenReason"
          rows="2"
          class="app-field__control app-field__control--textarea"
          data-testid="complaint-reopen-reason"
        />
      </label>
      <div class="mt-2 flex flex-wrap gap-2">
        <button
          type="button"
          class="inline-flex h-10 items-center rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white disabled:opacity-50"
          :disabled="isPending"
          @click="runReopen"
        >
          {{ t('complaints.actions.reopen') }}
        </button>
        <button
          type="button"
          class="inline-flex h-10 items-center rounded-xl border border-brand-border px-4 text-sm font-semibold text-brand-text"
          :disabled="isPending"
          @click="closePanel"
        >
          {{ t('complaints.cancel') }}
        </button>
      </div>
    </div>
  </div>
</template>
