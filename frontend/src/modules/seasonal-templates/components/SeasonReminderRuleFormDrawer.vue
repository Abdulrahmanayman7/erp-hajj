<script setup lang="ts">
import { computed, nextTick, onUnmounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Loader2, X } from 'lucide-vue-next'

import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'

import {
  APPROVED_RECIPIENT_PERMISSIONS,
  MAX_OFFSET_DAYS,
  REMINDER_SEVERITIES,
  REMINDER_TRIGGER_MODES,
  type ReminderRuleFormState,
  type ReminderSeverity,
  type ReminderTriggerMode,
} from '../types/seasonReminderRules'
import { computeFormFireDate } from '../validation/seasonReminderRuleValidation'

const props = defineProps<{
  open: boolean
  editing: boolean
  form: ReminderRuleFormState
  formError: string
  fieldErrors: Record<string, string>
  submitting: boolean
  seasonLabel: string
  /** Season `operational_starts_on` (Y-m-d) used for the computed fire date preview. */
  seasonStartsOn: string | null
}>()

const emit = defineEmits<{
  close: []
  submit: []
  'update:form': [ReminderRuleFormState]
}>()

const { t, locale } = useI18n()

const title = computed(() =>
  props.editing ? t('seasonalTemplates.reminders.form.editTitle') : t('seasonalTemplates.reminders.form.createTitle'),
)

const severityOptions = computed<AppSelectOption[]>(() =>
  REMINDER_SEVERITIES.map((s) => ({ value: s, label: t(`seasonalTemplates.reminders.severity.${s}`) })),
)
const permissionOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('seasonalTemplates.reminders.form.recipientPlaceholder') },
  ...APPROVED_RECIPIENT_PERMISSIONS.map((p) => ({
    value: p,
    label: t(`seasonalTemplates.reminders.recipients.${p}`),
    hint: p,
  })),
])

const fireDate = computed(() => computeFormFireDate(props.form, props.seasonStartsOn))
const fireDateLabel = computed(() => {
  if (!fireDate.value) return ''
  try {
    return new Intl.DateTimeFormat(locale.value === 'ar' ? 'ar-SA' : undefined, { dateStyle: 'full' }).format(
      new Date(`${fireDate.value}T00:00:00`),
    )
  } catch {
    return fireDate.value
  }
})

function patch(partial: Partial<ReminderRuleFormState>): void {
  emit('update:form', { ...props.form, ...partial })
}

function onTriggerMode(mode: ReminderTriggerMode): void {
  patch({ trigger_mode: mode })
}

function onSeverity(value: string | number | null): void {
  patch({ severity: String(value ?? 'info') as ReminderSeverity })
}

function onPermission(value: string | number | null): void {
  patch({ recipient_permission: value == null ? '' : String(value) })
}

function fieldMessage(key: string): string {
  const code = props.fieldErrors[key]
  return code ? t(`seasonalTemplates.reminders.validation.${code}`, { max: MAX_OFFSET_DAYS }) : ''
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
      document.addEventListener('keydown', onKeydown)
      await nextTick()
      return
    }
    document.removeEventListener('keydown', onKeydown)
  },
)

