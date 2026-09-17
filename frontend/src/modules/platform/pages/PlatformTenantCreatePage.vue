<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { ArrowLeft, ArrowRight, CheckCircle2, Loader2 } from 'lucide-vue-next'

import PasswordInput from '@/modules/auth/components/PasswordInput.vue'
import { ApiError } from '@/shared/api/http'
import AppPageHeader from '@/shared/components/AppPageHeader.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'
import { listTimezones } from '@/modules/settings/validation/settingsValidation'

import TenantStatusBadge from '../components/TenantStatusBadge.vue'
import { useCreatePlatformTenantMutation } from '../mutations/usePlatformTenantMutations'
import type { PlatformTenantCreateResult, ProvisionTenantPayload } from '../types/platform'
import { validateTenantWizardStep, type TenantWizardFieldErrors } from '../validation/platformValidation'

const { t } = useI18n()
const createMutation = useCreatePlatformTenantMutation()

const step = ref<1 | 2 | 3 | 4>(1)
const created = ref<PlatformTenantCreateResult | null>(null)
const createdWithInviteIntent = ref(true)
const formError = ref('')
const fieldErrors = ref<Record<string, string>>({})

const form = reactive({
  tenant_code: '',
  name: '',
  locale: 'ar',
  timezone: 'Asia/Riyadh',
  status: 'active' as 'active' | 'pending',
  contact_name: '',
  contact_email: '',
  contact_phone: '',
  notes: '',
  mail_from_address: '',
  mail_from_name: '',
  owner_name: '',
  owner_email: '',
  send_invite: true,
  temporary_password: '',
})

const steps = computed(() => [
  { id: 1 as const, label: t('platform.wizard.steps.tenant') },
  { id: 2 as const, label: t('platform.wizard.steps.owner') },
  { id: 3 as const, label: t('platform.wizard.steps.settings') },
  { id: 4 as const, label: t('platform.wizard.steps.review') },
])

const statusOptions = computed<AppSelectOption[]>(() => [
  { value: 'active', label: t('platform.status.active') },
  { value: 'pending', label: t('platform.status.pending') },
])

const localeOptions = computed<AppSelectOption[]>(() => [
  { value: 'ar', label: t('platform.locale.ar') },
  { value: 'en', label: t('platform.locale.en') },
])

const timezoneOptions = computed<AppSelectOption[]>(() =>
  listTimezones(form.timezone).map((tz) => ({ value: tz, label: tz })),
)

const isSubmitting = computed(() => createMutation.isPending.value)

const inviteStatusKey = computed(() => {
  if (!created.value) return null
  if (created.value.invite_sent) return 'sent'
  if (created.value.password_provisioned) return 'manual'
  if (createdWithInviteIntent.value) return 'failed'
  return 'manual'
})

function validationMessage(key: string | undefined): string {
  if (!key) return ''
  return t(`platform.validation.${key}`)
}

function applyStepErrors(errors: TenantWizardFieldErrors): boolean {
  const mapped: Record<string, string> = {}
  Object.entries(errors).forEach(([k, v]) => {
    if (v) mapped[k] = validationMessage(v)
  })
  fieldErrors.value = mapped
  return Object.keys(mapped).length === 0
}

function goNext(): void {
  formError.value = ''
  if (step.value === 1 || step.value === 2 || step.value === 3) {
    const errors = validateTenantWizardStep(step.value, form)
    if (!applyStepErrors(errors)) return
  }
  if (step.value < 4) {
    step.value = (step.value + 1) as 1 | 2 | 3 | 4
  }
}

function goBack(): void {
  formError.value = ''
  fieldErrors.value = {}
  if (step.value > 1 && !created.value) {
    step.value = (step.value - 1) as 1 | 2 | 3 | 4
  }
}

function buildPayload(): ProvisionTenantPayload {
  const payload: ProvisionTenantPayload = {
    tenant_code: form.tenant_code.trim().toLowerCase(),
    name: form.name.trim(),
    locale: form.locale,
    timezone: form.timezone,
    status: form.status,
    contact_name: form.contact_name.trim() || null,
    contact_email: form.contact_email.trim() || null,
    contact_phone: form.contact_phone.trim() || null,
    notes: form.notes.trim() || null,
    mail_from_address: form.mail_from_address.trim() || null,
    mail_from_name: form.mail_from_name.trim() || null,
    owner: {
      name: form.owner_name.trim(),
      email: form.owner_email.trim(),
      send_invite: form.send_invite,
    },
  }

  if (!form.send_invite) {
    payload.owner.temporary_password = form.temporary_password
  }

  return payload
}

