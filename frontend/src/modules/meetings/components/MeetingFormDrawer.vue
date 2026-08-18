<script setup lang="ts">
import { computed, nextTick, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Loader2, X } from 'lucide-vue-next'

import { listEmployees } from '@/modules/employees/api/employeesApi'
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
const titleInputRef = ref<HTMLInputElement | null>(null)
let previouslyFocused: HTMLElement | null = null

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

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape' && props.open && !props.submitting) {
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
      titleInputRef.value?.focus()
      return
    }
    document.removeEventListener('keydown', onKeydown)
    await nextTick()
    previouslyFocused?.focus?.()
    previouslyFocused = null
  },
)

onUnmounted(() => {
  document.removeEventListener('keydown', onKeydown)
})
</script>

<template>
  <Teleport to="body">
    <Transition name="mtg-drawer">
      <div v-if="open" class="mtg-drawer-root fixed inset-0 z-50" role="presentation">
        <div
          class="mtg-drawer-backdrop absolute inset-0 bg-[rgba(15,23,20,0.32)]"
          aria-hidden="true"
          @click="emit('close')"
        />

        <aside
          class="mtg-drawer-panel absolute inset-y-0 start-0 flex h-dvh w-full max-w-[520px] flex-col bg-brand-surface shadow-[-12px_0_40px_-24px_rgba(23,32,29,0.35)]"
          role="dialog"
          aria-modal="true"
          :aria-label="title"
          @click.stop
        >
          <header
            class="flex shrink-0 items-start justify-between gap-4 border-b border-brand-border px-6 py-5 sm:px-7"
          >
            <div class="min-w-0 text-start">
              <h3 class="text-[21px] font-bold leading-tight text-brand-text">{{ title }}</h3>
              <p class="mt-1.5 text-[13px] leading-relaxed text-brand-text-secondary">
                {{ subtitle }}
              </p>
            </div>
            <button
              type="button"
              class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] text-brand-text-secondary transition hover:bg-brand-bg hover:text-brand-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary/25"
              :aria-label="t('meetings.closeDrawer')"
              :disabled="submitting"
              @click="emit('close')"
            >
              <X class="h-4 w-4" :stroke-width="2.25" />
            </button>
          </header>

          <form
            id="meeting-form-drawer"
            class="flex min-h-0 flex-1 flex-col"
            @submit.prevent="emit('submit')"
          >
            <div class="flex-1 space-y-6 overflow-y-auto px-6 py-6 sm:px-7">
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
                    ref="titleInputRef"
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
                  <input
                    :value="form.scheduled_at"
                    type="datetime-local"
                    class="h-12 w-full rounded-[11px] border border-brand-border bg-brand-surface px-3.5 text-sm text-brand-text outline-none transition focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg disabled:opacity-70"
                    :disabled="submitting"
                    @input="patch({ scheduled_at: ($event.target as HTMLInputElement).value })"
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
            </div>

            <footer
              class="flex shrink-0 items-center justify-end gap-2.5 border-t border-brand-border bg-brand-surface px-6 py-4 sm:px-7"
            >
              <button
                type="button"
                class="inline-flex h-11 items-center justify-center rounded-[10px] border border-brand-border bg-brand-surface px-4 text-sm font-semibold text-brand-text transition hover:bg-brand-bg disabled:opacity-50"
                :disabled="submitting"
                @click="emit('close')"
              >
                {{ t('meetings.cancel') }}
              </button>
              <button
                type="submit"
                class="inline-flex h-11 min-w-[8.5rem] items-center justify-center gap-2 rounded-[10px] bg-brand-primary-dark px-5 text-sm font-semibold text-white transition hover:bg-brand-primary disabled:opacity-60"
                :disabled="submitting"
              >
                <Loader2
                  v-if="submitting"
                  class="h-4 w-4 animate-spin"
                  :stroke-width="2.25"
                  aria-hidden="true"
                />
                <span>{{ submitting ? t('meetings.saving') : primaryLabel }}</span>
              </button>
            </footer>
          </form>
        </aside>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.mtg-drawer-enter-active,
.mtg-drawer-leave-active {
  pointer-events: none;
}

.mtg-drawer-enter-active .mtg-drawer-backdrop {
  transition: opacity 200ms ease;
}

.mtg-drawer-leave-active .mtg-drawer-backdrop {
  transition: opacity 180ms ease;
}

.mtg-drawer-enter-from .mtg-drawer-backdrop,
.mtg-drawer-leave-to .mtg-drawer-backdrop {
  opacity: 0;
}

.mtg-drawer-enter-active .mtg-drawer-panel {
  transition:
    transform 280ms cubic-bezier(0.22, 1, 0.36, 1),
    opacity 280ms cubic-bezier(0.22, 1, 0.36, 1);
}

.mtg-drawer-leave-active .mtg-drawer-panel {
  transition:
    transform 220ms cubic-bezier(0.22, 1, 0.36, 1),
    opacity 220ms cubic-bezier(0.22, 1, 0.36, 1);
}

.mtg-drawer-enter-from .mtg-drawer-panel,
.mtg-drawer-leave-to .mtg-drawer-panel {
  transform: translateX(100%);
  opacity: 0.92;
}

.mtg-drawer-enter-to .mtg-drawer-panel,
.mtg-drawer-leave-from .mtg-drawer-panel {
  transform: translateX(0);
  opacity: 1;
}

[dir='ltr'] .mtg-drawer-enter-from .mtg-drawer-panel,
[dir='ltr'] .mtg-drawer-leave-to .mtg-drawer-panel {
  transform: translateX(-100%);
}

@media (prefers-reduced-motion: reduce) {
  .mtg-drawer-enter-active .mtg-drawer-backdrop,
  .mtg-drawer-leave-active .mtg-drawer-backdrop,
  .mtg-drawer-enter-active .mtg-drawer-panel,
  .mtg-drawer-leave-active .mtg-drawer-panel {
    transition: none;
  }

  .mtg-drawer-enter-from .mtg-drawer-panel,
  .mtg-drawer-leave-to .mtg-drawer-panel,
  [dir='ltr'] .mtg-drawer-enter-from .mtg-drawer-panel,
  [dir='ltr'] .mtg-drawer-leave-to .mtg-drawer-panel {
    transform: none;
    opacity: 1;
  }
}
</style>
