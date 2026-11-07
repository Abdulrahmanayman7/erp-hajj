<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Loader2, X } from 'lucide-vue-next'

import type { OperationalRisk, RiskFormState } from '../types/risks'
import OperationalLinksFields from './OperationalLinksFields.vue'

const props = defineProps<{
  open: boolean
  editing: OperationalRisk | null
  form: RiskFormState
  formError: string
  fieldErrors: Record<string, string>
  submitting: boolean
}>()

const emit = defineEmits<{
  close: []
  submit: []
  'update:form': [RiskFormState]
}>()

const { t } = useI18n()
const isEdit = computed(() => props.editing != null)
const patch = (part: Partial<RiskFormState>) => emit('update:form', { ...props.form, ...part })
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-50" role="presentation">
      <div class="absolute inset-0 bg-black/30" @click="emit('close')" />
      <aside
        class="app-drawer-panel absolute inset-y-0 start-0 flex w-full max-w-[540px] flex-col bg-brand-surface shadow-xl"
        role="dialog"
        aria-modal="true"
        data-testid="risk-form-drawer"
        v-autofocus-when
        @click.stop
      >
        <header class="flex items-start justify-between border-b border-brand-border px-4 py-5 sm:px-6">
          <div>
            <h3 class="text-lg font-bold text-brand-text">
              {{ t(isEdit ? 'risks.editTitle' : 'risks.createTitle') }}
            </h3>
            <p class="mt-1 text-sm text-brand-text-secondary">
              {{ t(isEdit ? 'risks.editSubtitle' : 'risks.createSubtitle') }}
            </p>
          </div>
          <button
            type="button"
            class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-brand-border"
            :disabled="submitting"
            :aria-label="t('risks.cancel')"
            @click="emit('close')"
          >
            <X class="h-4 w-4" />
          </button>
        </header>

        <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="emit('submit')">
          <div class="flex-1 space-y-5 overflow-y-auto px-4 py-6 sm:px-6">
            <label class="block">
              <span class="mb-1.5 block text-sm font-semibold text-brand-text">
                {{ t('risks.fields.title') }}
              </span>
              <input
                :value="form.title"
                required
                class="h-11 w-full rounded-xl border border-brand-border bg-brand-surface px-3 text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
                data-testid="risk-title-input"
                @input="patch({ title: ($event.target as HTMLInputElement).value })"
              />
              <p v-if="fieldErrors.title" class="mt-1 text-xs text-red-600">
                {{ t(`risks.validation.${fieldErrors.title}`) }}
              </p>
            </label>

            <label class="block">
              <span class="mb-1.5 block text-sm font-semibold text-brand-text">
                {{ t('risks.fields.description') }}
              </span>
              <textarea
                :value="form.description"
                rows="4"
                class="w-full rounded-xl border border-brand-border bg-brand-surface p-3 text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
                @input="patch({ description: ($event.target as HTMLTextAreaElement).value })"
              />
            </label>

            <OperationalLinksFields
              :model-value="form"
              :disabled="submitting"
              @update:model-value="emit('update:form', { ...form, ...$event })"
            />

            <p v-if="formError" class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">
              {{ formError }}
            </p>
          </div>

          <footer
            class="flex flex-col-reverse gap-2 border-t border-brand-border px-4 py-3 sm:flex-row sm:justify-end"
            style="padding-bottom: max(12px, env(safe-area-inset-bottom))"
          >
            <button
              type="button"
              class="inline-flex h-11 items-center justify-center rounded-xl border border-brand-border px-4 text-sm font-semibold"
              :disabled="submitting"
              @click="emit('close')"
            >
              {{ t('risks.cancel') }}
            </button>
            <button
              type="submit"
              class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white disabled:opacity-50"
              :disabled="submitting"
              data-testid="risk-form-submit"
            >
              <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
              {{ t(isEdit ? 'risks.save' : 'risks.createCta') }}
            </button>
          </footer>
        </form>
      </aside>
    </div>
  </Teleport>
</template>
