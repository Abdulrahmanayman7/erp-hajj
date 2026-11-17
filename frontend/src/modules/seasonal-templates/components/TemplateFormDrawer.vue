<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

import AppFormDrawer from '@/shared/components/AppFormDrawer.vue'
import { useCaptureOnOpen, useFormDirty } from '@/shared/composables/useFormDirty'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'

import { APPROVED_ENTITY_KINDS } from '../types/seasonCopy'
import type { ContractDefinitionRowForm, TemplateFormState } from '../types/seasonTemplates'
import { filterApprovedEntityKinds } from '../validation/seasonTemplateValidation'
import ContractDefinitionRowsEditor from './ContractDefinitionRowsEditor.vue'
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
const primaryLabel = computed(() =>
  props.editing ? t('seasonalTemplates.form.saveCta') : t('seasonalTemplates.form.createCta'),
)
const selectedKinds = computed(() => filterApprovedEntityKinds(props.form.entity_kinds))

const captureOptions = computed<AppSelectOption[]>(() => [
  { value: '', label: t('seasonalTemplates.form.captureNone') },
  ...(props.captureSeasons ?? []),
])
const showCapture = computed(() => (props.captureSeasons?.length ?? 0) > 0)
const hasContractKind = computed(() => selectedKinds.value.includes('contract_definitions'))
const hasReminderKind = computed(() => selectedKinds.value.includes('season_reminder_rules'))
/** All approved kinds are selectable; contract definitions are entered as snapshot rows in the editor below. */
const availableKinds: readonly string[] = APPROVED_ENTITY_KINDS

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

function onContractRows(rows: ContractDefinitionRowForm[]): void {
  patch({ contract_rows: rows })
}

function onContractAnchor(anchor: string): void {
  patch({ contract_anchor: anchor })
}

function onCodes(kind: string, value: string): void {
  patch({ codes: { ...props.form.codes, [kind]: value } })
}

const { dirty, capture, reset: resetDirty } = useFormDirty(() => props.form)
useCaptureOnOpen(() => props.open, capture, { onClose: () => resetDirty() })

function onOpenUpdate(value: boolean): void {
  if (!value) emit('close')
}
</script>

<template>
  <AppFormDrawer
    :open="open"
    :title="title"
    :description="t('seasonalTemplates.form.subtitle')"
    size="md"
    :dirty="dirty"
    :submitting="submitting"
    :cancel-label="t('seasonalTemplates.common.cancel')"
    :submit-label="primaryLabel"
    @update:open="onOpenUpdate"
    @close="emit('close')"
    @submit="emit('submit')"
  >
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
      v-if="hasContractKind"
      class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-start text-xs text-amber-900"
      data-testid="template-snapshot-only-hint"
    >
      {{ t('seasonalTemplates.form.snapshotOnlyHint') }}
    </p>

    <ContractDefinitionRowsEditor
      v-if="hasContractKind"
      :rows="form.contract_rows"
      :anchor="form.contract_anchor"
      :disabled="submitting"
      :editing="editing"
      :show-errors="!!fieldErrors.contract_rows"
      @update:rows="onContractRows"
      @update:anchor="onContractAnchor"
    />
    <p v-if="fieldMessage('contract_rows')" class="text-start text-xs text-red-600" role="alert">
      {{ fieldMessage('contract_rows') }}
    </p>

    <p
      v-if="hasReminderKind"
      class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-start text-xs text-amber-900"
      data-testid="template-reminder-hint"
    >
      {{ t('seasonalTemplates.form.reminderRulesHint') }}
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
  </AppFormDrawer>
</template>
