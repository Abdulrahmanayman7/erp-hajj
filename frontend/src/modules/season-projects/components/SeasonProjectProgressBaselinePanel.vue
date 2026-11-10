<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import { ApiError } from '@/shared/api/http'
import PermissionGuard from '@/shared/components/PermissionGuard.vue'
import { useConfirm } from '@/shared/composables/useConfirm'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'

import {
  useApproveSeasonProjectProgressBaselineMutation,
  useUpsertSeasonProjectProgressBaselineDraftMutation,
} from '../mutations/useSeasonProjectsMutations'
import { useSeasonProjectProgressBaselinesQuery } from '../queries/useSeasonProjectsQuery'
import type {
  SeasonProject,
  SeasonProjectProgressBaseline,
  SeasonProjectProgressCheckpointFormRow,
} from '../types/seasonProjects'
import {
  checkpointFormToIso,
  computeProgressLagMetric,
  emptyCheckpointFormRow,
  isoToDatetimeLocalValue,
  mapSeasonProjectErrorCode,
  PROGRESS_LAG_CLEAR_THRESHOLD,
  PROGRESS_LAG_OPEN_THRESHOLD,
  validateBaselineCheckpointForm,
} from '../validation/seasonProjectsValidation'

const props = defineProps<{
  project: SeasonProject
  seasonId: number | null
  seasonWritable: boolean
}>()

const { t } = useI18n()
const toast = useToast()
const { confirm } = useConfirm()
const { can } = usePermissions()

const enabled = computed(() => can('season_projects.view'))
const {
  data: baselinesData,
  isLoading,
  isError,
  refetch,
} = useSeasonProjectProgressBaselinesQuery(
  () => props.project.id,
  () => props.seasonId,
  enabled,
)
const baselines = computed(() => baselinesData.value ?? [])

const draftBaseline = computed(
  () => baselines.value.find((b) => b.status === 'draft') ?? null,
)
const approvedBaselines = computed(() =>
  baselines.value.filter((b) => b.status === 'approved').sort((a, b) => b.version - a.version),
)

const lagMetric = computed(() =>
  computeProgressLagMetric(baselines.value, props.project.progress_percent),
)

const upsertMutation = useUpsertSeasonProjectProgressBaselineDraftMutation()
const approveMutation = useApproveSeasonProjectProgressBaselineMutation()
const upsertPending = computed(() => upsertMutation.isPending.value)
const approvePending = computed(() => approveMutation.isPending.value)

const editorOpen = ref(false)
const formRows = ref<SeasonProjectProgressCheckpointFormRow[]>([emptyCheckpointFormRow()])
const formError = ref('')
const fieldErrors = ref<Record<string, string>>({})

function apiMessage(error: unknown): string {
  if (!(error instanceof ApiError)) return t('seasonProjects.errors.generic')
  const mapped = mapSeasonProjectErrorCode(error.code)
  if (mapped !== 'generic') return t(`seasonProjects.errors.${mapped}`)
  return error.message || t('seasonProjects.errors.generic')
}

function seedFormFromDraft(draft: SeasonProjectProgressBaseline | null): void {
  if (draft && draft.checkpoints.length) {
    formRows.value = draft.checkpoints.map((c) => ({
      effective_at: isoToDatetimeLocalValue(c.effective_at),
      planned_progress_percent: String(c.planned_progress_percent),
    }))
    return
  }
  formRows.value = [emptyCheckpointFormRow()]
}

function openEditor(): void {
  seedFormFromDraft(draftBaseline.value)
  formError.value = ''
  fieldErrors.value = {}
  editorOpen.value = true
}

function addRow(): void {
  formRows.value = [...formRows.value, emptyCheckpointFormRow()]
}

function removeRow(index: number): void {
  if (formRows.value.length <= 1) return
  formRows.value = formRows.value.filter((_, i) => i !== index)
}

async function submitDraft(): Promise<void> {
  fieldErrors.value = {}
  formError.value = ''
  const local = validateBaselineCheckpointForm(formRows.value)
  if (Object.keys(local).length) {
    fieldErrors.value = Object.fromEntries(
      Object.entries(local).map(([k, v]) => [k, t(`seasonProjects.errors.${v}`)]),
    )
    return
  }

  const checkpoints = formRows.value.map((row) => ({
    effective_at: checkpointFormToIso(row.effective_at),
    planned_progress_percent: Number(row.planned_progress_percent),
  }))

  try {
    await upsertMutation.mutateAsync({
      projectId: props.project.id,
      checkpoints,
      seasonId: props.seasonId,
    })
    toast.success(t('seasonProjects.toasts.baselineDraftSaved'))
    editorOpen.value = false
  } catch (error) {
    formError.value = apiMessage(error)
  }
}