async function onCreate(): Promise<void> {
  if (isSubmitting.value || created.value) return

  formError.value = ''
  // Re-validate all steps before submit
  for (const s of [1, 2, 3] as const) {
    const errors = validateTenantWizardStep(s, form)
    if (!applyStepErrors(errors)) {
      step.value = s
      return
    }
  }

  createdWithInviteIntent.value = form.send_invite

  try {
    const result = await createMutation.mutateAsync(buildPayload())
    created.value = result
    // Clear password from local state — never show after leaving create step
    form.temporary_password = ''
  } catch (error) {
    if (error instanceof ApiError && error.status === 422 && error.errors) {
      fieldErrors.value = Object.fromEntries(
        Object.entries(error.errors).map(([k, v]) => {
          const key = k.replace('owner.', 'owner_')
          return [key, v[0] ?? t('platform.errors.generic')]
        }),
      )
      if (fieldErrors.value.tenant_code || fieldErrors.value.name || fieldErrors.value.contact_email) {
        step.value = 1
      } else if (
        fieldErrors.value.owner_name ||
        fieldErrors.value.owner_email ||
        fieldErrors.value.temporary_password ||
        fieldErrors.value['owner.temporary_password']
      ) {
        step.value = 2
      } else if (fieldErrors.value.mail_from_address || fieldErrors.value.mail_from_name) {
        step.value = 3
      }
      return
    }
    formError.value =
      error instanceof ApiError
        ? error.message || t('platform.errors.generic')
        : t('platform.errors.generic')
  }
}
</script>

