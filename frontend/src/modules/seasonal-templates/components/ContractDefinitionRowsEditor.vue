<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Plus, Trash2 } from 'lucide-vue-next'

import { useCategoriesQuery } from '@/modules/contracts/queries/useCategoriesQuery'
import AppDateInput from '@/shared/components/AppDateInput.vue'
import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'

import {
  CONTRACT_COUNTERPARTY_KINDS,
  type ContractCounterpartyKind,
  type ContractDefinitionRowForm,
} from '../types/seasonTemplates'
import {
  MAX_CONTRACT_ROWS,
  emptyContractRow,
  validateContractRows,
} from '../validation/contractDefinitionRows'

const props = withDefaults(
  defineProps<{
    rows: ContractDefinitionRowForm[]
    anchor: string
    disabled?: boolean
    /** True when editing a template: an empty editor keeps the stored snapshot. */
    editing?: boolean
    /** Show field errors for every row (set after a failed submit). Duplicate codes always show. */
    showErrors?: boolean
  }>(),
  { disabled: false, editing: false, showErrors: false },
)

const emit = defineEmits<{
  'update:rows': [rows: ContractDefinitionRowForm[]]
  'update:anchor': [anchor: string]
}>()

const { t } = useI18n()

const categoriesQuery = useCategoriesQuery({ is_active: true, per_page: 100 })

const categoryOptions = computed<AppSelectOption[]>(() =>
  (categoriesQuery.data.value?.data ?? []).map((category) => ({
    value: category.id,
    label: category.name,
  })),
)
const categoriesLoading = computed(() => categoriesQuery.isLoading.value)
const categoriesError = computed(() => categoriesQuery.isError.value)
const categoriesEmpty = computed(
  () => !categoriesLoading.value && !categoriesError.value && categoryOptions.value.length === 0,
)

const validation = computed(() => validateContractRows(props.rows, props.anchor))
const canAdd = computed(() => !props.disabled && props.rows.length < MAX_CONTRACT_ROWS)

function message(code: string | undefined, always = false): string {
  if (!code) return ''
  if (!props.showErrors && !(always && code === 'duplicate_code')) return ''
  return t(`seasonalTemplates.validation.${code}`)
}

function rowError(index: number, field: string): string {
  return message(validation.value.rows[index]?.[field], true)
}

function addRow(): void {
  emit('update:rows', [...props.rows, emptyContractRow()])
}

function removeRow(index: number): void {
  emit(
    'update:rows',
    props.rows.filter((_, i) => i !== index),
  )
}

function patchRow(index: number, partial: Partial<ContractDefinitionRowForm>): void {
  emit(
    'update:rows',
    props.rows.map((row, i) => (i === index ? { ...row, ...partial } : row)),
  )
}

function onCategory(index: number, value: string | number | null): void {
  patchRow(index, { contract_category_id: value === null || value === '' ? '' : Number(value) })
}

const inputClass =
  'h-10 w-full rounded-xl border border-brand-border px-3 text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg'
</script>

