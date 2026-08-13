<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { LoaderCircle, RefreshCw, Save } from 'lucide-vue-next'

import { ApiError } from '@/shared/api/http'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
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
  listTimezones().map((tz) => ({ value: tz, label: tz })),
)

async function onSave(): Promise<void> {
  if (!canUpdate.value || !dirty.value || saving.value) {
    return
  }

  const localErrors = validateSettingsForm(form.value)
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
  <div class="mx-auto max-w-3xl space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ t('settings.title') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ t('settings.subtitle') }}</p>
      </div>
      <div class="flex items-center gap-2">
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 disabled:opacity-50"
          :disabled="isFetching"
          @click="refetch()"
        >
          <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': isFetching }" />
          {{ t('settings.refresh') }}
        </button>
        <button
          v-if="canUpdate"
          type="button"
          class="inline-flex items-center gap-2 rounded-lg bg-primary-700 px-4 py-2 text-sm font-medium text-white hover:bg-primary-800 disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="!dirty || saving"
          @click="onSave"
        >
          <LoaderCircle v-if="saving" class="h-4 w-4 animate-spin" />
          <Save v-else class="h-4 w-4" />
          {{ t('settings.save') }}
        </button>
      </div>
    </header>

    <div
      v-if="isLoading"
      class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
      role="status"
    >
      <div class="h-5 w-40 animate-pulse rounded bg-slate-200" />
      <div class="h-10 w-full animate-pulse rounded bg-slate-100" />
      <div class="h-10 w-full animate-pulse rounded bg-slate-100" />
    </div>

    <div
      v-else-if="isError"
      class="rounded-xl border border-rose-200 bg-rose-50 p-6 text-center"
    >
      <p class="text-sm text-rose-700">{{ t('settings.errors.load') }}</p>
      <button
        type="button"
        class="mt-3 rounded-lg border border-rose-300 bg-white px-3 py-1.5 text-sm text-rose-700"
        @click="refetch()"
      >
        {{ t('settings.retry') }}
      </button>
    </div>

    <template v-else>
      <p
        v-if="!canUpdate"
        class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800"
      >
        {{ t('settings.viewOnlyNotice') }}
      </p>

      <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h2 class="text-lg font-semibold text-slate-900">{{ t('settings.sections.general') }}</h2>

        <div class="grid gap-4">
          <label class="block space-y-1.5">
            <span class="text-sm font-medium text-slate-700">{{ t('settings.fields.name') }}</span>
            <input
              v-model="form.name"
              type="text"
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm disabled:bg-slate-50"
              :disabled="!canUpdate || saving"
              autocomplete="organization"
            />
            <p v-if="fieldErrors.name" class="text-xs text-rose-600">{{ fieldErrors.name }}</p>
          </label>

          <label class="block space-y-1.5">
            <span class="text-sm font-medium text-slate-700">{{ t('settings.fields.contactName') }}</span>
            <input
              v-model="form.contact_name"
              type="text"
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm disabled:bg-slate-50"
              :disabled="!canUpdate || saving"
            />
          </label>

          <label class="block space-y-1.5">
            <span class="text-sm font-medium text-slate-700">{{ t('settings.fields.contactEmail') }}</span>
            <input
              v-model="form.contact_email"
              type="email"
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm disabled:bg-slate-50"
              :disabled="!canUpdate || saving"
              autocomplete="email"
            />
            <p v-if="fieldErrors.contact_email" class="text-xs text-rose-600">
              {{ fieldErrors.contact_email }}
            </p>
          </label>

          <label class="block space-y-1.5">
            <span class="text-sm font-medium text-slate-700">{{ t('settings.fields.contactPhone') }}</span>
            <input
              v-model="form.contact_phone"
              type="tel"
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm disabled:bg-slate-50"
              :disabled="!canUpdate || saving"
              autocomplete="tel"
            />
            <p v-if="fieldErrors.contact_phone" class="text-xs text-rose-600">
              {{ fieldErrors.contact_phone }}
            </p>
          </label>
        </div>
      </section>

      <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h2 class="text-lg font-semibold text-slate-900">{{ t('settings.sections.regional') }}</h2>
        <p class="text-sm text-slate-600">{{ t('settings.timezoneHelp') }}</p>

        <div class="grid gap-4">
          <div class="space-y-1.5">
            <span class="text-sm font-medium text-slate-700">{{ t('settings.fields.timezone') }}</span>
            <AppSelect
              v-model="form.timezone"
              :options="timezoneOptions"
              :disabled="!canUpdate || saving"
              searchable
              :search-placeholder="t('settings.timezoneSearch')"
              :placeholder="t('settings.fields.timezone')"
            />
            <p v-if="fieldErrors.timezone" class="text-xs text-rose-600">{{ fieldErrors.timezone }}</p>
          </div>

          <div class="space-y-1.5">
            <span class="text-sm font-medium text-slate-700">{{ t('settings.fields.locale') }}</span>
            <input
              type="text"
              class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
              :value="t('settings.localeArabic')"
              disabled
              readonly
            />
            <p class="text-xs text-slate-500">{{ t('settings.localeReadonlyHint') }}</p>
          </div>
        </div>
      </section>
    </template>
  </div>
</template>