<template>
  <div class="space-y-5">
    <AppPageHeader
      :title="t('platform.wizard.title')"
      :subtitle="t('platform.wizard.subtitle')"
    >
      <template #actions>
        <RouterLink
          :to="{ name: 'platform-tenants' }"
          class="inline-flex h-11 items-center justify-center rounded-xl border border-brand-border px-4 text-sm font-semibold text-brand-text"
        >
          {{ t('platform.backToList') }}
        </RouterLink>
      </template>
    </AppPageHeader>

    <!-- Completion -->
    <div
      v-if="created"
      class="space-y-5 rounded-2xl border border-brand-border bg-brand-surface p-5 sm:p-6"
    >
      <div class="flex items-start gap-3">
        <CheckCircle2 class="mt-0.5 h-7 w-7 shrink-0 text-emerald-700" />
        <div>
          <h2 class="text-lg font-bold text-brand-text">{{ t('platform.wizard.completeTitle') }}</h2>
          <p class="mt-1 text-sm text-brand-text-secondary">{{ t('platform.wizard.completeSubtitle') }}</p>
        </div>
      </div>

      <dl class="grid gap-3 sm:grid-cols-2">
        <div class="rounded-xl bg-brand-bg/60 px-4 py-3">
          <dt class="text-xs text-brand-text-muted">{{ t('platform.fields.name') }}</dt>
          <dd class="mt-1 font-semibold">{{ created.name }}</dd>
        </div>
        <div class="rounded-xl bg-brand-bg/60 px-4 py-3">
          <dt class="text-xs text-brand-text-muted">{{ t('platform.fields.code') }}</dt>
          <dd class="mt-1 font-mono text-sm" dir="ltr">{{ created.code }}</dd>
        </div>
        <div class="rounded-xl bg-brand-bg/60 px-4 py-3">
          <dt class="text-xs text-brand-text-muted">{{ t('platform.columns.owner') }}</dt>
          <dd class="mt-1">
            <div class="font-semibold">{{ created.owner?.name ?? '—' }}</div>
            <div class="text-xs text-brand-text-muted" dir="ltr">{{ created.owner?.email }}</div>
          </dd>
        </div>
        <div class="rounded-xl bg-brand-bg/60 px-4 py-3">
          <dt class="text-xs text-brand-text-muted">{{ t('platform.columns.status') }}</dt>
          <dd class="mt-1">
            <TenantStatusBadge :status="created.status" />
          </dd>
        </div>
        <div class="rounded-xl bg-brand-bg/60 px-4 py-3 sm:col-span-2">
          <dt class="text-xs text-brand-text-muted">{{ t('platform.wizard.inviteStatus') }}</dt>
          <dd class="mt-1 font-medium">
            <span v-if="inviteStatusKey === 'sent'" class="text-emerald-800">
              {{ t('platform.wizard.inviteSent') }}
            </span>
            <span v-else-if="inviteStatusKey === 'manual'" class="text-brand-text">
              {{ t('platform.wizard.inviteManual') }}
            </span>
            <span v-else class="text-amber-900">
              {{ t('platform.wizard.inviteFailed') }}
              <span v-if="created.invite_code" class="ms-1 text-xs text-brand-text-muted" dir="ltr">
                ({{ created.invite_code }})
              </span>
            </span>
          </dd>
        </div>
      </dl>

      <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
        <RouterLink
          :to="{ name: 'platform-tenants' }"
          class="inline-flex h-11 items-center justify-center rounded-xl border px-4 text-sm font-semibold"
        >
          {{ t('platform.backToList') }}
        </RouterLink>
        <RouterLink
          :to="{ name: 'platform-tenant-details', params: { id: created.id } }"
          class="inline-flex h-11 items-center justify-center rounded-xl bg-brand-primary px-4 text-sm font-semibold text-white"
        >
          {{ t('platform.wizard.viewDetails') }}
        </RouterLink>
      </div>
    </div>

    <template v-else>
      <!-- Steps indicator -->
      <ol class="grid gap-2 sm:grid-cols-4">
        <li
          v-for="item in steps"
          :key="item.id"
          class="rounded-xl border px-3 py-2 text-sm"
          :class="
            step === item.id
              ? 'border-brand-primary bg-brand-primary-soft text-brand-primary-dark'
              : item.id < step
                ? 'border-emerald-200 bg-emerald-50 text-emerald-900'
                : 'border-brand-border bg-brand-surface text-brand-text-muted'
          "
        >
          <span class="font-semibold">{{ item.id }}.</span>
          {{ item.label }}
        </li>
      </ol>

      <div class="rounded-2xl border border-brand-border bg-brand-surface p-5 sm:p-6">
        <!-- Step 1 -->
        <div v-if="step === 1" class="space-y-4">
          <h2 class="text-base font-bold">{{ t('platform.wizard.steps.tenant') }}</h2>

          <div class="grid gap-4 sm:grid-cols-2">
            <label class="block sm:col-span-1">
              <span class="text-sm font-medium">{{ t('platform.fields.code') }}</span>
              <input
                v-model="form.tenant_code"
                dir="ltr"
                class="mt-1 h-11 w-full rounded-xl border border-brand-border px-3 text-sm"
                :placeholder="t('platform.fields.codePlaceholder')"
              />
              <p class="mt-1 text-xs text-brand-text-muted">{{ t('platform.fields.codeHint') }}</p>
              <p v-if="fieldErrors.tenant_code" class="mt-1 text-xs text-red-600">{{ fieldErrors.tenant_code }}</p>
            </label>

            <label class="block">
              <span class="text-sm font-medium">{{ t('platform.fields.name') }}</span>
              <input
                v-model="form.name"
                class="mt-1 h-11 w-full rounded-xl border border-brand-border px-3 text-sm"
              />
              <p v-if="fieldErrors.name" class="mt-1 text-xs text-red-600">{{ fieldErrors.name }}</p>
            </label>

            <label class="block">
              <span class="text-sm font-medium">{{ t('platform.fields.locale') }}</span>
              <div class="mt-1">
                <AppSelect v-model="form.locale" :options="localeOptions" />
              </div>
            </label>

            <label class="block">
              <span class="text-sm font-medium">{{ t('platform.fields.timezone') }}</span>
              <div class="mt-1">
                <AppSelect v-model="form.timezone" :options="timezoneOptions" searchable />
              </div>
            </label>

            <label class="block">
              <span class="text-sm font-medium">{{ t('platform.fields.status') }}</span>
              <div class="mt-1">
                <AppSelect v-model="form.status" :options="statusOptions" />
              </div>
            </label>

            <label class="block">
              <span class="text-sm font-medium">{{ t('platform.fields.contactName') }}</span>
              <input
                v-model="form.contact_name"
                class="mt-1 h-11 w-full rounded-xl border border-brand-border px-3 text-sm"
              />
            </label>

            <label class="block">
              <span class="text-sm font-medium">{{ t('platform.fields.contactEmail') }}</span>
              <input
                v-model="form.contact_email"
                type="email"
                dir="ltr"
                class="mt-1 h-11 w-full rounded-xl border border-brand-border px-3 text-sm"
              />
              <p v-if="fieldErrors.contact_email" class="mt-1 text-xs text-red-600">{{ fieldErrors.contact_email }}</p>
            </label>

            <label class="block">
              <span class="text-sm font-medium">{{ t('platform.fields.contactPhone') }}</span>
              <input
                v-model="form.contact_phone"
                dir="ltr"
                class="mt-1 h-11 w-full rounded-xl border border-brand-border px-3 text-sm"
              />
            </label>

            <label class="block sm:col-span-2">
              <span class="text-sm font-medium">{{ t('platform.fields.notes') }}</span>
              <textarea
                v-model="form.notes"
                rows="3"
                class="mt-1 w-full rounded-xl border border-brand-border px-3 py-2 text-sm"
              />
            </label>
          </div>
        </div>

        <!-- Step 2 -->
        <div v-else-if="step === 2" class="space-y-4">
          <h2 class="text-base font-bold">{{ t('platform.wizard.steps.owner') }}</h2>

          <div class="grid gap-4 sm:grid-cols-2">
            <label class="block">
              <span class="text-sm font-medium">{{ t('platform.fields.ownerName') }}</span>
              <input
                v-model="form.owner_name"
                class="mt-1 h-11 w-full rounded-xl border border-brand-border px-3 text-sm"
              />
              <p v-if="fieldErrors.owner_name" class="mt-1 text-xs text-red-600">{{ fieldErrors.owner_name }}</p>
            </label>

            <label class="block">
              <span class="text-sm font-medium">{{ t('platform.fields.ownerEmail') }}</span>
              <input
                v-model="form.owner_email"
                type="email"
                dir="ltr"
                class="mt-1 h-11 w-full rounded-xl border border-brand-border px-3 text-sm"
              />
              <p v-if="fieldErrors.owner_email" class="mt-1 text-xs text-red-600">{{ fieldErrors.owner_email }}</p>
            </label>
          </div>

          <label class="inline-flex items-center gap-2 text-sm">
            <input v-model="form.send_invite" type="checkbox" class="h-4 w-4 rounded border-brand-border" />
            {{ t('platform.fields.sendInvite') }}
          </label>
          <p class="text-xs text-brand-text-muted">{{ t('platform.fields.sendInviteHint') }}</p>

          <div v-if="!form.send_invite" class="max-w-md">
            <PasswordInput
              id="owner-temp-password"
              v-model="form.temporary_password"
              :label="t('platform.fields.temporaryPassword')"
              autocomplete="new-password"
              :error="fieldErrors.temporary_password"
            />
            <p class="mt-1 text-xs text-brand-text-muted">{{ t('auth.passwordPolicyHint') }}</p>
          </div>
        </div>

        <!-- Step 3 -->
        <div v-else-if="step === 3" class="space-y-4">
          <h2 class="text-base font-bold">{{ t('platform.wizard.steps.settings') }}</h2>
          <p class="text-sm text-brand-text-secondary">{{ t('platform.wizard.settingsHint') }}</p>

          <div class="grid gap-4 sm:grid-cols-2">
            <label class="block">
              <span class="text-sm font-medium">{{ t('platform.fields.mailFromAddress') }}</span>
              <input
                v-model="form.mail_from_address"
                type="email"
                dir="ltr"
                class="mt-1 h-11 w-full rounded-xl border border-brand-border px-3 text-sm"
              />
              <p v-if="fieldErrors.mail_from_address" class="mt-1 text-xs text-red-600">
                {{ fieldErrors.mail_from_address }}
              </p>
            </label>

            <label class="block">
              <span class="text-sm font-medium">{{ t('platform.fields.mailFromName') }}</span>
              <input
                v-model="form.mail_from_name"
                class="mt-1 h-11 w-full rounded-xl border border-brand-border px-3 text-sm"
              />
            </label>

            <div class="rounded-xl bg-brand-bg/60 px-4 py-3 sm:col-span-2">
              <p class="text-xs text-brand-text-muted">{{ t('platform.fields.timezone') }}</p>
              <p class="mt-1 font-medium" dir="ltr">{{ form.timezone }}</p>
              <p class="mt-1 text-xs text-brand-text-muted">{{ t('platform.wizard.timezoneSetInStep1') }}</p>
            </div>
          </div>
        </div>

        <!-- Step 4 review -->
        <div v-else class="space-y-4">
          <h2 class="text-base font-bold">{{ t('platform.wizard.steps.review') }}</h2>

          <dl class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-xl bg-brand-bg/50 px-4 py-3">
              <dt class="text-xs text-brand-text-muted">{{ t('platform.fields.name') }}</dt>
              <dd class="mt-1 font-semibold">{{ form.name }}</dd>
            </div>
            <div class="rounded-xl bg-brand-bg/50 px-4 py-3">
              <dt class="text-xs text-brand-text-muted">{{ t('platform.fields.code') }}</dt>
              <dd class="mt-1 font-mono text-sm" dir="ltr">{{ form.tenant_code.toLowerCase() }}</dd>
            </div>
            <div class="rounded-xl bg-brand-bg/50 px-4 py-3">
              <dt class="text-xs text-brand-text-muted">{{ t('platform.fields.status') }}</dt>
              <dd class="mt-1">{{ t(`platform.status.${form.status}`) }}</dd>
            </div>
            <div class="rounded-xl bg-brand-bg/50 px-4 py-3">
              <dt class="text-xs text-brand-text-muted">{{ t('platform.fields.timezone') }}</dt>
              <dd class="mt-1" dir="ltr">{{ form.timezone }}</dd>
            </div>
            <div class="rounded-xl bg-brand-bg/50 px-4 py-3">
              <dt class="text-xs text-brand-text-muted">{{ t('platform.fields.ownerName') }}</dt>
              <dd class="mt-1 font-semibold">{{ form.owner_name }}</dd>
            </div>
            <div class="rounded-xl bg-brand-bg/50 px-4 py-3">
              <dt class="text-xs text-brand-text-muted">{{ t('platform.fields.ownerEmail') }}</dt>
              <dd class="mt-1" dir="ltr">{{ form.owner_email }}</dd>
            </div>
            <div class="rounded-xl bg-brand-bg/50 px-4 py-3 sm:col-span-2">
              <dt class="text-xs text-brand-text-muted">{{ t('platform.fields.sendInvite') }}</dt>
              <dd class="mt-1">
                {{
                  form.send_invite
                    ? t('platform.wizard.reviewInviteYes')
                    : t('platform.wizard.reviewInviteNo')
                }}
              </dd>
            </div>
          </dl>

          <p
            v-if="formError"
            class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800"
            role="alert"
          >
            {{ formError }}
          </p>
        </div>

        <div class="mt-6 flex flex-col-reverse gap-2 border-t border-brand-border pt-4 sm:flex-row sm:justify-between">
          <button
            type="button"
            class="inline-flex h-11 items-center justify-center gap-2 rounded-xl border px-4 text-sm font-semibold disabled:opacity-40"
            :disabled="step === 1 || isSubmitting"
            @click="goBack"
          >
            <ArrowRight class="h-4 w-4" />
            {{ t('platform.wizard.back') }}
          </button>

          <button
            v-if="step < 4"
            type="button"
            class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-brand-primary px-4 text-sm font-semibold text-white"
            @click="goNext"
          >
            {{ t('platform.wizard.next') }}
            <ArrowLeft class="h-4 w-4" />
          </button>

          <button
            v-else
            type="button"
            class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-brand-primary px-4 text-sm font-semibold text-white disabled:opacity-60"
            :disabled="isSubmitting"
            :aria-busy="isSubmitting"
            @click="onCreate"
          >
            <Loader2 v-if="isSubmitting" class="h-4 w-4 animate-spin" />
            {{ isSubmitting ? t('platform.wizard.creating') : t('platform.wizard.create') }}
          </button>
        </div>
      </div>
    </template>
  </div>
</template>
