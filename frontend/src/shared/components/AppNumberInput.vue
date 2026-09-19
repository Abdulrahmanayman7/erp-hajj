<script setup lang="ts">
import { computed } from 'vue'

import { sanitizeNumberInput } from '@/shared/utils/numberInput'

defineOptions({ inheritAttrs: false })

const props = withDefaults(
  defineProps<{
    modelValue?: string | number | null
    integer?: boolean
    min?: number | string
    max?: number | string
    disabled?: boolean
    required?: boolean
    placeholder?: string
    id?: string
    name?: string
  }>(),
  {
    modelValue: '',
    integer: false,
    min: undefined,
    max: undefined,
    disabled: false,
    required: false,
    placeholder: undefined,
    id: undefined,
    name: undefined,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const unsigned = computed(() => props.min !== undefined && Number(props.min) >= 0)

const display = computed(() => {
  if (props.modelValue === null || props.modelValue === undefined) return ''
  return String(props.modelValue)
})

function onInput(event: Event): void {
  const el = event.target as HTMLInputElement
  const next = sanitizeNumberInput(el.value, {
    integer: props.integer,
    unsigned: unsigned.value,
  })
  el.value = next
  emit('update:modelValue', next)
}

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
    event.preventDefault()
  }
}
</script>

<template>
  <input
    :id="id"
    :name="name"
    :value="display"
    type="text"
    :inputmode="integer ? 'numeric' : 'decimal'"
    autocomplete="off"
    :disabled="disabled"
    :required="required"
    :placeholder="placeholder"
    :min="min"
    :max="max"
    v-bind="$attrs"
    @keydown="onKeydown"
    @input="onInput"
  />
</template>