onUnmounted(() => document.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-50" role="presentation" data-testid="reminder-form-drawer">
      <div class="absolute inset-0 bg-[rgba(15,23,20,0.32)]" aria-hidden="true" @click="emit('close')" />
      <aside
        v-autofocus-when
        class="absolute inset-y-0 start-0 flex h-dvh w-full max-w-[520px] flex-col bg-brand-surface shadow-[-12px_0_40px_-24px_rgba(23,32,29,0.35)]"
        role="dialog"
        aria-modal="true"
        :aria-label="title"
        @click.stop
      >
        <header class="flex shrink-0 items-start justify-between gap-4 border-b border-brand-border px-4 py-5 sm:px-7">
          <div class="min-w-0 text-start">
            <h3 class="text-[21px] font-bold leading-tight text-brand-text">{{ title }}</h3>
            <p class="mt-1.5 text-[13px] leading-relaxed text-brand-text-secondary">
              {{ t('seasonalTemplates.reminders.form.subtitle', { season: seasonLabel }) }}
            </p>
          </div>
          <button
            type="button"
            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] text-brand-text-secondary transition hover:bg-brand-bg hover:text-brand-text"
            :aria-label="t('seasonalTemplates.common.close')"
            :disabled="submitting"
            @click="emit('close')"
          >
            <X class="h-5 w-5" />
          </button>
        </header>

        <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="emit('submit')">
          <div class="flex-1 space-y-4 overflow-y-auto px-4 py-5 sm:px-7">
            <p
              v-if="formError"
              class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
              role="alert"
              data-testid="reminder-form-error"
            >
              {{ formError }}
            </p>

            <p
              class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-start text-xs text-amber-900"
              data-testid="reminder-draft-hint"
            >
              {{ t('seasonalTemplates.reminders.form.draftHint') }}
            </p>

            <label class="block space-y-1.5 text-start">
              <span class="text-sm font-semibold text-brand-text">{{ t('seasonalTemplates.reminders.form.code') }}</span>
              <input
                :value="form.code"
                type="text"
                maxlength="64"
                dir="ltr"
                class="h-11 w-full rounded-xl border border-brand-border px-3 text-start text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg"
                :disabled="submitting || editing"
                data-testid="reminder-code-input"
                @input="patch({ code: ($event.target as HTMLInputElement).value })"
              />
              <span class="block text-xs text-brand-text-muted">
                {{ editing ? t('seasonalTemplates.reminders.form.codeImmutable') : t('seasonalTemplates.reminders.form.codeHint') }}
              </span>
              <span v-if="fieldMessage('code')" class="text-xs text-red-600">{{ fieldMessage('code') }}</span>
            </label>

            <label class="block space-y-1.5 text-start">
              <span class="text-sm font-semibold text-brand-text">{{ t('seasonalTemplates.reminders.form.title') }}</span>
              <input
                :value="form.title"
                type="text"
                maxlength="255"
                class="h-11 w-full rounded-xl border border-brand-border px-3 text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg"
                :disabled="submitting"
                data-testid="reminder-title-input"
                @input="patch({ title: ($event.target as HTMLInputElement).value })"
              />
              <span v-if="fieldMessage('title')" class="text-xs text-red-600">{{ fieldMessage('title') }}</span>
            </label>

            <label class="block space-y-1.5 text-start">
              <span class="text-sm font-semibold text-brand-text">{{ t('seasonalTemplates.reminders.form.body') }}</span>
              <textarea
                :value="form.body"
                rows="3"
                maxlength="1000"
                class="w-full rounded-xl border border-brand-border px-3 py-2 text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg"
                :disabled="submitting"
                data-testid="reminder-body-input"
                @input="patch({ body: ($event.target as HTMLTextAreaElement).value })"
              />
              <span v-if="fieldMessage('body')" class="text-xs text-red-600">{{ fieldMessage('body') }}</span>
            </label>

            <div class="space-y-1.5 text-start">
              <span class="text-sm font-semibold text-brand-text">{{ t('seasonalTemplates.reminders.form.severity') }}</span>
              <AppSelect :model-value="form.severity" :options="severityOptions" :disabled="submitting" @update:model-value="onSeverity" />
              <span v-if="fieldMessage('severity')" class="text-xs text-red-600">{{ fieldMessage('severity') }}</span>
            </div>

            <fieldset class="space-y-2 text-start">
              <legend class="text-sm font-semibold text-brand-text">{{ t('seasonalTemplates.reminders.form.triggerMode') }}</legend>
              <label
                v-for="mode in REMINDER_TRIGGER_MODES"
                :key="mode"
                class="flex items-start gap-2 rounded-xl border border-brand-border px-3 py-2 text-sm"
                :class="form.trigger_mode === mode ? 'border-brand-primary bg-brand-bg/60' : ''"
              >
                <input
                  type="radio"
                  name="reminder-trigger-mode"
                  class="mt-1"
                  :value="mode"
                  :checked="form.trigger_mode === mode"
                  :disabled="submitting"
                  :data-testid="`reminder-mode-${mode}`"
                  @change="onTriggerMode(mode)"
                />
                <span>
                  <span class="block font-semibold text-brand-text">{{ t(`seasonalTemplates.reminders.triggerMode.${mode}`) }}</span>
                  <span class="block text-xs text-brand-text-muted">{{ t(`seasonalTemplates.reminders.triggerModeHint.${mode}`) }}</span>
                </span>
              </label>
              <span v-if="fieldMessage('trigger_mode')" class="text-xs text-red-600">{{ fieldMessage('trigger_mode') }}</span>
            </fieldset>

            <label v-if="form.trigger_mode === 'absolute_date'" class="block space-y-1.5 text-start">
              <span class="text-sm font-semibold text-brand-text">{{ t('seasonalTemplates.reminders.form.triggerOn') }}</span>
              <input
                :value="form.trigger_on"
                type="date"
                dir="ltr"
                class="h-11 w-full rounded-xl border border-brand-border px-3 text-start text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg"
                :disabled="submitting"
                data-testid="reminder-trigger-on-input"
                @input="patch({ trigger_on: ($event.target as HTMLInputElement).value })"
              />
              <span v-if="fieldMessage('trigger_on')" class="text-xs text-red-600">{{ fieldMessage('trigger_on') }}</span>
            </label>

            <label v-else class="block space-y-1.5 text-start">
              <span class="text-sm font-semibold text-brand-text">{{ t('seasonalTemplates.reminders.form.offsetDays') }}</span>
              <input
                :value="form.offset_days"
                type="number"
                step="1"
                :min="-MAX_OFFSET_DAYS"
                :max="MAX_OFFSET_DAYS"
                dir="ltr"
                class="h-11 w-full rounded-xl border border-brand-border px-3 text-start text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg"
                :disabled="submitting"
                data-testid="reminder-offset-input"
                @input="patch({ offset_days: ($event.target as HTMLInputElement).value })"
              />
              <span class="block text-xs text-brand-text-muted">{{ t('seasonalTemplates.reminders.form.offsetHint') }}</span>
              <span v-if="fieldMessage('offset_days')" class="text-xs text-red-600">{{ fieldMessage('offset_days') }}</span>
            </label>

            <p
              v-if="fireDate"
              class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-start text-xs text-emerald-900"
              data-testid="reminder-fire-date-preview"
            >
              {{ t('seasonalTemplates.reminders.form.fireDatePreview', { date: fireDateLabel }) }}
            </p>
            <p
              v-else-if="form.trigger_mode === 'offset_from_season_start' && !seasonStartsOn"
              class="rounded-xl border border-brand-border bg-brand-bg px-3 py-2 text-start text-xs text-brand-text-muted"
            >
              {{ t('seasonalTemplates.reminders.form.fireDateUnknown') }}
            </p>

            <div class="space-y-1.5 text-start">
              <span class="text-sm font-semibold text-brand-text">{{ t('seasonalTemplates.reminders.form.recipientPermission') }}</span>
              <AppSelect
                :model-value="form.recipient_permission"
                :options="permissionOptions"
                searchable
                :disabled="submitting"
                @update:model-value="onPermission"
              />
              <span class="block text-xs text-brand-text-muted">{{ t('seasonalTemplates.reminders.form.recipientHint') }}</span>
              <span v-if="fieldMessage('recipient_permission')" class="text-xs text-red-600">{{ fieldMessage('recipient_permission') }}</span>
            </div>
          </div>

          <footer class="flex shrink-0 items-center justify-end gap-3 border-t border-brand-border px-4 py-4 sm:px-7">
            <button
              type="button"
              class="inline-flex h-11 items-center justify-center rounded-xl border border-brand-border px-4 text-sm font-semibold"
              :disabled="submitting"
              @click="emit('close')"
            >
              {{ t('seasonalTemplates.common.cancel') }}
            </button>
            <button
              type="submit"
              class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white disabled:opacity-60"
              :disabled="submitting"
              data-testid="reminder-submit-btn"
            >
              <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
              <span>{{ editing ? t('seasonalTemplates.reminders.form.saveCta') : t('seasonalTemplates.reminders.form.createCta') }}</span>
            </button>
          </footer>
        </form>
      </aside>
    </div>
  </Teleport>
</template>
