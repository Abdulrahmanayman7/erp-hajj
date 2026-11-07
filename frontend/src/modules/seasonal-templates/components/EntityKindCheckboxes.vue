<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

import { APPROVED_ENTITY_KINDS } from '../types/seasonCopy'
import { filterApprovedEntityKinds } from '../validation/seasonTemplateValidation'

const props = withDefaults(
  defineProps<{
    modelValue: string[]
    /** Restricts the choices (e.g. to a template's kinds). Always intersected with the approved list. */
    available?: readonly string[]
    disabled?: boolean
    errorMessage?: string
  }>(),
  { available: () => APPROVED_ENTITY_KINDS, disabled: false, errorMessage: '' },
)

const emit = defineEmits<{ 'update:modelValue': [value: string[]] }>()

const { t } = useI18n()

const kinds = computed(() => filterApprovedEntityKinds(props.available))

function toggle(kind: string, checked: boolean): void {
  const next = checked
    ? [...new Set([...props.modelValue, kind])]
    : props.modelValue.filter((item) => item !== kind)
  emit('update:modelValue', filterApprovedEntityKinds(next))
}
</script>

<template>
  <fieldset class="space-y-2" data-testid="entity-kind-checkboxes">
    <div class="grid gap-2 sm:grid-cols-2">
      <label
        v-for="kind in kinds"
        :key="kind"
        class="flex cursor-pointer items-start gap-3 rounded-xl border border-brand-border bg-brand-surface p-3 text-start text-sm transition hover:border-brand-primary/60"
        :class="{ 'opacity-60': disabled }"
      >
        <input
          type="checkbox"
          class="mt-0.5 h-4 w-4 rounded border-brand-border accent-brand-primary"
          :checked="modelValue.includes(kind)"
          :disabled="disabled"
          :data-testid="`entity-kind-${kind}`"
          @change="toggle(kind, ($event.target as HTMLInputElement).checked)"
        />
        <span class="min-w-0">
          <span class="block font-semibold text-brand-text">
            {{ t(`seasonalTemplates.entityKinds.${kind}.label`) }}
          </span>
          <span class="mt-0.5 block text-xs text-brand-text-muted">
            {{ t(`seasonalTemplates.entityKinds.${kind}.hint`) }}
          </span>
        </span>
      </label>
    </div>
    <p v-if="errorMessage" class="text-xs text-red-600" role="alert">{{ errorMessage }}</p>
  </fieldset>
</template>
