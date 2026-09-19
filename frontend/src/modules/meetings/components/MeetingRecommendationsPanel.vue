<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRouter } from 'vue-router'
import { Loader2, Pencil, Plus, Trash2 } from 'lucide-vue-next'

import { listEmployees } from '@/modules/employees/api/employeesApi'
import { ApiError } from '@/shared/api/http'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { employeeSelectOption } from '@/shared/lookups/selectOptions'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'
import { createDecision } from '@/modules/decisions/api/decisionsApi'
import { canCreateDecisionFromRecommendation } from '@/modules/decisions/validation/decisionValidation'

import {
  useCreateRecommendationMutation,
  useDeleteRecommendationMutation,
  useUpdateRecommendationMutation,
} from '../mutations/useMeetingMutations'
import type {
  Meeting,
  MeetingRecommendation,
  RecommendationStatus,
} from '../types/meetings'
import {
  RECOMMENDATION_STATUSES,
  isMeetingLocked,
  mapMeetingErrorCode,
  validateRecommendationForm,
} from '../validation/meetingValidation'

const props = defineProps<{
  meeting: Meeting
}>()

const emit = defineEmits<{
  refreshed: []
}>()

const { t } = useI18n()
const toast = useToast()
const { confirm } = useConfirm()
const { can } = usePermissions()
const router = useRouter()

const createMutation = useCreateRecommendationMutation()
const updateMutation = useUpdateRecommendationMutation()
const deleteMutation = useDeleteRecommendationMutation()

const formOpen = ref(false)
const editing = ref<MeetingRecommendation | null>(null)
const formError = ref('')
const form = reactive({
  title: '',
  description: '',
  owner_employee_id: '' as number | '',
  agenda_item_id: '' as number | '',
  status: 'draft' as RecommendationStatus,
})

const readOnly = computed(() => isMeetingLocked(props.meeting.status))
const canManage = computed(() => can('meetings.manage_minutes') && !readOnly.value)

const recommendations = computed(() =>
  [...(props.meeting.recommendations ?? [])].sort((a, b) => a.sort_order - b.sort_order),
)

const agendaOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('meetings.noAgendaItem') },
  ...(props.meeting.agenda_items ?? []).map((item) => ({
    value: item.id,
    label: item.title,
  })),
])

const statusOptions = computed<AppSelectOption[]>(() =>
  RECOMMENDATION_STATUSES.map((status) => ({
    value: status,
    label: t(`meetings.recommendationStatus.${status}`),
  })),
)

const fetchActiveEmployees = (params: { search?: string; page: number; per_page: number }) =>
  listEmployees({ ...params, status: 'active' })
const emptyEmployee = computed<AppSelectOption>(() => ({ value: '', label: t('meetings.noEmployee') }))
const selectedOwner = computed(() =>
  editing.value?.owner ? employeeSelectOption(editing.value.owner) : null,
)

const isPending = computed(
  () =>
    createMutation.isPending.value ||
    updateMutation.isPending.value ||
    deleteMutation.isPending.value,
)

function apiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) {
    return t('meetings.errors.generic')
  }
  const mapped = mapMeetingErrorCode(error.code)
  if (mapped !== 'generic') {
    return t(`meetings.errors.${mapped}`)
  }
  return error.message || t('meetings.errors.generic')
}

function openCreate(): void {
  editing.value = null
  form.title = ''
  form.description = ''
  form.owner_employee_id = ''
  form.agenda_item_id = ''
  form.status = 'draft'
  formError.value = ''
  formOpen.value = true
}

function openEdit(item: MeetingRecommendation): void {
  editing.value = item
  form.title = item.title
  form.description = item.description ?? ''
  form.owner_employee_id = item.owner?.id ?? ''
  form.agenda_item_id = item.agenda_item_id ?? ''
  form.status = item.status
  formError.value = ''
  formOpen.value = true
}

function closeForm(): void {
  formOpen.value = false
  editing.value = null
}

