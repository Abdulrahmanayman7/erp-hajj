<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

import AppSelect, { type AppSelectOption } from '@/shared/components/AppSelect.vue'

import type { CopyDecision, SeasonCopyPreview, SeasonCopyPreviewLine } from '../types/seasonCopy'
import {
  allowedDecisions,
  conflictKey,
  dateTransformRows,
  formatSummaryValue,
  lineActionBadgeClass,
  unresolvedConflicts,
} from '../validation/seasonCopyValidation'

const props = defineProps<{
  preview: SeasonCopyPreview
  decisions: Record<string, CopyDecision | ''>
  /** Resolution selects are read-only while executing or when the user cannot execute. */
  disabled?: boolean
  canResolve?: boolean
}>()

const emit = defineEmits<{ 'update:decisions': [value: Record<string, CopyDecision | ''>] }>()

const { t, te } = useI18n()

const counts = computed(() => props.preview.counts ?? { add: 0, change: 0, exclude: 0, conflict: 0 })
const unresolved = computed(() => unresolvedConflicts(props.preview, props.decisions).length)

function kindLabel(kind: string): string {
  const key = `seasonalTemplates.entityKinds.${kind}.label`
  return te(key) ? t(key) : kind
}

function reasonLabel(code: string | undefined): string {
  if (!code) return ''
  const key = `seasonalTemplates.reasons.${code}`
  return te(key) ? t(key) : code
}

function conflictLabel(code: string | null): string {
  if (!code) return ''
  const key = `seasonalTemplates.conflictCodes.${code}`
  return te(key) ? t(key) : code
}

function decisionOptions(line: SeasonCopyPreviewLine): AppSelectOption[] {
  return [
    { value: '', label: t('seasonalTemplates.copy.decisionPlaceholder') },
    ...allowedDecisions(line).map((decision) => ({
      value: decision,
      label: t(`seasonalTemplates.copy.decision.${decision}`),
    })),
  ]
}

function setDecision(line: SeasonCopyPreviewLine, value: string | number | null): void {
  emit('update:decisions', {
    ...props.decisions,
    [conflictKey(line)]: (value === 'skip' || value === 'overwrite' ? value : '') as CopyDecision | '',
  })
}

function summaryEntries(line: SeasonCopyPreviewLine): string[] {
  const summary = line.summary ?? {}
  if (summary.diff) {
    return Object.entries(summary.diff).map(
      ([field, change]) =>
        `${field}: ${formatSummaryValue(change.before)} ← ${formatSummaryValue(change.after)}`,
    )
  }
  if (summary.fields) {
    return Object.entries(summary.fields).map(([field, value]) => `${field}: ${formatSummaryValue(value)}`)
  }
  if (summary.detail) {
    return Object.entries(summary.detail).map(([field, value]) => `${field}: ${formatSummaryValue(value)}`)
  }
  return []
}
</script>

<template>
  <section class="space-y-4" data-testid="copy-preview-panel">
    <div class="grid gap-3 sm:grid-cols-4">
      <div
        v-for="key in ['add', 'change', 'exclude', 'conflict'] as const"
        :key="key"
        class="rounded-2xl border border-brand-border bg-brand-bg p-4 text-start"
      >
        <p class="text-xs font-semibold text-brand-text-muted">{{ t(`seasonalTemplates.copy.counts.${key}`) }}</p>
        <p class="mt-1 text-2xl font-bold text-brand-text" :data-testid="`preview-count-${key}`">{{ counts[key] }}</p>
      </div>
    </div>

    <p
      v-if="unresolved > 0"
      class="rounded-xl border border-amber-200 bg-amber-50 px-3.5 py-2.5 text-start text-sm text-amber-900"
      role="status"
      data-testid="preview-unresolved"
    >
      {{ t('seasonalTemplates.copy.unresolved', { count: unresolved }) }}
    </p>

    <div
      v-if="preview.lines.length === 0"
      class="rounded-2xl border border-brand-border bg-brand-surface p-8 text-center text-sm text-brand-text-muted"
    >
      {{ t('seasonalTemplates.copy.noLines') }}
    </div>

    <div v-else class="overflow-x-auto rounded-2xl border border-brand-border bg-brand-surface">
      <table class="min-w-full text-sm" data-testid="preview-lines-table">
        <thead class="bg-[#F4F6F5]">
          <tr>
            <th class="px-4 py-3 text-start text-xs font-bold text-brand-text-muted">{{ t('seasonalTemplates.copy.columns.kind') }}</th>
            <th class="px-4 py-3 text-start text-xs font-bold text-brand-text-muted">{{ t('seasonalTemplates.copy.columns.key') }}</th>
            <th class="px-4 py-3 text-start text-xs font-bold text-brand-text-muted">{{ t('seasonalTemplates.copy.columns.action') }}</th>
            <th class="px-4 py-3 text-start text-xs font-bold text-brand-text-muted">{{ t('seasonalTemplates.copy.columns.details') }}</th>
            <th class="px-4 py-3 text-start text-xs font-bold text-brand-text-muted">{{ t('seasonalTemplates.copy.columns.decision') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="line in preview.lines"
            :key="conflictKey(line)"
            class="border-t border-brand-border/80 align-top"
          >
            <td class="px-4 py-3 text-start">{{ kindLabel(line.entity_kind) }}</td>
            <td class="px-4 py-3 text-start font-mono text-xs" dir="ltr">{{ line.natural_key }}</td>
            <td class="px-4 py-3 text-start">
              <span
                class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                :class="lineActionBadgeClass(line.action)"
              >
                {{ t(`seasonalTemplates.copy.action.${line.action}`) }}
              </span>
              <p v-if="line.conflict_code" class="mt-1 text-xs text-amber-800">
                {{ conflictLabel(line.conflict_code) }}
              </p>
            </td>
            <td class="px-4 py-3 text-start text-xs text-brand-text-secondary">
              <p v-if="line.summary?.reason_code" class="font-semibold">
                {{ reasonLabel(line.summary.reason_code) }}
              </p>
              <ul v-if="summaryEntries(line).length" class="space-y-0.5" dir="ltr">
                <li v-for="entry in summaryEntries(line)" :key="entry" class="text-start">{{ entry }}</li>
              </ul>
              <div v-if="dateTransformRows(line).length" class="mt-2" data-testid="preview-date-transforms">
                <p class="font-semibold text-brand-text">{{ t('seasonalTemplates.copy.dateTransforms') }}</p>
                <ul class="space-y-0.5" dir="ltr">
                  <li v-for="row in dateTransformRows(line)" :key="row.field" class="text-start">
                    {{ row.field }}: {{ row.before }} → {{ row.after }}
                  </li>
                </ul>
              </div>
            </td>
            <td class="min-w-44 px-4 py-3 text-start">
              <template v-if="line.action === 'conflict'">
                <AppSelect
                  :model-value="decisions[conflictKey(line)] ?? ''"
                  :options="decisionOptions(line)"
                  :disabled="disabled || !canResolve"
                  @update:model-value="setDecision(line, $event)"
                />
              </template>
              <span v-else class="text-xs text-brand-text-muted">—</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>
