<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRouter } from 'vue-router'
import { ChevronLeft, ChevronRight, Eye, Pencil, Plus, Search } from 'lucide-vue-next'

import { useOrganizationUnitsFlatQuery } from '@/modules/organization/queries/useOrganizationUnitsQuery'
import { ApiError } from '@/shared/api/http'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import AppTooltip from '@/shared/components/AppTooltip.vue'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'
import { useDebouncedRef } from '@/shared/composables/useDebouncedRef'

import MeetingFormDrawer from '../components/MeetingFormDrawer.vue'
import {
  useCreateMeetingMutation,
  useUpdateMeetingMutation,
} from '../mutations/useMeetingMutations'
import { useMeetingsQuery } from '../queries/useMeetingsQuery'
import type { Meeting, MeetingFormState, MeetingStatus } from '../types/meetings'
import {
  MEETING_LOCATION_TYPES,
  MEETING_STATUSES,
  canEditMeeting,
  fromDatetimeLocalValue,
  isMeetingToday,
  mapMeetingErrorCode,
  meetingStatusBadgeClass,
  meetingStatusDotClass,
  resolveMeetingsListState,
  toDatetimeLocalValue,
  validateMeetingForm,
} from '../validation/meetingValidation'

const { t } = useI18n()
const router = useRouter()
const { permissions } = usePermissions()
const toast = useToast()

const focusedRowIndex = ref(-1)

const filters = reactive({
  search: '',
  status: 'all' as MeetingStatus | 'all',
  organization_unit_id: '' as number | '',
  date_from: '',
  date_to: '',
  upcoming: false,
  page: 1,
  per_page: 15,
  sort: 'scheduled_at',
  direction: 'desc',
})

const committedSearch = useDebouncedRef(() => filters.search)
const queryParams = computed(() => ({
  search: committedSearch.value || undefined,
  status: filters.status === 'all' ? undefined : filters.status,
  organization_unit_id: filters.organization_unit_id,
  date_from: filters.date_from || undefined,
  date_to: filters.date_to || undefined,
  upcoming: filters.upcoming ? (1 as const) : undefined,
  page: filters.page,
  per_page: filters.per_page,
  sort: filters.sort,
  direction: filters.direction,
}))

const { data, isLoading, isError, refetch, isFetching } = useMeetingsQuery(queryParams)

const { data: orgUnitsData } = useOrganizationUnitsFlatQuery({ status: 'active' })

const createMutation = useCreateMeetingMutation()
const updateMutation = useUpdateMeetingMutation()

const meetings = computed(() => data.value?.data ?? [])
const meta = computed(() => data.value?.meta)
const listState = computed(() =>
  resolveMeetingsListState({
    isLoading: isLoading.value,
    isError: isError.value,
    count: meetings.value.length,
  }),
)

const drawerOpen = ref(false)
const editing = ref<Meeting | null>(null)
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

const statusOptions = computed<AppSelectOption[]>(() => [
  { value: 'all', label: t('meetings.filters.allStatuses') },
  ...MEETING_STATUSES.map((status) => ({
    value: status,
    label: t(`meetings.status.${status}`),
  })),
])

const orgUnitFilterOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('meetings.filters.allOrgUnits') },
  ...(orgUnitsData.value?.data ?? []).map((u) => ({
    value: u.id,
    label: u.name,
    hint: u.code,
  })),
])

const orgUnitFormOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('meetings.noOrgUnit') },
  ...(orgUnitsData.value?.data ?? []).map((u) => ({
    value: u.id,
    label: u.name,
    hint: u.code,
  })),
])

const locationTypeOptions = computed<AppSelectOption[]>(() =>
  MEETING_LOCATION_TYPES.map((type) => ({
    value: type,
    label: t(`meetings.locationType.${type}`),
  })),
)

const isFormSubmitting = computed(
  () => createMutation.isPending.value || updateMutation.isPending.value,
)

const permissionList = computed(() => permissions.value ?? [])

watch(
  () => [
    committedSearch.value,
    filters.status,
    filters.organization_unit_id,
    filters.date_from,
    filters.date_to,
    filters.upcoming,
  ],
  () => {
    filters.page = 1
  },
)

