<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { ArrowRight, Pencil } from 'lucide-vue-next'

import { useEmployeesQuery } from '@/modules/employees/queries/useEmployeesQuery'
import { useOrganizationUnitsFlatQuery } from '@/modules/organization/queries/useOrganizationUnitsQuery'
import { ApiError } from '@/shared/api/http'
import type { AppSelectOption } from '@/shared/components/AppSelect.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'

import MeetingAgendaPanel from '../components/MeetingAgendaPanel.vue'
import MeetingAttendeesPanel from '../components/MeetingAttendeesPanel.vue'
import MeetingFormDrawer from '../components/MeetingFormDrawer.vue'
import MeetingLifecycleActions from '../components/MeetingLifecycleActions.vue'
import MeetingMinutesPanel from '../components/MeetingMinutesPanel.vue'
import MeetingRecommendationsPanel from '../components/MeetingRecommendationsPanel.vue'
import MeetingTimeline from '../components/MeetingTimeline.vue'
import { useUpdateMeetingMutation } from '../mutations/useMeetingMutations'
import { useMeetingQuery } from '../queries/useMeetingQuery'
import type { MeetingFormState } from '../types/meetings'
import {
  MEETING_LOCATION_TYPES,
  canEditMeeting,
  fromDatetimeLocalValue,
  isMeetingToday,
  mapMeetingErrorCode,
  meetingStatusBadgeClass,
  toDatetimeLocalValue,
  validateMeetingForm,
} from '../validation/meetingValidation'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { can, permissions } = usePermissions()
const toast = useToast()

const meetingId = computed(() => {
  const raw = route.params.id
  const value = Number(Array.isArray(raw) ? raw[0] : raw)
  return Number.isFinite(value) ? value : null
})

const { data, isLoading, isError, refetch } = useMeetingQuery(meetingId)
const meeting = computed(() => data.value ?? null)

const { data: orgUnitsData } = useOrganizationUnitsFlatQuery({ status: 'active' })
const { data: employeesData } = useEmployeesQuery(
  computed(() => ({ status: 'active' as const, per_page: 100 })),
)

const updateMutation = useUpdateMeetingMutation()
const isFormSubmitting = computed(() => updateMutation.isPending.value)

const drawerOpen = ref(false)
const formError = ref('')
const fieldErrors = reactive<Record<string, string>>({})
const form = reactive<MeetingFormState>({
  title: '',
  description: '',
  scheduled_at: '',
  location_type: 'physical',
  location_text: '',
  meeting_link: '',
  organization_unit_id: '',
  chairperson_employee_id: '',
  secretary_employee_id: '',
  notes: '',
})

const permissionList = computed(() => permissions.value ?? [])

const canEdit = computed(
  () =>
    meeting.value != null &&
    canEditMeeting(meeting.value.status, permissionList.value) &&
    can('meetings.update'),
)

const orgUnitFormOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('meetings.noOrgUnit') },
  ...(orgUnitsData.value?.data ?? []).map((u) => ({
    value: u.id,
    label: u.name,
    hint: u.code,
  })),
])

const employeeFormOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('meetings.noEmployee') },
  ...(employeesData.value?.data ?? []).map((e) => ({
    value: e.id,
    label: e.full_name,
    hint: e.employee_number,
  })),
])

const locationTypeOptions = computed<AppSelectOption[]>(() =>
  MEETING_LOCATION_TYPES.map((type) => ({
    value: type,
    label: t(`meetings.locationType.${type}`),
  })),
)

function formatScheduledAt(value: string | null | undefined): string {
  if (!value) return '—'
  try {
    return new Intl.DateTimeFormat('ar-SA', {
      dateStyle: 'medium',
      timeStyle: 'short',
    }).format(new Date(value))
  } catch {
    return value
  }
}

function openEdit(): void {
  if (!meeting.value || !canEdit.value) return
  Object.assign(form, {
    title: meeting.value.title,
    description: meeting.value.description ?? '',
    scheduled_at: toDatetimeLocalValue(meeting.value.scheduled_at),
    location_type: meeting.value.location_type,
    location_text: meeting.value.location_text ?? '',
    meeting_link: meeting.value.meeting_link ?? '',
    organization_unit_id: meeting.value.organization_unit?.id ?? '',
    chairperson_employee_id: meeting.value.chairperson?.id ?? '',
    secretary_employee_id: meeting.value.secretary?.id ?? '',
    notes: meeting.value.notes ?? '',
  })
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  drawerOpen.value = true
}

