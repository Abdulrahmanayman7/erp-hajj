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
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { employeeSelectOption, toSelectId } from '@/shared/lookups/selectOptions'
import { useConfirm } from '@/shared/composables/useConfirm'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'

import {
  useApproveRiskAssessmentMutation,
  useAssignRiskMutation,
  useCloseRiskMutation,
  useDeleteRiskMutation,
} from '../mutations/useRiskMutations'
import type { OperationalRisk, RiskClosureOutcome } from '../types/risks'
import { RISK_CLOSURE_OUTCOMES } from '../types/risks'
import {
  canApproveRiskAssessment,
  canAssignRisk,
  canCloseRisk,
  canSoftDeleteRisk,
  isValidClosureOutcome,
  mapRiskErrorCode,
} from '../validation/riskValidation'

const props = defineProps<{
  risk: OperationalRisk
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

const approve = useApproveRiskAssessmentMutation()
const assign = useAssignRiskMutation()
const close = useCloseRiskMutation()
const remove = useDeleteRiskMutation()

const assignOwnerId = ref<number | ''>(props.risk.owner_employee_id ?? '')
const closureOutcome = ref<RiskClosureOutcome | ''>('')
const closeReason = ref('')
const actionError = ref('')

const showApprove = computed(() =>
  canApproveRiskAssessment({
    risk: props.risk,
    currentUserId: me.value?.id,
    hasApprovePermission: can('risks.approve_assessment'),
  }),
)

const showAssign = computed(() => can('risks.assign') && canAssignRisk(props.risk.status))
const showClose = computed(() => can('risks.close') && canCloseRisk(props.risk.status))
const showDelete = computed(() => can('risks.delete') && canSoftDeleteRisk(props.risk.status))

const isPending = computed(
  () =>
    approve.isPending.value ||
    assign.isPending.value ||
    close.isPending.value ||
    remove.isPending.value,
)

const closureOptions = computed<AppSelectOption[]>(() =>
  RISK_CLOSURE_OUTCOMES.map((o) => ({ value: o, label: t(`risks.closure.${o}`) })),
)

const emptyOwner = computed<AppSelectOption>(() => ({
  value: '',
  label: t('risks.fields.noOwner'),
}))

const selectedOwner = computed(() =>
  props.risk.owner_employee ? employeeSelectOption(props.risk.owner_employee) : null,
)

const fetchActiveEmployees = (params: { search?: string; page: number; per_page: number }) =>
  listEmployees({ ...params, status: 'active' })

function apiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) return t('risks.errors.generic')
  const mapped = mapRiskErrorCode(error.code)
  return mapped !== 'generic' ? t(`risks.errors.${mapped}`) : error.message || t('risks.errors.generic')
}

async function runApprove(): Promise<void> {
  actionError.value = ''
  const ok = await confirm({
    title: t('risks.confirm.approve.title'),
    message: t('risks.confirm.approve.body'),
    confirmLabel: t('risks.actions.approve'),
    cancelLabel: t('risks.cancel'),
  })
  if (!ok) return
  try {
    await approve.mutateAsync({ id: props.risk.id, seasonId: selectedSeasonId.value })
    toast.success(t('risks.toasts.approved'))
    emit('refreshed')
  } catch (error) {
    actionError.value = apiMessage(error)
    toast.error(actionError.value)
  }
}

async function runAssign(): Promise<void> {
  actionError.value = ''
  if (assignOwnerId.value === '') {
    actionError.value = t('risks.validation.required')
    return
  }
  try {
    await assign.mutateAsync({
      id: props.risk.id,
      payload: { owner_employee_id: Number(assignOwnerId.value) },
      seasonId: selectedSeasonId.value,
    })
    toast.success(t('risks.toasts.assigned'))
    emit('refreshed')
  } catch (error) {
    actionError.value = apiMessage(error)
    toast.error(actionError.value)
  }
}