async function onApprove(baseline: SeasonProjectProgressBaseline): Promise<void> {
  const ok = await confirm({
    title: t('seasonProjects.baseline.approveConfirmTitle'),
    message: t('seasonProjects.baseline.approveConfirmBody', { version: baseline.version }),
    confirmLabel: t('seasonProjects.baseline.approve'),
    cancelLabel: t('seasonProjects.cancel'),
  })
  if (!ok) return
  try {
    await approveMutation.mutateAsync({
      projectId: props.project.id,
      baselineId: baseline.id,
      seasonId: props.seasonId,
    })
    toast.success(t('seasonProjects.toasts.baselineApproved'))
  } catch (error) {
    toast.error(apiMessage(error))
  }
}

function formatLag(value: number | null): string {
  if (value == null) return '—'
  return value.toFixed(1)
}

function formatPercent(value: number | null | undefined): string {
  if (value == null || Number.isNaN(Number(value))) return '—'
  return `${Number(value).toFixed(1)}%`
}

watch(draftBaseline, (draft) => {
  if (!editorOpen.value) seedFormFromDraft(draft)
})
</script>

<template>
  <section class="rounded-xl border border-brand-border bg-brand-surface p-4">
    <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
      <div>
        <h2 class="text-base font-semibold">{{ t('seasonProjects.baseline.title') }}</h2>
        <p class="text-sm text-brand-muted">{{ t('seasonProjects.baseline.subtitle') }}</p>
      </div>
      <PermissionGuard permission="season_projects.update">
        <button
          v-if="seasonWritable"
          type="button"
          class="rounded-lg border px-3 py-1.5 text-sm"
          @click="openEditor"
        >
          {{
            draftBaseline
              ? t('seasonProjects.baseline.editDraft')
              : t('seasonProjects.baseline.createDraft')
          }}
        </button>
      </PermissionGuard>
    </div>

    <p class="mb-3 text-xs text-brand-muted">
      {{
        t('seasonProjects.baseline.thresholdsHint', {
          open: PROGRESS_LAG_OPEN_THRESHOLD,
          clear: PROGRESS_LAG_CLEAR_THRESHOLD,
        })
      }}
    </p>

    <div
      v-if="lagMetric.state === 'unavailable'"
      class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm"
    >
      <p class="font-medium">{{ t('seasonProjects.baseline.lagUnavailable') }}</p>
      <p class="text-xs">
        {{
          lagMetric.unavailable_reason === 'no_checkpoint_at_evaluation_time'
            ? t('seasonProjects.baseline.noCheckpointYet')
            : t('seasonProjects.baseline.noApprovedBaseline')
        }}
      </p>
    </div>
    <div
      v-else
      class="mb-4 grid gap-3 rounded-lg border border-brand-border/70 bg-white/60 p-3 text-sm sm:grid-cols-2 lg:grid-cols-4"
    >
      <div>
        <p class="text-brand-muted">{{ t('seasonProjects.baseline.plannedAtNow') }}</p>
        <p class="font-semibold">{{ formatPercent(lagMetric.planned_progress_percent) }}</p>
      </div>
      <div>
        <p class="text-brand-muted">{{ t('seasonProjects.baseline.actualProgress') }}</p>
        <p class="font-semibold">{{ formatPercent(lagMetric.actual_progress_percent) }}</p>
      </div>
      <div>
        <p class="text-brand-muted">{{ t('seasonProjects.baseline.lagPoints') }}</p>
        <p class="font-semibold">{{ formatLag(lagMetric.lag_percentage_points) }}</p>
      </div>
      <div>
        <p class="text-brand-muted">{{ t('seasonProjects.baseline.approvedVersion') }}</p>
        <p class="font-semibold">v{{ lagMetric.baseline_version }}</p>
      </div>
    </div>

    <p v-if="isLoading" class="text-sm text-brand-muted">{{ t('seasonProjects.loading') }}</p>
    <div v-else-if="isError" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm">
      <p class="mb-2">{{ t('seasonProjects.errors.generic') }}</p>
      <button type="button" class="underline" @click="() => refetch()">
        {{ t('seasonProjects.retry') }}
      </button>
    </div>
    <p v-else-if="!baselines.length" class="text-sm text-brand-muted">
      {{ t('seasonProjects.baseline.empty') }}
    </p>

    <ul v-else class="space-y-3">
      <li
        v-for="baseline in baselines"
        :key="baseline.id"
        class="rounded-lg border border-brand-border/70 px-3 py-3 text-sm"
      >
        <div class="mb-2 flex flex-wrap items-start justify-between gap-2">
          <div>
            <p class="font-medium">
              {{ t('seasonProjects.baseline.versionLabel', { version: baseline.version }) }}
              <span
                class="ms-2 rounded-full px-2 py-0.5 text-xs"
                :class="
                  baseline.status === 'approved'
                    ? 'bg-emerald-100 text-emerald-800'
                    : 'bg-amber-100 text-amber-900'
                "
              >
                {{ t(`seasonProjects.baseline.status.${baseline.status}`) }}
              </span>
            </p>
            <p v-if="baseline.status === 'approved'" class="text-xs text-brand-muted">
              {{ t('seasonProjects.baseline.approvedAt') }}:
              {{ baseline.approved_at ?? '—' }}
              · {{ t('seasonProjects.baseline.approvedBy') }}:
              {{ baseline.approved_by ?? '—' }}
            </p>
            <p v-else class="text-xs text-brand-muted">
              {{ t('seasonProjects.baseline.draftImmutableHint') }}
            </p>
          </div>
          <PermissionGuard permission="season_projects.approve_progress_baseline">
            <button
              v-if="baseline.status === 'draft' && seasonWritable"
              type="button"
              class="rounded-lg border border-brand-primary px-3 py-1.5 text-sm text-brand-primary disabled:opacity-50"
              :disabled="approvePending"
              @click="onApprove(baseline)"
            >
              {{ t('seasonProjects.baseline.approve') }}
            </button>
          </PermissionGuard>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full text-start text-xs">
            <thead class="text-brand-muted">
              <tr>
                <th class="px-2 py-1 font-medium">{{ t('seasonProjects.baseline.effectiveAt') }}</th>
                <th class="px-2 py-1 font-medium">
                  {{ t('seasonProjects.baseline.plannedPercent') }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(cp, idx) in baseline.checkpoints"
                :key="cp.id ?? `${baseline.id}-${idx}`"
                class="border-t border-brand-border/40"
              >
                <td class="px-2 py-1">{{ cp.effective_at }}</td>
                <td class="px-2 py-1">{{ formatPercent(cp.planned_progress_percent) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </li>
    </ul>

    <p
      v-if="approvedBaselines.length"
      class="mt-3 text-xs text-brand-muted"
    >
      {{ t('seasonProjects.baseline.immutableApproved') }}
    </p>
  </section>

  <div
    v-if="editorOpen"
    class="fixed inset-0 z-40 flex justify-end bg-black/30"
    role="dialog"
    aria-modal="true"
    @click.self="editorOpen = false"
  >
    <div class="flex h-full w-full max-w-lg flex-col bg-white shadow-xl">
      <header class="border-b px-4 py-3">
        <h2 class="text-lg font-semibold">{{ t('seasonProjects.baseline.editorTitle') }}</h2>
        <p class="text-xs text-brand-muted">{{ t('seasonProjects.baseline.editorSubtitle') }}</p>
      </header>
      <form class="flex flex-1 flex-col gap-3 overflow-y-auto p-4" @submit.prevent="submitDraft">
        <div
          v-for="(row, index) in formRows"
          :key="index"
          class="rounded-lg border border-brand-border/70 p-3"
        >
          <div class="mb-2 flex items-center justify-between gap-2">
            <p class="text-sm font-medium">
              {{ t('seasonProjects.baseline.checkpointN', { n: index + 1 }) }}
            </p>
            <button
              v-if="formRows.length > 1"
              type="button"
              class="text-xs text-red-600 underline"
              @click="removeRow(index)"
            >
              {{ t('seasonProjects.delete') }}
            </button>
          </div>
          <label class="mb-2 block text-sm">
            <span class="mb-1 block">{{ t('seasonProjects.baseline.effectiveAt') }}</span>
            <input
              v-model="row.effective_at"
              type="datetime-local"
              class="w-full rounded-lg border border-brand-border px-3 py-2"
            >
            <span
              v-if="fieldErrors[`checkpoints.${index}.effective_at`]"
              class="text-xs text-red-600"
            >
              {{ fieldErrors[`checkpoints.${index}.effective_at`] }}
            </span>
          </label>
          <label class="block text-sm">
            <span class="mb-1 block">{{ t('seasonProjects.baseline.plannedPercent') }}</span>
            <input
              v-model="row.planned_progress_percent"
              type="number"
              min="0"
              max="100"
              step="0.01"
              class="w-full rounded-lg border border-brand-border px-3 py-2"
            >
            <span
              v-if="fieldErrors[`checkpoints.${index}.planned_progress_percent`]"
              class="text-xs text-red-600"
            >
              {{ fieldErrors[`checkpoints.${index}.planned_progress_percent`] }}
            </span>
          </label>
        </div>

        <button type="button" class="rounded-lg border px-3 py-2 text-sm" @click="addRow">
          {{ t('seasonProjects.baseline.addCheckpoint') }}
        </button>

        <p v-if="fieldErrors.checkpoints" class="text-sm text-red-600">
          {{ fieldErrors.checkpoints }}
        </p>
        <p v-if="formError" class="rounded bg-red-50 px-3 py-2 text-sm text-red-700">
          {{ formError }}
        </p>

        <div class="mt-auto flex gap-2 border-t pt-3">
          <button
            type="button"
            class="rounded-lg border px-3 py-2 text-sm"
            @click="editorOpen = false"
          >
            {{ t('seasonProjects.cancel') }}
          </button>
          <button
            type="submit"
            class="rounded-lg bg-brand-primary px-3 py-2 text-sm text-white disabled:opacity-50"
            :disabled="upsertPending"
          >
            {{ t('seasonProjects.baseline.saveDraft') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
