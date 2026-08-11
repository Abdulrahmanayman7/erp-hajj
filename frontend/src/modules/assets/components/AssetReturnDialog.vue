<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Loader2, X } from 'lucide-vue-next'

import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'

import type { ReturnCustodyFormState } from '../types/assets'
import { ASSET_CONDITIONS, RETURN_NEXT_STATUSES } from '../types/assets'

const props = defineProps<{
  open: boolean
  form: ReturnCustodyFormState
  formError: string
  fieldErrors: Record<string, string>
  submitting: boolean
}>()

const emit = defineEmits<{
  close: []
  submit: []
  'update:form': [ReturnCustodyFormState]
}>()

const { t } = useI18n()

const nextStatusOptions = computed<AppSelectOption[]>(() =>
  RETURN_NEXT_STATUSES.map((status) => ({
    value: status,
    label: t(`assets.status.${status}`),
  })),
)

const conditionOptions = computed<AppSelectOption[]>(() =>
  ASSET_CONDITIONS.map((condition) => ({
    value: condition,
    label: t(`assets.condition.${condition}`),
  })),
)

function patch(part: Partial<ReturnCustodyFormState>): void {
  emit('update:form', { ...props.form, ...part })
}
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4" role="presentation">
      <div class="absolute inset-0 bg-black/30" @click="emit('close')" />
      <div
        class="relative z-10 w-full max-w-lg overflow-hidden rounded-2xl bg-brand-surface shadow-xl"
        role="dialog"
        aria-modal="true"
        @click.stop
      >
        <header class="flex items-start justify-between border-b border-brand-border px-5 py-4">
          <div>
            <h3 class="text-lg font-bold text-brand-text">{{ t('assets.return.title') }}</h3>
            <p class="mt-1 text-sm text-brand-text-secondary">{{ t('assets.return.subtitle') }}</p>
          </div>
          <button
            type="button"
            class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-brand-text-muted hover:bg-brand-bg"
            :disabled="submitting"
            :aria-label="t('assets.closeDrawer')"
            @click="emit('close')"
          >
            <X class="h-4 w-4" />
          </button>
        </header>

        <form class="space-y-4 px-5 py-5" @submit.prevent="emit('submit')">
          <label class="block">
            <span class="text-sm font-medium">{{ t('assets.fields.nextStatus') }}</span>
            <AppSelect
              class="mt-1"
              :model-value="form.next_status"
              :options="nextStatusOptions"
              @update:model-value="patch({ next_status: $event as ReturnCustodyFormState['next_status'] })"
            />
            <p v-if="fieldErrors.next_status" class="mt-1 text-xs text-red-600">
              {{ t(`assets.validation.${fieldErrors.next_status}`) }}
            </p>
          </label>

          <label class="block">
            <span class="text-sm font-medium">{{ t('assets.fields.conditionAtReturn') }}</span>
            <AppSelect
              class="mt-1"
              :model-value="form.condition_at_return"
              :options="conditionOptions"
              @update:model-value="patch({ condition_at_return: $event as ReturnCustodyFormState['condition_at_return'] })"
            />
          </label>

          <label class="block">
            <span class="text-sm font-medium">{{ t('assets.fields.returnNotes') }}</span>
            <textarea
              :value="form.return_notes"
              rows="3"
              class="mt-1 w-full rounded-xl border border-brand-border px-3 py-2 text-sm"
              @input="patch({ return_notes: ($event.target as HTMLTextAreaElement).value })"
            />
          </label>

          <p v-if="formError" class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            {{ formError }}
          </p>

          <div class="flex justify-end gap-2 pt-2">
            <button type="button" class="h-11 rounded-xl border px-4 text-sm font-semibold" :disabled="submitting" @click="emit('close')">
              {{ t('assets.cancel') }}
            </button>
            <button
              type="submit"
              class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white disabled:opacity-60"
              :disabled="submitting"
            >
              <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
              {{ t('assets.actions.return') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </Teleport>
</template>
