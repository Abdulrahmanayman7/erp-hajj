<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { ArrowRight, Building2, CalendarClock, FileText, MapPin, Pencil, UserRound } from 'lucide-vue-next'

import EntityDocumentsSection from '@/modules/documents/components/EntityDocumentsSection.vue'
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
  meetingStatusDotClass,
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
  <div class="mx-auto max-w-[1200px] space-y-5">
    <div class="flex flex-wrap items-center gap-3">
      <button
        type="button"
        class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-brand-border bg-brand-surface px-3 text-sm font-semibold text-brand-text transition hover:bg-brand-bg"
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
      <section class="rounded-2xl border border-brand-border bg-brand-surface px-5 py-5 sm:px-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <p class="font-mono text-xs font-semibold tracking-wide text-brand-text-muted" dir="ltr">
                {{ meeting.meeting_number }}
              </p>
              <span
                class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold tracking-wide"
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
            <h2 class="mt-2 text-[1.65rem] font-bold leading-snug text-brand-text sm:text-[1.85rem]">
              {{ meeting.title }}
            </h2>
            <p class="mt-2 text-sm text-brand-text-secondary">
              <span class="font-semibold text-brand-text">{{ formatScheduledAt(meeting.scheduled_at) }}</span>
              <span class="mx-1.5 text-brand-text-muted">·</span>
              {{ t(`meetings.locationType.${meeting.location_type}`) }}
            </p>
          </div>

          <PermissionGuard v-if="canEdit" permission="meetings.update">
            <button
              type="button"
              class="inline-flex h-10 items-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-4 text-sm font-semibold text-brand-primary-dark transition hover:bg-brand-primary-soft"
              @click="openEdit"
            >
              <Pencil class="h-4 w-4" />
              {{ t('meetings.actions.edit') }}
            </button>
          </PermissionGuard>
        </div>
      </section>

      <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
        <div class="space-y-5">
          <section class="rounded-2xl border border-brand-border bg-brand-surface">
            <header class="border-b border-brand-border px-5 py-4">
              <h3 class="text-sm font-bold text-brand-text">{{ t('meetings.detailsSummary') }}</h3>
            </header>
            <dl class="grid gap-0 sm:grid-cols-2">
              <div class="flex gap-3 border-b border-brand-border/80 px-5 py-4 sm:border-e">
                <span
                  class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary"
                >
                  <CalendarClock class="h-4 w-4" :stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                  <dt class="text-xs font-semibold text-brand-text-muted">
                    {{ t('meetings.fields.scheduledAt') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text">
                    {{ formatScheduledAt(meeting.scheduled_at) }}
                  </dd>
                </div>
              </div>

              <div class="flex gap-3 border-b border-brand-border/80 px-5 py-4">
                <span
                  class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary"
                >
                  <MapPin class="h-4 w-4" :stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                  <dt class="text-xs font-semibold text-brand-text-muted">
                    {{ t('meetings.fields.locationType') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text">
                    {{ t(`meetings.locationType.${meeting.location_type}`) }}
                  </dd>
                  <dd
                    v-if="meeting.location_text"
                    class="mt-0.5 text-xs text-brand-text-secondary"
                  >
                    {{ meeting.location_text }}
                  </dd>
                  <dd
                    v-if="meeting.meeting_link"
                    class="mt-0.5 font-mono text-xs text-brand-primary-dark"
                    dir="ltr"
                  >
                    {{ meeting.meeting_link }}
                  </dd>
                </div>
              </div>

              <div class="flex gap-3 border-b border-brand-border/80 px-5 py-4 sm:border-e">
                <span
                  class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary"
                >
                  <Building2 class="h-4 w-4" :stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                  <dt class="text-xs font-semibold text-brand-text-muted">
                    {{ t('meetings.fields.organizationUnit') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text">
                    {{ meeting.organization_unit?.name ?? '—' }}
                  </dd>
                </div>
              </div>

              <div class="flex gap-3 border-b border-brand-border/80 px-5 py-4">
                <span
                  class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary"
                >
                  <UserRound class="h-4 w-4" :stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                  <dt class="text-xs font-semibold text-brand-text-muted">
                    {{ t('meetings.fields.chairperson') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text">
                    {{ meeting.chairperson?.full_name ?? '—' }}
                  </dd>
                </div>
              </div>

              <div class="flex gap-3 border-b border-brand-border/80 px-5 py-4 sm:border-e sm:border-b-0">
                <span
                  class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary"
                >
                  <UserRound class="h-4 w-4" :stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                  <dt class="text-xs font-semibold text-brand-text-muted">
                    {{ t('meetings.fields.secretary') }}
                  </dt>
                  <dd class="mt-1 text-sm font-semibold text-brand-text">
                    {{ meeting.secretary?.full_name ?? '—' }}
                  </dd>
                </div>
              </div>

              <div class="flex gap-3 px-5 py-4">
                <span
                  class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-bg text-brand-primary"
                >
                  <FileText class="h-4 w-4" :stroke-width="1.75" />
                </span>
                <div class="min-w-0">
                  <dt class="text-xs font-semibold text-brand-text-muted">
                    {{ t('meetings.fields.description') }}
                  </dt>
                  <dd class="mt-1 whitespace-pre-wrap text-sm font-semibold text-brand-text">
                    {{ meeting.description || '—' }}
                  </dd>
                </div>
              </div>
            </dl>
          </section>

          <section
            v-if="meeting.notes"
            class="rounded-2xl border border-brand-border bg-brand-surface"
          >
            <header class="flex items-center gap-2 border-b border-brand-border px-5 py-4">
              <FileText class="h-4 w-4 text-brand-primary" :stroke-width="1.75" />
              <h3 class="text-sm font-bold text-brand-text">{{ t('meetings.fields.notes') }}</h3>
            </header>
            <p class="whitespace-pre-wrap px-5 py-4 text-sm leading-relaxed text-brand-text-secondary">
              {{ meeting.notes }}
            </p>
          </section>

          <MeetingTimeline :transitions="meeting.transitions ?? []" />

          <MeetingAttendeesPanel
            :meeting="meeting"
            @refreshed="onRefreshed"
          />

          <MeetingAgendaPanel :meeting="meeting" @refreshed="onRefreshed" />

          <MeetingMinutesPanel :meeting="meeting" @refreshed="onRefreshed" />

          <MeetingRecommendationsPanel
            :meeting="meeting"
            @refreshed="onRefreshed"
          />

          <EntityDocumentsSection
            linkable-type="meeting"
            :linkable-id="meeting.id"
            :link-label="`${meeting.meeting_number} — ${meeting.title}`"
          />
        </div>

        <aside class="space-y-5 xl:sticky xl:top-4 xl:self-start">
          <section class="rounded-2xl border border-brand-border bg-brand-surface">
            <header class="border-b border-brand-border px-5 py-4">
              <h3 class="text-sm font-bold text-brand-text">{{ t('meetings.lifecycleTitle') }}</h3>
              <p class="mt-1 text-xs text-brand-text-secondary">
                {{ t('meetings.lifecycleHint') }}
              </p>
            </header>
            <div class="px-4 py-4">
              <MeetingLifecycleActions :meeting="meeting" @refreshed="onRefreshed" />
            </div>
          </section>
        </aside>
      </div>
    </template>

    <MeetingFormDrawer
      :open="drawerOpen"
      :editing="meeting"
      :form="form"
      :form-error="formError"
      :field-errors="fieldErrors"
      :submitting="isFormSubmitting"
      :org-unit-options="orgUnitFormOptions"
      :location-type-options="locationTypeOptions"
      @close="drawerOpen = false"
      @submit="submitForm"
      @update:form="assignForm"
    />
  </div>
</template>