async function runClose(): Promise<void> {
  actionError.value = ''
  if (!isValidClosureOutcome(closureOutcome.value)) {
    actionError.value = t('risks.validation.required')
    return
  }
  const ok = await confirm({
    title: t('risks.confirm.close.title'),
    message: t('risks.confirm.close.body'),
    confirmLabel: t('risks.actions.close'),
    cancelLabel: t('risks.cancel'),
  })
  if (!ok) return
  try {
    await close.mutateAsync({
      id: props.risk.id,
      payload: {
        closure_outcome: closureOutcome.value,
        close_reason: closeReason.value.trim() || null,
      },
      seasonId: selectedSeasonId.value,
    })
    toast.success(t('risks.toasts.closed'))
    emit('refreshed')
  } catch (error) {
    actionError.value = apiMessage(error)
    toast.error(actionError.value)
  }
}

async function runDelete(): Promise<void> {
  actionError.value = ''
  const ok = await confirm({
    title: t('risks.confirm.delete.title'),
    message: t('risks.confirm.delete.body'),
    confirmLabel: t('risks.actions.delete'),
    cancelLabel: t('risks.cancel'),
    variant: 'danger',
  })
  if (!ok) return
  try {
    await remove.mutateAsync({ id: props.risk.id, seasonId: selectedSeasonId.value })
    toast.success(t('risks.toasts.deleted'))
    await router.push('/app/risks')
  } catch (error) {
    actionError.value = apiMessage(error)
    toast.error(actionError.value)
  }
}
</script>

<template>
  <div class="space-y-4" data-testid="risk-lifecycle-actions">
    <button
      v-if="showApprove"
      type="button"
      class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 text-sm font-semibold text-emerald-950 hover:bg-emerald-100 disabled:opacity-50"
      :disabled="isPending"
      data-testid="risk-approve-btn"
      @click="runApprove"
    >
      <Loader2 v-if="isPending" class="h-3.5 w-3.5 animate-spin" />
      {{ t('risks.actions.approve') }}
    </button>

    <div v-if="showAssign" class="space-y-2 rounded-xl border border-brand-border bg-brand-bg p-3">
      <p class="text-sm font-semibold text-brand-text">{{ t('risks.actions.assign') }}</p>
      <AppRemoteSelect
        :model-value="assignOwnerId"
        query-key="employees-active"
        :fetcher="fetchActiveEmployees"
        :map-option="employeeSelectOption"
        :empty-option="emptyOwner"
        :selected-option="selectedOwner"
        :placeholder="t('risks.fields.owner')"
        @update:model-value="assignOwnerId = toSelectId($event)"
      />
      <button
        type="button"
        class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl border border-sky-200 bg-sky-50 px-4 text-sm font-semibold text-sky-950 hover:bg-sky-100 disabled:opacity-50"
        :disabled="isPending"
        data-testid="risk-assign-btn"
        @click="runAssign"
      >
        <Loader2 v-if="isPending" class="h-3.5 w-3.5 animate-spin" />
        {{ t('risks.actions.assign') }}
      </button>
    </div>

    <div v-if="showClose" class="space-y-2 rounded-xl border border-brand-border bg-brand-bg p-3">
      <p class="text-sm font-semibold text-brand-text">{{ t('risks.actions.close') }}</p>
      <AppSelect
        :model-value="closureOutcome"
        :options="[{ value: '', label: t('risks.fields.selectOutcome') }, ...closureOptions]"
        @update:model-value="closureOutcome = String($event || '') as RiskClosureOutcome | ''"
      />
      <textarea
        v-model="closeReason"
        rows="2"
        class="w-full rounded-xl border border-brand-border p-2.5 text-sm"
        :placeholder="t('risks.fields.closeReason')"
      />
      <button
        type="button"
        class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl border border-teal-200 bg-teal-50 px-4 text-sm font-semibold text-teal-950 hover:bg-teal-100 disabled:opacity-50"
        :disabled="isPending"
        data-testid="risk-close-btn"
        @click="runClose"
      >
        <Loader2 v-if="isPending" class="h-3.5 w-3.5 animate-spin" />
        {{ t('risks.actions.close') }}
      </button>
    </div>

    <button
      v-if="showDelete"
      type="button"
      class="inline-flex h-10 w-full items-center justify-center rounded-xl border border-red-300 bg-red-50 px-4 text-sm font-semibold text-red-800 hover:bg-red-100 disabled:opacity-50"
      :disabled="isPending"
      data-testid="risk-delete-btn"
      @click="runDelete"
    >
      {{ t('risks.actions.delete') }}
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
