<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { AlertTriangle, Globe2, LoaderCircle, Save } from 'lucide-vue-next'
import { onBeforeRouteLeave } from 'vue-router'

import { ApiError } from '@/shared/api/http'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'

import { useUpdateTenantSettingsMutation } from '../mutations/useUpdateTenantSettingsMutation'
import { useTenantSettingsQuery } from '../queries/useTenantSettingsQuery'
import {
  buildSettingsPatch,
  isSettingsDirty,
  listTimezones,
  settingsToForm,
  validateSettingsForm,
  type SettingsFormState,
} from '../validation/settingsValidation'

const { t } = useI18n()
const toast = useToast()
const { confirm } = useConfirm()
const { can } = usePermissions()

const canView = computed(() => can('tenant_settings.view'))
const canUpdate = computed(() => can('tenant_settings.update'))

const { data, isLoading, isError, refetch, isFetching } = useTenantSettingsQuery(canView)
const updateMutation = useUpdateTenantSettingsMutation()

const form = ref<SettingsFormState>({
  name: '',
  contact_name: '',
  contact_email: '',
  contact_phone: '',
  timezone: 'Asia/Riyadh',
})
const baseline = ref<SettingsFormState>({ ...form.value })
const fieldErrors = ref<Record<string, string>>({})

watch(
  data,
  (value) => {
    if (!value) {
      return
    }
    const next = settingsToForm(value)
    form.value = { ...next }
    baseline.value = { ...next }
    fieldErrors.value = {}
  },
  { immediate: true },
)

const dirty = computed(() => isSettingsDirty(form.value, baseline.value))
const saving = computed(() => updateMutation.isPending.value)
const timezoneOptions = computed<AppSelectOption[]>(() =>
  listTimezones(form.value.timezone).map((tz) => ({
    value: tz,
    ...timezonePresentation(tz),
  })),
)
const timezoneChanged = computed(() => form.value.timezone !== baseline.value.timezone)

function timezonePresentation(timezone: string): Pick<AppSelectOption, 'label' | 'hint'> {
  const labels: Record<string, string> = {
    'Asia/Riyadh': 'الرياض (GMT+3)',
    'Asia/Dubai': 'دبي (GMT+4)',
    'Asia/Kuwait': 'الكويت (GMT+3)',
    'Asia/Bahrain': 'المنامة (GMT+3)',
    'Asia/Qatar': 'الدوحة (GMT+3)',
    'Africa/Cairo': 'القاهرة (GMT+2)',
    UTC: 'التوقيت العالمي (GMT)',
  }

  return {
    label: labels[timezone] ?? timezone.replace(/_/g, ' '),
    hint: timezone,
  }
}

function clearFieldError(field: string): void {
  if (fieldErrors.value[field]) {
    const next = { ...fieldErrors.value }
    delete next[field]
    fieldErrors.value = next
  }
}

async function confirmLeave(): Promise<boolean> {
  return confirm({
    title: t('settings.leaveConfirm.title'),
    message: t('settings.leaveConfirm.message'),
    cancelLabel: t('settings.leaveConfirm.stay'),
    confirmLabel: t('settings.leaveConfirm.discard'),
    variant: 'warning',
  })
}

onBeforeRouteLeave(async () => {
  if (!dirty.value || saving.value) {
    return true
  }

  return await confirmLeave()
})

function onBeforeUnload(event: BeforeUnloadEvent): void {
  if (!dirty.value || saving.value) {
    return
  }

  event.preventDefault()
  event.returnValue = ''
}

onMounted(() => window.addEventListener('beforeunload', onBeforeUnload))
onBeforeUnmount(() => window.removeEventListener('beforeunload', onBeforeUnload))

