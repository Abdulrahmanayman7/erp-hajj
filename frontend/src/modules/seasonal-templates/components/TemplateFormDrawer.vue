<script setup lang="ts">
import { computed, nextTick, onUnmounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Loader2, X } from 'lucide-vue-next'

import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'

import { APPROVED_ENTITY_KINDS, SNAPSHOT_ONLY_KINDS } from '../types/seasonCopy'
import type { TemplateFormState } from '../types/seasonTemplates'
import { filterApprovedEntityKinds, snapshotOnlySelected } from '../validation/seasonTemplateValidation'
import EntityKindCheckboxes from './EntityKindCheckboxes.vue'

const props = defineProps<{
  open: boolean
  editing: boolean
  form: TemplateFormState
  formError: string
  fieldErrors: Record<string, string>
  submitting: boolean
  /** Seasons the user may capture snapshots from (loaded by the page; empty hides the picker). */
  captureSeasons?: AppSelectOption[]
}>()

const emit = defineEmits<{
  close: []
  submit: []
  'update:form': [TemplateFormState]
}>()

const { t } = useI18n()

const title = computed(() =>
  props.editing ? t('seasonalTemplates.form.editTitle') : t('seasonalTemplates.form.createTitle'),
)
const selectedKinds = computed(() => filterApprovedEntityKinds(props.form.entity_kinds))

const captureOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('seasonalTemplates.form.captureNone') },
  ...(props.captureSeasons ?? []),
])
const showCapture = computed(() => (props.captureSeasons?.length ?? 0) > 0)
const snapshotOnlyKinds = computed(() => snapshotOnlySelected(selectedKinds.value))
/**
 * Snapshot-only kinds cannot be captured from a season, so a brand-new template cannot offer them.
 * While editing they stay selectable so an existing stored snapshot is kept (server-side).
 */
const availableKinds = computed<readonly string[]>(() =>
  props.editing ? APPROVED_ENTITY_KINDS : APPROVED_ENTITY_KINDS.filter((kind) => !SNAPSHOT_ONLY_KINDS.includes(kind)),
)

function onCaptureSeason(value: string | number | null): void {
  patch({ capture_season_id: value === '' || value == null ? '' : Number(value) })
}

function patch(partial: Partial<TemplateFormState>): void {
  emit('update:form', { ...props.form, ...partial })
}

function fieldMessage(key: string): string {
  const code = props.fieldErrors[key]
  return code ? t(`seasonalTemplates.validation.${code}`) : ''
}

function onKinds(kinds: string[]): void {
  patch({ entity_kinds: kinds })
}

function onCodes(kind: string, value: string): void {
  patch({ codes: { ...props.form.codes, [kind]: value } })
}

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape' && props.open && !props.submitting) {
    event.preventDefault()
    emit('close')
  }
}

watch(
  () => props.open,
  async (isOpen) => {
    if (isOpen) {
      document.addEventListener('keydown', onKeydown)
      await nextTick()
      return
    }
    document.removeEventListener('keydown', onKeydown)
  },
)

