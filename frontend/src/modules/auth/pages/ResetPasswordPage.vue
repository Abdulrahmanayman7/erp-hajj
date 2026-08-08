<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'

import { ApiError } from '@/shared/api/http'

import PasswordInput from '../components/PasswordInput.vue'
import { useResetPasswordMutation } from '../mutations/useResetPasswordMutation'
import { validateResetPassword } from '../validation/authValidation'

const { t } = useI18n()
const route = useRoute()
const { mutate: submitReset, isPending: isSaving } = useResetPasswordMutation()

const token = computed(() => String(route.query.token ?? ''))
const email = computed(() => String(route.query.email ?? ''))

const password = ref('')
const passwordConfirmation = ref('')
const fieldErrors = ref<Record<string, string>>({})
const formError = ref('')

const missingParams = computed(() => !token.value || !email.value)

function onSubmit(): void {
  formError.value = ''
  fieldErrors.value = {}

  const validation = validateResetPassword(password.value, passwordConfirmation.value)
  if (validation.password || validation.password_confirmation) {
    fieldErrors.value = {
      password: validation.password ? t(`auth.validation.${validation.password}`) : '',
      password_confirmation: validation.password_confirmation
        ? t(`auth.validation.${validation.password_confirmation}`)
        : '',
    }
    return
  }

  submitReset(
    {
      token: token.value,
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    },
    {
      onError: (error) => {
        if (!(error instanceof ApiError)) {
          formError.value = t('auth.errors.generic')
          return
        }
        if (error.code === 'AUTH_PASSWORD_RESET_EXPIRED') {
          formError.value = t('auth.errors.AUTH_PASSWORD_RESET_EXPIRED')
          return
        }
        if (error.code === 'AUTH_PASSWORD_RESET_INVALID') {
          formError.value = t('auth.errors.AUTH_PASSWORD_RESET_INVALID')
          return
        }
        if (error.status === 422 && error.errors) {
          fieldErrors.value = Object.fromEntries(
            Object.entries(error.errors).map(([k, v]) => [k, v[0] ?? '']),
          )
          return
        }
        formError.value = t('auth.errors.generic')
      },
    },
  )
}
</script>

<template>
  <div>
    <h2 class="text-xl font-semibold text-neutral-900">{{ t('auth.resetTitle') }}</h2>
    <p class="mt-1 text-sm text-neutral-600">{{ t('auth.resetSubtitle') }}</p>
    <p class="mt-2 text-xs text-neutral-500">{{ t('auth.passwordPolicyHint') }}</p>

    <div v-if="missingParams" class="mt-6 rounded-md bg-red-50 px-3 py-3 text-sm text-red-800" role="alert">
      {{ t('auth.errors.AUTH_PASSWORD_RESET_INVALID') }}
      <p class="mt-2">
        <RouterLink to="/forgot-password" class="font-medium underline">{{ t('auth.requestNewLink') }}</RouterLink>
      </p>
    </div>

    <form v-else class="mt-6 space-y-4" @submit.prevent="onSubmit">
      <PasswordInput
        id="reset-password"
        v-model="password"
        :label="t('auth.newPassword')"
        autocomplete="new-password"
        :error="fieldErrors.password"
      />
      <PasswordInput
        id="reset-password-confirmation"
        v-model="passwordConfirmation"
        :label="t('auth.confirmPassword')"
        autocomplete="new-password"
        :error="fieldErrors.password_confirmation"
      />

      <p v-if="formError" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">
        {{ formError }}
      </p>

      <button
        type="submit"
        class="w-full rounded-lg bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-900 disabled:opacity-60"
        :disabled="isSaving"
      >
        {{ isSaving ? t('auth.saving') : t('auth.resetPassword') }}
      </button>
    </form>

    <p class="mt-6 text-center text-sm">
      <RouterLink to="/login" class="font-medium text-emerald-800 hover:underline">{{ t('auth.backToLogin') }}</RouterLink>
    </p>
  </div>
</template>
