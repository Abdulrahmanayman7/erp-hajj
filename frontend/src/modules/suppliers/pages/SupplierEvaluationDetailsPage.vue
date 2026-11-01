<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, RouterLink } from 'vue-router'
import { ArrowRight } from 'lucide-vue-next'

import { getCriteriaPack } from '../api/evaluationsApi'
import {
  useAcceptAppealMutation,
  useApproveEvaluationMutation,
  useFileAppealMutation,
  useRejectAppealMutation,
  useRejectEvaluationMutation,
  useSubmitEvaluationMutation,
  useUpdateEvaluationMutation,
} from '../mutations/useEvaluationMutations'
import { useEvaluationAppealsQuery, useEvaluationQuery } from '../queries/useEvaluationsQuery'
import type { EvaluationDraftScore } from '../types/evaluations'
import { mapEvaluationErrorCode, weightedPreview } from '../validation/evaluationValidation'
import { ApiError } from '@/shared/api/http'
import { useCurrentUserQuery } from '@/modules/auth/queries/useCurrentUserQuery'
import { usePermissions } from '@/shared/composables/usePermissions'
import { useToast } from '@/shared/composables/useToast'

const { t } = useI18n()
const route = useRoute()
const toast = useToast()
const { can } = usePermissions()
const { data: currentUser } = useCurrentUserQuery()

const evaluationId = computed(() => {
  const raw = route.params.id
  const value = Number(Array.isArray(raw) ? raw[0] : raw)
  return Number.isFinite(value) ? value : null
})

const { data, isLoading, isError, refetch } = useEvaluationQuery(evaluationId)
const evaluation = computed(() => data.value ?? null)
const { data: appealsData, refetch: refetchAppeals } = useEvaluationAppealsQuery(evaluationId)
const appeals = computed(() => appealsData.value ?? [])

const draftScores = ref<EvaluationDraftScore[]>([])
const criteriaWeights = ref(new Map<number, number>())
const criteriaNames = ref(new Map<number, string>())
const monitoring = ref(false)
const appealReason = ref('')

const updateMutation = useUpdateEvaluationMutation()
const submitMutation = useSubmitEvaluationMutation()
const approveMutation = useApproveEvaluationMutation()
const rejectMutation = useRejectEvaluationMutation()
const fileAppealMutation = useFileAppealMutation()
const acceptAppealMutation = useAcceptAppealMutation()
const rejectAppealMutation = useRejectAppealMutation()

const preview = computed(() => weightedPreview(draftScores.value, criteriaWeights.value))
const isPreparer = computed(
  () =>
    evaluation.value?.prepared_by?.id != null
    && evaluation.value.prepared_by.id === currentUser.value?.id,
)

watch(
  evaluation,
  async (value) => {
    if (!value) return
    monitoring.value = value.monitoring_recommended
    if (value.status === 'draft') {
      draftScores.value = [...(value.draft_scores ?? [])]
      try {
        const pack = await getCriteriaPack(value.criteria_pack_id)
        const weights = new Map<number, number>()
        const names = new Map<number, string>()
        for (const line of pack.criteria ?? []) {
          if (!line.is_active) continue
          weights.set(line.id, Number(line.weight))
          names.set(line.id, line.name)
          if (!draftScores.value.some((s) => s.criterion_id === line.id)) {
            draftScores.value.push({ criterion_id: line.id, score: 0, note: null })
          }
        }
        criteriaWeights.value = weights
        criteriaNames.value = names
      } catch {
        /* pack load optional for read path */
      }
    }
  },
  { immediate: true },
)

async function saveDraft(): Promise<void> {
  if (!evaluation.value) return
  try {
    await updateMutation.mutateAsync({
      id: evaluation.value.id,
      payload: {
        monitoring_recommended: monitoring.value,
        scores: draftScores.value,
      },
    })
    toast.success(t('evaluations.saved'))
    await refetch()
  } catch (error) {
    const code = error instanceof ApiError ? error.code : undefined
    toast.error(t(mapEvaluationErrorCode(code)))
  }
}

async function submit(): Promise<void> {
  if (!evaluation.value) return
  await saveDraft()
  try {
    await submitMutation.mutateAsync(evaluation.value.id)
    toast.success(t('evaluations.submitted'))
    await refetch()
  } catch (error) {
    const code = error instanceof ApiError ? error.code : undefined
    toast.error(t(mapEvaluationErrorCode(code)))
  }
}

