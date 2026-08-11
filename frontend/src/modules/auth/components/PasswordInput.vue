<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

withDefaults(
  defineProps<{
    modelValue: string
    id: string
    label: string
    autocomplete?: string
    error?: string
    /** Taller enterprise field used on the redesigned login form. */
    size?: 'md' | 'lg'
    showLockIcon?: boolean
  }>(),
  {
    size: 'md',
    showLockIcon: false,
  },
)

defineEmits<{
  'update:modelValue': [value: string]
}>()

const { t } = useI18n()
const visible = ref(false)
</script>

<template>
  <div>
    <label :for="id" class="mb-1.5 block text-sm font-medium text-[#17352E]">{{ label }}</label>
    <div class="relative">
      <span
        v-if="showLockIcon"
        class="pointer-events-none absolute inset-s-3.5 top-1/2 -translate-y-1/2 text-[#065A46]/50"
        aria-hidden="true"
      >
        <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
          <rect x="5" y="11" width="14" height="10" rx="2" />
          <path d="M8 11V8a4 4 0 0 1 8 0v3" />
        </svg>
      </span>
      <input
        :id="id"
        :type="visible ? 'text' : 'password'"
        :value="modelValue"
        :autocomplete="autocomplete"
        class="login-field w-full rounded-2xl border border-[#E3E5DF] bg-white text-sm text-[#17352E] outline-none transition focus:border-[#064E3B] focus:ring-2 focus:ring-[#064E3B]/15 disabled:opacity-60"
        :class="[
          size === 'lg' ? 'h-12 text-sm sm:h-[52px] sm:text-[0.95rem]' : 'h-11',
          showLockIcon ? 'ps-11' : 'ps-3',
          'pe-12',
        ]"
        :aria-invalid="!!error"
        :aria-describedby="error ? `${id}-error` : undefined"
        @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
      />
      <button
        type="button"
        class="absolute inset-e-2 top-1/2 inline-flex h-9 min-w-9 -translate-y-1/2 items-center justify-center rounded-lg text-[#73827E] transition hover:bg-[#065A46]/5 hover:text-[#065A46] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#065A46]"
        :aria-label="visible ? t('auth.hidePassword') : t('auth.showPassword')"
        @click="visible = !visible"
      >
        <svg v-if="!visible" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
          <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
          <circle cx="12" cy="12" r="3" />
        </svg>
        <svg v-else class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
          <path d="M3 3l18 18" />
          <path d="M10.6 10.6A3 3 0 0 0 12 15a3 3 0 0 0 2.4-1.2" />
          <path d="M9.9 5.1A10.8 10.8 0 0 1 12 5c6.5 0 10 7 10 7a17.6 17.6 0 0 1-4.2 4.8" />
          <path d="M6.1 6.1C3.9 7.8 2 12 2 12s3.5 7 10 7c1.4 0 2.7-.3 3.9-.8" />
        </svg>
      </button>
    </div>
    <p v-if="error" :id="`${id}-error`" class="mt-2 text-sm text-red-700" role="alert">{{ error }}</p>
  </div>
</template>