async function submitForm(): Promise<void> {
  formError.value = ''
  const validation = validateRecommendationForm(form)
  if (validation.title) {
    formError.value = t('meetings.validation.required')
    return
  }

  const payload = {
    title: form.title.trim(),
    description: form.description.trim() || null,
    owner_employee_id: form.owner_employee_id === '' ? null : Number(form.owner_employee_id),
    agenda_item_id: form.agenda_item_id === '' ? null : Number(form.agenda_item_id),
    status: form.status,
  }

  try {
    if (editing.value) {
      await updateMutation.mutateAsync({
        meetingId: props.meeting.id,
        recommendationId: editing.value.id,
        payload,
      })
      toast.success(t('meetings.toasts.recommendationUpdated'))
    } else {
      await createMutation.mutateAsync({
        meetingId: props.meeting.id,
        payload,
      })
      toast.success(t('meetings.toasts.recommendationCreated'))
    }
    closeForm()
    emit('refreshed')
  } catch (error) {
    formError.value = apiMessage(error)
  }
}

async function removeItem(item: MeetingRecommendation): Promise<void> {
  const confirmed = await confirm({
    title: t('meetings.confirmDeleteRecommendationTitle'),
    message: t('meetings.confirmDeleteRecommendationBody'),
    confirmLabel: t('meetings.actions.delete'),
    cancelLabel: t('meetings.cancel'),
    variant: 'danger',
  })
  if (!confirmed) return
  try {
    await deleteMutation.mutateAsync({
      meetingId: props.meeting.id,
      recommendationId: item.id,
    })
    toast.success(t('meetings.toasts.recommendationDeleted'))
    emit('refreshed')
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

function recommendationStatusClass(status: RecommendationStatus): string {
  return status === 'final'
    ? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200/70'
    : 'bg-neutral-100 text-neutral-700 ring-1 ring-neutral-200/80'
}

async function createDecisionFromRecommendation(item: MeetingRecommendation): Promise<void> {
  try {
    const decision = await createDecision({ source_recommendation_id: item.id })
    await router.push(`/app/decisions/${decision.id}`)
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

function canCreateLinkedDecision(item: MeetingRecommendation): boolean {
  return canCreateDecisionFromRecommendation({
    recommendationStatus: item.status,
    meetingStatus: props.meeting.status,
    hasLinkedDecision: item.linked_decision != null,
    permissions: can('decisions.create') ? ['decisions.create'] : [],
  })
}
</script>

<template>
  <div class="rounded-2xl border border-brand-border bg-brand-surface p-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h3 class="text-base font-bold text-brand-text">
          {{ t('meetings.sections.recommendations') }}
        </h3>
        <p class="mt-1 text-sm text-brand-text-secondary">
          {{ t('meetings.recommendationsSubtitle') }}
        </p>
      </div>
      <PermissionGuard v-if="canManage" permission="meetings.manage_minutes">
        <button
          type="button"
          class="inline-flex h-10 items-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-3 text-sm font-semibold text-brand-primary-dark hover:bg-brand-primary-soft"
          @click="openCreate"
        >
          <Plus class="h-4 w-4" />
          {{ t('meetings.actions.addRecommendation') }}
        </button>
      </PermissionGuard>
    </div>

    <form
      v-if="formOpen"
      class="mt-4 space-y-3 rounded-xl border border-brand-border bg-brand-bg p-4"
      v-autofocus-when
      @submit.prevent="submitForm"
    >
      <label class="block">
        <span class="mb-1.5 block text-sm font-semibold text-brand-text">
          {{ t('meetings.fields.recommendationTitle') }}
        </span>
        <input
          v-model="form.title"
          type="text"
          class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface px-3 text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
          :disabled="isPending"
        />
      </label>
      <label class="block">
        <span class="mb-1.5 block text-sm font-semibold text-brand-text">
          {{ t('meetings.fields.recommendationDescription') }}
        </span>
        <textarea
          v-model="form.description"
          rows="2"
          class="w-full rounded-xl border border-brand-border bg-brand-surface px-3 py-2 text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
          :disabled="isPending"
        />
      </label>
      <div class="grid gap-3 sm:grid-cols-2">
        <div>
          <span class="mb-1.5 block text-sm font-semibold text-brand-text">
            {{ t('meetings.fields.recommendationOwner') }}
          </span>
          <AppRemoteSelect
            :model-value="form.owner_employee_id"
            query-key="employees-active"
            :fetcher="fetchActiveEmployees"
            :map-option="employeeSelectOption"
            :empty-option="emptyEmployee"
            :selected-option="selectedOwner"
            :disabled="isPending"
            :enabled="formOpen"
            @update:model-value="form.owner_employee_id = $event === '' || $event == null ? '' : Number($event)"
          />
        </div>
        <div>
          <span class="mb-1.5 block text-sm font-semibold text-brand-text">
            {{ t('meetings.fields.recommendationStatus') }}
          </span>
          <AppSelect
            v-model="form.status"
            :options="statusOptions"
            :disabled="isPending"
          />
        </div>
      </div>
      <div>
        <span class="mb-1.5 block text-sm font-semibold text-brand-text">
          {{ t('meetings.fields.agendaItem') }}
        </span>
        <AppSelect
          v-model="form.agenda_item_id"
          :options="agendaOptions"
          :disabled="isPending"
        />
      </div>
      <p v-if="formError" class="text-xs text-red-600" role="alert">{{ formError }}</p>
      <div class="flex justify-end gap-2">
        <button
          type="button"
          class="inline-flex h-9 items-center rounded-lg border border-brand-border px-3 text-sm font-semibold"
          :disabled="isPending"
          @click="closeForm"
        >
          {{ t('meetings.cancel') }}
        </button>
        <button
          type="submit"
          class="inline-flex h-9 items-center gap-2 rounded-lg bg-brand-primary-dark px-3 text-sm font-semibold text-white"
          :disabled="isPending"
        >
          <Loader2 v-if="isPending" class="h-3.5 w-3.5 animate-spin" />
          {{ editing ? t('meetings.save') : t('meetings.actions.addRecommendation') }}
        </button>
      </div>
    </form>

    <div
      v-if="recommendations.length === 0"
      class="mt-6 rounded-xl border border-dashed border-brand-border px-4 py-8 text-center text-sm text-brand-text-muted"
    >
      {{ t('meetings.recommendationsEmpty') }}
    </div>

    <ul v-else class="mt-4 space-y-3">
      <li
        v-for="item in recommendations"
        :key="item.id"
        class="rounded-xl border border-brand-border/80 bg-[#FAFBFA] p-4"
      >
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <p class="text-sm font-bold text-brand-text">{{ item.title }}</p>
              <span
                class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold"
                :class="recommendationStatusClass(item.status)"
              >
                {{ t(`meetings.recommendationStatus.${item.status}`) }}
              </span>
            </div>
            <p
              v-if="item.description"
              class="mt-1 whitespace-pre-wrap text-sm text-brand-text-secondary"
            >
              {{ item.description }}
            </p>
            <p v-if="item.owner" class="mt-2 text-xs text-brand-text-muted">
              {{ t('meetings.fields.recommendationOwner') }}: {{ item.owner.full_name }}
            </p>
          </div>
          <div v-if="canManage" class="flex items-center gap-1">
            <button
              type="button"
              class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-brand-primary-dark hover:bg-brand-primary-soft"
              :aria-label="t('meetings.actions.edit')"
              :disabled="isPending"
              @click="openEdit(item)"
            >
              <Pencil class="h-4 w-4" />
            </button>
            <button
              type="button"
              class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-red-700 hover:bg-red-50"
              :aria-label="t('meetings.actions.delete')"
              :disabled="isPending"
              @click="removeItem(item)"
            >
              <Trash2 class="h-4 w-4" />
            </button>
          </div>
          <div
            v-if="item.status === 'final' && meeting.status === 'completed'"
            class="flex items-center gap-2"
          >
            <RouterLink
              v-if="item.linked_decision"
              :to="`/app/decisions/${item.linked_decision.id}`"
              class="rounded-lg border border-brand-border px-3 py-2 text-sm font-semibold text-brand-primary-dark"
            >
              {{ t('meetings.actions.viewDecision') }}
            </RouterLink>
            <button
            v-else-if="canCreateLinkedDecision(item)"
              type="button"
              class="rounded-lg border border-brand-border px-3 py-2 text-sm font-semibold text-brand-primary-dark"
              @click="createDecisionFromRecommendation(item)"
            >
              {{ t('meetings.actions.createDecision') }}
            </button>
          </div>
        </div>
      </li>
    </ul>
  </div>
</template>
