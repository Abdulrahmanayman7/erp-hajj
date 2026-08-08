<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

defineProps<{
  modelValue: string
  id: string
  label: string
  autocomplete?: string
  error?: string
}>()

defineEmits<{
  'update:modelValue': [value: string]
}>()

const { t } = useI18n()
const visible = ref(false)
</script>

<template>
  <div class="space-y-1.5">
    <label :for="id" class="block text-sm font-medium text-neutral-700">{{ label }}</label>
    <div class="relative">
      <input
        :id="id"
        :type="visible ? 'text' : 'password'"
        :value="modelValue"
        :autocomplete="autocomplete"
        class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-2.5 pe-12 text-sm text-neutral-900 outline-none focus:border-emerald-700 focus:ring-2 focus:ring-emerald-700/20"
        :aria-invalid="!!error"
        @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
      />
      <button
        type="button"
        class="absolute inset-e-2 top-1/2 -translate-y-1/2 rounded px-2 py-1 text-xs text-neutral-600 hover:bg-neutral-100"
        :aria-label="visible ? t('auth.hidePassword') : t('auth.showPassword')"
        @click="visible = !visible"
      >
        {{ visible ? t('auth.hidePassword') : t('auth.showPassword') }}
      </button>
    </div>
    <p v-if="error" class="text-sm text-red-700" role="alert">{{ error }}</p>
  </div>
</template>