onUnmounted(() => document.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-50" role="presentation" data-testid="template-form-drawer">
      <div class="absolute inset-0 bg-[rgba(15,23,20,0.32)]" aria-hidden="true" @click="emit('close')" />
      <aside
        v-autofocus-when
        class="absolute inset-y-0 start-0 flex h-dvh w-full max-w-[520px] flex-col bg-brand-surface shadow-[-12px_0_40px_-24px_rgba(23,32,29,0.35)]"
        role="dialog"
        aria-modal="true"
        :aria-label="title"
        @click.stop
      >
        <header class="flex shrink-0 items-start justify-between gap-4 border-b border-brand-border px-4 py-5 sm:px-7">
          <div class="min-w-0 text-start">
            <h3 class="text-[21px] font-bold leading-tight text-brand-text">{{ title }}</h3>
            <p class="mt-1.5 text-[13px] leading-relaxed text-brand-text-secondary">
              {{ t('seasonalTemplates.form.subtitle') }}
            </p>
          </div>
          <button
            type="button"
            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] text-brand-text-secondary transition hover:bg-brand-bg hover:text-brand-text"
            :aria-label="t('seasonalTemplates.common.close')"
            :disabled="submitting"
            @click="emit('close')"
          >
            <X class="h-5 w-5" />
          </button>
        </header>

        <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="emit('submit')">
          <div class="flex-1 space-y-4 overflow-y-auto px-4 py-5 sm:px-7">
            <p
              v-if="formError"
              class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
              role="alert"
            >
              {{ formError }}
            </p>

            <label class="block space-y-1.5 text-start">
              <span class="text-sm font-semibold text-brand-text">{{ t('seasonalTemplates.form.name') }}</span>
              <input
                :value="form.name"
                type="text"
                maxlength="200"
                class="h-11 w-full rounded-xl border border-brand-border px-3 text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg"
                :disabled="submitting"
                data-testid="template-name-input"
                @input="patch({ name: ($event.target as HTMLInputElement).value })"
              />
              <span v-if="fieldMessage('name')" class="text-xs text-red-600">{{ fieldMessage('name') }}</span>
            </label>

            <label class="block space-y-1.5 text-start">
              <span class="text-sm font-semibold text-brand-text">{{ t('seasonalTemplates.form.description') }}</span>
              <textarea
                :value="form.description"
                rows="3"
                maxlength="5000"
                class="w-full rounded-xl border border-brand-border px-3 py-2 text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg"
                :disabled="submitting"
                @input="patch({ description: ($event.target as HTMLTextAreaElement).value })"
              />
              <span v-if="fieldMessage('description')" class="text-xs text-red-600">{{ fieldMessage('description') }}</span>
            </label>

            <div class="space-y-2 text-start">
              <p class="text-sm font-semibold text-brand-text">{{ t('seasonalTemplates.form.entityKinds') }}</p>
              <p class="text-xs text-brand-text-muted">{{ t('seasonalTemplates.form.entityKindsHint') }}</p>
              <EntityKindCheckboxes
                :model-value="form.entity_kinds"
                :available="availableKinds"
                :disabled="submitting"
                :error-message="fieldMessage('entity_kinds')"
                @update:model-value="onKinds"
              />
            </div>

            <div v-if="showCapture" class="space-y-1.5 text-start" data-testid="template-capture">
              <p class="text-sm font-semibold text-brand-text">{{ t('seasonalTemplates.form.captureSeason') }}</p>
              <p class="text-xs text-brand-text-muted">
                {{ editing ? t('seasonalTemplates.form.captureHintEdit') : t('seasonalTemplates.form.captureHint') }}
              </p>
              <AppSelect
                :model-value="form.capture_season_id"
                :options="captureOptions"
                searchable
                :disabled="submitting"
                @update:model-value="onCaptureSeason"
              />
            </div>

            <p
              v-if="snapshotOnlyKinds.length"
              class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-start text-xs text-amber-900"
              data-testid="template-snapshot-only-hint"
            >
              {{ t('seasonalTemplates.form.snapshotOnlyHint') }}
            </p>

            <div v-if="selectedKinds.length" class="space-y-3 text-start">
              <p class="text-sm font-semibold text-brand-text">{{ t('seasonalTemplates.form.codes') }}</p>
              <p class="text-xs text-brand-text-muted">{{ t('seasonalTemplates.form.codesHint') }}</p>
              <label v-for="kind in selectedKinds" :key="kind" class="block space-y-1">
                <span class="text-xs font-semibold text-brand-text-secondary">
                  {{ t(`seasonalTemplates.entityKinds.${kind}.label`) }}
                </span>
                <textarea
                  :value="form.codes[kind] ?? ''"
                  rows="2"
                  dir="ltr"
                  class="w-full rounded-xl border border-brand-border px-3 py-2 text-start text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg"
                  :placeholder="t('seasonalTemplates.form.codesPlaceholder')"
                  :disabled="submitting"
                  @input="onCodes(kind, ($event.target as HTMLTextAreaElement).value)"
                />
              </label>
            </div>
          </div>

          <footer class="flex shrink-0 items-center justify-end gap-3 border-t border-brand-border px-4 py-4 sm:px-7">
            <button
              type="button"
              class="inline-flex h-11 items-center justify-center rounded-xl border border-brand-border px-4 text-sm font-semibold"
              :disabled="submitting"
              @click="emit('close')"
            >
              {{ t('seasonalTemplates.common.cancel') }}
            </button>
            <button
              type="submit"
              class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-brand-primary-dark px-4 text-sm font-semibold text-white disabled:opacity-60"
              :disabled="submitting"
              data-testid="template-submit-btn"
            >
              <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
              <span>{{ editing ? t('seasonalTemplates.form.saveCta') : t('seasonalTemplates.form.createCta') }}</span>
            </button>
          </footer>
        </form>
      </aside>
    </div>
  </Teleport>
</template>
