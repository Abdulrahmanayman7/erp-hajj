<script setup lang="ts">
import { computed, ref, type ComponentPublicInstance } from 'vue'
import { useI18n } from 'vue-i18n'
import { Loader2 } from 'lucide-vue-next'

import AppFormDrawer from '@/shared/components/AppFormDrawer.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { useCaptureOnOpen, useFormDirty } from '@/shared/composables/useFormDirty'

import type { GpsConnectionMethod, GpsProvider } from '../types/gpsProviders'

export interface GpsProviderFormState {
  name: string
  code: string
  connection_method: GpsConnectionMethod
  is_enabled: boolean
  poll_interval_seconds: string
  base_url: string
  endpoint_url: string
  webhook_secret: string
  api_token: string
  email: string
  password: string
}

const props = defineProps<{
  open: boolean
  editing: GpsProvider | null
  form: GpsProviderFormState
  submitting: boolean
  formError: string | null
}>()

const emit = defineEmits<{
  close: []
  submit: []
  'update:form': [value: GpsProviderFormState]
}>()

const { t } = useI18n()
const step = ref(1)
const drawerRef = ref<ComponentPublicInstance<{ requestClose: () => Promise<void> }> | null>(null)

const isEdit = computed(() => props.editing != null)
const title = computed(() => (isEdit.value ? t('gpsProviders.edit') : t('gpsProviders.add')))

const { dirty, capture, reset: resetDirty } = useFormDirty(() => props.form)

useCaptureOnOpen(
  () => props.open,
  () => {
    step.value = 1
    capture()
  },
  {
    onClose: () => {
      step.value = 1
      resetDirty()
    },
  },
)

const methodOptions = computed<AppSelectOption[]>(() => [
  { value: 'webhook', label: t('gpsProviders.methods.webhook') },
  { value: 'rest_pull', label: t('gpsProviders.methods.rest_pull') },
  { value: 'traccar', label: t('gpsProviders.methods.traccar') },
  {
    value: 'mobile_driver',
    label: t('gpsProviders.methods.mobile_driver'),
    disabled: true,
  },
  { value: 'simulator', label: t('gpsProviders.methods.simulator') },
])

const showRestFields = computed(() => props.form.connection_method === 'rest_pull')
const showTraccarFields = computed(() => props.form.connection_method === 'traccar')
const showWebhookFields = computed(
  () =>
    props.form.connection_method === 'webhook' ||
    props.form.connection_method === 'rest_pull' ||
    props.form.connection_method === 'traccar',
)
const showSimulatorNote = computed(() => props.form.connection_method === 'simulator')

const step1Valid = computed(
  () =>
    props.form.name.trim().length > 0 &&
    (isEdit.value || props.form.code.trim().length > 0) &&
    !!props.form.connection_method,
)

function patch(partial: Partial<GpsProviderFormState>): void {
  emit('update:form', { ...props.form, ...partial })
}

function onOpenUpdate(value: boolean): void {
  if (!value) emit('close')
}

function onPrimaryFooterAction(): void {
  if (step.value === 1) {
    void drawerRef.value?.requestClose()
    return
  }
  step.value -= 1
}
</script>

