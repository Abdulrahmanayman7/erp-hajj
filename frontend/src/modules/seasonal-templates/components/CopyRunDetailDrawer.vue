<script setup lang="ts">
import { computed, onUnmounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Loader2, X } from 'lucide-vue-next'

import { useSeasonCopyRunQuery } from '../queries/useSeasonCopyQuery'
import { runStatusBadgeClass } from '../validation/seasonCopyValidation'
import CopyRunLinesTable from './CopyRunLinesTable.vue'

const props = defineProps<{
  runId: number | null
  seasonNames: Record<number, string>
}>()

const emit = defineEmits<{ close: [] }>()

const { t, te } = useI18n()

const open = computed(() => props.runId != null)
const { data: run, isLoading, isError, refetch } = useSeasonCopyRunQuery(() => props.runId)

function seasonName(id: number | null): string {
  return id == null ? '—' : (props.seasonNames[id] ?? `#${id}`)
}

function errorLabel(code: string | null, message: string | null): string {
  if (code && te(`seasonalTemplates.errors.${code}`)) return t(`seasonalTemplates.errors.${code}`)
  return message ?? '—'
}

function formatWhen(iso: string | null | undefined): string {
  if (!iso) return '—'
  try {
    return new Intl.DateTimeFormat('ar-SA', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso))
  } catch {
    return iso
  }
}

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape' && open.value) {
    event.preventDefault()
    emit('close')
  }
}

watch(open, (isOpen) => {
  if (isOpen) document.addEventListener('keydown', onKeydown)
  else document.removeEventListener('keydown', onKeydown)
})

onUnmounted(() => document.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-50" role="presentation" data-testid="run-detail-drawer">
      <div class="absolute inset-0 bg-[rgba(15,23,20,0.32)]" aria-hidden="true" @click="emit('close')" />
      <aside
        class="absolute inset-y-0 start-0 flex h-dvh w-full max-w-[720px] flex-col bg-brand-surface shadow-[-12px_0_40px_-24px_rgba(23,32,29,0.35)]"
        role="dialog"
        aria-modal="true"
        :aria-label="t('seasonalTemplates.runs.detailTitle')"
        @click.stop
      >
        <header class="flex shrink-0 items-start justify-between gap-4 border-b border-brand-border px-4 py-5 sm:px-7">
          <div class="min-w-0 text-start">
            <h3 class="text-[21px] font-bold leading-tight text-brand-text">
              {{ t('seasonalTemplates.runs.detailTitle') }}
            </h3>
            <p v-if="run" class="mt-1.5 font-mono text-xs text-brand-text-secondary" dir="ltr">{{ run.run_number }}</p>
          </div>
          <button
            type="button"
            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] text-brand-text-secondary transition hover:bg-brand-bg hover:text-brand-text"
            :aria-label="t('seasonalTemplates.common.close')"
            @click="emit('close')"
          >
            <X class="h-5 w-5" />
          </button>
        </header>

        <div class="flex-1 space-y-5 overflow-y-auto px-4 py-5 text-start sm:px-7">
          <div v-if="isLoading" class="flex items-center justify-center gap-2 p-10 text-sm text-brand-text-muted">
            <Loader2 class="h-4 w-4 animate-spin" />
            {{ t('seasonalTemplates.common.loading') }}
          </div>
          <div v-else-if="isError || !run" class="rounded-2xl border border-red-200 bg-red-50 p-8 text-center">
            <p class="text-sm text-red-700">{{ t('seasonalTemplates.errors.load') }}</p>
            <button type="button" class="mt-3 text-sm font-semibold underline" @click="() => refetch()">
              {{ t('seasonalTemplates.common.retry') }}
            </button>
          </div>
          <template v-else>
            <dl class="grid gap-3 rounded-2xl border border-brand-border bg-brand-bg p-4 text-sm sm:grid-cols-2">
              <div>
                <dt class="text-xs font-semibold text-brand-text-muted">{{ t('seasonalTemplates.runs.columns.status') }}</dt>
                <dd class="mt-1">
                  <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="runStatusBadgeClass(run.status)">
                    {{ t(`seasonalTemplates.runs.status.${run.status}`) }}
                  </span>
                </dd>
              </div>
              <div>
                <dt class="text-xs font-semibold text-brand-text-muted">{{ t('seasonalTemplates.runs.columns.mode') }}</dt>
                <dd class="mt-1">{{ t(`seasonalTemplates.copy.mode.${run.mode}`) }}</dd>
              </div>
              <div>
                <dt class="text-xs font-semibold text-brand-text-muted">{{ t('seasonalTemplates.copy.sourceSeason') }}</dt>
                <dd class="mt-1">{{ seasonName(run.source_season_id) }}</dd>
              </div>
              <div>
                <dt class="text-xs font-semibold text-brand-text-muted">{{ t('seasonalTemplates.copy.targetSeason') }}</dt>
                <dd class="mt-1">{{ seasonName(run.target_season_id) }}</dd>
              </div>
              <div>
                <dt class="text-xs font-semibold text-brand-text-muted">{{ t('seasonalTemplates.runs.startedAt') }}</dt>
                <dd class="mt-1">{{ formatWhen(run.started_at) }}</dd>
              </div>
              <div>
                <dt class="text-xs font-semibold text-brand-text-muted">{{ t('seasonalTemplates.runs.finishedAt') }}</dt>
                <dd class="mt-1">{{ formatWhen(run.finished_at) }}</dd>
              </div>
              <div class="sm:col-span-2">
                <dt class="text-xs font-semibold text-brand-text-muted">{{ t('seasonalTemplates.runs.correlationId') }}</dt>
                <dd class="mt-1 break-all font-mono text-xs" dir="ltr">{{ run.correlation_id }}</dd>
              </div>
            </dl>

            <p
              v-if="run.status === 'failed'"
              class="rounded-xl border border-red-200 bg-red-50 px-3.5 py-2.5 text-sm text-red-700"
              role="alert"
              data-testid="run-error"
            >
              {{ errorLabel(run.error_code, run.error_message) }}
            </p>

            <div v-if="run.counts" class="grid gap-3 sm:grid-cols-4">
              <div v-for="key in ['added', 'changed', 'skipped', 'excluded'] as const" :key="key" class="rounded-2xl border border-brand-border bg-brand-surface p-4">
                <p class="text-xs font-semibold text-brand-text-muted">{{ t(`seasonalTemplates.runs.result.${key}`) }}</p>
                <p class="mt-1 text-2xl font-bold text-brand-text">{{ run.counts[key] }}</p>
              </div>
            </div>

            <div class="space-y-2">
              <h4 class="text-sm font-bold text-brand-text">{{ t('seasonalTemplates.runs.lines') }}</h4>
              <CopyRunLinesTable :lines="run.lines ?? []" />
            </div>
          </template>
        </div>
      </aside>
    </div>
  </Teleport>
</template>