async function onSave(): Promise<void> {
  if (!canUpdate.value || !dirty.value || saving.value) {
    return
  }

  const localErrors = validateSettingsForm(form.value, {
    allowTimezone: baseline.value.timezone,
  })
  fieldErrors.value = localErrors
  if (Object.keys(localErrors).length > 0) {
    return
  }

  const payload = buildSettingsPatch(form.value, baseline.value)
  if (!payload) {
    return
  }

  try {
    const updated = await updateMutation.mutateAsync(payload)
    const next = settingsToForm(updated)
    form.value = { ...next }
    baseline.value = { ...next }
    fieldErrors.value = {}
    toast.success(t('settings.successSave'))
  } catch (error) {
    if (error instanceof ApiError) {
      if (error.status === 422 && error.errors) {
        const mapped: Record<string, string> = {}
        Object.entries(error.errors).forEach(([key, messages]) => {
          const short = key.replace(/^general\./, '').replace(/^regional\./, '')
          mapped[short] = Array.isArray(messages) ? String(messages[0]) : String(messages)
        })
        fieldErrors.value = mapped
      }
      toast.error(error.message || t('settings.errors.generic'))
      return
    }
    toast.error(t('settings.errors.generic'))
  }
}
</script>

<template>
  <div class="mx-auto max-w-6xl min-w-0 space-y-6">
    <AppPageHeader
      :title="t('settings.title')"
      :subtitle="t('settings.subtitle')"
    />

    <template v-if="isLoading">
      <section
        v-for="section in 2"
        :key="section"
        class="rounded-2xl border border-brand-border bg-brand-surface p-5 shadow-sm sm:p-7"
        role="status"
      >
        <span class="sr-only">{{ t('settings.loading') }}</span>
        <div class="h-6 w-40 animate-pulse rounded bg-brand-bg" />
        <div class="mt-2 h-4 w-72 max-w-full animate-pulse rounded bg-brand-bg" />
        <div class="mt-7 grid grid-cols-1 gap-5">
          <div v-for="field in 4" :key="field" class="space-y-2">
            <div class="h-4 w-24 animate-pulse rounded bg-brand-bg" />
            <div class="h-11 animate-pulse rounded-xl bg-brand-bg" />
          </div>
        </div>
      </section>
    </template>

    <div
      v-else-if="isError"
      class="rounded-2xl border border-rose-200 bg-rose-50 p-8 text-center"
      role="alert"
    >
      <p class="text-base font-bold text-rose-800">{{ t('settings.errors.load') }}</p>
      <p class="mt-1 text-sm text-rose-700">{{ t('settings.errors.loadHint') }}</p>
      <button
        type="button"
        class="mt-4 inline-flex h-11 w-full items-center justify-center rounded-xl border border-rose-300 bg-white px-4 text-sm font-bold text-rose-800 transition hover:bg-rose-100 sm:h-10 sm:w-auto"
        :disabled="isFetching"
        @click="refetch()"
      >
        {{ t('settings.retry') }}
      </button>
    </div>

    <template v-else>
      <div
        v-if="!canUpdate"
        class="flex gap-3 rounded-2xl border border-brand-gold/35 bg-brand-gold-soft px-4 py-4 text-brand-text"
        role="status"
      >
        <Globe2 class="mt-0.5 h-5 w-5 shrink-0 text-brand-warning" aria-hidden="true" />
        <div>
          <p class="font-bold">{{ t('settings.viewOnly.title') }}</p>
          <p class="mt-0.5 text-sm text-brand-text-secondary">{{ t('settings.viewOnly.message') }}</p>
        </div>
      </div>

      <div
        v-if="canUpdate && dirty"
        class="flex items-center gap-2 rounded-xl border border-brand-gold/30 bg-brand-gold-soft px-4 py-3 text-sm font-semibold text-brand-text"
        role="status"
      >
        <AlertTriangle class="h-4 w-4 shrink-0 text-brand-warning" aria-hidden="true" />
        {{ t('settings.unsavedChanges') }}
      </div>

      <section class="rounded-2xl border border-brand-border bg-brand-surface p-5 shadow-sm sm:p-7">
        <div class="mb-7">
          <h2 class="text-lg font-bold text-brand-text">{{ t('settings.sections.general') }}</h2>
          <p class="mt-1 text-sm text-brand-text-secondary">{{ t('settings.sections.generalDescription') }}</p>
        </div>

        <dl v-if="!canUpdate" class="grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2">
          <div
            v-for="field in [
              ['name', t('settings.fields.name')],
              ['contact_name', t('settings.fields.contactName')],
              ['contact_email', t('settings.fields.contactEmail')],
              ['contact_phone', t('settings.fields.contactPhone')],
            ]"
            :key="field[0]"
          >
            <dt class="text-sm font-semibold text-brand-text-secondary">{{ field[1] }}</dt>
            <dd class="mt-1 break-words text-sm font-bold text-brand-text">
              {{ form[field[0] as keyof SettingsFormState] || t('settings.notProvided') }}
            </dd>
          </div>
        </dl>
        <div v-else class="grid grid-cols-1 gap-5 sm:grid-cols-2">
          <label class="block space-y-2 sm:col-span-2">
            <span class="text-sm font-bold text-brand-text">
              {{ t('settings.fields.name') }} <span class="text-rose-700">*</span>
            </span>
            <input
              v-model="form.name"
              type="text"
              class="h-11 w-full rounded-xl border bg-brand-surface px-3 text-sm text-brand-text outline-none transition placeholder:text-brand-text-muted focus:border-brand-primary/50 focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg"
              :class="fieldErrors.name ? 'border-rose-500' : 'border-brand-border'"
              :disabled="saving"
              :aria-invalid="Boolean(fieldErrors.name)"
              :aria-describedby="fieldErrors.name ? 'settings-name-error' : undefined"
              autocomplete="organization"
              @input="clearFieldError('name')"
            />
            <p
              v-if="fieldErrors.name"
              id="settings-name-error"
              class="text-xs font-medium text-rose-700"
              role="alert"
            >
              {{ fieldErrors.name }}
            </p>
          </label>

          <label class="block space-y-1.5">
            <span class="text-sm font-bold text-brand-text">{{ t('settings.fields.contactName') }}</span>
            <input
              v-model="form.contact_name"
              type="text"
              class="h-11 w-full rounded-xl border bg-brand-surface px-3 text-sm text-brand-text outline-none transition focus:border-brand-primary/50 focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg"
              :class="fieldErrors.contact_name ? 'border-rose-500' : 'border-brand-border'"
              :disabled="saving"
              :aria-invalid="Boolean(fieldErrors.contact_name)"
              :aria-describedby="fieldErrors.contact_name ? 'settings-contact-name-error' : undefined"
              @input="clearFieldError('contact_name')"
            />
            <p
              v-if="fieldErrors.contact_name"
              id="settings-contact-name-error"
              class="text-xs font-medium text-rose-700"
              role="alert"
            >
              {{ fieldErrors.contact_name }}
            </p>
          </label>

          <label class="block space-y-1.5">
            <span class="text-sm font-bold text-brand-text">{{ t('settings.fields.contactEmail') }}</span>
            <input
              v-model="form.contact_email"
              type="email"
              class="h-11 w-full rounded-xl border bg-brand-surface px-3 text-sm text-brand-text outline-none transition focus:border-brand-primary/50 focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg"
              :class="fieldErrors.contact_email ? 'border-rose-500' : 'border-brand-border'"
              :disabled="saving"
              :aria-invalid="Boolean(fieldErrors.contact_email)"
              :aria-describedby="fieldErrors.contact_email ? 'settings-contact-email-error' : undefined"
              autocomplete="email"
              @input="clearFieldError('contact_email')"
            />
            <p
              v-if="fieldErrors.contact_email"
              id="settings-contact-email-error"
              class="text-xs font-medium text-rose-700"
              role="alert"
            >
              {{ fieldErrors.contact_email }}
            </p>
          </label>

          <label class="block space-y-1.5">
            <span class="text-sm font-bold text-brand-text">{{ t('settings.fields.contactPhone') }}</span>
            <input
              v-model="form.contact_phone"
              type="tel"
              class="h-11 w-full rounded-xl border bg-brand-surface px-3 text-sm text-brand-text outline-none transition focus:border-brand-primary/50 focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg"
              :class="fieldErrors.contact_phone ? 'border-rose-500' : 'border-brand-border'"
              :disabled="saving"
              :aria-invalid="Boolean(fieldErrors.contact_phone)"
              :aria-describedby="fieldErrors.contact_phone ? 'settings-contact-phone-error' : undefined"
              autocomplete="tel"
              @input="clearFieldError('contact_phone')"
            />
            <p
              v-if="fieldErrors.contact_phone"
              id="settings-contact-phone-error"
              class="text-xs font-medium text-rose-700"
              role="alert"
            >
              {{ fieldErrors.contact_phone }}
            </p>
          </label>
        </div>
      </section>

      <section class="rounded-2xl border border-brand-border bg-brand-surface p-5 shadow-sm sm:p-7">
        <div class="mb-7">
          <h2 class="text-lg font-bold text-brand-text">{{ t('settings.sections.regional') }}</h2>
          <p class="mt-1 text-sm text-brand-text-secondary">{{ t('settings.sections.regionalDescription') }}</p>
        </div>

        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
          <div class="space-y-1.5">
            <span class="text-sm font-bold text-brand-text">
              {{ t('settings.fields.timezone') }}
              <span v-if="canUpdate" class="text-rose-700">*</span>
            </span>
            <AppSelect
              v-if="canUpdate"
              v-model="form.timezone"
              :options="timezoneOptions"
              :disabled="saving"
              searchable
              :search-placeholder="t('settings.timezoneSearch')"
              :placeholder="t('settings.fields.timezone')"
              @update:model-value="clearFieldError('timezone')"
            />
            <div v-else class="rounded-xl border border-brand-border bg-brand-bg px-3 py-2.5">
              <p class="text-sm font-bold text-brand-text">{{ timezonePresentation(form.timezone).label }}</p>
              <p class="mt-0.5 text-xs font-medium text-brand-text-muted" dir="ltr">{{ form.timezone }}</p>
            </div>
            <p v-if="canUpdate" class="text-xs text-brand-text-muted">{{ t('settings.timezoneHint') }}</p>
            <p v-if="fieldErrors.timezone" class="text-xs font-medium text-rose-700" role="alert">
              {{ fieldErrors.timezone }}
            </p>
            <div
              v-if="canUpdate && timezoneChanged"
              class="mt-3 flex gap-2 rounded-xl border border-brand-gold/30 bg-brand-gold-soft p-3 text-xs leading-5 text-brand-text"
            >
              <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0 text-brand-warning" aria-hidden="true" />
              {{ t('settings.timezoneWarning') }}
            </div>
          </div>

          <div class="space-y-1.5">
            <span class="text-sm font-bold text-brand-text">{{ t('settings.fields.locale') }}</span>
            <div class="flex items-center justify-between gap-3 rounded-xl border border-brand-border bg-brand-bg px-3 py-2.5">
              <span class="min-w-0 break-words text-sm font-bold text-brand-text">{{ t('settings.localeArabic') }}</span>
              <span
                class="shrink-0 rounded-full bg-brand-primary-soft px-2.5 py-1 text-xs font-bold text-brand-primary-dark"
              >
                {{ t('settings.default') }}
              </span>
            </div>
            <p class="text-xs text-brand-text-muted">{{ t('settings.localeReadonlyHint') }}</p>
          </div>
        </div>
      </section>

      <div
        v-if="canUpdate"
        class="app-sticky-form-actions"
      >
        <div class="app-sticky-form-actions__inner">
          <button
            type="button"
            class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-brand-primary-dark px-5 text-sm font-bold text-white transition hover:bg-brand-primary disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"
            :disabled="!dirty || saving"
            :aria-busy="saving"
            @click="onSave"
          >
            <LoaderCircle v-if="saving" class="h-4 w-4 animate-spin" aria-hidden="true" />
            <Save v-else class="h-4 w-4" aria-hidden="true" />
            {{ saving ? t('settings.saving') : t('settings.saveChanges') }}
          </button>
        </div>
      </div>
    </template>
  </div>
</template>