watch(
  meetings,
  (rows) => {
    if (rows.length === 0) {
      focusedRowIndex.value = -1
      return
    }
    if (focusedRowIndex.value >= rows.length) {
      focusedRowIndex.value = rows.length - 1
    }
  },
  { deep: false },
)

function focusRow(index: number): void {
  if (meetings.value.length === 0) {
    focusedRowIndex.value = -1
    return
  }
  focusedRowIndex.value = Math.min(Math.max(index, 0), meetings.value.length - 1)
}

function openFocusedMeeting(): void {
  const meeting = meetings.value[focusedRowIndex.value]
  if (!meeting) return
  void router.push(`/app/meetings/${meeting.id}`)
}

function onTableKeydown(event: KeyboardEvent): void {
  if (drawerOpen.value || meetings.value.length === 0) return

  const target = event.target as HTMLElement | null
  if (target) {
    const tag = target.tagName
    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || target.isContentEditable) {
      return
    }
  }

  if (event.key === 'ArrowDown') {
    event.preventDefault()
    focusRow(focusedRowIndex.value < 0 ? 0 : focusedRowIndex.value + 1)
    return
  }

  if (event.key === 'ArrowUp') {
    event.preventDefault()
    focusRow(focusedRowIndex.value < 0 ? 0 : focusedRowIndex.value - 1)
    return
  }

  if (event.key === 'Home') {
    event.preventDefault()
    focusRow(0)
    return
  }

  if (event.key === 'End') {
    event.preventDefault()
    focusRow(meetings.value.length - 1)
    return
  }

  if (event.key === 'Enter' && focusedRowIndex.value >= 0) {
    event.preventDefault()
    openFocusedMeeting()
  }
}

function rowToneClass(index: number): string {
  if (focusedRowIndex.value === index) {
    return 'bg-[#EDF6F1]'
  }
  return index % 2 === 1 ? 'bg-[#FAFBFA]' : 'bg-brand-surface'
}

function rowAccentClass(index: number): string {
  return focusedRowIndex.value === index
    ? 'border-s-brand-primary'
    : 'border-s-transparent'
}

function emptyForm(): MeetingFormState {
  return {
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
  }
}

function assignForm(next: MeetingFormState): void {
  Object.assign(form, next)
}