<template>
  <section class="space-y-3 text-start" data-testid="contract-rows-editor">
    <div>
      <p class="text-sm font-semibold text-brand-text">{{ t('seasonalTemplates.contractRows.title') }}</p>
      <p class="mt-0.5 text-xs text-brand-text-muted">{{ t('seasonalTemplates.contractRows.hint') }}</p>
      <p v-if="editing" class="mt-0.5 text-xs text-amber-800" data-testid="contract-rows-editing-hint">
        {{ t('seasonalTemplates.contractRows.editingHint') }}
      </p>
    </div>

    <p v-if="categoriesLoading" class="text-xs text-brand-text-muted" data-testid="contract-categories-loading">
      {{ t('seasonalTemplates.contractRows.categoriesLoading') }}
    </p>
    <p
      v-else-if="categoriesError"
      class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700"
      role="alert"
      data-testid="contract-categories-error"
    >
      {{ t('seasonalTemplates.contractRows.categoriesError') }}
    </p>
    <p
      v-else-if="categoriesEmpty"
      class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900"
      data-testid="contract-categories-empty"
    >
      {{ t('seasonalTemplates.contractRows.categoriesEmpty') }}
    </p>

    <div v-if="rows.length > 0" class="space-y-1">
      <label class="block space-y-1">
        <span class="text-xs font-semibold text-brand-text-secondary">
          {{ t('seasonalTemplates.contractRows.anchor') }}
        </span>
        <AppDateInput
          :model-value="anchor"
          :disabled="disabled"
          :invalid="!!message(validation.anchor)"
          @update:model-value="emit('update:anchor', $event)"
        />
      </label>
      <p class="text-xs text-brand-text-muted">{{ t('seasonalTemplates.contractRows.anchorHint') }}</p>
      <p v-if="message(validation.anchor)" class="text-xs text-red-600" data-testid="contract-anchor-error">
        {{ message(validation.anchor) }}
      </p>
    </div>

    <p
      v-if="rows.length === 0"
      class="rounded-xl border border-dashed border-brand-border px-3 py-4 text-center text-xs text-brand-text-muted"
      data-testid="contract-rows-empty"
    >
      {{ t('seasonalTemplates.contractRows.empty') }}
    </p>

    <ul v-else class="space-y-3">
      <li
        v-for="(row, index) in rows"
        :key="index"
        class="space-y-3 rounded-xl border border-brand-border bg-brand-surface p-3"
        :data-testid="`contract-row-${index}`"
      >
        <div class="flex items-center justify-between gap-2">
          <p class="text-xs font-bold text-brand-text">
            {{ t('seasonalTemplates.contractRows.rowTitle', { n: index + 1 }) }}
          </p>
          <button
            type="button"
            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-brand-text-secondary transition hover:bg-red-50 hover:text-red-700 disabled:opacity-50"
            :aria-label="t('seasonalTemplates.contractRows.remove')"
            :disabled="disabled"
            :data-testid="`contract-row-remove-${index}`"
            @click="removeRow(index)"
          >
            <Trash2 class="h-4 w-4" />
          </button>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
          <label class="block space-y-1">
            <span class="text-xs font-semibold text-brand-text-secondary">
              {{ t('seasonalTemplates.contractRows.rowTitleLabel') }}
            </span>
            <input
              :value="row.title"
              type="text"
              maxlength="255"
              :class="inputClass"
              :disabled="disabled"
              :data-testid="`contract-row-title-${index}`"
              @input="patchRow(index, { title: ($event.target as HTMLInputElement).value })"
            />
            <span v-if="rowError(index, 'title')" class="text-xs text-red-600">{{ rowError(index, 'title') }}</span>
          </label>

          <label class="block space-y-1">
            <span class="text-xs font-semibold text-brand-text-secondary">
              {{ t('seasonalTemplates.contractRows.code') }}
            </span>
            <input
              :value="row.code"
              type="text"
              dir="ltr"
              maxlength="255"
              :class="[inputClass, 'text-start']"
              :disabled="disabled"
              :data-testid="`contract-row-code-${index}`"
              @input="patchRow(index, { code: ($event.target as HTMLInputElement).value })"
            />
            <span
              v-if="rowError(index, 'code')"
              class="text-xs text-red-600"
              :data-testid="`contract-row-code-error-${index}`"
            >
              {{ rowError(index, 'code') }}
            </span>
          </label>

          <div class="space-y-1">
            <span class="text-xs font-semibold text-brand-text-secondary">
              {{ t('seasonalTemplates.contractRows.category') }}
            </span>
            <AppSelect
              :model-value="row.contract_category_id === '' ? null : row.contract_category_id"
              :options="categoryOptions"
              :placeholder="t('seasonalTemplates.contractRows.categoryPlaceholder')"
              searchable
              :disabled="disabled"
              :error="!!rowError(index, 'contract_category_id')"
              @update:model-value="onCategory(index, $event)"
            />
            <span v-if="rowError(index, 'contract_category_id')" class="text-xs text-red-600">
              {{ rowError(index, 'contract_category_id') }}
            </span>
          </div>

          <label class="block space-y-1">
            <span class="text-xs font-semibold text-brand-text-secondary">
              {{ t('seasonalTemplates.contractRows.counterpartyKind') }}
            </span>
            <select
              :value="row.counterparty_kind"
              :class="inputClass"
              :disabled="disabled"
              :data-testid="`contract-row-kind-${index}`"
              @change="
                patchRow(index, {
                  counterparty_kind: ($event.target as HTMLSelectElement).value as ContractCounterpartyKind,
                })
              "
            >
              <option v-for="kind in CONTRACT_COUNTERPARTY_KINDS" :key="kind" :value="kind">
                {{ t(`seasonalTemplates.contractRows.counterpartyKinds.${kind}`) }}
              </option>
            </select>
          </label>

          <div class="space-y-1">
            <span class="text-xs font-semibold text-brand-text-secondary">
              {{ t('seasonalTemplates.contractRows.startDate') }}
            </span>
            <AppDateInput
              :model-value="row.start_date"
              :disabled="disabled"
              :invalid="!!rowError(index, 'start_date')"
              @update:model-value="patchRow(index, { start_date: $event })"
            />
            <span v-if="rowError(index, 'start_date')" class="text-xs text-red-600">
              {{ rowError(index, 'start_date') }}
            </span>
          </div>

          <div class="space-y-1">
            <span class="text-xs font-semibold text-brand-text-secondary">
              {{ t('seasonalTemplates.contractRows.endDate') }}
            </span>
            <AppDateInput
              :model-value="row.end_date"
              :disabled="disabled"
              :invalid="!!rowError(index, 'end_date')"
              @update:model-value="patchRow(index, { end_date: $event })"
            />
            <span v-if="rowError(index, 'end_date')" class="text-xs text-red-600">
              {{ rowError(index, 'end_date') }}
            </span>
          </div>

          <label class="block space-y-1">
            <span class="text-xs font-semibold text-brand-text-secondary">
              {{ t('seasonalTemplates.contractRows.currency') }}
            </span>
            <input
              :value="row.currency"
              type="text"
              dir="ltr"
              maxlength="3"
              placeholder="SAR"
              :class="[inputClass, 'text-start uppercase']"
              :disabled="disabled"
              :data-testid="`contract-row-currency-${index}`"
              @input="patchRow(index, { currency: ($event.target as HTMLInputElement).value })"
            />
            <span v-if="rowError(index, 'currency')" class="text-xs text-red-600">
              {{ rowError(index, 'currency') }}
            </span>
          </label>

          <label class="block space-y-1 sm:col-span-2">
            <span class="text-xs font-semibold text-brand-text-secondary">
              {{ t('seasonalTemplates.contractRows.notes') }}
            </span>
            <textarea
              :value="row.notes"
              rows="2"
              maxlength="5000"
              class="w-full rounded-xl border border-brand-border px-3 py-2 text-sm outline-none focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/15 disabled:bg-brand-bg"
              :disabled="disabled"
              @input="patchRow(index, { notes: ($event.target as HTMLTextAreaElement).value })"
            />
            <span v-if="rowError(index, 'notes')" class="text-xs text-red-600">{{ rowError(index, 'notes') }}</span>
          </label>
        </div>
      </li>
    </ul>

    <p v-if="message(validation.list)" class="text-xs text-red-600">{{ message(validation.list) }}</p>

    <button
      type="button"
      class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-brand-border px-3 text-sm font-semibold text-brand-text transition hover:border-brand-primary/60 disabled:opacity-50"
      :disabled="!canAdd"
      data-testid="contract-row-add"
      @click="addRow"
    >
      <Plus class="h-4 w-4" />
      <span>{{ t('seasonalTemplates.contractRows.add') }}</span>
    </button>
  </section>
</template>