async function approve(): Promise<void> {
  if (!evaluation.value) return
  try {
    await approveMutation.mutateAsync({
      id: evaluation.value.id,
      monitoring: monitoring.value,
    })
    toast.success(t('evaluations.approved'))
    await refetch()
  } catch (error) {
    const code = error instanceof ApiError ? error.code : undefined
    toast.error(t(mapEvaluationErrorCode(code)))
  }
}

async function reject(): Promise<void> {
  if (!evaluation.value) return
  const reason = window.prompt(t('evaluations.rejectPrompt'))
  if (!reason || reason.trim().length < 10) return
  try {
    await rejectMutation.mutateAsync({ id: evaluation.value.id, reason: reason.trim() })
    toast.success(t('evaluations.rejected'))
    await refetch()
  } catch (error) {
    const code = error instanceof ApiError ? error.code : undefined
    toast.error(t(mapEvaluationErrorCode(code)))
  }
}

async function fileAppeal(): Promise<void> {
  if (!evaluation.value || appealReason.value.trim().length < 10) return
  try {
    await fileAppealMutation.mutateAsync({
      evaluationId: evaluation.value.id,
      reason: appealReason.value.trim(),
    })
    appealReason.value = ''
    toast.success(t('evaluations.appealFiled'))
    await refetchAppeals()
  } catch (error) {
    const code = error instanceof ApiError ? error.code : undefined
    toast.error(t(mapEvaluationErrorCode(code)))
  }
}

async function acceptAppeal(id: number): Promise<void> {
  try {
    await acceptAppealMutation.mutateAsync(id)
    toast.success(t('evaluations.appealAccepted'))
    await refetchAppeals()
    await refetch()
  } catch (error) {
    const code = error instanceof ApiError ? error.code : undefined
    toast.error(t(mapEvaluationErrorCode(code)))
  }
}

async function rejectAppeal(id: number): Promise<void> {
  try {
    await rejectAppealMutation.mutateAsync({ appealId: id })
    toast.success(t('evaluations.appealRejected'))
    await refetchAppeals()
  } catch (error) {
    const code = error instanceof ApiError ? error.code : undefined
    toast.error(t(mapEvaluationErrorCode(code)))
  }
}
</script>

