<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { Loader2, Plus, Trash2 } from 'lucide-vue-next'

import { listEmployees } from '@/modules/employees/api/employeesApi'
import { ApiError } from '@/shared/api/http'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { employeeSelectOption, toSelectId } from '@/shared/lookups/selectOptions'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'

import {
  useAddAttendeeMutation,
  useRemoveAttendeeMutation,
  useUpdateAttendeeMutation,
} from '../mutations/useMeetingMutations'
import type { AttendanceStatus, Meeting, MeetingAttendee } from '../types/meetings'
import {
  ATTENDANCE_STATUSES,
  isMeetingLocked,
  mapMeetingErrorCode,
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

const addMutation = useAddAttendeeMutation()
const updateMutation = useUpdateAttendeeMutation()
const removeMutation = useRemoveAttendeeMutation()

const selectedEmployeeId = ref<number | ''>('')
const formError = ref('')

const readOnly = computed(() => isMeetingLocked(props.meeting.status))
const canManage = computed(() => can('meetings.manage_attendees') && !readOnly.value)

const attendees = computed(() => props.meeting.attendees ?? [])
const takenEmployeeIds = computed(() => attendees.value.map((a) => a.employee.id))
const fetchActiveEmployees = (params: { search?: string; page: number; per_page: number }) =>
  listEmployees({ ...params, status: 'active' })

const attendanceOptions = computed<AppSelectOption[]>(() =>
  ATTENDANCE_STATUSES.map((status) => ({
    value: status,
    label: t(`meetings.attendance.${status}`),
  })),
)

const isPending = computed(
  () =>
    addMutation.isPending.value ||
    updateMutation.isPending.value ||
    removeMutation.isPending.value,
)

const isAdding = computed(() => addMutation.isPending.value)

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

async function addAttendee(): Promise<void> {
  formError.value = ''
  if (selectedEmployeeId.value === '') {
    formError.value = t('meetings.validation.employeeRequired')
    return
  }
  try {
    await addMutation.mutateAsync({
      meetingId: props.meeting.id,
      payload: { employee_id: Number(selectedEmployeeId.value) },
    })
    selectedEmployeeId.value = ''
    toast.success(t('meetings.toasts.attendeeAdded'))
    emit('refreshed')
  } catch (error) {
    formError.value = apiMessage(error)
  }
}

async function updateAttendance(
  attendee: MeetingAttendee,
  status: AttendanceStatus,
): Promise<void> {
  if (status === attendee.attendance_status) return
  try {
    await updateMutation.mutateAsync({
      meetingId: props.meeting.id,
      attendeeId: attendee.id,
      payload: { attendance_status: status },
    })
    toast.success(t('meetings.toasts.attendanceUpdated'))
    emit('refreshed')
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

async function removeAttendee(attendee: MeetingAttendee): Promise<void> {
  const confirmed = await confirm({
    title: t('meetings.confirmRemoveAttendeeTitle'),
    message: t('meetings.confirmRemoveAttendeeBody', {
      name: attendee.employee.full_name,
    }),
    confirmLabel: t('meetings.actions.remove'),
    cancelLabel: t('meetings.cancel'),
    variant: 'danger',
  })
  if (!confirmed) return
  try {
    await removeMutation.mutateAsync({
      meetingId: props.meeting.id,
      attendeeId: attendee.id,
    })
    toast.success(t('meetings.toasts.attendeeRemoved'))
    emit('refreshed')
  } catch (error) {
    toast.error(apiMessage(error))
  }
}
</script>

<template>
  <div class="rounded-2xl border border-brand-border bg-brand-surface p-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h3 class="text-base font-bold text-brand-text">{{ t('meetings.sections.attendees') }}</h3>
        <p class="mt-1 text-sm text-brand-text-secondary">
          {{ t('meetings.attendeesSubtitle') }}
        </p>
      </div>
      <span
        class="inline-flex rounded-full bg-brand-primary-soft px-2.5 py-0.5 text-xs font-semibold text-brand-primary-dark"
      >
        {{ t('meetings.attendeeCount', { count: attendees.length }) }}
      </span>
    </div>

    <PermissionGuard v-if="canManage" permission="meetings.manage_attendees">
      <div class="mt-4 flex flex-wrap items-end gap-2">
        <div class="min-w-56 flex-1">
          <span class="mb-1.5 block text-sm font-semibold text-brand-text">
            {{ t('meetings.fields.addAttendee') }}
          </span>
          <AppRemoteSelect
            :model-value="selectedEmployeeId"
            query-key="employees-active"
            :fetcher="fetchActiveEmployees"
            :map-option="employeeSelectOption"
            :empty-option="{ value: '', label: t('meetings.selectEmployee') }"
            :exclude-values="takenEmployeeIds"
            :disabled="isPending"
            @update:model-value="selectedEmployeeId = toSelectId($event)"
          />
        </div>
        <button
          type="button"
          class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white disabled:opacity-50"
          :disabled="isPending"
          @click="addAttendee"
        >
          <Loader2 v-if="isAdding" class="h-4 w-4 animate-spin" />
          <Plus v-else class="h-4 w-4" />
          {{ t('meetings.actions.addAttendee') }}
        </button>
      </div>
      <p v-if="formError" class="mt-2 text-xs text-red-600" role="alert">{{ formError }}</p>
    </PermissionGuard>

    <div
      v-if="attendees.length === 0"
      class="mt-6 rounded-xl border border-dashed border-brand-border px-4 py-8 text-center text-sm text-brand-text-muted"
    >
      {{ t('meetings.attendeesEmpty') }}
    </div>

    <ul v-else class="mt-4 divide-y divide-brand-border/80">
      <li
        v-for="attendee in attendees"
        :key="attendee.id"
        class="flex flex-wrap items-center justify-between gap-3 py-3"
      >
        <div class="min-w-0">
          <p class="text-sm font-semibold text-brand-text">{{ attendee.employee.full_name }}</p>
          <p class="font-mono text-xs text-brand-text-muted" dir="ltr">
            {{ attendee.employee.employee_number }}
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <AppSelect
            :model-value="attendee.attendance_status"
            :options="attendanceOptions"
            :disabled="!canManage || isPending"
            @update:model-value="
              updateAttendance(attendee, ($event as AttendanceStatus) || 'invited')
            "
          />
          <PermissionGuard v-if="canManage" permission="meetings.manage_attendees">
            <button
              type="button"
              class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-red-700 transition hover:bg-red-50"
              :aria-label="t('meetings.actions.remove')"
              :disabled="isPending"
              @click="removeAttendee(attendee)"
            >
              <Trash2 class="h-4 w-4" />
            </button>
          </PermissionGuard>
        </div>
      </li>
    </ul>
  </div>
</template>
