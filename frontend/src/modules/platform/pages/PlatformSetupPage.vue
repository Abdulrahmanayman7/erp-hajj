<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

import PasswordInput from '@/modules/auth/components/PasswordInput.vue'
import { ApiError } from '@/shared/api/http'

import { useBootstrapPlatformAdminMutation } from '../mutations/useBootstrapPlatformAdminMutation'
import { usePlatformSetupStatusQuery } from '../queries/usePlatformSetupStatusQuery'
import { validatePlatformSetup } from '../validation/platformValidation'

const { t } = useI18n()
const router = useRouter()
const { data: setupStatus, isLoading: isStatusLoading, isError: isStatusError } =
  usePlatformSetupStatusQuery()
const { mutate: bootstrap, isPending: isSubmitting } = useBootstrapPlatformAdminMutation()

const name = ref('')
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const fieldErrors = ref<Record<string, string>>({})
const formError = ref('')

function fieldMessage(key: string | undefined): string {
  if (!key) return ''
  return t(`auth.validation.${key}`)
}

function mapServerError(error: unknown): void {
  if (!(error instanceof ApiError)) {
    formError.value = t('platform.setup.errors.generic')
    return
  }

  if (error.status === 422 && error.errors) {
    fieldErrors.value = Object.fromEntries(
      Object.entries(error.errors).map(([k, v]) => [k, v[0] ?? t('platform.setup.errors.generic')]),
    )
    return
  }

  formError.value = error.message || t('platform.setup.errors.generic')
}

function onSubmit(): void {
  if (isSubmitting.value) return

  formError.value = ''
  fieldErrors.value = {}

  const validation = validatePlatformSetup({
    name: name.value,
    email: email.value,
    password: password.value,
    password_confirmation: passwordConfirmation.value,
  })

  if (Object.keys(validation).length > 0) {
    fieldErrors.value = {
      name: fieldMessage(validation.name),
      email: fieldMessage(validation.email),
      password: fieldMessage(validation.password),
      password_confirmation: fieldMessage(validation.password_confirmation),
    }
    return
  }

  bootstrap(
    {
      name: name.value.trim(),
      email: email.value.trim(),
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    },
    { onError: mapServerError },
  )
}

function goLogin(): void {
  void router.replace({ name: 'login' })
}
</script>

<template>
  <div>
    <h2 class="text-xl font-semibold text-neutral-900">{{ t('platform.setup.title') }}</h2>
    <p class="mt-1 text-sm text-neutral-600">{{ t('platform.setup.subtitle') }}</p>

    <div v-if="isStatusLoading" class="mt-6 text-sm text-neutral-600">
      {{ t('platform.setup.checking') }}
    </div>

    <div
      v-else-if="isStatusError || setupStatus?.available === false"
      class="mt-6 space-y-4"
    >
      <p class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900" role="status">
        {{ t('platform.setup.unavailable') }}
      </p>
      <button
        type="button"
        class="w-full rounded-lg bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-900"
        @click="goLogin"
      >
        {{ t('auth.backToLogin') }}
      </button>
    </div>

    <form v-else class="mt-6 space-y-4" @submit.prevent="onSubmit">
      <div class="space-y-1.5">
        <label for="setup-name" class="block text-sm font-medium text-neutral-700">
          {{ t('platform.setup.name') }}
        </label>
        <input
          id="setup-name"
          v-model="name"
          type="text"
          autocomplete="name"
          class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm outline-none focus:border-emerald-700 focus:ring-2 focus:ring-emerald-700/20"
          :aria-invalid="!!fieldErrors.name"
        />
        <p v-if="fieldErrors.name" class="text-sm text-red-700" role="alert">{{ fieldErrors.name }}</p>
      </div>

      <div class="space-y-1.5">
        <label for="setup-email" class="block text-sm font-medium text-neutral-700">
          {{ t('auth.email') }}
        </label>
        <input
          id="setup-email"
          v-model="email"
          type="email"
          autocomplete="username"
          class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm outline-none focus:border-emerald-700 focus:ring-2 focus:ring-emerald-700/20"
          :aria-invalid="!!fieldErrors.email"
        />
        <p v-if="fieldErrors.email" class="text-sm text-red-700" role="alert">{{ fieldErrors.email }}</p>
      </div>

      <PasswordInput
        id="setup-password"
        v-model="password"
        :label="t('auth.password')"
        autocomplete="new-password"
        :error="fieldErrors.password"
      />

      <PasswordInput
        id="setup-password-confirmation"
        v-model="passwordConfirmation"
        :label="t('auth.confirmPassword')"
        autocomplete="new-password"
        :error="fieldErrors.password_confirmation"
      />

      <p class="text-xs text-neutral-500">{{ t('auth.passwordPolicyHint') }}</p>

      <p
        v-if="formError"
        class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-800"
        role="alert"
      >
        {{ formError }}
      </p>

      <button
        type="submit"
        class="w-full rounded-lg bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-900 disabled:cursor-not-allowed disabled:opacity-60"
        :disabled="isSubmitting"
        :aria-busy="isSubmitting"
      >
        {{ isSubmitting ? t('platform.setup.submitting') : t('platform.setup.submit') }}
      </button>
    </form>
  </div>
</template>
