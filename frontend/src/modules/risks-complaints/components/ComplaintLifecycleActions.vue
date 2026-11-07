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

async function runStartReview(): Promise<void> {
  actionError.value = ''
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
  <div class="space-y-4" data-testid="complaint-lifecycle-actions">
    <button
      v-if="showStartReview"
      type="button"
      class="inline-flex h-10 w-full items-center justify-center rounded-xl border border-sky-200 bg-sky-50 px-4 text-sm font-semibold text-sky-950 disabled:opacity-50"
      :disabled="isPending"
      data-testid="complaint-start-review-btn"
      @click="runStartReview"
    >
      {{ t('complaints.actions.startReview') }}
    </button>

    <div v-if="showAssign" class="space-y-2 rounded-xl border border-brand-border bg-brand-bg p-3">
      <p class="text-sm font-semibold">{{ t('complaints.actions.assign') }}</p>
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
      <button
        type="button"
        class="inline-flex h-10 w-full items-center justify-center rounded-xl border border-sky-200 bg-sky-50 px-4 text-sm font-semibold text-sky-950 disabled:opacity-50"
        :disabled="isPending"
        data-testid="complaint-assign-btn"
        @click="runAssign"
      >
        <Loader2 v-if="isPending" class="h-3.5 w-3.5 animate-spin" />
        {{ t('complaints.actions.assign') }}
      </button>
    </div>

    <div v-if="showEscalate" class="space-y-2 rounded-xl border border-brand-border bg-brand-bg p-3">
      <p class="text-sm font-semibold">{{ t('complaints.actions.escalate') }}</p>
      <AppRemoteSelect
        :model-value="escalateToId"
        query-key="employees-active"
        :fetcher="fetchActiveEmployees"
        :map-option="employeeSelectOption"
        :empty-option="emptyEmployee"
        :placeholder="t('complaints.fields.escalateTo')"
        @update:model-value="escalateToId = toSelectId($event)"
      />
      <textarea
        v-model="escalateReason"
        rows="2"
        class="w-full rounded-xl border border-brand-border p-2.5 text-sm"
        :placeholder="t('complaints.fields.escalateReason')"
        data-testid="complaint-escalate-reason"
      />
      <button
        type="button"
        class="inline-flex h-10 w-full items-center justify-center rounded-xl border border-amber-200 bg-amber-50 px-4 text-sm font-semibold text-amber-950 disabled:opacity-50"
        :disabled="isPending"
        data-testid="complaint-escalate-btn"
        @click="runEscalate"
      >
        {{ t('complaints.actions.escalate') }}
      </button>
    </div>

    <div v-if="showResolve" class="space-y-2 rounded-xl border border-brand-border bg-brand-bg p-3">
      <p class="text-sm font-semibold">{{ t('complaints.actions.resolve') }}</p>
      <textarea
        v-model="resolutionText"
        rows="3"
        class="w-full rounded-xl border border-brand-border p-2.5 text-sm"
        :placeholder="t('complaints.fields.resolutionText')"
        data-testid="complaint-resolution-input"
      />
      <button
        type="button"
        class="inline-flex h-10 w-full items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-4 text-sm font-semibold text-emerald-950 disabled:opacity-50"
        :disabled="isPending"
        data-testid="complaint-resolve-btn"
        @click="runResolve"
      >
        {{ t('complaints.actions.resolve') }}
      </button>
    </div>

    <div v-if="showClose" class="space-y-2 rounded-xl border border-brand-border bg-brand-bg p-3">
      <p class="text-sm font-semibold">{{ t('complaints.actions.close') }}</p>
      <textarea
        v-model="closeReason"
        rows="2"
        class="w-full rounded-xl border border-brand-border p-2.5 text-sm"
        :placeholder="t('complaints.fields.closeReason')"
        data-testid="complaint-close-reason"
      />
      <button
        type="button"
        class="inline-flex h-10 w-full items-center justify-center rounded-xl border border-teal-200 bg-teal-50 px-4 text-sm font-semibold text-teal-950 disabled:opacity-50"
        :disabled="isPending"
        data-testid="complaint-close-btn"
        @click="runClose"
      >
        {{ t('complaints.actions.close') }}
      </button>
    </div>

    <div v-if="showReopen" class="space-y-2 rounded-xl border border-brand-border bg-brand-bg p-3">
      <p class="text-sm font-semibold">{{ t('complaints.actions.reopen') }}</p>
      <textarea
        v-model="reopenReason"
        rows="2"
        class="w-full rounded-xl border border-brand-border p-2.5 text-sm"
        :placeholder="t('complaints.fields.reopenReason')"
        data-testid="complaint-reopen-reason"
      />
      <button
        type="button"
        class="inline-flex h-10 w-full items-center justify-center rounded-xl border border-orange-200 bg-orange-50 px-4 text-sm font-semibold text-orange-950 disabled:opacity-50"
        :disabled="isPending"
        data-testid="complaint-reopen-btn"
        @click="runReopen"
      >
        {{ t('complaints.actions.reopen') }}
      </button>
    </div>

    <button
      v-if="showDelete"
      type="button"
      class="inline-flex h-10 w-full items-center justify-center rounded-xl border border-red-300 bg-red-50 px-4 text-sm font-semibold text-red-800 disabled:opacity-50"
      :disabled="isPending"
      data-testid="complaint-delete-btn"
      @click="runDelete"
    >
      {{ t('complaints.actions.delete') }}
    </button>

    <p
      v-if="actionError"
      class="rounded-xl border border-red-200 bg-red-50 px-3.5 py-2.5 text-sm text-red-700"
      role="alert"
    >
      {{ actionError }}
    </p>
  </div>
</template>
