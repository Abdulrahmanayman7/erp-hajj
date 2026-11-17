<script setup lang="ts">
import { computed, nextTick, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Loader2, X } from 'lucide-vue-next'

import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
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
const nameInputRef = ref<HTMLInputElement | null>(null)
let previouslyFocused: HTMLElement | null = null

const isEdit = computed(() => props.editing != null)
const title = computed(() => (isEdit.value ? t('gpsProviders.edit') : t('gpsProviders.add')))

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
      step.value = 1
      previouslyFocused =
        document.activeElement instanceof HTMLElement ? document.activeElement : null
      document.addEventListener('keydown', onKeydown)
      await nextTick()
      nameInputRef.value?.focus()
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
    <Transition name="gps-drawer">
      <div v-if="open" class="gps-drawer-root fixed inset-0 z-50" role="presentation" data-test="gps-provider-form">
        <div
          class="absolute inset-0 bg-[rgba(15,23,20,0.32)]"
          aria-hidden="true"
          @click="!submitting && emit('close')"
        />

        <aside
          class="gps-drawer-panel absolute inset-y-0 start-0 flex h-dvh w-full max-w-[520px] flex-col bg-brand-surface shadow-[-12px_0_40px_-24px_rgba(23,32,29,0.35)] sm:max-w-[520px]"
          role="dialog"
          aria-modal="true"
          :aria-label="title"
          @click.stop
        >
          <header class="flex shrink-0 items-start justify-between gap-4 border-b border-brand-border px-4 py-4 sm:px-6">
            <div class="min-w-0 space-y-1">
              <h2 class="text-lg font-bold text-brand-text">{{ title }}</h2>
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
            </div>
            <button
              type="button"
              class="rounded-lg p-2 text-brand-text-muted hover:bg-[var(--surface-subtle)]"
              :aria-label="t('gpsProviders.cancel')"
              :disabled="submitting"
              @click="emit('close')"
            >
              <X class="h-5 w-5" />
            </button>
          </header>

          <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-6">
            <p v-if="formError" class="mb-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
              {{ formError }}
            </p>

            <div v-show="step === 1" class="space-y-4">
              <label class="block text-sm">
                <span class="mb-1 block font-semibold text-brand-text">{{ t('gpsProviders.name') }}</span>
                <input
                  ref="nameInputRef"
                  :value="form.name"
                  required
                  class="w-full rounded-xl border border-brand-border px-3 py-2.5 text-sm"
                  data-test="gps-provider-name"
                  @input="patch({ name: ($event.target as HTMLInputElement).value })"
                />
              </label>
              <label v-if="!isEdit" class="block text-sm">
                <span class="mb-1 block font-semibold text-brand-text">{{ t('gpsProviders.code') }}</span>
                <input
                  :value="form.code"
                  required
                  class="w-full rounded-xl border border-brand-border px-3 py-2.5 text-sm"
                  data-test="gps-provider-code"
                  @input="patch({ code: ($event.target as HTMLInputElement).value })"
                />
                <span class="mt-1 block text-xs text-brand-text-muted">{{ t('gpsProviders.codeHint') }}</span>
              </label>
              <div>
                <p class="mb-1 text-sm font-semibold text-brand-text">{{ t('gpsProviders.method') }}</p>
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

              <label v-if="showTraccarFields" class="block text-sm">
                <span class="mb-1 block font-semibold">{{ t('gpsProviders.baseUrl') }}</span>
                <input
                  :value="form.base_url"
                  class="w-full rounded-xl border border-brand-border px-3 py-2.5 text-sm"
                  dir="ltr"
                  @input="patch({ base_url: ($event.target as HTMLInputElement).value })"
                />
              </label>

              <label v-if="showRestFields" class="block text-sm">
                <span class="mb-1 block font-semibold">{{ t('gpsProviders.endpointUrl') }}</span>
                <input
                  :value="form.endpoint_url"
                  class="w-full rounded-xl border border-brand-border px-3 py-2.5 text-sm"
                  dir="ltr"
                  @input="patch({ endpoint_url: ($event.target as HTMLInputElement).value })"
                />
              </label>

              <template v-if="showWebhookFields">
                <label class="block text-sm">
                  <span class="mb-1 block font-semibold">{{ t('gpsProviders.webhookSecret') }}</span>
                  <input
                    :value="form.webhook_secret"
                    type="password"
                    autocomplete="new-password"
                    class="w-full rounded-xl border border-brand-border px-3 py-2.5 text-sm"
                    @input="patch({ webhook_secret: ($event.target as HTMLInputElement).value })"
                  />
                </label>
                <label class="block text-sm">
                  <span class="mb-1 block font-semibold">{{ t('gpsProviders.apiToken') }}</span>
                  <input
                    :value="form.api_token"
                    type="password"
                    autocomplete="new-password"
                    class="w-full rounded-xl border border-brand-border px-3 py-2.5 text-sm"
                    @input="patch({ api_token: ($event.target as HTMLInputElement).value })"
                  />
                </label>
              </template>

              <template v-if="showTraccarFields">
                <label class="block text-sm">
                  <span class="mb-1 block font-semibold">{{ t('gpsProviders.email') }}</span>
                  <input
                    :value="form.email"
                    class="w-full rounded-xl border border-brand-border px-3 py-2.5 text-sm"
                    @input="patch({ email: ($event.target as HTMLInputElement).value })"
                  />
                </label>
                <label class="block text-sm">
                  <span class="mb-1 block font-semibold">{{ t('gpsProviders.password') }}</span>
                  <input
                    :value="form.password"
                    type="password"
                    autocomplete="new-password"
                    class="w-full rounded-xl border border-brand-border px-3 py-2.5 text-sm"
                    @input="patch({ password: ($event.target as HTMLInputElement).value })"
                  />
                </label>
              </template>

              <label
                v-if="form.connection_method === 'rest_pull' || form.connection_method === 'traccar' || form.connection_method === 'simulator'"
                class="block text-sm"
              >
                <span class="mb-1 block font-semibold">{{ t('gpsProviders.pollSeconds') }}</span>
                <input
                  :value="form.poll_interval_seconds"
                  type="number"
                  min="30"
                  class="w-full rounded-xl border border-brand-border px-3 py-2.5 text-sm"
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

          <footer class="flex shrink-0 items-center justify-between gap-2 border-t border-brand-border px-4 py-4 sm:px-6">
            <button
              type="button"
              class="rounded-xl border border-brand-border px-4 py-2.5 text-sm font-semibold"
              :disabled="submitting"
              @click="step === 1 ? emit('close') : (step -= 1)"
            >
              {{ step === 1 ? t('gpsProviders.cancel') : t('gpsProviders.back') }}
            </button>
            <button
              v-if="step < 3"
              type="button"
              class="rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50"
              :disabled="step === 1 && !step1Valid"
              data-test="gps-provider-next"
              @click="step += 1"
            >
              {{ t('gpsProviders.next') }}
            </button>
            <button
              v-else
              type="button"
              class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50"
              :disabled="submitting || !step1Valid"
              data-test="gps-provider-save"
              @click="emit('submit')"
            >
              <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
              {{ t('gpsProviders.save') }}
            </button>
          </footer>
        </aside>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.gps-drawer-enter-active,
.gps-drawer-leave-active {
  transition: opacity 0.2s ease;
}
.gps-drawer-enter-active .gps-drawer-panel,
.gps-drawer-leave-active .gps-drawer-panel {
  transition: transform 0.22s ease;
}
.gps-drawer-enter-from,
.gps-drawer-leave-to {
  opacity: 0;
}
.gps-drawer-enter-from .gps-drawer-panel,
.gps-drawer-leave-to .gps-drawer-panel {
  transform: translateX(1.25rem);
}
@media (prefers-reduced-motion: reduce) {
  .gps-drawer-enter-active,
  .gps-drawer-leave-active,
  .gps-drawer-enter-active .gps-drawer-panel,
  .gps-drawer-leave-active .gps-drawer-panel {
    transition: none;
  }
}
</style>