<template>
  <AppFormDrawer
    ref="drawerRef"
    :open="open"
    :title="title"
    size="md"
    :dirty="dirty"
    :submitting="submitting"
    hide-default-footer
    @update:open="onOpenUpdate"
    @close="emit('close')"
  >
    <template #header>
      <h3 class="text-lg font-bold text-brand-text">{{ title }}</h3>
      <p class="text-xs text-brand-text-muted">{{ t('gpsProviders.drawerSubtitle') }}</p>
      <ol class="mt-3 flex flex-wrap gap-2 text-[11px] font-semibold">
        <li
          class="rounded-full px-2.5 py-1"
          :class="step === 1 ? 'bg-brand-primary text-white' : 'bg-[var(--surface-subtle)] text-brand-text-muted'"
        >
          1 · {{ t('gpsProviders.stepInfo') }}
        </li>
        <li
          class="rounded-full px-2.5 py-1"
          :class="step === 2 ? 'bg-brand-primary text-white' : 'bg-[var(--surface-subtle)] text-brand-text-muted'"
        >
          2 · {{ t('gpsProviders.stepConnection') }}
        </li>
        <li
          class="rounded-full px-2.5 py-1"
          :class="step === 3 ? 'bg-brand-primary text-white' : 'bg-[var(--surface-subtle)] text-brand-text-muted'"
        >
          3 · {{ t('gpsProviders.stepReview') }}
        </li>
      </ol>
    </template>

    <div data-test="gps-provider-form" class="contents">
      <p v-if="formError" class="mb-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
        {{ formError }}
      </p>

      <div v-show="step === 1" class="space-y-4">
        <label class="app-field">
          <span class="app-field__label">{{ t('gpsProviders.name') }}</span>
          <input
            :value="form.name"
            required
            class="app-field__control app-field__control--input"
            data-test="gps-provider-name"
            @input="patch({ name: ($event.target as HTMLInputElement).value })"
          />
        </label>
        <label v-if="!isEdit" class="app-field">
          <span class="app-field__label">{{ t('gpsProviders.code') }}</span>
          <input
            :value="form.code"
            required
            class="app-field__control app-field__control--input"
            data-test="gps-provider-code"
            @input="patch({ code: ($event.target as HTMLInputElement).value })"
          />
          <span class="mt-1 block text-xs text-brand-text-muted">{{ t('gpsProviders.codeHint') }}</span>
        </label>
        <div class="app-field">
          <p class="app-field__label">{{ t('gpsProviders.method') }}</p>
          <AppSelect
            :model-value="form.connection_method"
            :options="methodOptions"
            @update:model-value="patch({ connection_method: $event as GpsConnectionMethod })"
          />
        </div>
        <label class="flex items-center gap-2 text-sm">
          <input
            type="checkbox"
            :checked="form.is_enabled"
            @change="patch({ is_enabled: ($event.target as HTMLInputElement).checked })"
          />
          {{ t('gpsProviders.enabled') }}
        </label>
        <p class="text-xs text-brand-text-muted">{{ t('gpsProviders.enabledHint') }}</p>
      </div>

      <div v-show="step === 2" class="space-y-4">
        <p
          v-if="showSimulatorNote"
          class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900"
        >
          {{ t('gpsProviders.simulatorHint') }}
        </p>

        <label v-if="showTraccarFields" class="app-field">
          <span class="app-field__label">{{ t('gpsProviders.baseUrl') }}</span>
          <input
            :value="form.base_url"
            class="app-field__control app-field__control--input"
            dir="ltr"
            @input="patch({ base_url: ($event.target as HTMLInputElement).value })"
          />
        </label>

        <label v-if="showRestFields" class="app-field">
          <span class="app-field__label">{{ t('gpsProviders.endpointUrl') }}</span>
          <input
            :value="form.endpoint_url"
            class="app-field__control app-field__control--input"
            dir="ltr"
            @input="patch({ endpoint_url: ($event.target as HTMLInputElement).value })"
          />
        </label>

        <template v-if="showWebhookFields">
          <label class="app-field">
            <span class="app-field__label">{{ t('gpsProviders.webhookSecret') }}</span>
            <input
              :value="form.webhook_secret"
              type="password"
              autocomplete="new-password"
              class="app-field__control app-field__control--input"
              @input="patch({ webhook_secret: ($event.target as HTMLInputElement).value })"
            />
          </label>
          <label class="app-field">
            <span class="app-field__label">{{ t('gpsProviders.apiToken') }}</span>
            <input
              :value="form.api_token"
              type="password"
              autocomplete="new-password"
              class="app-field__control app-field__control--input"
              @input="patch({ api_token: ($event.target as HTMLInputElement).value })"
            />
          </label>
        </template>

        <template v-if="showTraccarFields">
          <label class="app-field">
            <span class="app-field__label">{{ t('gpsProviders.email') }}</span>
            <input
              :value="form.email"
              class="app-field__control app-field__control--input"
              @input="patch({ email: ($event.target as HTMLInputElement).value })"
            />
          </label>
          <label class="app-field">
            <span class="app-field__label">{{ t('gpsProviders.password') }}</span>
            <input
              :value="form.password"
              type="password"
              autocomplete="new-password"
              class="app-field__control app-field__control--input"
              @input="patch({ password: ($event.target as HTMLInputElement).value })"
            />
          </label>
        </template>

        <label
          v-if="form.connection_method === 'rest_pull' || form.connection_method === 'traccar' || form.connection_method === 'simulator'"
          class="app-field"
        >
          <span class="app-field__label">{{ t('gpsProviders.pollSeconds') }}</span>
          <input
            :value="form.poll_interval_seconds"
            type="number"
            min="30"
            class="app-field__control app-field__control--input"
            @input="patch({ poll_interval_seconds: ($event.target as HTMLInputElement).value })"
          />
        </label>

        <p class="text-xs text-brand-text-muted">{{ t('gpsProviders.secretHint') }}</p>
      </div>

      <div v-show="step === 3" class="space-y-3 rounded-xl border border-brand-border bg-[var(--surface-subtle)] p-4 text-sm">
        <div class="flex justify-between gap-3">
          <span class="text-brand-text-muted">{{ t('gpsProviders.name') }}</span>
          <span class="font-semibold">{{ form.name || '—' }}</span>
        </div>
        <div v-if="!isEdit" class="flex justify-between gap-3">
          <span class="text-brand-text-muted">{{ t('gpsProviders.code') }}</span>
          <span class="font-semibold" dir="ltr">{{ form.code || '—' }}</span>
        </div>
        <div class="flex justify-between gap-3">
          <span class="text-brand-text-muted">{{ t('gpsProviders.method') }}</span>
          <span class="font-semibold">{{ t(`gpsProviders.methods.${form.connection_method}`) }}</span>
        </div>
        <div class="flex justify-between gap-3">
          <span class="text-brand-text-muted">{{ t('gpsProviders.enabled') }}</span>
          <span class="font-semibold">{{ form.is_enabled ? t('gpsProviders.yes') : t('gpsProviders.no') }}</span>
        </div>
        <p class="border-t border-brand-border pt-3 text-xs text-brand-text-muted">
          {{ t('gpsProviders.reviewGateNote') }}
        </p>
      </div>
    </div>

    <template #footer>
      <button
        type="button"
        class="inline-flex h-11 items-center justify-center rounded-xl border border-brand-border px-4 text-sm font-semibold disabled:opacity-50 sm:min-w-[7.5rem]"
        :disabled="submitting"
        @click="onPrimaryFooterAction"
      >
        {{ step === 1 ? t('gpsProviders.cancel') : t('gpsProviders.back') }}
      </button>
      <button
        v-if="step < 3"
        type="button"
        class="inline-flex h-11 items-center justify-center rounded-xl bg-brand-primary px-4 text-sm font-semibold text-white disabled:opacity-50 sm:min-w-[7.5rem]"
        :disabled="step === 1 && !step1Valid"
        data-test="gps-provider-next"
        @click="step += 1"
      >
        {{ t('gpsProviders.next') }}
      </button>
      <button
        v-else
        type="button"
        class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-brand-primary px-4 text-sm font-semibold text-white disabled:opacity-50 sm:min-w-[7.5rem]"
        :disabled="submitting || !step1Valid"
        data-test="gps-provider-save"
        @click="emit('submit')"
      >
        <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
        {{ t('gpsProviders.save') }}
      </button>
    </template>
  </AppFormDrawer>
</template>
