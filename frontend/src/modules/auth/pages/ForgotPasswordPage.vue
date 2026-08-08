<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'

import { ApiError } from '@/shared/api/http'

import { useForgotPasswordMutation } from '../mutations/useForgotPasswordMutation'
import { validateForgotPassword } from '../validation/authValidation'

const { t } = useI18n()
const { mutate: requestReset, isPending: isSending } = useForgotPasswordMutation()

const email = ref('')
const fieldError = ref('')
const formError = ref('')
const successMessage = ref('')

function onSubmit(): void {
  fieldError.value = ''
  formError.value = ''
  successMessage.value = ''

  const validation = validateForgotPassword(email.value)
  if (validation.email) {
    fieldError.value = t(`auth.validation.${validation.email}`)
    return
  }

  requestReset(
    { email: email.value.trim() },
    {
      onSuccess: (message) => {
        successMessage.value = message || t('auth.forgotSuccess')
      },
      onError: (error) => {
        if (error instanceof ApiError && error.code === 'AUTH_TOO_MANY_ATTEMPTS') {
          formError.value = t('auth.errors.tooManyAttempts')
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
    <h2 class="text-xl font-semibold text-neutral-900">{{ t('auth.forgotTitle') }}</h2>
    <p class="mt-1 text-sm text-neutral-600">{{ t('auth.forgotSubtitle') }}</p>

    <form v-if="!successMessage" class="mt-6 space-y-4" @submit.prevent="onSubmit">
      <div class="space-y-1.5">
        <label for="forgot-email" class="block text-sm font-medium text-neutral-700">{{ t('auth.email') }}</label>
        <input
          id="forgot-email"
          v-model="email"
          type="email"
          autocomplete="username"
          class="w-full rounded-lg border border-neutral-300 px-3 py-2.5 text-sm outline-none focus:border-emerald-700 focus:ring-2 focus:ring-emerald-700/20"
        />
        <p v-if="fieldError" class="text-sm text-red-700" role="alert">{{ fieldError }}</p>
      </div>

      <p v-if="formError" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">
        {{ formError }}
      </p>

      <button
        type="submit"
        class="w-full rounded-lg bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-900 disabled:opacity-60"
        :disabled="isSending"
      >
        {{ isSending ? t('auth.sending') : t('auth.sendResetLink') }}
      </button>
    </form>

    <div v-else class="mt-6 rounded-md bg-emerald-50 px-3 py-3 text-sm text-emerald-900" role="status">
      {{ successMessage }}
    </div>

    <p class="mt-6 text-center text-sm">
      <RouterLink to="/login" class="font-medium text-emerald-800 hover:underline">{{ t('auth.backToLogin') }}</RouterLink>
    </p>
  </div>
</template>
