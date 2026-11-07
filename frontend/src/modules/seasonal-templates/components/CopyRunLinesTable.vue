<script setup lang="ts">
import { useI18n } from 'vue-i18n'

import type { SeasonCopyRunLine } from '../types/seasonCopy'

defineProps<{ lines: SeasonCopyRunLine[] }>()

const { t, te } = useI18n()

function kindLabel(kind: string): string {
  const key = `seasonalTemplates.entityKinds.${kind}.label`
  return te(key) ? t(key) : kind
}

function resultLabel(result: string): string {
  const key = `seasonalTemplates.runs.result.${result}`
  return te(key) ? t(key) : result
}

function reasonLabel(code: string | null): string {
  if (!code) return '—'
  const key = `seasonalTemplates.reasons.${code}`
  return te(key) ? t(key) : code
}
</script>

<template>
  <div v-if="lines.length === 0" class="rounded-xl border border-brand-border bg-brand-bg p-5 text-center text-sm text-brand-text-muted">
    {{ t('seasonalTemplates.runs.noLines') }}
  </div>
  <div v-else class="overflow-x-auto rounded-2xl border border-brand-border bg-brand-surface">
    <table class="min-w-full text-sm" data-testid="run-lines-table">
      <thead class="bg-[#F4F6F5]">
        <tr>
          <th class="px-4 py-3 text-start text-xs font-bold text-brand-text-muted">{{ t('seasonalTemplates.copy.columns.kind') }}</th>
          <th class="px-4 py-3 text-start text-xs font-bold text-brand-text-muted">{{ t('seasonalTemplates.copy.columns.key') }}</th>
          <th class="px-4 py-3 text-start text-xs font-bold text-brand-text-muted">{{ t('seasonalTemplates.runs.columns.result') }}</th>
          <th class="px-4 py-3 text-start text-xs font-bold text-brand-text-muted">{{ t('seasonalTemplates.runs.columns.reason') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="line in lines" :key="`${line.entity_kind}:${line.natural_key}`" class="border-t border-brand-border/80">
          <td class="px-4 py-3 text-start">{{ kindLabel(line.entity_kind) }}</td>
          <td class="px-4 py-3 text-start font-mono text-xs" dir="ltr">{{ line.natural_key }}</td>
          <td class="px-4 py-3 text-start">{{ resultLabel(line.result) }}</td>
          <td class="px-4 py-3 text-start text-xs text-brand-text-secondary">{{ reasonLabel(line.reason_code) }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
