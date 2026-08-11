<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { AlertTriangle, Loader2, X } from 'lucide-vue-next'

import type { LifecycleReasonFormState } from '../types/assets'

const props = defineProps<{
  open: boolean
  kind: 'retire' | 'declare_lost' | null
  form: LifecycleReasonFormState
  formError: string
  fieldErrors: Record<string, string>
  submitting: boolean
}>()

const emit = defineEmits<{
  close: []
  submit: []
  'update:form': [LifecycleReasonFormState]
}>()

const { t } = useI18n()

const title = computed(() =>
  props.kind === 'declare_lost' ? t('assets.lifecycle.lostTitle') : t('assets.lifecycle.retireTitle'),
)
const subtitle = computed(() =>
  props.kind === 'declare_lost'
    ? t('assets.lifecycle.lostSubtitle')
    : t('assets.lifecycle.retireSubtitle'),
)
const confirmLabel = computed(() =>
  props.kind === 'declare_lost' ? t('assets.actions.declareLost') : t('assets.actions.retire'),
)

function patch(part: Partial<LifecycleReasonFormState>): void {
  emit('update:form', { ...props.form, ...part })
}
</script>

<template>
  <Teleport to="body">
    <div v-if="open && kind" class="fixed inset-0 z-50 flex items-center justify-center p-4" role="presentation">
      <div class="absolute inset-0 bg-black/30" @click="emit('close')" />
      <div
        class="relative z-10 w-full max-w-lg overflow-hidden rounded-2xl bg-brand-surface shadow-xl"
        role="dialog"
        aria-modal="true"
        @click.stop
      >
        <header class="flex items-start justify-between border-b border-brand-border bg-red-50/60 px-5 py-4">
          <div>
            <h3 class="flex items-center gap-2 text-lg font-bold text-brand-text">
              <AlertTriangle class="h-5 w-5 text-red-700" />
              {{ title }}
            </h3>
            <p class="mt-1 text-sm text-brand-text-secondary">{{ subtitle }}</p>
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
            <span class="text-sm font-medium">{{ t('assets.fields.reason') }}</span>
            <textarea
              :value="form.reason"
              required
              rows="4"
              class="mt-1 w-full rounded-xl border border-brand-border px-3 py-2 text-sm"
              @input="patch({ reason: ($event.target as HTMLTextAreaElement).value })"
            />
            <p v-if="fieldErrors.reason" class="mt-1 text-xs text-red-600">
              {{ t(`assets.validation.${fieldErrors.reason}`) }}
            </p>
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
              class="inline-flex h-11 items-center gap-2 rounded-xl bg-red-700 px-4 text-sm font-semibold text-white disabled:opacity-60"
              :disabled="submitting"
            >
              <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
              {{ confirmLabel }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </Teleport>
</template>
