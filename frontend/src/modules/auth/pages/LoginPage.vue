<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'

import hajjMosqueVisual from '@/assets/brand/hajj-mosque-visual.png'
import rafeeaLogo from '@/assets/brand/rafeea-logo.png'
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
const setupSuccess = computed(() => route.query.setup === '1')

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
  <!--
    Layout shell is LTR so the split matches the reference (hero left / form right).
    Arabic UI blocks keep dir="rtl".
  -->
  <div
    class="login-page relative h-full max-h-full overflow-hidden bg-[#FDFBF7] text-[#1A3A32]"
    dir="ltr"
    style="padding-top: env(safe-area-inset-top, 0px); padding-bottom: env(safe-area-inset-bottom, 0px)"
  >
    <div
      class="login-grid relative z-10 mx-auto grid h-full w-full max-w-[1600px] grid-cols-1 grid-rows-[minmax(0,28%)_minmax(0,72%)] md:grid-rows-[minmax(0,32%)_minmax(0,68%)] lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)] lg:grid-rows-1"
    >
      <!-- HERO -->
      <section
        class="login-hero relative flex min-h-0 flex-col items-end justify-start overflow-hidden px-4 pb-3 pt-4 sm:px-8 sm:pt-6 lg:px-12 lg:pb-8 lg:pe-36 lg:ps-16 lg:pt-12 xl:pe-44 xl:ps-20 xl:pt-14"
        aria-label="ERP Hajj"
      >
        <!-- Photo: CSS object-cover (not SVG) so it never stretches -->
        <div class="pointer-events-none absolute inset-0 z-0" aria-hidden="true">
          <img
            :src="hajjMosqueVisual"
            alt=""
            class="absolute inset-0 h-full w-full object-cover object-[52%_42%]"
            width="2560"
            height="1706"
            decoding="async"
            fetchpriority="high"
            sizes="(min-width: 1024px) 60vw, 100vw"
          />
          <!-- Soft wash: readable text on top, photo still visible -->
          <div
            class="absolute inset-0"
            style="
              background:
                linear-gradient(180deg, rgba(253, 251, 247, 0.88) 0%, rgba(253, 251, 247, 0.55) 32%, rgba(253, 251, 247, 0.18) 58%, rgba(3, 61, 48, 0.55) 100%),
                linear-gradient(90deg, rgba(253, 251, 247, 0.35) 0%, rgba(253, 251, 247, 0.08) 55%, rgba(253, 251, 247, 0.65) 100%);
            "
          />
        </div>

        <!-- Desktop S-curve seam (decorative lines only — does not clip the photo) -->
        <div
          class="pointer-events-none absolute inset-y-0 right-0 z-[1] hidden w-24 lg:block xl:w-32"
          aria-hidden="true"
        >
          <svg class="h-full w-full" viewBox="0 0 128 900" preserveAspectRatio="none">
            <path d="M128 0C70 140 18 280 36 450 54 620 110 760 128 900V0Z" fill="#FDFBF7" />
            <path
              d="M96 0C48 150 8 300 28 455 48 610 96 760 112 900"
              fill="none"
              stroke="#C6A15B"
              stroke-width="2"
              opacity="0.95"
            />
            <path
              d="M84 0C40 150 4 300 22 455 40 610 86 760 100 900"
              fill="none"
              stroke="#C6A15B"
              stroke-width="1.1"
              opacity="0.5"
            />
          </svg>
        </div>

        <!-- Hero typography — top-right of the Makkah image -->
        <div
          class="login-hero-card relative z-10 w-full max-w-[500px] rounded-2xl bg-white/95 px-4 py-3 shadow-[0_12px_40px_-20px_rgba(3,61,48,0.28)] sm:rounded-3xl sm:px-7 sm:py-6"
          dir="rtl"
        >
          <div class="flex flex-col items-start text-start">
            <h1
              class="font-bold leading-none tracking-tight"
              style="font-size: clamp(1.75rem, 3.4vw, 3.5rem)"
              dir="ltr"
            >
              <span class="text-[#064E3B]">ERP</span>
              <span class="ms-2.5 text-[#C6A15B]">Hajj</span>
            </h1>

            <p class="mt-2 text-base font-semibold leading-snug text-[#064E3B] sm:mt-[1.05rem] sm:text-[1.55rem] lg:text-[1.7rem]">
              {{ t('app.tagline') }}
            </p>

            <div class="mt-2 h-px w-14 bg-[#C6A15B]/90 sm:mt-4" aria-hidden="true" />

            <p class="login-hero-desc mt-3 hidden max-w-[500px] text-[1.05rem] font-normal leading-[1.9] text-[#2F3F3A] sm:mt-5 sm:block sm:text-[1.1rem]">
              {{ t('auth.brandDescription') }}
            </p>
          </div>
        </div>
      </section>

      <!-- LOGIN CARD -->
      <section
        class="login-form-pane relative flex min-h-0 items-stretch justify-center overflow-hidden bg-[#FDFBF7] px-4 py-2 sm:items-center sm:px-6 sm:py-3 lg:px-10 lg:py-4"
        dir="rtl"
      >
        <div class="relative flex w-full max-w-[400px] min-h-0 flex-col justify-center">
          <div
            class="login-card relative max-h-full overflow-y-auto overscroll-contain rounded-[24px] border border-[#EDE8DE] bg-white px-5 py-4 shadow-[0_20px_48px_-28px_rgba(20,40,35,0.28)] sm:rounded-[36px] sm:px-8 sm:py-6 lg:overflow-visible lg:py-7"
          >
            <div class="flex flex-col items-center text-center">
              <img
                :src="rafeeaLogo"
                :alt="t('auth.companyName')"
                class="login-logo h-16 w-auto bg-transparent object-contain sm:h-28 lg:h-36"
                width="280"
                height="144"
                decoding="async"
              />

              <h2 class="mt-2 text-lg font-bold text-[#111827] sm:mt-4 sm:text-[1.55rem]">
                {{ t('auth.loginTitle') }}
              </h2>
              <p class="mt-0.5 text-xs text-[#8A9390] sm:mt-1 sm:text-sm">
                {{ t('auth.loginSubtitle') }}
              </p>
            </div>

            <p
              v-if="setupSuccess"
              class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900 sm:mt-4"
              role="status"
            >
              {{ t('platform.setup.successBanner') }}
            </p>

            <p
              v-if="resetSuccess"
              class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900 sm:mt-4"
              role="status"
            >
              {{ t('auth.resetSuccessBanner') }}
            </p>

            <form class="mt-3 space-y-3 sm:mt-5 sm:space-y-5" @submit.prevent="onSubmit">
              <div>
                <label for="login-email" class="mb-1.5 block text-sm font-medium text-[#2C3E3A]">
                  {{ t('auth.email') }}
                </label>
                <div class="relative">
                  <span
                    class="pointer-events-none absolute inset-s-3.5 top-1/2 -translate-y-1/2 text-[#6B7C76]"
                    aria-hidden="true"
                  >
                    <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
                      <rect x="3" y="5" width="18" height="14" rx="2" />
                      <path d="m3 7 9 7 9-7" />
                    </svg>
                  </span>
                  <input
                    id="login-email"
                    v-model="email"
                    type="email"
                    autocomplete="username"
                    :placeholder="t('auth.emailPlaceholder')"
                    class="login-field h-11 w-full rounded-2xl border border-[#E3E5DF] bg-white ps-11 pe-3 text-sm text-[#17352E] outline-none transition placeholder:text-[#A0A8A5] focus:border-[#064E3B] focus:ring-2 focus:ring-[#064E3B]/15 sm:h-[52px] sm:text-[0.95rem]"
                    :aria-invalid="!!fieldErrors.email"
                    :aria-describedby="fieldErrors.email ? 'login-email-error' : undefined"
                  />
                </div>
                <p v-if="fieldErrors.email" id="login-email-error" class="mt-1.5 text-sm text-red-700" role="alert">
                  {{ fieldErrors.email }}
                </p>
              </div>

              <PasswordInput
                id="login-password"
                v-model="password"
                :label="t('auth.password')"
                autocomplete="current-password"
                :error="fieldErrors.password"
                size="lg"
                show-lock-icon
              />

              <div class="flex items-center justify-between gap-3">
                <label class="inline-flex min-h-9 cursor-pointer items-center gap-2 text-sm text-[#2C3E3A]">
                  <input
                    v-model="remember"
                    type="checkbox"
                    class="login-checkbox h-[17px] w-[17px] shrink-0 appearance-none rounded-[4px] border border-[#C7CFC8] bg-white transition checked:border-[#064E3B] checked:bg-[#064E3B] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#064E3B]"
                  />
                  {{ t('auth.rememberMe') }}
                </label>
                <RouterLink
                  to="/forgot-password"
                  class="inline-flex min-h-9 items-center text-sm text-[#9AA19E] transition hover:text-[#064E3B] hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#C6A15B]"
                >
                  {{ t('auth.forgotPasswordLink') }}
                </RouterLink>
              </div>

              <p
                v-if="formError"
                class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800"
                role="alert"
              >
                {{ formError }}
              </p>

              <button
                type="submit"
                class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-full bg-gradient-to-l from-[#033D30] to-[#064E3B] px-4 text-sm font-semibold text-white shadow-[0_12px_28px_-10px_rgba(6,78,59,0.65)] transition hover:from-[#022F26] hover:to-[#053F30] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#064E3B] active:scale-[0.995] disabled:cursor-not-allowed disabled:opacity-60 sm:h-[52px] sm:text-base"
                :disabled="isLoggingIn"
                :aria-busy="isLoggingIn"
              >
                <span>{{ isLoggingIn ? t('auth.loggingIn') : t('auth.login') }}</span>
                <svg
                  v-if="!isLoggingIn"
                  class="h-4 w-4 rtl:rotate-180"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  aria-hidden="true"
                >
                  <path d="M5 12h14" />
                  <path d="m13 6 6 6-6 6" />
                </svg>
              </button>
            </form>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
@media (max-height: 720px) and (max-width: 1023px) {
  .login-grid {
    grid-template-rows: minmax(0, 22%) minmax(0, 78%);
  }

  .login-logo {
    height: 3.25rem;
  }

  .login-hero-card {
    padding-block: 0.65rem;
    padding-inline: 0.85rem;
  }

  .login-card {
    padding-block: 0.85rem;
  }
}

@media (max-height: 640px) and (max-width: 1023px) {
  .login-grid {
    grid-template-rows: minmax(0, 0) minmax(0, 100%);
  }

  .login-hero {
    display: none;
  }
}
</style>