function clearFieldErrors(): void {
  Object.keys(fieldErrors).forEach((k) => delete fieldErrors[k])
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

function openCreate(): void {
  editing.value = null
  assignForm(emptyForm())
  formError.value = ''
  clearFieldErrors()
  drawerOpen.value = true
}

function openEdit(meeting: Meeting): void {
  if (!canEditMeeting(meeting.status, permissionList.value)) return
  editing.value = meeting
  assignForm({
    title: meeting.title,
    description: meeting.description ?? '',
    scheduled_at: toDatetimeLocalValue(meeting.scheduled_at),
    location_type: meeting.location_type,
    location_text: meeting.location_text ?? '',
    meeting_link: meeting.meeting_link ?? '',
    organization_unit_id: meeting.organization_unit?.id ?? '',
    chairperson_employee_id: meeting.chairperson?.id ?? '',
    secretary_employee_id: meeting.secretary?.id ?? '',
    notes: meeting.notes ?? '',
  })
  formError.value = ''
  clearFieldErrors()
  drawerOpen.value = true
}

function closeDrawer(): void {
  drawerOpen.value = false
}

async function submitForm(): Promise<void> {
  formError.value = ''
  clearFieldErrors()
  const validation = validateMeetingForm(form)
  if (validation.title) {
    fieldErrors.title = fieldMessage(validation.title)
    return
  }

  const payload = {
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
  }

  try {
    if (editing.value) {
      await updateMutation.mutateAsync({ id: editing.value.id, payload })
      toast.success(t('meetings.toasts.updated'))
    } else {
      await createMutation.mutateAsync(payload)
      toast.success(t('meetings.toasts.created'))
    }
    closeDrawer()
  } catch (error) {
    formError.value = apiMessage(error)
  }
}

function formatScheduledDate(value: string | null): string {
  if (!value) return '—'
  try {
    return new Intl.DateTimeFormat('ar-SA', {
      dateStyle: 'medium',
    }).format(new Date(value))
  } catch {
    return value
  }
}

function formatScheduledTime(value: string | null): string {
  if (!value) return ''
  try {
    return new Intl.DateTimeFormat('ar-SA', {
      timeStyle: 'short',
    }).format(new Date(value))
  } catch {
    return ''
  }
}

function canEditRow(meeting: Meeting): boolean {
  return canEditMeeting(meeting.status, permissionList.value)
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div class="min-w-0">
        <h2 class="text-[1.75rem] font-bold leading-tight text-brand-text">
          {{ t('meetings.title') }}
        </h2>
        <p class="mt-1.5 text-sm text-brand-text-secondary">
          {{ t('meetings.subtitle') }}
        </p>
        <p v-if="meta" class="mt-2">
          <span
            class="inline-flex items-center rounded-full bg-brand-primary-soft px-2.5 py-0.5 text-xs font-semibold text-brand-primary-dark"
          >
            {{ t('meetings.total', { count: meta.total }) }}
          </span>
        </p>
      </div>
      <PermissionGuard permission="meetings.create">
        <button
          type="button"
          class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white transition hover:bg-brand-primary"
          @click="openCreate"
        >
          <Plus class="h-4 w-4" :stroke-width="2.25" />
          <span>{{ t('meetings.add') }}</span>
        </button>
      </PermissionGuard>
    </div>

    <div
      class="flex flex-wrap items-center gap-3 rounded-2xl border border-brand-border bg-brand-surface p-4 shadow-[0_1px_2px_rgba(23,32,29,0.03)]"
    >
      <div class="relative min-w-48 flex-1">
        <Search
          class="pointer-events-none absolute inset-s-3 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-text-muted"
          :stroke-width="1.75"
          aria-hidden="true"
        />
        <input
          v-model="filters.search"
          type="search"
          class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface pe-3 ps-10 text-sm text-brand-text outline-none transition placeholder:text-brand-text-muted focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
          :placeholder="t('meetings.searchPlaceholder')"
        />
      </div>
      <AppSelect v-model="filters.status" :options="statusOptions" />
      <AppSelect
        v-model="filters.organization_unit_id"
        :options="orgUnitFilterOptions"
        searchable
      />
      <label class="block">
        <span class="sr-only">{{ t('meetings.filters.dateFrom') }}</span>
        <input
          v-model="filters.date_from"
          type="date"
          class="h-11 rounded-xl border border-brand-border bg-brand-surface px-3 text-sm text-brand-text outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
        />
      </label>
      <label class="block">
        <span class="sr-only">{{ t('meetings.filters.dateTo') }}</span>
        <input
          v-model="filters.date_to"
          type="date"
          class="h-11 rounded-xl border border-brand-border bg-brand-surface px-3 text-sm text-brand-text outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
        />
      </label>
      <label
        class="inline-flex h-11 cursor-pointer items-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-3 text-sm font-semibold text-brand-text"
      >
        <input v-model="filters.upcoming" type="checkbox" class="rounded border-brand-border" />
        <span>{{ t('meetings.filters.upcoming') }}</span>
      </label>
    </div>

    <div
      v-if="listState === 'loading'"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center text-sm text-brand-text-muted"
    >
      {{ t('meetings.loading') }}
    </div>
    <div
      v-else-if="listState === 'error'"
      class="rounded-2xl border border-red-200 bg-red-50 p-10 text-center"
    >
      <p class="text-sm text-red-700">{{ t('meetings.errors.load') }}</p>
      <button
        type="button"
        class="mt-3 text-sm font-semibold text-brand-primary-dark underline"
        @click="() => refetch()"
      >
        {{ t('meetings.retry') }}
      </button>
    </div>
    <div
      v-else-if="listState === 'empty'"
      class="rounded-2xl border border-brand-border bg-brand-surface p-10 text-center"
    >
      <p class="text-sm text-brand-text-muted">{{ t('meetings.empty') }}</p>
      <PermissionGuard permission="meetings.create">
        <button
          type="button"
          class="mt-4 inline-flex h-10 items-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white"
          @click="openCreate"
        >
          <Plus class="h-4 w-4" />
          {{ t('meetings.add') }}
        </button>
      </PermissionGuard>
    </div>
    <div
      v-else
      ref="tableRoot"
      class="overflow-hidden rounded-2xl border border-brand-border bg-brand-surface shadow-[0_1px_2px_rgba(23,32,29,0.03)] outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/25"
      tabindex="0"
      role="grid"
      :aria-rowcount="meetings.length"
      :aria-label="t('meetings.title')"
      @keydown="onTableKeydown"
    >
      <div class="overflow-x-auto">
        <table class="min-w-full border-separate border-spacing-0 text-sm">
          <thead>
            <tr class="bg-[#F4F6F5]">
              <th
                class="whitespace-nowrap border-b border-s-[3px] border-brand-border border-s-transparent px-5 py-3.5 text-start text-xs font-bold tracking-wide text-brand-text"
              >
                {{ t('meetings.columns.meetingNumber') }}
              </th>
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-start text-xs font-bold tracking-wide text-brand-text"
              >
                {{ t('meetings.columns.title') }}
              </th>
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text"
              >
                {{ t('meetings.columns.scheduledAt') }}
              </th>
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text"
              >
                {{ t('meetings.columns.chairperson') }}
              </th>
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text"
              >
                {{ t('meetings.columns.organizationUnit') }}
              </th>
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text"
              >
                {{ t('meetings.columns.status') }}
              </th>
              <th
                class="whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text"
              >
                {{ t('meetings.columns.attendeeCount') }}
              </th>
              <th
                class="w-28 whitespace-nowrap border-b border-brand-border px-5 py-3.5 text-center text-xs font-bold tracking-wide text-brand-text"
              >
                {{ t('meetings.columns.actions') }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(meeting, index) in meetings"
              :key="meeting.id"
              class="group"
              :class="rowToneClass(index)"
              role="row"
              :aria-selected="focusedRowIndex === index"
              @mouseenter="focusedRowIndex = index"
            >
              <td
                class="whitespace-nowrap border-b border-brand-border/80 border-s-[3px] px-5 py-3.5 transition-colors duration-150 group-hover:border-s-brand-primary group-hover:bg-[#EDF6F1]"
                :class="rowAccentClass(index)"
              >
                <RouterLink
                  :to="`/app/meetings/${meeting.id}`"
                  class="inline-flex items-center gap-1.5 rounded-lg border border-brand-border bg-brand-bg px-2.5 py-1 font-mono text-[12px] font-bold tracking-wide text-brand-primary-dark shadow-[0_1px_0_rgba(23,32,29,0.04)] transition group-hover:border-brand-primary/30 group-hover:bg-brand-surface hover:border-brand-primary/35 hover:bg-brand-primary-soft hover:text-brand-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/25"
                  :title="t('meetings.actions.view')"
                  dir="ltr"
                >
                  {{ meeting.meeting_number }}
                </RouterLink>
              </td>
              <td
                class="border-b border-brand-border/80 px-5 py-3.5 font-semibold text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]"
              >
                <RouterLink
                  :to="`/app/meetings/${meeting.id}`"
                  class="transition group-hover:text-brand-primary-dark hover:underline hover:underline-offset-2"
                >
                  {{ meeting.title }}
                </RouterLink>
              </td>
              <td
                class="border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]"
              >
                <div
                  v-if="meeting.scheduled_at"
                  class="inline-flex flex-col items-center gap-0.5 leading-tight"
                >
                  <span class="font-semibold">{{ formatScheduledDate(meeting.scheduled_at) }}</span>
                  <span class="text-xs text-brand-text" dir="ltr">{{
                    formatScheduledTime(meeting.scheduled_at)
                  }}</span>
                </div>
                <span v-else>—</span>
              </td>
              <td
                class="border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]"
              >
                {{ meeting.chairperson?.full_name ?? '—' }}
              </td>
              <td
                class="border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]"
              >
                {{ meeting.organization_unit?.name ?? '—' }}
              </td>
              <td
                class="border-b border-brand-border/80 px-5 py-3.5 text-center transition-colors duration-150 group-hover:bg-[#EDF6F1]"
              >
                <div class="flex flex-wrap items-center justify-center gap-1.5">
                  <span
                    class="inline-flex min-w-[7.25rem] items-center justify-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-bold tracking-wide shadow-sm"
                    :class="meetingStatusBadgeClass(meeting.status)"
                  >
                    <span
                      class="h-1.5 w-1.5 shrink-0 rounded-full"
                      :class="meetingStatusDotClass(meeting.status)"
                      aria-hidden="true"
                    />
                    {{ t(`meetings.status.${meeting.status}`) }}
                  </span>
                  <span
                    v-if="meeting.is_upcoming"
                    class="inline-flex items-center rounded-full bg-sky-50 px-2 py-0.5 text-[11px] font-semibold text-sky-900 ring-1 ring-inset ring-sky-200/80"
                  >
                    {{ t('meetings.upcomingBadge') }}
                  </span>
                  <span
                    v-else-if="isMeetingToday(meeting.scheduled_at)"
                    class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-900 ring-1 ring-inset ring-amber-200/80"
                  >
                    {{ t('meetings.todayBadge') }}
                  </span>
                </div>
              </td>
              <td
                class="border-b border-brand-border/80 px-5 py-3.5 text-center text-brand-text transition-colors duration-150 group-hover:bg-[#EDF6F1]"
              >
                {{ meeting.attendee_count ?? '—' }}
              </td>
              <td
                class="border-b border-brand-border/80 px-5 py-3.5 text-center transition-colors duration-150 group-hover:bg-[#EDF6F1]"
              >
                <div
                  class="inline-flex items-center justify-center gap-0.5 opacity-70 transition group-hover:opacity-100"
                >
                  <AppTooltip :text="t('meetings.actions.view')">
                    <RouterLink
                      :to="`/app/meetings/${meeting.id}`"
                      class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-brand-text transition hover:bg-brand-surface hover:text-brand-primary-dark"
                      :aria-label="t('meetings.actions.view')"
                    >
                      <Eye class="h-4 w-4" :stroke-width="2" />
                    </RouterLink>
                  </AppTooltip>
                  <PermissionGuard v-if="canEditRow(meeting)" permission="meetings.update">
                    <AppTooltip :text="t('meetings.actions.edit')">
                      <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-brand-primary-dark transition hover:bg-brand-surface"
                        :aria-label="t('meetings.actions.edit')"
                        @click="openEdit(meeting)"
                      >
                        <Pencil class="h-4 w-4" :stroke-width="2" />
                      </button>
                    </AppTooltip>
                  </PermissionGuard>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div
        v-if="meta && meta.last_page > 1"
        class="flex items-center justify-between gap-3 border-t border-brand-border bg-[#F7F8F6] px-5 py-3 text-sm"
      >
        <button
          type="button"
          class="inline-flex h-9 items-center gap-1 rounded-lg border border-brand-border bg-brand-surface px-3 font-semibold text-brand-text transition hover:bg-brand-bg disabled:cursor-not-allowed disabled:opacity-40"
          :disabled="filters.page <= 1 || isFetching"
          @click="filters.page -= 1"
        >
          <ChevronRight class="h-4 w-4" :stroke-width="2" />
          <span>{{ t('meetings.prev') }}</span>
        </button>
        <span class="text-xs font-semibold text-brand-text-muted">
          {{ filters.page }} / {{ meta.last_page }}
        </span>
        <button
          type="button"
          class="inline-flex h-9 items-center gap-1 rounded-lg border border-brand-border bg-brand-surface px-3 font-semibold text-brand-text transition hover:bg-brand-bg disabled:cursor-not-allowed disabled:opacity-40"
          :disabled="filters.page >= meta.last_page || isFetching"
          @click="filters.page += 1"
        >
          <span>{{ t('meetings.next') }}</span>
          <ChevronLeft class="h-4 w-4" :stroke-width="2" />
        </button>
      </div>
    </div>

    <MeetingFormDrawer
      :open="drawerOpen"
      :editing="editing"
      :form="form"
      :form-error="formError"
      :field-errors="fieldErrors"
      :submitting="isFormSubmitting"
      :org-unit-options="orgUnitFormOptions"
      :location-type-options="locationTypeOptions"
      @close="closeDrawer"
      @submit="submitForm"
      @update:form="assignForm"
    />
  </div>
</template>