function fieldMessage(key: string | undefined): string {
  if (!key) return ''
  return t(`meetings.validation.${key}`)
}

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

async function submitForm(): Promise<void> {
  if (!meeting.value) return
  formError.value = ''
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
  const validation = validateMeetingForm(form)
  if (validation.title) {
    fieldErrors.title = fieldMessage(validation.title)
    return
  }

  try {
    await updateMutation.mutateAsync({
      id: meeting.value.id,
      payload: {
        title: form.title.trim(),
        description: form.description.trim() || null,
        location_type: form.location_type,
        location_text: form.location_text.trim() || null,
        meeting_link: form.meeting_link.trim() || null,
        organization_unit_id:
          form.organization_unit_id === '' ? null : Number(form.organization_unit_id),
        chairperson_employee_id:
          form.chairperson_employee_id === '' ? null : Number(form.chairperson_employee_id),
        secretary_employee_id:
          form.secretary_employee_id === '' ? null : Number(form.secretary_employee_id),
        notes: form.notes.trim() || null,
        scheduled_at: fromDatetimeLocalValue(form.scheduled_at),
      },
    })
    toast.success(t('meetings.toasts.updated'))
    drawerOpen.value = false
    await refetch()
  } catch (error) {
    formError.value = apiMessage(error)
  }
}

function assignForm(next: MeetingFormState): void {
  Object.assign(form, next)
}