<template>
  <div class="space-y-6">
    <RouterLink
      to="/app/supplier-evaluations"
      class="inline-flex items-center gap-1 text-sm font-semibold text-brand-primary"
    >
      <ArrowRight class="h-4 w-4" />
      {{ t('evaluations.backToList') }}
    </RouterLink>

    <p v-if="isLoading" class="text-sm text-brand-text-muted">{{ t('common.loading') }}</p>
    <p v-else-if="isError || !evaluation" class="text-sm text-red-700">{{ t('evaluations.loadError') }}</p>

    <template v-else>
      <header class="rounded-2xl border border-brand-border bg-brand-surface p-5">
        <h1 class="text-xl font-extrabold text-brand-text">{{ evaluation.evaluation_number }}</h1>
        <p class="mt-1 text-sm text-brand-text-secondary">
          {{ t(`evaluations.kinds.${evaluation.evaluation_kind}`) }} ·
          {{ t(`evaluations.statuses.${evaluation.status}`) }} · v{{ evaluation.version_no }}
        </p>
        <p class="mt-2 text-lg font-bold text-brand-primary">
          {{ t('evaluations.columns.score') }}:
          {{ evaluation.total_score ?? preview }}
        </p>
      </header>

      <section
        v-if="evaluation.status === 'draft' && can('supplier_evaluations.prepare')"
        class="space-y-4 rounded-2xl border border-brand-border bg-brand-surface p-5"
      >
        <h2 class="font-bold">{{ t('evaluations.scoring') }}</h2>
        <div v-for="row in draftScores" :key="row.criterion_id" class="grid gap-2 md:grid-cols-3">
          <p class="text-sm font-semibold">
            {{ criteriaNames.get(row.criterion_id) ?? row.criterion_id }}
            <span class="text-brand-text-muted">({{ criteriaWeights.get(row.criterion_id) ?? 0 }}%)</span>
          </p>
          <input
            v-model.number="row.score"
            type="number"
            min="0"
            max="100"
            class="rounded-xl border border-brand-border px-3 py-2"
          />
          <input
            v-model="row.note"
            :placeholder="t('evaluations.fields.note')"
            class="rounded-xl border border-brand-border px-3 py-2"
          />
        </div>
        <label class="flex items-center gap-2 text-sm">
          <input v-model="monitoring" type="checkbox" />
          {{ t('evaluations.fields.monitoringProposed') }}
        </label>
        <div class="flex flex-wrap gap-2">
          <button
            type="button"
            class="rounded-xl border border-brand-border px-4 py-2 text-sm font-semibold"
            @click="saveDraft"
          >
            {{ t('common.save') }}
          </button>
          <button
            type="button"
            class="rounded-xl bg-brand-primary px-4 py-2 text-sm font-bold text-white"
            @click="submit"
          >
            {{ t('evaluations.submit') }}
          </button>
        </div>
      </section>

      <section
        v-else
        class="space-y-3 rounded-2xl border border-brand-border bg-brand-surface p-5"
      >
        <h2 class="font-bold">{{ t('evaluations.snapshots') }}</h2>
        <ul class="divide-y divide-brand-border rounded-xl border border-brand-border">
          <li
            v-for="snap in evaluation.score_snapshots ?? []"
            :key="snap.id"
            class="flex justify-between px-4 py-3 text-sm"
          >
            <span>{{ snap.criterion_name }} ({{ snap.weight }}%)</span>
            <span class="font-bold">{{ snap.score }}</span>
          </li>
        </ul>
      </section>

      <section
        v-if="evaluation.status === 'pending_approval' && can('supplier_evaluations.approve') && !isPreparer"
        class="space-y-3 rounded-2xl border border-brand-border bg-brand-surface p-5"
      >
        <label class="flex items-center gap-2 text-sm">
          <input v-model="monitoring" type="checkbox" />
          {{ t('evaluations.fields.monitoringFinal') }}
        </label>
        <div class="flex gap-2">
          <button
            type="button"
            class="rounded-xl bg-brand-primary px-4 py-2 text-sm font-bold text-white"
            @click="approve"
          >
            {{ t('evaluations.approve') }}
          </button>
          <button
            type="button"
            class="rounded-xl border border-red-200 px-4 py-2 text-sm font-semibold text-red-700"
            @click="reject"
          >
            {{ t('evaluations.reject') }}
          </button>
        </div>
      </section>

      <section
        v-if="evaluation.status === 'approved'"
        class="space-y-4 rounded-2xl border border-brand-border bg-brand-surface p-5"
      >
        <h2 class="font-bold">{{ t('evaluations.appeals') }}</h2>
        <div
          v-if="can('supplier_evaluations.prepare') || can('supplier_evaluations.handle_appeal')"
          class="space-y-2"
        >
          <textarea
            v-model="appealReason"
            rows="3"
            class="w-full rounded-xl border border-brand-border px-3 py-2 text-sm"
            :placeholder="t('evaluations.appealReason')"
          />
          <button
            type="button"
            class="rounded-xl border border-brand-border px-4 py-2 text-sm font-semibold"
            @click="fileAppeal"
          >
            {{ t('evaluations.fileAppeal') }}
          </button>
        </div>
        <ul class="space-y-2">
          <li
            v-for="appeal in appeals"
            :key="appeal.id"
            class="rounded-xl border border-brand-border px-4 py-3 text-sm"
          >
            <p class="font-semibold">{{ t(`evaluations.appealStatuses.${appeal.status}`) }}</p>
            <p class="text-brand-text-secondary">{{ appeal.reason }}</p>
            <div v-if="appeal.status === 'open' && can('supplier_evaluations.handle_appeal')" class="mt-2 flex gap-2">
              <button
                type="button"
                class="rounded-lg bg-brand-primary px-3 py-1 text-xs font-bold text-white"
                @click="acceptAppeal(appeal.id)"
              >
                {{ t('evaluations.acceptAppeal') }}
              </button>
              <button
                type="button"
                class="rounded-lg border px-3 py-1 text-xs font-semibold"
                @click="rejectAppeal(appeal.id)"
              >
                {{ t('evaluations.rejectAppeal') }}
              </button>
            </div>
            <RouterLink
              v-if="appeal.resulting_evaluation_id"
              :to="`/app/supplier-evaluations/${appeal.resulting_evaluation_id}`"
              class="mt-2 inline-block text-xs font-semibold text-brand-primary"
            >
              {{ t('evaluations.openCorrection') }}
            </RouterLink>
          </li>
        </ul>
      </section>
    </template>
  </div>
</template>
