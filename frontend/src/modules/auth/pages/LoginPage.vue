<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'

import { ApiError } from '@/shared/api/http'

import PasswordInput from '../components/PasswordInput.vue'
import { useLoginMutation } from '../mutations/useLoginMutation'
import { isAuthBlockCode } from '../types/auth'
import { validateLogin } from '../validation/authValidation'

const { t } = useI18n()
const route = useRoute()
const { mutate: login, isPending: isLoggingIn } = useLoginMutation()

const email = ref('')
const password = ref('')
const remember = ref(false)
const fieldErrors = ref<Record<string, string>>({})
const formError = ref('')

const resetSuccess = computed(() => route.query.reset === '1')

function fieldMessage(key: string | undefined): string | undefined {
  if (!key) return undefined
  return t(`auth.validation.${key}`)
}

function mapServerError(error: unknown): void {
  if (!(error instanceof ApiError)) {
    formError.value = t('auth.errors.generic')
    return
  }

  if (error.status === 422 && error.errors) {
    fieldErrors.value = Object.fromEntries(
      Object.entries(error.errors).map(([k, v]) => [k, v[0] ?? t('auth.errors.generic')]),
    )
    return
  }

  if (error.code === 'AUTH_INVALID_CREDENTIALS') {
    formError.value = t('auth.errors.invalidCredentials')
    return
  }

  if (error.code === 'AUTH_TOO_MANY_ATTEMPTS') {
    formError.value = t('auth.errors.tooManyAttempts')
    return
  }

  if (isAuthBlockCode(error.code)) {
    formError.value = t(`auth.errors.${error.code}`)
    return
  }

  formError.value = error.message || t('auth.errors.generic')
}

function onSubmit(): void {
  formError.value = ''
  fieldErrors.value = {}

  const validation = validateLogin(email.value, password.value)
  if (validation.email || validation.password) {
    fieldErrors.value = {
      email: fieldMessage(validation.email) ?? '',
      password: fieldMessage(validation.password) ?? '',
    }
    return
  }

  login(
    {
      email: email.value.trim(),
      password: password.value,
      remember: remember.value,
    },
    { onError: mapServerError },
  )
}
</script>

<template>
  <div>
    <h2 class="text-xl font-semibold text-neutral-900">{{ t('auth.loginTitle') }}</h2>
    <p class="mt-1 text-sm text-neutral-600">{{ t('auth.loginSubtitle') }}</p>

    <p
      v-if="resetSuccess"
      class="mt-4 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800"
      role="status"
    >
      {{ t('auth.resetSuccessBanner') }}
    </p>

    <form class="mt-6 space-y-4" @submit.prevent="onSubmit">
      <div class="space-y-1.5">
        <label for="login-email" class="block text-sm font-medium text-neutral-700">{{ t('auth.email') }}</label>
        <input
          id="login-email"
          v-model="email"
          type="email"
          autocomplete="username"
          class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm outline-none focus:border-emerald-700 focus:ring-2 focus:ring-emerald-700/20"
          :aria-invalid="!!fieldErrors.email"
        />
        <p v-if="fieldErrors.email" class="text-sm text-red-700" role="alert">{{ fieldErrors.email }}</p>
      </div>

      <PasswordInput
        id="login-password"
        v-model="password"
        :label="t('auth.password')"
        autocomplete="current-password"
        :error="fieldErrors.password"
      />

      <div class="flex items-center justify-between gap-3">
        <label class="flex items-center gap-2 text-sm text-neutral-700">
          <input v-model="remember" type="checkbox" class="rounded border-neutral-300 text-emerald-800 focus:ring-emerald-700" />
          {{ t('auth.rememberMe') }}
        </label>
        <RouterLink to="/forgot-password" class="text-sm font-medium text-emerald-800 hover:underline">
          {{ t('auth.forgotPasswordLink') }}
        </RouterLink>
      </div>

      <p v-if="formError" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">
        {{ formError }}
      </p>

      <button
        type="submit"
        class="w-full rounded-lg bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-900 disabled:cursor-not-allowed disabled:opacity-60"
        :disabled="isLoggingIn"
      >
        {{ isLoggingIn ? t('auth.loggingIn') : t('auth.login') }}
      </button>
    </form>
  </div>
</template>