async function onRefreshed(): Promise<void> {
  await refetch()
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center gap-3">
      <button
        type="button"
        class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-brand-border bg-brand-surface px-3 text-sm font-semibold text-brand-text hover:bg-brand-bg"
        @click="router.push('/app/meetings')"
      >
        <ArrowRight class="h-4 w-4" />
        {{ t('meetings.backToList') }}
      </button>
    </div>

    <div
      v-if="isLoading"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('meetings.loadingDetails') }}
    </div>

    <div
      v-else-if="isError || !meeting"
      class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center"
    >
      <p class="text-sm text-red-700">{{ t('meetings.errors.loadDetails') }}</p>
      <button
        type="button"
        class="mt-3 text-sm font-semibold text-brand-primary-dark underline"
        @click="() => refetch()"
      >
        {{ t('meetings.retry') }}
      </button>
    </div>

    <template v-else>
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-2">
            <p class="font-mono text-sm text-brand-text-muted" dir="ltr">
              {{ meeting.meeting_number }}
            </p>
            <span
              class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
              :class="meetingStatusBadgeClass(meeting.status)"
            >
              {{ t(`meetings.status.${meeting.status}`) }}
            </span>
            <span
              v-if="meeting.is_upcoming"
              class="inline-flex items-center rounded-full bg-sky-50 px-2 py-0.5 text-[11px] font-semibold text-sky-900 ring-1 ring-sky-200/70"
            >
              {{ t('meetings.upcomingBadge') }}
            </span>
            <span
              v-else-if="isMeetingToday(meeting.scheduled_at)"
              class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-900 ring-1 ring-amber-200/70"
            >
              {{ t('meetings.todayBadge') }}
            </span>
          </div>
          <h2 class="mt-2 text-[1.75rem] font-bold leading-tight text-brand-text">
            {{ meeting.title }}
          </h2>
        </div>
        <PermissionGuard v-if="canEdit" permission="meetings.update">
          <button
            type="button"
            class="inline-flex h-11 items-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-4 text-sm font-semibold text-brand-primary-dark hover:bg-brand-primary-soft"
            @click="openEdit"
          >
            <Pencil class="h-4 w-4" />
            {{ t('meetings.actions.edit') }}
          </button>
        </PermissionGuard>
      </div>

      <section class="space-y-4">
        <h3 class="text-base font-bold text-brand-text">{{ t('meetings.sections.overview') }}</h3>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          <div class="rounded-2xl border border-brand-border bg-brand-surface p-4">
            <p class="text-xs font-bold text-brand-text-muted">
              {{ t('meetings.fields.scheduledAt') }}
            </p>
            <p class="mt-1.5 text-sm font-semibold text-brand-text">
              {{ formatScheduledAt(meeting.scheduled_at) }}
            </p>
          </div>
          <div class="rounded-2xl border border-brand-border bg-brand-surface p-4">
            <p class="text-xs font-bold text-brand-text-muted">
              {{ t('meetings.fields.locationType') }}
            </p>
            <p class="mt-1.5 text-sm font-semibold text-brand-text">
              {{ t(`meetings.locationType.${meeting.location_type}`) }}
            </p>
            <p v-if="meeting.location_text" class="mt-1 text-xs text-brand-text-secondary">
              {{ meeting.location_text }}
            </p>
            <p
              v-if="meeting.meeting_link"
              class="mt-1 font-mono text-xs text-brand-primary-dark"
              dir="ltr"
            >
              {{ meeting.meeting_link }}
            </p>
          </div>
          <div class="rounded-2xl border border-brand-border bg-brand-surface p-4">
            <p class="text-xs font-bold text-brand-text-muted">
              {{ t('meetings.fields.organizationUnit') }}
            </p>
            <p class="mt-1.5 text-sm font-semibold text-brand-text">
              {{ meeting.organization_unit?.name ?? '—' }}
            </p>
          </div>
          <div class="rounded-2xl border border-brand-border bg-brand-surface p-4">
            <p class="text-xs font-bold text-brand-text-muted">
              {{ t('meetings.fields.chairperson') }}
            </p>
            <p class="mt-1.5 text-sm font-semibold text-brand-text">
              {{ meeting.chairperson?.full_name ?? '—' }}
            </p>
          </div>
          <div class="rounded-2xl border border-brand-border bg-brand-surface p-4">
            <p class="text-xs font-bold text-brand-text-muted">
              {{ t('meetings.fields.secretary') }}
            </p>
            <p class="mt-1.5 text-sm font-semibold text-brand-text">
              {{ meeting.secretary?.full_name ?? '—' }}
            </p>
          </div>
          <div class="rounded-2xl border border-brand-border bg-brand-surface p-4">
            <p class="text-xs font-bold text-brand-text-muted">
              {{ t('meetings.fields.description') }}
            </p>
            <p class="mt-1.5 whitespace-pre-wrap text-sm text-brand-text-secondary">
              {{ meeting.description || '—' }}
            </p>
          </div>
        </div>

        <div
          v-if="meeting.notes"
          class="rounded-2xl border border-brand-border bg-brand-surface p-4"
        >
          <p class="text-xs font-bold text-brand-text-muted">{{ t('meetings.fields.notes') }}</p>
          <p class="mt-1.5 whitespace-pre-wrap text-sm text-brand-text-secondary">
            {{ meeting.notes }}
          </p>
        </div>

        <div class="rounded-2xl border border-brand-border bg-brand-surface p-4">
          <p class="mb-3 text-sm font-bold text-brand-text">{{ t('meetings.lifecycleTitle') }}</p>
          <MeetingLifecycleActions :meeting="meeting" @refreshed="onRefreshed" />
        </div>

        <MeetingTimeline :transitions="meeting.transitions ?? []" />
      </section>

      <MeetingAttendeesPanel
        :meeting="meeting"
        :employee-options="employeeFormOptions"
        @refreshed="onRefreshed"
      />

      <MeetingAgendaPanel :meeting="meeting" @refreshed="onRefreshed" />

      <MeetingMinutesPanel :meeting="meeting" @refreshed="onRefreshed" />

      <MeetingRecommendationsPanel
        :meeting="meeting"
        :employee-options="employeeFormOptions"
        @refreshed="onRefreshed"
      />

      <div
        class="rounded-2xl border border-dashed border-brand-border bg-[#F7F8F6] p-5 text-sm text-brand-text-muted"
      >
        <p class="text-xs font-bold text-brand-text-muted">
          {{ t('meetings.sections.attachments') }}
        </p>
        <p class="mt-1.5">{{ t('meetings.attachmentsPlaceholder') }}</p>
      </div>
    </template>

    <MeetingFormDrawer
      :open="drawerOpen"
      :editing="meeting"
      :form="form"
      :form-error="formError"
      :field-errors="fieldErrors"
      :submitting="isFormSubmitting"
      :employee-options="employeeFormOptions"
      :org-unit-options="orgUnitFormOptions"
      :location-type-options="locationTypeOptions"
      @close="drawerOpen = false"
      @submit="submitForm"
      @update:form="assignForm"
    />
  </div>
</template>
