<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Loader2, X } from 'lucide-vue-next'

import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'

import type { ComplaintCategory, ComplaintFormState, OperationalComplaint } from '../types/complaints'
import { COMPLAINT_CATEGORIES } from '../types/complaints'
import OperationalLinksFields from './OperationalLinksFields.vue'

const props = defineProps<{
  open: boolean
  editing: OperationalComplaint | null
  form: ComplaintFormState
  formError: string
  fieldErrors: Record<string, string>
  submitting: boolean
}>()

const emit = defineEmits<{
  close: []
  submit: []
  'update:form': [ComplaintFormState]
}>()

const { t } = useI18n()
const isEdit = computed(() => props.editing != null)
const patch = (part: Partial<ComplaintFormState>) => emit('update:form', { ...props.form, ...part })

const categoryOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('complaints.fields.selectCategory') },
  ...COMPLAINT_CATEGORIES.map((c) => ({ value: c, label: t(`complaints.category.${c}`) })),
])
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-50" role="presentation">
      <div class="absolute inset-0 bg-black/30" @click="emit('close')" />
      <aside
        class="app-drawer-panel absolute inset-y-0 start-0 flex w-full max-w-[540px] flex-col bg-brand-surface shadow-xl"
        role="dialog"
        aria-modal="true"
        data-testid="complaint-form-drawer"
        v-autofocus-when
        @click.stop
      >
        <header class="flex items-start justify-between border-b border-brand-border px-4 py-5 sm:px-6">
          <div>
            <h3 class="text-lg font-bold text-brand-text">
              {{ t(isEdit ? 'complaints.editTitle' : 'complaints.createTitle') }}
            </h3>
            <p class="mt-1 text-sm text-brand-text-secondary">
              {{ t(isEdit ? 'complaints.editSubtitle' : 'complaints.createSubtitle') }}
            </p>
          </div>
          <button
            type="button"
            class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-brand-border"
            :disabled="submitting"
            @click="emit('close')"
          >
            <X class="h-4 w-4" />
          </button>
        </header>

        <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="emit('submit')">
          <div class="flex-1 space-y-5 overflow-y-auto px-4 py-6 sm:px-6">
            <label class="block">
              <span class="mb-1.5 block text-sm font-semibold">{{ t('complaints.fields.title') }}</span>
              <input
                :value="form.title"
                required
                class="h-11 w-full rounded-xl border border-brand-border px-3 text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
                data-testid="complaint-title-input"
                @input="patch({ title: ($event.target as HTMLInputElement).value })"
              />
              <p v-if="fieldErrors.title" class="mt-1 text-xs text-red-600">
                {{ t(`complaints.validation.${fieldErrors.title}`) }}
              </p>
            </label>

            <label class="block">
              <span class="mb-1.5 block text-sm font-semibold">{{ t('complaints.fields.category') }}</span>
              <AppSelect
                :model-value="form.category_code"
                :options="categoryOptions"
                @update:model-value="
                  patch({ category_code: String($event || '') as ComplaintCategory | '' })
                "
              />
              <p v-if="fieldErrors.category_code" class="mt-1 text-xs text-red-600">
                {{ t(`complaints.validation.${fieldErrors.category_code}`) }}
              </p>
            </label>

            <label class="block">
              <span class="mb-1.5 block text-sm font-semibold">{{ t('complaints.fields.body') }}</span>
              <textarea
                :value="form.body"
                required
                rows="5"
                class="w-full rounded-xl border border-brand-border p-3 text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15"
                data-testid="complaint-body-input"
                @input="patch({ body: ($event.target as HTMLTextAreaElement).value })"
              />
              <p v-if="fieldErrors.body" class="mt-1 text-xs text-red-600">
                {{ t(`complaints.validation.${fieldErrors.body}`) }}
              </p>
            </label>

            <OperationalLinksFields
              :model-value="form"
              :disabled="submitting"
              @update:model-value="emit('update:form', { ...form, ...$event })"
            />

            <p
              v-if="formError"
              class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
              role="alert"
            >
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
              @click="emit('close')"
            >
              {{ t('complaints.cancel') }}
            </button>
            <button
              type="submit"
              class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white disabled:opacity-50"
              :disabled="submitting"
              data-testid="complaint-form-submit"
            >
              <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
              {{ t(isEdit ? 'complaints.save' : 'complaints.createCta') }}
            </button>
          </footer>
        </form>
      </aside>
    </div>
  </Teleport>
</template>
