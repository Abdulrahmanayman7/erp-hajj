<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

import { listEmployees } from '@/modules/employees/api/employeesApi'
import AppDateTimeInput from '@/shared/components/AppDateTimeInput.vue'
import AppFormDrawer from '@/shared/components/AppFormDrawer.vue'
import AppRemoteSelect from '@/shared/components/AppRemoteSelect.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { employeeSelectOption, toSelectId } from '@/shared/lookups/selectOptions'

import type { Meeting, MeetingFormState, MeetingLocationType } from '../types/meetings'

const props = defineProps<{
  open: boolean
  editing: Meeting | null
  form: MeetingFormState
  formError: string
  fieldErrors: Record<string, string>
  submitting: boolean
  orgUnitOptions: AppSelectOption[]
  locationTypeOptions: AppSelectOption[]
}>()

const emit = defineEmits<{
  close: []
  submit: []
  'update:form': [MeetingFormState]
}>()

const { t } = useI18n()

const isEdit = computed(() => props.editing != null)
const title = computed(() => (isEdit.value ? t('meetings.editTitle') : t('meetings.createTitle')))
const subtitle = computed(() =>
  isEdit.value ? t('meetings.editSubtitle') : t('meetings.createSubtitle'),
)
const primaryLabel = computed(() =>
  isEdit.value ? t('meetings.editCta') : t('meetings.createCta'),
)

const meetingNumberDisplay = computed(() => {
  if (isEdit.value && props.editing) {
    return props.editing.meeting_number
  }
  return t('meetings.meetingNumberPlaceholder')
})

const showMeetingLink = computed(
  () => props.form.location_type === 'remote' || props.form.location_type === 'hybrid',
)
const fetchActiveEmployees = (params: { search?: string; page: number; per_page: number }) =>
  listEmployees({ ...params, status: 'active' })
const emptyEmployee = computed<AppSelectOption>(() => ({ value: '', label: t('meetings.noEmployee') }))
const selectedChairperson = computed(() =>
  props.editing?.chairperson ? employeeSelectOption(props.editing.chairperson) : null,
)
const selectedSecretary = computed(() =>
  props.editing?.secretary ? employeeSelectOption(props.editing.secretary) : null,
)

function patch(partial: Partial<MeetingFormState>): void {
  emit('update:form', { ...props.form, ...partial })
}

function onOpenUpdate(value: boolean): void {
  if (!value) emit('close')
}
</script>

<template>
  <AppFormDrawer
    :open="open"
    :title="title"
    :description="subtitle"
    size="md"
    :submitting="submitting"
    :cancel-label="t('meetings.cancel')"
    :submit-label="primaryLabel"
    form-id="meeting-form-drawer"
    @update:open="onOpenUpdate"
    @close="emit('close')"
    @submit="emit('submit')"
  >
    <section class="space-y-4">
      <h4 class="text-sm font-bold text-brand-text">
        {{ t('meetings.sections.basics') }}
      </h4>

      <label class="block">
        <span class="mb-2 block text-sm font-semibold text-brand-text">
          {{ t('meetings.fields.meetingNumber') }}
        </span>
        <input
          :value="meetingNumberDisplay"
          type="text"
          readonly
          class="h-12 w-full rounded-[11px] border border-brand-border bg-brand-bg px-3.5 text-sm text-brand-text-secondary outline-none"
        />
        <p class="mt-1.5 text-xs text-brand-text-muted">
          {{ t('meetings.meetingNumberHint') }}
        </p>
      </label>

      <label class="block">
        <span class="mb-2 block text-sm font-semibold text-brand-text">
          {{ t('meetings.fields.title') }}
        </span>
        <input
          :value="form.title"
          type="text"
          required
          class="h-12 w-full rounded-[11px] border border-brand-border bg-brand-surface px-3.5 text-sm text-brand-text outline-none transition placeholder:text-brand-text-muted focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg disabled:opacity-70"
          :disabled="submitting"
          @input="patch({ title: ($event.target as HTMLInputElement).value })"
        />
        <p v-if="fieldErrors.title" class="mt-1.5 text-xs text-red-600">
          {{ fieldErrors.title }}
        </p>
      </label>

      <label class="block">
        <span class="mb-2 block text-sm font-semibold text-brand-text">
          {{ t('meetings.fields.description') }}
        </span>
        <textarea
          :value="form.description"
          rows="3"
          class="w-full rounded-[11px] border border-brand-border bg-brand-surface px-3.5 py-3 text-sm text-brand-text outline-none transition placeholder:text-brand-text-muted focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg disabled:opacity-70"
          :disabled="submitting"
          @input="patch({ description: ($event.target as HTMLTextAreaElement).value })"
        />
      </label>
    </section>

    <section class="space-y-4">
      <h4 class="text-sm font-bold text-brand-text">
        {{ t('meetings.sections.scheduleLocation') }}
      </h4>

      <label class="block">
        <span class="mb-2 block text-sm font-semibold text-brand-text">
          {{ t('meetings.fields.scheduledAt') }}
        </span>
        <AppDateTimeInput
          :model-value="form.scheduled_at"
          :disabled="submitting"
          :placeholder="t('meetings.fields.scheduledAt')"
          @update:model-value="patch({ scheduled_at: $event })"
        />
        <p class="mt-1.5 text-xs text-brand-text-muted">
          {{ t('meetings.scheduledAtHint') }}
        </p>
      </label>

      <div>
        <span class="mb-2 block text-sm font-semibold text-brand-text">
          {{ t('meetings.fields.locationType') }}
        </span>
        <AppSelect
          :model-value="form.location_type"
          :options="locationTypeOptions"
          :disabled="submitting"
          @update:model-value="
            patch({
              location_type: ($event as MeetingLocationType) || 'physical',
            })
          "
        />
      </div>

      <label class="block">
        <span class="mb-2 block text-sm font-semibold text-brand-text">
          {{ t('meetings.fields.locationText') }}
        </span>
        <input
          :value="form.location_text"
          type="text"
          class="h-12 w-full rounded-[11px] border border-brand-border bg-brand-surface px-3.5 text-sm text-brand-text outline-none transition focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg disabled:opacity-70"
          :disabled="submitting"
          @input="patch({ location_text: ($event.target as HTMLInputElement).value })"
        />
      </label>

      <label v-if="showMeetingLink" class="block">
        <span class="mb-2 block text-sm font-semibold text-brand-text">
          {{ t('meetings.fields.meetingLink') }}
        </span>
        <input
          :value="form.meeting_link"
          type="url"
          dir="ltr"
          class="h-12 w-full rounded-[11px] border border-brand-border bg-brand-surface px-3.5 text-sm text-brand-text outline-none transition focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg disabled:opacity-70"
          :disabled="submitting"
          @input="patch({ meeting_link: ($event.target as HTMLInputElement).value })"
        />
      </label>
    </section>

    <section class="space-y-4">
      <h4 class="text-sm font-bold text-brand-text">
        {{ t('meetings.sections.officials') }}
      </h4>

      <div>
        <span class="mb-2 block text-sm font-semibold text-brand-text">
          {{ t('meetings.fields.organizationUnit') }}
        </span>
        <AppSelect
          :model-value="form.organization_unit_id"
          :options="orgUnitOptions"
          :placeholder="t('meetings.selectOrgUnit')"
          :disabled="submitting"
          searchable
          @update:model-value="
            patch({
              organization_unit_id:
                $event === null || $event === '' ? '' : Number($event),
            })
          "
        />
      </div>

      <div>
        <span class="mb-2 block text-sm font-semibold text-brand-text">
          {{ t('meetings.fields.chairperson') }}
        </span>
        <AppRemoteSelect
          :model-value="form.chairperson_employee_id"
          query-key="employees-active"
          :fetcher="fetchActiveEmployees"
          :map-option="employeeSelectOption"
          :empty-option="emptyEmployee"
          :selected-option="selectedChairperson"
          :placeholder="t('meetings.selectChairperson')"
          :disabled="submitting"
          :enabled="open"
          @update:model-value="patch({ chairperson_employee_id: toSelectId($event) })"
        />
      </div>

      <div>
        <span class="mb-2 block text-sm font-semibold text-brand-text">
          {{ t('meetings.fields.secretary') }}
        </span>
        <AppRemoteSelect
          :model-value="form.secretary_employee_id"
          query-key="employees-active"
          :fetcher="fetchActiveEmployees"
          :map-option="employeeSelectOption"
          :empty-option="emptyEmployee"
          :selected-option="selectedSecretary"
          :placeholder="t('meetings.selectSecretary')"
          :disabled="submitting"
          :enabled="open"
          @update:model-value="patch({ secretary_employee_id: toSelectId($event) })"
        />
      </div>
    </section>

    <section class="space-y-4">
      <h4 class="text-sm font-bold text-brand-text">
        {{ t('meetings.sections.notes') }}
      </h4>
      <label class="block">
        <span class="mb-2 block text-sm font-semibold text-brand-text">
          {{ t('meetings.fields.notes') }}
        </span>
        <textarea
          :value="form.notes"
          rows="3"
          class="w-full rounded-[11px] border border-brand-border bg-brand-surface px-3.5 py-3 text-sm text-brand-text outline-none transition placeholder:text-brand-text-muted focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg disabled:opacity-70"
          :disabled="submitting"
          @input="patch({ notes: ($event.target as HTMLTextAreaElement).value })"
        />
      </label>
    </section>

    <p
      v-if="formError"
      class="rounded-[11px] border border-red-200 bg-red-50 px-3.5 py-3 text-sm text-red-700"
      role="alert"
    >
      {{ formError }}
    </p>
  </AppFormDrawer>
</template>
